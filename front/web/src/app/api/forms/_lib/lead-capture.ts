import { NextRequest, NextResponse, after } from 'next/server';
import { resolveBackendBaseUrl } from '@/lib/backend-url';

type MarketingLeadType = 'signup' | 'demo_request' | 'newsletter' | 'contact' | 'solution_survey';

/**
 * Whether marketing forms are enabled for the vitrine, mirroring
 * `NEXT_PUBLIC_ENABLE_FORMS` (see `modules/vitrine/lib/env.ts`). Read
 * directly from `process.env` here (not `getEnvConfig()`) because this
 * runs in the Next.js API route runtime, not the vitrine module tree.
 * Defaults to enabled to preserve current behavior when unset.
 */
export function areFormsEnabled(): boolean {
  return process.env.NEXT_PUBLIC_ENABLE_FORMS !== 'false';
}

/**
 * Standard 503 response returned by every `/api/forms/*` route when
 * `NEXT_PUBLIC_ENABLE_FORMS=false` (issue #1305: the flag was previously
 * defined but never enforced anywhere, so submissions always succeeded
 * regardless of its value).
 */
export function formsDisabledResponse(): NextResponse {
  return NextResponse.json(
    {
      success: false,
      message: 'Les formulaires sont temporairement desactives.',
      error: 'FORMS_DISABLED',
    },
    { status: 503 }
  );
}

export type MarketingLeadPayload = {
  type: MarketingLeadType;
  email: string;
  locale?: string;
  page?: string;
  source?: string;
  timestamp?: string;
  data?: Record<string, unknown>;
};

export type MarketingLeadCaptureResult = {
  id: string;
  crmForwarded: boolean;
  emailForwarded: boolean;
  /**
   * #7301 — état de la **persistance durable** du lead dans la plateforme.
   *
   * - `persisted` : écrit et confirmé avant la réponse (API chaude) ;
   * - `pending`   : l'API n'a pas répondu dans le budget imparti ; l'écriture
   *                 continue **après** la réponse (`after()` de Next) ;
   * - `failed`    : toutes les tentatives ont échoué → une **alerte** est émise.
   *
   * Avant ce correctif, l'échec était avalé par un simple `console.info` et le
   * lead n'était écrit nulle part (data-loss silencieuse).
   */
  persisted: LeadPersistOutcome;
};

export type LeadPersistOutcome = 'persisted' | 'pending' | 'failed';

type ForwarderTarget = 'crm' | 'email';

const DEFAULT_TIMEOUT_MS = 2500;
const supportedLocales = new Set(['fr', 'en', 'ar', 'tr']);

/**
 * #7301 — réglages de la persistance durable. Le lot de leads doit survivre à
 * un cold start de l'API (instance Render gratuite : plusieurs secondes à
 * ~30 s) sans pour autant faire attendre le prospect.
 *
 * - `LEAD_PERSIST_FAST_PATH_MS` : budget accordé à l'écriture AVANT la réponse.
 *   Au-delà, la réponse part et l'écriture continue en tâche de fond.
 * - `LEAD_PERSIST_TIMEOUT_MS` : délai par tentative (en tâche de fond, on peut
 *   se permettre d'attendre un cold start).
 * - `LEAD_PERSIST_ATTEMPTS` / `LEAD_PERSIST_BACKOFF_MS` : réessais bornés.
 */
const LEAD_PERSIST_FAST_PATH_MS = Number(
  process.env.MARKETING_LEAD_FAST_PATH_BUDGET_MS || 2500
);
const LEAD_PERSIST_TIMEOUT_MS = Number(
  process.env.MARKETING_LEAD_PERSIST_TIMEOUT_MS || 8000
);
const LEAD_PERSIST_ATTEMPTS = Math.max(
  1,
  Number(process.env.MARKETING_LEAD_PERSIST_ATTEMPTS || 3)
);
const LEAD_PERSIST_BACKOFF_MS = (process.env.MARKETING_LEAD_PERSIST_BACKOFF_MS || '500,2000')
  .split(',')
  .map((value) => Number(value.trim()))
  .filter((value) => Number.isFinite(value) && value >= 0);

export function getClientIp(request: NextRequest): string {
  const forwardedFor = request.headers.get('x-forwarded-for');

  if (forwardedFor) {
    return forwardedFor.split(',')[0]?.trim() || 'unknown';
  }

  return request.headers.get('x-real-ip') || 'unknown';
}

export function normalizeLeadLocale(locale?: string): string {
  const normalized = locale?.trim().toLowerCase();

  return normalized && supportedLocales.has(normalized) ? normalized : 'fr';
}

export async function captureMarketingLead(
  request: NextRequest,
  payload: MarketingLeadPayload
): Promise<MarketingLeadCaptureResult> {
  const id = createLeadId(payload.type);
  const locale = normalizeLeadLocale(payload.locale);
  const lead = {
    id,
    type: payload.type,
    email: payload.email,
    locale,
    page: payload.page || '/',
    source: payload.source || `${payload.type}_form`,
    timestamp: payload.timestamp || new Date().toISOString(),
    ip: getClientIp(request),
    userAgent: request.headers.get('user-agent') || 'unknown',
    referrer: request.headers.get('referer') || request.headers.get('referrer') || null,
    data: payload.data || {},
  };

  logLeadEvent('marketing.lead.received', lead);

  const [crmForwarded, emailForwarded] = await Promise.all([
    forwardLead('crm', lead),
    forwardLead('email', lead),
  ]);

  logLeadEvent('marketing.lead.processed', {
    id,
    type: payload.type,
    locale,
    page: lead.page,
    crmForwarded,
    emailForwarded,
  });

  // Persist the lead in the platform's own CRM pipeline (PA2-MKT-007), so
  // it survives even when the external CRM/email webhooks above are down
  // or unconfigured.
  //
  // #7301 — l'ancienne version *attendait* un unique essai de 2,5 s et se
  // contentait d'un `console.info` en cas d'échec, en affirmant que le lead
  // était « déjà durablement loggé » : c'était FAUX (les logs d'une fonction
  // serverless ne sont pas un stockage durable — ils tournent et sont perdus).
  // Sur une instance API froide, l'écriture dépassait 2,5 s et le lead
  // disparaissait, sans que personne ne le sache.
  //
  // Désormais : réessais bornés, budget avant réponse (l'utilisateur n'attend
  // pas un cold start), puis poursuite **après** la réponse — `after()` est le
  // seul moyen de garder la fonction serverless en vie au-delà de la réponse —
  // et **alerte** si tout échoue. L'échec n'est plus silencieux.
  const persistence = persistLeadWithRetry(lead, crmForwarded, emailForwarded, logLeadAlert);
  const persisted = await settleWithin(persistence, LEAD_PERSIST_FAST_PATH_MS);

  if (persisted === 'pending') {
    deferLeadPersistence(persistence);
  }

  return {
    id,
    crmForwarded,
    emailForwarded,
    persisted,
  };
}

function createLeadId(type: MarketingLeadType): string {
  return `${type}_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
}

const LEOPARDO_API_URL = process.env.LEOPARDO_API_URL ||
  resolveBackendBaseUrl().replace(/\/api\/v1$/, '');

/** Émetteur d'alerte injectable (facilite les tests). */
type AlertLogger = (event: string, payload: Record<string, unknown>) => void;

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}

/**
 * #7301 — écrit le lead dans le pipeline de la plateforme, avec **réessais
 * bornés**. Ne rejette JAMAIS : une panne de persistance ne doit pas faire
 * échouer la soumission du formulaire.
 *
 * @returns `true` si le lead a été écrit, `false` après épuisement des essais
 *          (une **alerte** est alors émise — l'échec n'est jamais silencieux).
 */
export async function persistLeadWithRetry(
  lead: Record<string, unknown>,
  crmForwarded: boolean,
  emailForwarded: boolean,
  alert: AlertLogger = logLeadAlert
): Promise<boolean> {
  let lastFailure: Record<string, unknown> = {};

  for (let attempt = 1; attempt <= LEAD_PERSIST_ATTEMPTS; attempt += 1) {
    const result = await persistLeadOnce(lead, crmForwarded, emailForwarded, attempt);

    if (result.ok) {
      return true;
    }

    lastFailure = result.failure ?? {};

    // Un rejet définitif (4xx : signature invalide, contrat refusé…) ne
    // guérira pas en réessayant : on s'arrête tout de suite.
    if (!result.retryable) {
      break;
    }

    const backoff = LEAD_PERSIST_BACKOFF_MS[attempt - 1];

    if (attempt < LEAD_PERSIST_ATTEMPTS && backoff) {
      await sleep(backoff);
    }
  }

  // Plusieurs essais, aucun succès : ce n'est pas une ligne de log parmi
  // d'autres, c'est un lead potentiellement perdu → ALERTE (niveau erreur).
  alert('marketing.lead.persist_alert', {
    id: lead.id,
    type: lead.type,
    email: lead.email,
    attempts: LEAD_PERSIST_ATTEMPTS,
    ...lastFailure,
  });

  return false;
}

async function persistLeadOnce(
  lead: Record<string, unknown>,
  crmForwarded: boolean,
  emailForwarded: boolean,
  attempt: number
): Promise<{ ok: boolean; retryable?: boolean; failure?: Record<string, unknown> }> {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), LEAD_PERSIST_TIMEOUT_MS);

  try {
    const response = await fetch(`${LEOPARDO_API_URL}/api/v1/marketing/leads`, {
      method: 'POST',
      headers: buildForwardHeaders('crm'),
      body: JSON.stringify({
        external_id: lead.id,
        type: lead.type,
        email: lead.email,
        locale: lead.locale,
        page: lead.page,
        source: lead.source,
        ip: lead.ip,
        referrer: lead.referrer,
        payload: lead.data,
        crm_forwarded: crmForwarded,
        email_forwarded: emailForwarded,
        captured_at: lead.timestamp,
      }),
      signal: controller.signal,
    });

    if (response.ok) {
      return { ok: true };
    }

    // #7301 — 5xx / 408 / 429 sont transitoires (cold start, redéploiement,
    // quota) : on retente. Un 4xx est définitif (secret invalide, contrat) :
    // retenter ne ferait que retarder l'alerte.
    const retryable = response.status >= 500 || response.status === 408 || response.status === 429;

    logLeadEvent('marketing.lead.persist_failed', {
      id: lead.id,
      type: lead.type,
      status: response.status,
      attempt,
      retryable,
    });

    return { ok: false, retryable, failure: { status: response.status } };
  } catch (error) {
    const errorName = error instanceof Error ? error.name : 'unknown';

    logLeadEvent('marketing.lead.persist_error', {
      id: lead.id,
      type: lead.type,
      attempt,
      // `AbortError` = délai dépassé (typiquement un cold start de l'API).
      error: errorName,
      retryable: true,
    });

    return { ok: false, retryable: true, failure: { error: errorName } };
  } finally {
    clearTimeout(timeout);
  }
}

/**
 * #7301 — attend `task` au plus `budgetMs` avant de laisser partir la réponse.
 * Au-delà du budget, renvoie `pending` : le prospect n'attend pas un cold start
 * de l'API, l'écriture se poursuit en tâche de fond.
 */
export async function settleWithin(
  task: Promise<boolean>,
  budgetMs: number
): Promise<LeadPersistOutcome> {
  let timer: ReturnType<typeof setTimeout> | undefined;
  const budget = new Promise<'pending'>((resolve) => {
    timer = setTimeout(() => resolve('pending'), budgetMs);
  });

  try {
    return await Promise.race([
      task.then((ok): LeadPersistOutcome => (ok ? 'persisted' : 'failed')),
      budget,
    ]);
  } finally {
    if (timer !== undefined) {
      clearTimeout(timer);
    }
  }
}

/**
 * #7301 — poursuit `task` APRÈS l'envoi de la réponse. `after()` est la
 * primitive Next prévue pour cela : sans elle, la plateforme serverless peut
 * geler la fonction dès la réponse envoyée et l'écriture n'aboutit jamais.
 * (C'est précisément pour cela que l'ancien code *attendait* l'écriture — mais
 * avec un délai de 2,5 s qui la tuait sur une instance froide.)
 */
function deferLeadPersistence(task: Promise<boolean>): void {
  try {
    after(async () => {
      await task;
    });
  } catch {
    // Hors contexte de requête (tests unitaires, scripts) : `after()` n'est pas
    // disponible ; la promesse se termine seule — elle ne rejette jamais.
    void task;
  }
}

/**
 * #7301 — échec de persistance = **alerte**. On loggue en `console.error`
 * (niveau que l'observabilité de la plateforme sait remonter, contrairement au
 * `console.info` d'origine qui n'alertait personne) et on relaie
 * optionnellement vers `MARKETING_ALERT_WEBHOOK_URL` (Slack/CRM/mail).
 */
export function logLeadAlert(event: string, payload: Record<string, unknown>): void {
  console.error(
    JSON.stringify({
      event,
      service: 'leopardo-web',
      severity: 'error',
      ...payload,
    })
  );

  void notifyAlertWebhook(event, payload);
}

async function notifyAlertWebhook(
  event: string,
  payload: Record<string, unknown>
): Promise<void> {
  const url = process.env.MARKETING_ALERT_WEBHOOK_URL;

  if (!url) {
    return;
  }

  try {
    await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        event,
        service: 'leopardo-web',
        severity: 'error',
        ...payload,
      }),
      signal: AbortSignal.timeout(2000),
    });
  } catch {
    // Une alerte qui échoue ne doit jamais casser la requête appelante.
  }
}

async function forwardLead(
  target: ForwarderTarget,
  lead: Record<string, unknown>
): Promise<boolean> {
  const url =
    target === 'crm'
      ? process.env.MARKETING_CRM_WEBHOOK_URL
      : process.env.MARKETING_EMAIL_WEBHOOK_URL;

  if (!url) {
    return false;
  }

  const timeoutMs = Number(process.env.MARKETING_LEAD_FORWARD_TIMEOUT_MS || DEFAULT_TIMEOUT_MS);
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);

  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: buildForwardHeaders(target),
      body: JSON.stringify({
        target,
        lead,
      }),
      signal: controller.signal,
    });

    if (!response.ok) {
      logLeadEvent('marketing.lead.forward_failed', {
        id: lead.id,
        type: lead.type,
        target,
        status: response.status,
      });

      return false;
    }

    return true;
  } catch (error) {
    logLeadEvent('marketing.lead.forward_error', {
      id: lead.id,
      type: lead.type,
      target,
      error: error instanceof Error ? error.name : 'unknown',
    });

    return false;
  } finally {
    clearTimeout(timeout);
  }
}

function buildForwardHeaders(target: ForwarderTarget): HeadersInit {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'X-Leopardo-Lead-Target': target,
  };
  const token = process.env.MARKETING_LEAD_WEBHOOK_TOKEN;

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  return headers;
}

function logLeadEvent(event: string, payload: Record<string, unknown>): void {
  if (process.env.NODE_ENV === 'test') {
    return;
  }

  console.info(
    JSON.stringify({
      event,
      service: 'leopardo-web',
      ...payload,
    })
  );
}
