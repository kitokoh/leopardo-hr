import { cookies } from 'next/headers';
import { NextResponse } from 'next/server';

const COOKIE_NAME = 'leopardo_google_signup';

/**
 * GET /api/forms/google-signup
 *
 * Renvoie l'identité Google **vérifiée** déposée par le callback OAuth
 * (`/api/v1/auth/google/callback`) quand un utilisateur a cliqué « Continuer
 * avec Google » depuis la page d'inscription, puis efface le cookie.
 *
 * QA onboarding 2026-09-14 : sans ce relais, le parcours d'inscription Google
 * était impossible (le bouton n'existait pas sur /signup) et, côté backend, un
 * e-mail Google inconnu renvoyait `UNKNOWN_ACCOUNT` — le parcours
 * « invitation-first » (#3724) interdit d'auto-provisionner un tenant depuis un
 * callback OAuth. On transmet donc l'identité au formulaire, qui la pré-remplit
 * et laisse le tunnel d'essai créer l'espace (même chemin que l'inscription
 * classique).
 *
 * Pourquoi un cookie httpOnly + cette route, plutôt que l'e-mail dans l'URL :
 * une adresse e-mail ne doit pas se retrouver dans les journaux d'accès, les
 * en-têtes `Referer` ou l'historique du navigateur. Le cookie est à usage
 * unique (effacé dès lecture) et de courte durée (10 minutes).
 *
 * Aucun texte utilisateur ici : uniquement des codes d'erreur, la mise en mots
 * appartient au composant (garde i18n PA2-I18N-014).
 */
export async function GET(): Promise<NextResponse> {
  const cookieStore = await cookies();
  const raw = cookieStore.get(COOKIE_NAME)?.value;

  if (!raw) {
    return NextResponse.json(
      { success: false, error: 'NO_GOOGLE_SIGNUP' },
      { status: 404, headers: { 'Cache-Control': 'no-store' } },
    );
  }

  // Usage unique : on ne relit pas l'identité indéfiniment.
  cookieStore.set(COOKIE_NAME, '', {
    httpOnly: true,
    sameSite: 'lax',
    path: '/',
    maxAge: 0,
  });

  try {
    const data = JSON.parse(raw) as {
      email?: unknown;
      first_name?: unknown;
      last_name?: unknown;
      plan?: unknown;
    };

    if (typeof data.email !== 'string' || data.email === '') {
      return NextResponse.json(
        { success: false, error: 'INVALID_GOOGLE_SIGNUP' },
        { status: 422, headers: { 'Cache-Control': 'no-store' } },
      );
    }

    return NextResponse.json(
      {
        success: true,
        data: {
          email: data.email,
          first_name: typeof data.first_name === 'string' ? data.first_name : '',
          last_name: typeof data.last_name === 'string' ? data.last_name : '',
          plan: typeof data.plan === 'string' ? data.plan : null,
        },
      },
      { headers: { 'Cache-Control': 'no-store' } },
    );
  } catch {
    return NextResponse.json(
      { success: false, error: 'INVALID_GOOGLE_SIGNUP' },
      { status: 422, headers: { 'Cache-Control': 'no-store' } },
    );
  }
}
