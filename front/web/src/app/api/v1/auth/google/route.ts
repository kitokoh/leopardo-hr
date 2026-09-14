import { NextRequest, NextResponse } from 'next/server';

import { resolveBackendBaseUrl } from '@/lib/backend-url';

/**
 * Audit onboarding 2026-09-14 — le bouton « Continuer avec Google » pointait
 * directement sur le proxy générique `/api/v1/auth/google` : quand l'API
 * répondait `503 GOOGLE_OAUTH_NOT_CONFIGURED` (aucun `GOOGLE_CLIENT_ID` posé),
 * le navigateur affichait le **JSON brut** de l'erreur. L'utilisateur restait
 * bloqué sur une page blanche technique, sans retour vers le formulaire.
 *
 * Ce handler dédié :
 *  - relaie l'appel OAuth côté serveur (le state CSRF reste dans le cookie de
 *    session de l'API, réémis ici vers le navigateur) ;
 *  - en succès : redirige vers l'écran de consentement Google ;
 *  - en échec (OAuth non configuré, panne, réponse inattendue) : redirige vers
 *    l'écran de connexion avec un code d'erreur affichable, au lieu du JSON.
 */
const OAUTH_STATE_COOKIE_PASSTHROUGH = ['set-cookie'];

function safeNextPath(raw: string | null): string | null {
  if (!raw) return null;
  // On n'accepte qu'un chemin interne (« /dashboard »), jamais une URL absolue :
  // sinon le paramètre devient un open-redirect.
  return raw.startsWith('/') && !raw.startsWith('//') ? raw : null;
}

function errorRedirect(request: NextRequest, code: string, next: string | null) {
  const url = new URL('/auth/login', request.url);
  url.searchParams.set('error', code);
  if (next) url.searchParams.set('next', next);
  return NextResponse.redirect(url);
}

export async function GET(request: NextRequest) {
  const next = safeNextPath(request.nextUrl.searchParams.get('next'));

  // On relaie TOUTE la query au backend (GoogleAuthButton y met `intent=signup`
  // et `plan=…`) — seul `next`, qui n'existe que pour cette vitrine, est retiré.
  const upstreamParams = new URLSearchParams(request.nextUrl.searchParams);
  upstreamParams.delete('next');

  let upstream: Response;
  try {
    upstream = await fetch(`${resolveBackendBaseUrl()}/auth/google?${upstreamParams.toString()}`, {
      method: 'GET',
      headers: {
        Accept: 'application/json',
        // Le cookie de session de l'API porte le `state` OAuth : il doit
        // accompagner la requête, sinon le callback rejette la réponse.
        Cookie: request.headers.get('cookie') ?? '',
      },
      redirect: 'manual',
      cache: 'no-store',
      signal: AbortSignal.timeout(15_000),
    });
  } catch {
    return errorRedirect(request, 'google_network', next);
  }

  const location = upstream.headers.get('location');

  if (upstream.status >= 300 && upstream.status < 400 && location) {
    const redirect = NextResponse.redirect(location);
    // Réémission des cookies de session de l'API sur le domaine vitrine.
    for (const header of OAUTH_STATE_COOKIE_PASSTHROUGH) {
      for (const value of upstream.headers.getSetCookie?.() ?? []) {
        if (header === 'set-cookie') redirect.headers.append('set-cookie', value);
      }
    }
    return redirect;
  }

  // 503 GOOGLE_OAUTH_NOT_CONFIGURED, 5xx, réponse JSON inattendue…
  return errorRedirect(request, 'google_unavailable', next);
}
