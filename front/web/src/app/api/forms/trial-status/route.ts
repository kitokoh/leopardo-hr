import { NextRequest, NextResponse } from 'next/server';
import { resolveBackendBaseUrl } from '@/lib/backend-url';

/**
 * GET /api/forms/trial-status?token=...
 *
 * Proxy same-origin vers le backend `GET /api/v1/trial/status` (issue #2469,
 * suite #2437) : permet à la vitrine de suivre l'état du provisioning d'un
 * essai guidé sans exposer l'email brut ni le token dans l'URL visible du
 * navigateur (le token reste en sessionStorage côté client, le polling passe
 * par ce proxy). Même pattern que les autres routes `/api/forms/*` (base URL
 * résolue, timeout court, pas de log d'email).
 */

const LEOPARDO_API_URL =
  process.env.LEOPARDO_API_URL || resolveBackendBaseUrl().replace(/\/api\/v1$/, '');

export async function GET(request: NextRequest) {
  const token = request.nextUrl.searchParams.get('token');

  // Le backend valide `size:64` — on refuse tôt les tokens malformés pour ne
  // pas consommer de bande passante backend sur du bruit.
  if (!token || token.length !== 64) {
    return NextResponse.json(
      {
        success: false,
        error: 'PROVISIONING_TOKEN_INVALID',
        message: 'Jeton de suivi invalide.',
      },
      { status: 400 }
    );
  }

  try {
    const response = await fetch(
      `${LEOPARDO_API_URL}/api/v1/trial/status?token=${encodeURIComponent(token)}`,
      {
        method: 'GET',
        headers: {
          Accept: 'application/json',
        },
        // Timeout court : le polling tourne toutes les ~5 s, un backend lent
        // (cold start Render) ne doit pas empiler les requêtes.
        signal: AbortSignal.timeout(8000),
      }
    );

    const body = await response.json();

    return NextResponse.json(body, { status: response.status });
  } catch (error) {
    const errorName = error instanceof Error ? error.name : 'NETWORK_ERROR';
    console.error(
      JSON.stringify({
        event: 'marketing.trial_status_proxy_failed',
        service: 'leopardo-web',
        error: errorName,
      })
    );
    return NextResponse.json(
      {
        success: false,
        error: 'NETWORK_ERROR',
        message: 'Service temporairement indisponible. Reessayez dans un instant.',
      },
      { status: 502 }
    );
  }
}
