import { NextRequest, NextResponse } from 'next/server';
import { resolveBackendBaseUrl } from '@/lib/backend-url';
import { z } from 'zod';
import { areFormsEnabled, formsDisabledResponse, getClientIp } from '../_lib/lead-capture';
import { RateLimiter } from '@/modules/vitrine/lib/validation';

// #2469 : proxy same-origin vers GET /api/v1/trial/status (suivi du
// provisioning de l'essai guidé, volet backend #2437 / PR #2445).
// Le token transite en query (pattern identique aux autres routes
// /api/forms/*) ; le client le garde en sessionStorage, jamais dans l'URL
// visible ni dans l'email. Aucune donnée personnelle n'est exposée.

const rateLimiter = new RateLimiter(20, 60 * 1000);

const tokenSchema = z.string().length(64).regex(/^[A-Za-z0-9]+$/);

const LEOPARDO_API_URL =
  process.env.LEOPARDO_API_URL || resolveBackendBaseUrl().replace(/\/api\/v1$/, '');

export async function GET(request: NextRequest) {
  if (!areFormsEnabled()) {
    return formsDisabledResponse();
  }

  const ip = getClientIp(request);
  if (!rateLimiter.isAllowed(ip)) {
    return NextResponse.json(
      {
        success: false,
        message: 'Trop de tentatives. Veuillez reessayer plus tard.',
        error: 'RATE_LIMIT_EXCEEDED',
      },
      { status: 429 }
    );
  }

  const token = request.nextUrl.searchParams.get('token') || '';
  if (!tokenSchema.safeParse(token).success) {
    return NextResponse.json(
      {
        success: false,
        message: 'Jeton de suivi invalide.',
        error: 'PROVISIONING_TOKEN_INVALID',
      },
      { status: 400 }
    );
  }

  try {
    const trialResponse = await fetch(
      `${LEOPARDO_API_URL}/api/v1/trial/status?token=${encodeURIComponent(token)}`,
      {
        method: 'GET',
        headers: {
          Accept: 'application/json',
        },
        // Timeout court : le polling tourne toutes les ~5 s, on ne veut pas
        // laisser des requêtes pendantes empiler les workers.
        signal: AbortSignal.timeout(8000),
      }
    );

    const trialData = await trialResponse.json();

    if (trialResponse.ok && trialData.success) {
      return NextResponse.json({
        success: true,
        data: {
          status: trialData.data?.status ?? 'pending',
          login_url: trialData.data?.login_url,
        },
      });
    }

    if (trialResponse.status === 404) {
      return NextResponse.json(
        {
          success: false,
          message: 'Jeton de suivi introuvable ou expire.',
          error: 'PROVISIONING_TOKEN_INVALID',
        },
        { status: 404 }
      );
    }

    return NextResponse.json(
      {
        success: false,
        message: trialData.message || 'Statut du provisioning indisponible.',
        error: trialData.error || 'TRIAL_STATUS_ERROR',
      },
      { status: 502 }
    );
  } catch (error) {
    const name = error instanceof Error ? error.name : 'NETWORK_ERROR';
    console.error(
      JSON.stringify({
        event: 'marketing.trial_status_proxy_failed',
        service: 'leopardo-web',
        error: name,
      })
    );
    // Le polling du client absorbe les erreurs transitoires (il réessaie) —
    // on renvoie un 502 sans données pour ne pas casser le flux.
    return NextResponse.json(
      {
        success: false,
        message: 'Le statut de votre espace est momentanement indisponible.',
        error: 'TRIAL_STATUS_UNAVAILABLE',
      },
      { status: 502 }
    );
  }
}
