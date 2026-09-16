import { NextRequest } from 'next/server';

import { resolveBackendBaseUrl } from '@/lib/backend-url';

/**
 * BC-19 (#7425) — relais serveur du viewer caméra tiers.
 *
 * Le contrat backend est `GET /api/v1/view/cam` avec le jeton dans l'en-tête
 * `X-Token` (`?t=` a été retiré côté API : le jeton ne doit pas traîner dans
 * les logs proxy/CDN). Cette route same-origin reçoit le jeton depuis la page
 * publique `/view/cam`, le relaie en en-tête et renvoie la réponse de l'API
 * telle quelle — la page n'a donc jamais besoin d'appeler le backend en
 * direct (CSP `connect-src 'self'`) ni de connaître son origine de déploiement.
 *
 * Aucune session n'est requise : le jeton tiers EST la credential (la route
 * API est `security: []`). Aucun corps de réponse n'est mis en cache.
 */

export const dynamic = 'force-dynamic';

export async function GET(request: NextRequest): Promise<Response> {
  const token = (request.headers.get('x-token') ?? '').trim();

  if (token === '') {
    return Response.json(
      { error: 'INVALID_TOKEN', message: 'INVALID_TOKEN' },
      { status: 404, headers: { 'Cache-Control': 'no-store' } },
    );
  }

  const acceptLanguage = request.headers.get('accept-language') ?? 'fr';

  let upstream: Response;
  try {
    upstream = await fetch(`${resolveBackendBaseUrl()}/view/cam`, {
      method: 'GET',
      headers: {
        'X-Token': token,
        Accept: 'application/json',
        'Accept-Language': acceptLanguage,
      },
      cache: 'no-store',
      redirect: 'manual',
      signal: AbortSignal.timeout(15_000),
    });
  } catch {
    return Response.json(
      { error: 'backend_unavailable', message: 'backend_unavailable' },
      { status: 502, headers: { 'Cache-Control': 'no-store' } },
    );
  }

  const body = await upstream.text();

  return new Response(body, {
    status: upstream.status,
    headers: {
      'Content-Type': 'application/json',
      'Cache-Control': 'no-store',
    },
  });
}
