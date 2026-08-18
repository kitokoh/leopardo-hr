import { NextRequest, NextResponse } from 'next/server';
import { resolveBackendBaseUrl } from '@/lib/backend-url';
import { areFormsEnabled, formsDisabledResponse, getClientIp } from '../_lib/lead-capture';
import { RateLimiter } from '@/modules/vitrine/lib/validation';
import { isValidProvisioningToken } from '../_lib/provisioning-token';

/**
 * GET /api/forms/trial-status?token=<provisioning_token>
 * Le token est relayé au backend via l'en-tête X-Token (#4931 : plus jamais en query string).
 *
 * #2469 — proxy same-origin vers GET /api/v1/trial/status du backend.
 * Permet à la vitrine de suivre l'état du provisioning du guided trial
 * (pending / ready / failed) sans exposer le token dans l'URL visible ni
 * l'email : le token vit en sessionStorage, ce proxy fait le relais.
 *
 * Pattern identique aux autres routes /api/forms/* (lead-capture, throttle,
 * short timeout). Réponse « pass-through » du backend (status + login_url
 * uniquement quand ready).
 */

const LEOPARDO_API_URL =
  process.env.LEOPARDO_API_URL || resolveBackendBaseUrl().replace(/\/api\/v1$/, '');

// Polling ~5 s côté client → 12 req/min max par prospect. Marge à 30/min.
const rateLimiter = new RateLimiter(30, 60 * 1000);

export async function GET(request: NextRequest) {
  if (!areFormsEnabled()) {
    return formsDisabledResponse();
  }

  const token = request.nextUrl.searchParams.get('token') ?? '';

  if (!isValidProvisioningToken(token)) {
    return NextResponse.json(
      {
        success: false,
        error: 'PROVISIONING_TOKEN_INVALID',
        message: 'Lien de suivi invalide.',
      },
      { status: 404 }
    );
  }

  const ip = getClientIp(request);
  if (!rateLimiter.isAllowed(ip)) {
    return NextResponse.json(
      {
        success: false,
        message: 'Trop de tentatives. Veuillez réessayer plus tard.',
        error: 'RATE_LIMIT_EXCEEDED',
      },
      { status: 429 }
    );
  }

  try {
    const backendResponse = await fetch(
      `${LEOPARDO_API_URL}/api/v1/trial/status`,
      {
        method: 'GET',
        headers: {
          'X-Token': token,
          Accept: 'application/json',
        },
        // Timeout court : le polling reprendra au cycle suivant.
        signal: AbortSignal.timeout(8000),
        cache: 'no-store',
      }
    );

    const payload = await backendResponse.json().catch(() => null);

    if (!backendResponse.ok || payload === null || payload.success === false) {
      // 404 (token inconnu/consommé) ou 5xx backend : même contrat que le
      // backend — la vitrine n'expose jamais l'email ni le token.
      return NextResponse.json(
        {
          success: false,
          error: payload?.error || 'TRIAL_STATUS_UNAVAILABLE',
          message: payload?.message || 'Suivi temporairement indisponible.',
        },
        { status: backendResponse.status === 404 ? 404 : 502 }
      );
    }

    // Pass-through minimal : status + login_url (uniquement quand ready).
    const data = payload.data ?? {};
    const passthrough: Record<string, string> = {
      status: String(data.status ?? 'pending'),
    };
    if (typeof data.login_url === 'string' && data.login_url !== '') {
      passthrough.login_url = data.login_url;
    }
    if (typeof data.message === 'string') {
      passthrough.message = data.message;
    }

    return NextResponse.json({ success: true, data: passthrough }, { status: 200 });
  } catch (error) {
    console.error(
      JSON.stringify({
        event: 'marketing.trial_status_proxy_failed',
        service: 'leopardo-web',
        error: error instanceof Error ? error.name : 'NETWORK_ERROR',
      })
    );
    return NextResponse.json(
      {
        success: false,
        error: 'TRIAL_STATUS_UNAVAILABLE',
        message: 'Suivi temporairement indisponible. Reessayez dans quelques instants.',
      },
      { status: 502 }
    );
  }
}
