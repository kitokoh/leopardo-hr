import { NextRequest, NextResponse } from 'next/server';

import { resolveBackendBaseUrl } from '@/lib/backend-url';
import { RateLimiter } from '@/modules/vitrine/lib/validation';

import { areFormsEnabled, formsDisabledResponse, getClientIp } from '../_lib/lead-capture';
import { isValidProvisioningToken } from '../_lib/provisioning-token';

/**
 * POST /api/forms/trial-password
 *
 * Proxy same-origin vers `POST /api/v1/trial/set-password` du backend.
 *
 * Onboarding **sans dépendance au mailer** : le parcours guidé créait le
 * manager avec un mot de passe aléatoire jamais communiqué et un `login_url`
 * vers une page à mot de passe ; l'email d'accès étant best-effort (mailer non
 * configuré en dev comme en prod), un prospect pouvait être provisionné sans
 * aucun moyen d'entrer. Le `provisioning_token` — déjà détenu par le navigateur,
 * il est renvoyé au signup puis pollé via /api/forms/trial-status — sert
 * désormais à définir soi-même son mot de passe.
 *
 * Le token voyage en en-tête `X-Token` (#4931), jamais dans l'URL.
 *
 * Aucun texte destiné à l'utilisateur ici : seuls des **codes d'erreur** sont
 * relayés, la mise en mots se fait côté client via le catalogue i18n
 * (garde `check-i18n-diff.js`).
 */

const LEOPARDO_API_URL =
  process.env.LEOPARDO_API_URL || resolveBackendBaseUrl().replace(/\/api\/v1$/, '');

// 10 tentatives / minute / IP : large pour une saisie, étroit contre le bourrage.
const rateLimiter = new RateLimiter(10, 60 * 1000);

interface TrialPasswordBody {
  token?: unknown;
  password?: unknown;
  password_confirmation?: unknown;
}

export async function POST(request: NextRequest) {
  if (!areFormsEnabled()) {
    return formsDisabledResponse();
  }

  let body: TrialPasswordBody;
  try {
    body = (await request.json()) as TrialPasswordBody;
  } catch {
    return NextResponse.json({ success: false, error: 'INVALID_JSON' }, { status: 400 });
  }

  const token = typeof body.token === 'string' ? body.token : '';
  const password = typeof body.password === 'string' ? body.password : '';
  const confirmation =
    typeof body.password_confirmation === 'string' ? body.password_confirmation : '';

  if (!isValidProvisioningToken(token)) {
    return NextResponse.json(
      { success: false, error: 'PROVISIONING_TOKEN_INVALID' },
      { status: 404 },
    );
  }

  // Mêmes règles que le backend (`Password::min(8)->numbers()`, #5620) : on
  // évite un aller-retour réseau pour une saisie manifestement invalide.
  if (password.length < 8 || !/[0-9]/.test(password)) {
    return NextResponse.json(
      { success: false, error: 'PASSWORD_TOO_WEAK' },
      { status: 422 },
    );
  }

  if (password !== confirmation) {
    return NextResponse.json(
      { success: false, error: 'PASSWORD_MISMATCH' },
      { status: 422 },
    );
  }

  const ip = getClientIp(request);
  if (!rateLimiter.isAllowed(ip)) {
    return NextResponse.json({ success: false, error: 'RATE_LIMIT_EXCEEDED' }, { status: 429 });
  }

  try {
    const backendResponse = await fetch(`${LEOPARDO_API_URL}/api/v1/trial/set-password`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Token': token,
      },
      body: JSON.stringify({ password, password_confirmation: confirmation }),
      signal: AbortSignal.timeout(10000),
      cache: 'no-store',
    });

    const payload = (await backendResponse.json().catch(() => null)) as {
      success?: boolean;
      error?: string;
      data?: { login_url?: string };
    } | null;

    if (!backendResponse.ok || payload === null || payload.success === false) {
      const passthroughStatuses = [404, 409, 422, 429];
      return NextResponse.json(
        {
          success: false,
          error: payload?.error || 'TRIAL_PASSWORD_UNAVAILABLE',
        },
        {
          status: passthroughStatuses.includes(backendResponse.status)
            ? backendResponse.status
            : 502,
        },
      );
    }

    const loginUrl =
      typeof payload.data?.login_url === 'string' && payload.data.login_url !== ''
        ? payload.data.login_url
        : '/auth/login';

    return NextResponse.json({ success: true, data: { login_url: loginUrl } }, { status: 200 });
  } catch (error) {
    console.error(
      JSON.stringify({
        event: 'marketing.trial_password_proxy_failed',
        service: 'leopardo-web',
        error: error instanceof Error ? error.name : 'NETWORK_ERROR',
      }),
    );

    return NextResponse.json(
      { success: false, error: 'TRIAL_PASSWORD_UNAVAILABLE' },
      { status: 502 },
    );
  }
}
