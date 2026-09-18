import { NextRequest, NextResponse } from 'next/server';
import { z } from 'zod';
import { areFormsEnabled, formsDisabledResponse, getClientIp } from '../_lib/lead-capture';
import { resolveBackendBaseUrl } from '@/lib/backend-url';
import { RateLimiter } from '@/modules/vitrine/lib/validation';

/**
 * #7496 — collecte first-party des étapes du funnel d'acquisition.
 *
 * Appelée par `trackFunnelStep()` (module vitrine, consentement requis) puis
 * relayée serveur-à-serveur vers `POST /api/v1/funnel/events` de la
 * plateforme, avec le même secret partagé que les leads marketing
 * (`MARKETING_LEAD_WEBHOOK_TOKEN`) — le navigateur ne détient jamais le
 * secret, même pattern que `_lib/lead-capture.ts`.
 *
 * Règles :
 *  - AUCUNE PII : pas d'e-mail, pas de valeur de formulaire, pas de réponse
 *    d'entretien — seulement le nom d'étape (liste fermée), un identifiant de
 *    corrélation pseudonyme et l'attribution (source/utm_*).
 *  - Fire-and-forget : la mesure ne fait jamais échouer le parcours. La
 *    réponse est 202 dès que le payload est accepté ; l'échec du relais est
 *    silencieux côté prospect (loggé côté serveur).
 */

// Généreux (1 événement par étape, ~10 étapes par parcours) mais borné :
// une IP qui dépasse 120 événements / 15 min n'est pas un prospect.
const rateLimiter = new RateLimiter(120, 15 * 60 * 1000);

/** Liste FERMÉE des étapes acceptées — miroir de FUNNEL_EVENTS (funnel.ts). */
const FUNNEL_EVENT_NAMES = [
  'signup_view',
  'signup_email_submitted',
  'signup_otp_sent',
  'signup_otp_verified',
  'space_provisioned',
  'welcome_seen',
  'interview_started',
  'interview_question_answered',
  'interview_skipped',
  'interview_completed',
  'interview_dismissed',
  'first_module_opened',
] as const;

const boundedString = z.string().min(1).max(120);

const funnelEventSchema = z.object({
  event: z.enum(FUNNEL_EVENT_NAMES),
  correlation_id: z.string().min(4).max(64),
  occurred_at: z.string().max(40).optional(),
  attribution: z
    .object({
      source: boundedString.optional(),
      plan: boundedString.optional(),
      module: boundedString.optional(),
      utm_source: boundedString.optional(),
      utm_medium: boundedString.optional(),
      utm_campaign: boundedString.optional(),
      utm_content: boundedString.optional(),
      utm_term: boundedString.optional(),
    })
    .optional(),
  context: z
    .object({
      page: z.string().max(300).optional(),
      step_key: boundedString.optional(),
      question_index: z.number().int().min(0).max(1000).optional(),
      resend: z.boolean().optional(),
    })
    .optional(),
});

const FORWARD_TIMEOUT_MS = Number(process.env.FUNNEL_EVENT_FORWARD_TIMEOUT_MS || 5000);

export async function POST(request: NextRequest) {
  if (!areFormsEnabled()) {
    return formsDisabledResponse();
  }

  try {
    const ip = getClientIp(request);
    if (!rateLimiter.isAllowed(ip)) {
      return NextResponse.json(
        { success: false, error: 'RATE_LIMIT_EXCEEDED' },
        { status: 429 }
      );
    }

    const parsed = funnelEventSchema.safeParse(await request.json());
    if (!parsed.success) {
      return NextResponse.json(
        { success: false, error: 'VALIDATION_ERROR' },
        { status: 422 }
      );
    }

    const backendBase = resolveBackendBaseUrl(); // inclut déjà /api/v1
    const token = process.env.MARKETING_LEAD_WEBHOOK_TOKEN || '';
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    };
    if (token !== '') {
      headers.Authorization = `Bearer ${token}`;
    }

    // Relais fire-and-forget borné : la réponse au navigateur ne dépend pas
    // de l'API (un cold start ne doit pas retarder le tunnel).
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), FORWARD_TIMEOUT_MS);
    void fetch(`${backendBase}/funnel/events`, {
      method: 'POST',
      headers,
      body: JSON.stringify(parsed.data),
      signal: controller.signal,
    })
      .catch((error: unknown) => {
        console.warn('[funnel-event] forward failed', {
          event: parsed.data.event,
          message: error instanceof Error ? error.message : 'unknown',
        });
      })
      .finally(() => clearTimeout(timer));

    return NextResponse.json({ success: true }, { status: 202 });
  } catch {
    // Corps illisible ou erreur inattendue : la mesure n'est jamais une 500
    // visible du tunnel.
    return NextResponse.json(
      { success: false, error: 'VALIDATION_ERROR' },
      { status: 422 }
    );
  }
}
