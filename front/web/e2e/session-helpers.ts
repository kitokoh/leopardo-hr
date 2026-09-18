import { type Page } from '@playwright/test';

// Issue #2746 — le proxy serveur (`src/proxy.ts`, ex-middleware, merge #2364)
// protège la zone dashboard via le cookie httpOnly `leopardo_token`, posé
// par le proxy Next.js `src/app/api/v1/auth/login/route.ts` après un vrai
// login. Les tests e2e mockés court-circuitent ce proxy : ils doivent donc
// poser le cookie eux-mêmes (exactement comme le ferait le backend réel),
// sinon le middleware redirige toute navigation dashboard vers /auth/login.

export const SESSION_COOKIE_NAME = 'leopardo_token';
export const E2E_SESSION_TOKEN = 'e2e-mocked-session-token';

// Issue #7618 (suite) — la bannière de consentement cookies (#7593,
// `ConsentBanner`, `z-[90]`, fixed bottom, montée dans `src/app/layout.tsx`
// donc AUSSI sur le dashboard) s'affiche tant que le cookie
// `leopardo_consent` est absent, et son conteneur intercepte les clics sur
// les éléments du bas de page (timeouts Playwright constatés sur les boutons
// d'action des tableaux restaurant : « Confirmer », « Envoyer »).
// Une session E2E mockée modélise donc un visiteur qui a DÉJÀ exprimé son
// choix — tout refusé, le défaut CNIL — pour que la bannière ne s'affiche
// jamais. La valeur doit rester parsable par `parseConsent`
// (`src/modules/vitrine/lib/consent.ts`) avec la version courante du texte.
export const CONSENT_COOKIE_NAME = 'leopardo_consent';
export const E2E_CONSENT_STATE = JSON.stringify({
  version: 1,
  necessary: true,
  analytics: false,
  marketing: false,
  decidedAt: '2026-09-01T08:00:00+00:00',
});

/** Header Set-Cookie à ajouter aux réponses mockées de `POST /auth/login`. */
export const sessionCookieHeader = `${SESSION_COOKIE_NAME}=${E2E_SESSION_TOKEN}; Path=/; HttpOnly; SameSite=Lax`;

/** Pose le cookie de session, comme le fait le proxy Next après un login réel. */
export async function setSessionCookie(page: Page): Promise<void> {
  const base = process.env.BASE_URL || 'http://localhost:3000';
  await page.context().addCookies([
    {
      name: SESSION_COOKIE_NAME,
      value: E2E_SESSION_TOKEN,
      url: base,
      httpOnly: true,
      sameSite: 'Lax',
    },
    {
      // #7618 — visiteur ayant déjà décidé : pas de bannière cookies qui
      // intercepte les clics. Lu par `document.cookie` ⇒ PAS httpOnly.
      name: CONSENT_COOKIE_NAME,
      value: encodeURIComponent(E2E_CONSENT_STATE),
      url: base,
      sameSite: 'Lax',
    },
  ]);
}
