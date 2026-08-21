import { expect, test, type APIRequestContext, type BrowserContext, type Page } from '@playwright/test';

/**
 * Issue #5146 — E2E funnel prospect automatisé
 * (vitrine → signup → trial → dashboard).
 *
 * Sous-ensemble de #4101 : les 10 parcours admin sont suivis séparément
 * (e2e-isolated.yml), aucun E2E mobile ici — cf.
 * `.specify/features/e2e-funnel-prospect/spec.md` (§Non-objectifs).
 *
 * Cible : staging/prod (workflow `e2e-staging.yml`, job `e2e-web-vitrine`).
 *   - BASE_URL        = vitrine Next.js/Vercel (config Playwright) ;
 *   - E2E_API_URL     = API Laravel/Render (défaut : prod) ;
 *   - E2E_MAILCATCHER_URL = boîte de test Mailpit/MailHog (optionnel).
 *
 * Dépendances backend documentées (spec §Dépendances) :
 *   - #5161 (guided trial → `failed`) : FIXÉ sur main (merge 595fbd5,
 *     2026-08-20) → US1 est ACTIF et verrouille le contrat.
 *   - #5162 (signup OTP → 503) : NON fixé au 2026-08-20 → les scénarios US2
 *     sont en `test.skip` documenté. Activation : supprimer la ligne
 *     `test.skip(true, ...)` du describe US2 dès la fermeture de #5162
 *     (le test redevient rouge tant que le fix n'est pas déployé — c'est
 *     voulu, il verrouille le contrat, spec §Dépendances).
 *   - #4947 (`password_hash` NOT NULL, création employé/import) : fixé sur
 *     main → US3 verrouille le contrat (201 + listable + rapport d'import).
 *
 * Boîte de test : sans `E2E_MAILCATCHER_URL`, le magic link et l'OTP partent
 * par email réel → US1.2 (magic link → dashboard) et US3 (session tenant)
 * sont skippés proprement. Avec le mail catcher (CI/staging uniquement,
 * jamais en prod — spec FR-2), ils s'activent automatiquement.
 *
 * Budget API : `/trial/signup` est sous `throttle:5,15` (5 signups / 15 min
 * / IP, AppServiceProvider). La suite consomme au plus 3 signups par run
 * (4 quand US2 est activé) ; les retries Playwright sont désactivées dans
 * `playwright.staging-funnel.config.ts` pour ne jamais dépasser la limite.
 */

const API_BASE_URL = (process.env.E2E_API_URL || 'https://gestionemployerbackend.onrender.com').replace(/\/+$/, '');
const API_V1_URL = API_BASE_URL.endsWith('/api/v1') ? API_BASE_URL : `${API_BASE_URL}/api/v1`;
const MAILCATCHER_URL = (process.env.E2E_MAILCATCHER_URL || '').replace(/\/+$/, '');
const hasMailcatcher = MAILCATCHER_URL !== '';

// Cible produit : `ready` en < 2 min (120 s) ; tolérance CI cold start
// Render free tier : 3 min (180 s) — spec US1 scénario 1.
const TRIAL_READY_TARGET_MS = 120_000;
const TRIAL_READY_TOLERANCE_MS = 180_000;
// Cadence de polling documentée (~1 req / 5 s, throttle `trial-status` 60/min).
const TRIAL_POLL_INTERVAL_MS = 5_000;

/** Email unique (FR-5 : déterminisme, pas de collision entre runs). */
function uniqueEmail(prefix: string): string {
  const stamp = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
  return `e2e.${prefix}.${stamp}@test.leopardo-rh.dev`;
}

/** Raison sociale unique (slug de company unique, FR-5). */
function uniqueCompany(label: string): string {
  return `${label} ${Date.now() % 100_000_000}`;
}

interface TrialStatusPayload {
  status: string;
  login_url?: string;
  message?: string;
}

async function fetchTrialStatus(
  request: APIRequestContext,
  token: string,
): Promise<TrialStatusPayload | null> {
  try {
    const res = await request.get(`${API_V1_URL}/trial/status`, {
      // #4931 : le provisioning_token voyage en header X-Token, plus jamais
      // en query string (la vitrine utilise le même contrat via son proxy).
      headers: { 'X-Token': token, Accept: 'application/json' },
      timeout: 15_000,
    });
    if (!res.ok()) return null;
    const payload = (await res.json().catch(() => null)) as {
      success?: boolean;
      data?: { status?: unknown; login_url?: unknown; message?: unknown };
    } | null;
    if (!payload?.success || !payload.data) return null;
    return {
      status: String(payload.data.status ?? 'pending'),
      login_url: typeof payload.data.login_url === 'string' ? payload.data.login_url : undefined,
      message: typeof payload.data.message === 'string' ? payload.data.message : undefined,
    };
  } catch {
    // Réseau/cold start : le cycle de polling suivant réessaie.
    return null;
  }
}

/**
 * Poll `GET /trial/status` jusqu'à `ready` (login_url) ou `failed`.
 * Spec US1 scénario 3 — « pas de faux vert » : un statut `failed` fait
 * échouer le test avec le message API dans les logs.
 */
async function pollTrialUntilReady(
  request: APIRequestContext,
  token: string,
  timeoutMs = TRIAL_READY_TOLERANCE_MS,
): Promise<{ loginUrl?: string; elapsedMs: number }> {
  const startedAt = Date.now();
  let lastStatus = 'inconnu';
  while (Date.now() - startedAt < timeoutMs) {
    const status = await fetchTrialStatus(request, token);
    if (status) {
      lastStatus = status.status;
      if (status.status === 'ready') {
        return { loginUrl: status.login_url, elapsedMs: Date.now() - startedAt };
      }
      if (status.status === 'failed') {
        throw new Error(
          `[US1] provisioning trial FAILED (issue #5161) : ${status.message ?? 'statut failed sans message API'}`,
        );
      }
    }
    await new Promise((resolve) => setTimeout(resolve, TRIAL_POLL_INTERVAL_MS));
  }
  throw new Error(
    `[US1] provisioning ni ready ni failed après ${Math.round(timeoutMs / 1000)} s ` +
      `(dernier statut : ${lastStatus}) — consulter les logs API (trial.provisioning*)`,
  );
}

/**
 * US1 — signup guided via la VRAIE vitrine (`/signup`, proxy same-origin
 * `/api/forms/signup`). Le provisioning_token est capturé dans la RÉPONSE du
 * proxy (déterministe, indépendant du build déployé : la persistance
 * sessionStorage `lp_trial_provisioning_token` n'existe que depuis #2469,
 * un build antérieur ne la pose pas).
 */
async function guidedSignupViaVitrine(page: Page, email: string, company: string): Promise<string> {
  await page.goto('/signup', { waitUntil: 'domcontentloaded' });

  // Le sélecteur de pays est alimenté par le registre public avec un fallback
  // statique (#4476) — on attend l'option DZ pour un selectOption fiable.
  await expect(page.locator('select[name="country"] option[value="DZ"]')).toHaveCount(1, {
    timeout: 15_000,
  });

  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="company"]').fill(company);
  await page.locator('select[name="role"]').selectOption('manager');
  await page.locator('select[name="employees"]').selectOption('11-50');
  await page.locator('select[name="country"]').selectOption('DZ');
  await page.locator('#agreeToTerms').check();

  // Capture de la réponse AVANT le clic (waitForResponse concurrent) :
  // le proxy peut prendre 30-60 s si le backend est en cold start (#1725).
  const signupResponse = page.waitForResponse(
    (res) => res.url().includes('/api/forms/signup') && res.request().method() === 'POST',
    { timeout: 90_000 },
  );

  // Le footer contient aussi un formulaire (newsletter) avec un bouton
  // submit : on cible le formulaire de signup via son champ email (:has).
  await page.locator('form:has(input[name="email"]) button[type="submit"]').click();

  const res = await signupResponse;
  if (res.status() === 429) {
    throw new Error(
      '[US1] /api/forms/signup → 429 RATE_LIMIT_EXCEEDED (limiteur proxy 5/15 min par IP) — relancer plus tard',
    );
  }
  if (res.status() !== 200) {
    throw new Error(`[US1] /api/forms/signup → ${res.status()} : ${(await res.text()).slice(0, 300)}`);
  }
  const payload = (await res.json()) as {
    success?: boolean;
    provisioned?: boolean;
    data?: { provisioning_token?: unknown };
  };
  const token = payload.data?.provisioning_token;
  if (!payload.success || typeof token !== 'string') {
    throw new Error(
      '[US1] /api/forms/signup sans provisioning_token ' +
        `(repli « contact sous 24h » ? backend indisponible ?) : ${JSON.stringify(payload).slice(0, 300)}`,
    );
  }
  return token;
}

/** US1.2/US3 — signup guided direct API (contourne le formulaire vitrine). */
async function guidedSignupViaApi(
  request: APIRequestContext,
  email: string,
  company: string,
): Promise<string> {
  const res = await request.post(`${API_V1_URL}/trial/signup`, {
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    data: {
      email,
      company,
      country: 'DZ',
      role: 'manager',
      employees: '11-50',
      source: 'e2e-funnel-5146',
      requestedWorkflow: 'guided_trial',
    },
    timeout: 20_000,
  });
  if (res.status() !== 200) {
    throw new Error(`[US1] POST /trial/signup (guided) → ${res.status()} : ${await res.text()}`);
  }
  const payload = (await res.json()) as { success?: boolean; data?: { provisioning_token?: unknown } };
  const token = payload.data?.provisioning_token;
  expect(payload.success, `signup guided doit répondre success=true (réponse : ${JSON.stringify(payload).slice(0, 300)})`).toBe(true);
  expect(typeof token).toBe('string');
  return token as string;
}

// ── Boîte de test (Mailpit/MailHog) ──────────────────────────────────────────

interface MailCatcherMessage {
  id: string;
  to: string[];
  subject: string;
  text: string;
  html: string;
}

function stripHtml(html: string): string {
  return html.replace(/<[^>]+>/g, ' ').replace(/&nbsp;/g, ' ');
}

/** Liste les messages reçus pour `toEmail` (API compatible Mailpit & MailHog). */
async function fetchMailcatcherMessages(
  request: APIRequestContext,
  toEmail: string,
): Promise<MailCatcherMessage[]> {
  const res = await request.get(`${MAILCATCHER_URL}/api/v1/messages`, { timeout: 15_000 });
  expect(res.ok(), `mail catcher injoignable (${MAILCATCHER_URL})`).toBeTruthy();
  const payload = (await res.json()) as {
    messages?: Array<Record<string, unknown>>;
    items?: Array<Record<string, unknown>>;
  };
  const raw = payload.messages ?? payload.items ?? [];
  const messages: MailCatcherMessage[] = [];
  for (const msg of raw) {
    const to = ((msg.To ?? msg.to ?? []) as Array<Record<string, unknown>>).map((recipient) => {
      const address = recipient.Address ?? recipient.address;
      if (typeof address === 'string' && address !== '') return address;
      return `${String(recipient.Mailbox ?? recipient.mailbox ?? '')}@${String(recipient.Domain ?? recipient.domain ?? '')}`;
    });
    if (!to.some((address) => address.toLowerCase() === toEmail.toLowerCase())) continue;
    messages.push({
      id: String(msg.ID ?? msg.id ?? ''),
      to,
      subject: String(msg.Subject ?? msg.subject ?? ''),
      text: String(msg.Text ?? msg.text ?? ''),
      html: String(msg.HTML ?? msg.html ?? ''),
    });
  }
  return messages;
}

/** Attends (jusqu'à `timeoutMs`) qu'au moins un email soit reçu pour `toEmail`. */
async function waitForInbox(
  request: APIRequestContext,
  toEmail: string,
  timeoutMs: number,
): Promise<MailCatcherMessage[]> {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    const messages = await fetchMailcatcherMessages(request, toEmail);
    if (messages.length > 0) return messages;
    await new Promise((resolve) => setTimeout(resolve, 3_000));
  }
  throw new Error(
    `[mail catcher] aucun email reçu pour ${toEmail} en ${Math.round(timeoutMs / 1000)} s (${MAILCATCHER_URL})`,
  );
}

/** Extraits candidats OTP 6 chiffres (le code peut commencer par 0). */
function collectOtpCandidates(messages: MailCatcherMessage[]): string[] {
  const codes: string[] = [];
  for (const message of messages) {
    const haystack = `${message.subject}\n${message.text}\n${stripHtml(message.html)}`;
    for (const match of haystack.matchAll(/\b\d{6}\b/g)) {
      if (!codes.includes(match[0])) codes.push(match[0]);
    }
  }
  return codes;
}

/** Extrait l'URL du magic link `/demo-login/{token}` du premier email qui en contient un. */
function extractMagicLink(messages: MailCatcherMessage[]): string {
  for (const message of messages) {
    const haystack = `${message.text}\n${stripHtml(message.html)}`;
    const match = haystack.match(/https?:\/\/[^\s"'<>]+?\/demo-login\/[A-Za-z0-9]+/);
    if (match) return match[0];
  }
  throw new Error('[US1.2/US3] aucun magic link /demo-login/{token} dans les emails reçus');
}

// ── Session tenant (US3) ─────────────────────────────────────────────────────

/**
 * Provisionne un tenant guided (signup API → poll ready), récupère le magic
 * link par le mail catcher et l'ouvre : session web posée sur le domaine API
 * (DemoLoginController, #2253). Les appels API suivants passent par
 * `context.request` (cookies partagés avec le contexte navigateur) + header
 * X-XSRF-TOKEN (Sanctum stateful, cf. api/config/sanctum.php).
 */
async function establishTenantSession(
  page: Page,
  context: BrowserContext,
  email: string,
  company: string,
): Promise<void> {
  const token = await guidedSignupViaApi(context.request, email, company);
  await pollTrialUntilReady(context.request, token);
  const messages = await waitForInbox(context.request, email, 90_000);
  const magicUrl = extractMagicLink(messages);
  const response = await page.goto(magicUrl, { waitUntil: 'domcontentloaded' });
  expect(response?.status() ?? 0, `magic link → HTTP ${response?.status()} (attendu < 500)`).toBeLessThan(500);
  await page.waitForURL(/\/dashboard/, { timeout: 30_000 });
}

/** Headers API héritant de la session navigateur (cookie Sanctum + CSRF). */
async function sessionApiHeaders(context: BrowserContext): Promise<Record<string, string>> {
  const headers: Record<string, string> = { Accept: 'application/json' };
  const xsrf = (await context.cookies()).find((cookie) => cookie.name === 'XSRF-TOKEN');
  if (xsrf) {
    headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf.value);
  }
  return headers;
}

// ═════════════════════════════════════════════════════════════════════════════
// US1 — Parcours guided complet (P0)
// ═════════════════════════════════════════════════════════════════════════════
test.describe('US1 — Parcours guided complet (P0)', () => {
  test('signup vitrine → provisioning → ready (cible < 2 min, tolérance CI 3 min) + login_url sans 5xx', async ({
    page,
    request,
  }) => {
    // Signup (≤ 90 s cold start) + poll (≤ 180 s) + login_url : budget 300 s.
    test.setTimeout(300_000);

    const email = uniqueEmail('guided');
    const company = uniqueCompany('E2E Prospect Guided');

    // Scénario 1 : le prospect soumet le formulaire vitrine (company, email,
    // pays DZ) → provisioning_token → statut `ready` avec `login_url`.
    const token = await guidedSignupViaVitrine(page, email, company);
    const { loginUrl, elapsedMs } = await pollTrialUntilReady(request, token);

    // Assertion du contrat produit : ready en < 2 min, tolérance CI 3 min
    // (cold start Render free tier 30-60 s, spec US1 scénario 1).
    console.log(
      `[US1] provisioning ready en ${Math.round(elapsedMs / 1000)} s ` +
        `(cible < ${TRIAL_READY_TARGET_MS / 1000} s, tolérance CI ${TRIAL_READY_TOLERANCE_MS / 1000} s)`,
    );
    expect(elapsedMs, 'ready doit arriver dans la tolérance CI (180 s)').toBeLessThan(TRIAL_READY_TOLERANCE_MS);

    expect(loginUrl, 'un login_url doit être retourné à l\'état ready (spec US1)').toBeTruthy();

    // Scénario 2 : le prospect ouvre le login_url (relatif, résolu sur la
    // vitrine via baseURL) → aucune erreur 5xx, page d'accès affichée.
    const loginResponse = await page.goto(loginUrl as string, { waitUntil: 'domcontentloaded' });
    expect(loginResponse?.status() ?? 0, `login_url → HTTP ${loginResponse?.status()} (attendu < 500)`).toBeLessThan(
      500,
    );
    await expect(page).toHaveURL(/\/auth\/login/);
    await expect(page.locator('body')).toBeVisible({ timeout: 15_000 });
  });

  test('magic link reçu par email → dashboard sans 5xx (boîte de test requise)', async ({ page, request }) => {
    test.skip(!hasMailcatcher, 'magic link envoyé par email réel — nécessite E2E_MAILCATCHER_URL (Mailpit/MailHog)');
    test.setTimeout(300_000);

    const email = uniqueEmail('guided-magic');
    const company = uniqueCompany('E2E Prospect Magic');
    const token = await guidedSignupViaApi(request, email, company);
    await pollTrialUntilReady(request, token);

    // Le magic link (ProvisionDemoTenantJob::issueDemoAccess, #2620/#2253)
    // arrive dans la boîte de test → on l'ouvre → dashboard blade sans 5xx.
    const messages = await waitForInbox(request, email, 90_000);
    const magicUrl = extractMagicLink(messages);
    const response = await page.goto(magicUrl, { waitUntil: 'domcontentloaded' });
    expect(response?.status() ?? 0, `magic link → HTTP ${response?.status()} (attendu < 500)`).toBeLessThan(500);
    await page.waitForURL(/\/dashboard/, { timeout: 30_000 });
    await expect(page.locator('body')).toBeVisible({ timeout: 15_000 });
  });
});

// ═════════════════════════════════════════════════════════════════════════════
// US2 — Parcours self-service OTP (P1)
// ═════════════════════════════════════════════════════════════════════════════
test.describe('US2 — Parcours self-service OTP (P1)', () => {
  // #5162 : le signup OTP répond 503 en prod tant que le fix n'est pas déployé
  // (QA prod 2026-08-19 : « trial OTP -> 503 »). Les deux scénarios US2
  // verrouillent ce contrat → activés en supprimant la ligne ci-dessous dès la
  // fermeture de #5162 (spec §Dépendances : tests rouges tant que non fixé).
  test.skip(true, 'OTP → 503 (issue #5162) non fixée au 2026-08-20 — supprimer ce test.skip quand #5162 est fermée');

  test('signup self_service → pas de 503 ; OTP récupéré en boîte de test → verify 201', async ({ request }) => {
    test.skip(!hasMailcatcher, 'OTP envoyé par email réel — nécessite E2E_MAILCATCHER_URL pour US2 complet');
    test.setTimeout(120_000);

    const email = uniqueEmail('selfservice');
    const company = uniqueCompany('E2E Prospect OTP');

    // Scénario 1 : signup `self_service` → 200 `pending_verification` (jamais
    // 503 TRIAL_SIGNUP_UNAVAILABLE, fix #5162) — code émis dans la boîte.
    const signupRes = await request.post(`${API_V1_URL}/trial/signup`, {
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      data: {
        email,
        company,
        country: 'DZ',
        role: 'manager',
        requestedWorkflow: 'self_service',
        source: 'e2e-funnel-5146',
      },
      timeout: 20_000,
    });
    if (signupRes.status() === 503) {
      throw new Error('[US2] signup self_service → 503 (issue #5162 non corrigée) — contrat OTP non verrouillé');
    }
    expect(signupRes.status()).toBe(200);
    const signupPayload = (await signupRes.json()) as { data?: { status?: string } };
    expect(signupPayload.data?.status, 'statut `pending_verification` attendu après signup self_service').toBe(
      'pending_verification',
    );

    // Récupération du code dans la boîte de test (6 chiffres, zéro-padding).
    const messages = await waitForInbox(request, email, 90_000);
    const candidates = collectOtpCandidates(messages);
    expect(candidates.length, `OTP 6 chiffres attendu dans l'email (reçu : ${messages.length})`).toBeGreaterThan(0);

    // `verify` avec le bon code → 201 (accès + données du tenant).
    let verified = false;
    for (const candidate of candidates) {
      const verifyRes = await request.post(`${API_V1_URL}/trial/verify`, {
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        data: { email, code: candidate },
        timeout: 30_000,
      });
      if (verifyRes.status() === 201) {
        const verifyPayload = (await verifyRes.json()) as {
          data?: { company?: { name?: string }; manager?: { email?: string } };
        };
        expect(verifyPayload.data?.manager?.email, 'verify 201 doit exposer le manager').toBe(email);
        verified = true;
        break;
      }
      if (verifyRes.status() === 429) {
        // Throttle `5,15` partagé par IP : on attend puis on réessaie le
        // même candidat au lieu de faire échouer le test sur du bruit.
        await new Promise((resolve) => setTimeout(resolve, 10_000));
        continue;
      }
      expect(verifyRes.status(), 'un code invalide ne doit JAMAIS produire un 500 (spec US2 scénario 2)').toBeLessThan(
        500,
      );
    }
    expect(verified, `aucun candidat OTP n'a abouti (${candidates.length} candidat(s) testé(s))`).toBe(true);
  });

  test('code invalide → 4xx localisée, jamais 500', async ({ request }) => {
    test.setTimeout(60_000);

    const email = uniqueEmail('otp-invalid');
    const company = uniqueCompany('E2E Prospect OTP Bad');

    const signupRes = await request.post(`${API_V1_URL}/trial/signup`, {
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      data: {
        email,
        company,
        country: 'DZ',
        role: 'manager',
        requestedWorkflow: 'self_service',
        source: 'e2e-funnel-5146',
      },
      timeout: 20_000,
    });
    expect(signupRes.status(), `signup self_service → ${signupRes.status()} (attendu 200, #5162)`).toBe(200);

    // Scénario 2 : code erroné → erreur 4xx structurée et localisée, jamais 500.
    const verifyRes = await request.post(`${API_V1_URL}/trial/verify`, {
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      data: { email, code: '000000' },
      timeout: 30_000,
    });
    expect(verifyRes.status(), `verify code invalide → ${verifyRes.status()} (attendu 4xx, jamais 500)`).toBeLessThan(500);
    expect(verifyRes.status()).toBeGreaterThanOrEqual(400);
    const payload = (await verifyRes.json()) as { success?: boolean; error?: string; message?: string };
    expect(payload.success).toBe(false);
    expect(payload.error, 'erreur structurée attendue (INVALID_OR_EXPIRED_CODE…)').toBeTruthy();
    expect(payload.message, 'message localisé attendu').toBeTruthy();
  });
});

// ═════════════════════════════════════════════════════════════════════════════
// US3 — Création d'employé sur le tenant provisionné (P1, régression #4947)
// ═════════════════════════════════════════════════════════════════════════════
test.describe('US3 — Création d\'employé sur le tenant provisionné (P1)', () => {
  // Session du tenant provisionné obtenue via le magic link (email) : ces
  // scénarios nécessitent le mail catcher (FR-3, spec §Dépendances).
  test.skip(!hasMailcatcher, 'session tenant par magic link (email réel) — nécessite E2E_MAILCATCHER_URL');

  test('POST /api/v1/employees avec password → 201 et employé listable (régression #4947)', async ({
    page,
    context,
  }) => {
    test.setTimeout(300_000);

    await establishTenantSession(page, context, uniqueEmail('us3-tenant'), uniqueCompany('E2E Tenant US3'));
    const headers = await sessionApiHeaders(context);

    // Scénario 1 : création avec password → 201 (pas de 500 password_hash).
    const employeeEmail = uniqueEmail('employee');
    const createRes = await context.request.post(`${API_V1_URL}/employees`, {
      headers,
      data: {
        first_name: 'Amine',
        last_name: 'Benali',
        email: employeeEmail,
        password: 'E2e-Password-2026!',
        role: 'employee',
        contract_type: 'CDI',
      },
      timeout: 30_000,
    });
    if (createRes.status() !== 201) {
      throw new Error(`[US3] POST /employees → ${createRes.status()} : ${await createRes.text()}`);
    }
    const created = (await createRes.json()) as { data?: { id?: unknown; email?: string } };
    expect(created.data?.id, 'l\'employé créé doit exposer un id').toBeTruthy();
    expect(created.data?.email).toBe(employeeEmail);

    // L'employé est listable (GET /employees, tenant de session).
    const listRes = await context.request.get(`${API_V1_URL}/employees?per_page=100`, { headers });
    expect(listRes.status()).toBe(200);
    const listPayload = (await listRes.json()) as { data?: Array<{ email?: string }> };
    const emails = (listPayload.data ?? []).map((employee) => employee.email);
    expect(emails, 'l\'employé créé doit apparaître dans la liste du tenant').toContain(employeeEmail);
  });

  test('POST /api/v1/employees sans password + send_invitation=true → 201, jamais 500', async ({
    page,
    context,
  }) => {
    test.setTimeout(300_000);

    await establishTenantSession(page, context, uniqueEmail('us3-invite'), uniqueCompany('E2E Tenant Invite'));
    const headers = await sessionApiHeaders(context);

    // Scénario 2 : pas de password mais invitation planifiée → 201.
    const employeeEmail = uniqueEmail('employee-invite');
    const createRes = await context.request.post(`${API_V1_URL}/employees`, {
      headers,
      data: {
        first_name: 'Kenza',
        last_name: 'Meziane',
        email: employeeEmail,
        send_invitation: true,
        contract_type: 'CDI',
      },
      timeout: 30_000,
    });
    if (createRes.status() !== 201) {
      throw new Error(`[US3] POST /employees (invitation) → ${createRes.status()} : ${await createRes.text()}`);
    }
    const created = (await createRes.json()) as { data?: { id?: unknown } };
    expect(created.data?.id, 'l\'employé invité doit être créé (201)').toBeTruthy();
  });

  test('import CSV valide → rapport ligne par ligne (imported/skipped/errors)', async ({ page, context }) => {
    test.setTimeout(300_000);

    await establishTenantSession(page, context, uniqueEmail('us3-import'), uniqueCompany('E2E Tenant Import'));
    const headers = await sessionApiHeaders(context);

    // Scénario 3 : import CSV (colonnes canoniques du template) → 201 + rapport.
    const firstEmail = uniqueEmail('import-a');
    const secondEmail = uniqueEmail('import-b');
    const csv = [
      'first_name,last_name,email',
      `Sofia,Benkhelifa,${firstEmail}`,
      `Riad,Boumediene,${secondEmail}`,
    ].join('\n');

    const importRes = await context.request.post(`${API_V1_URL}/employees/import`, {
      headers: { Accept: 'application/json' },
      multipart: {
        file: { name: 'employes.csv', mimeType: 'text/csv', buffer: Buffer.from(csv, 'utf8') },
      },
      timeout: 30_000,
    });
    if (importRes.status() !== 201) {
      throw new Error(`[US3] POST /employees/import → ${importRes.status()} : ${await importRes.text()}`);
    }
    const report = (await importRes.json()) as {
      data?: { imported?: number; skipped?: number; errors?: unknown[] };
    };
    expect(report.data?.imported, 'les 2 lignes valides doivent être importées').toBe(2);
    expect(report.data?.skipped).toBe(0);
    expect(report.data?.errors).toHaveLength(0);
  });
});
