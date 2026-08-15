import { NextRequest, NextResponse } from 'next/server';
import { resolveBackendBaseUrl } from '@/lib/backend-url';

// Issue #2469 — Suivi du provisioning des essais guidés (suite #2437).
//
// Proxy same-origin vers `GET /api/v1/trial/status?token=…` du backend.
// Le provisioning_token est passé en query (HTTPS), jamais l'email brut.
// La réponse backend est relayée telle quelle : le client n'a pas besoin
// de connaître l'URL du backend (pas de CORS, pas de secret exposé).

const LEOPARDO_API_URL =
  process.env.LEOPARDO_API_URL || resolveBackendBaseUrl().replace(/\/api\/v1$/, '');

export async function GET(request: NextRequest) {
  const token = request.nextUrl.searchParams.get('token') || '';

  // Garde-fou : le backend attend exactement 64 caractères (size:64).
  if (!/^[A-Za-z0-9]{64}$/.test(token)) {
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
    const upstream = await fetch(
      `${LEOPARDO_API_URL}/api/v1/trial/status?token=${encodeURIComponent(token)}`,
      {
        method: 'GET',
        headers: {
          Accept: 'application/json',
        },
        // Le provisioning peut prendre quelques secondes (provision du tenant).
        signal: AbortSignal.timeout(10000),
        cache: 'no-store',
      }
    );

    const payload = await upstream.json();

    return NextResponse.json(payload, {
      status: upstream.status,
      headers: {
        'Cache-Control': 'no-store',
      },
    });
  } catch (error) {
    // Erreur réseau / timeout backend : le client réessaiera (polling).
    console.error(
      JSON.stringify({
        event: 'marketing.trial_status_proxy_error',
        service: 'leopardo-web',
        error: error instanceof Error ? error.name : 'NETWORK_ERROR',
      })
    );

    return NextResponse.json(
      {
        success: false,
        error: 'TRIAL_STATUS_UNAVAILABLE',
        message: 'Le statut de votre espace est momentanément indisponible. Veuillez réessayer.',
      },
      { status: 502 }
    );
  }
}
