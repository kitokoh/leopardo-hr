import { type Page } from '@playwright/test';

// Issue #2746 — le middleware serveur (`src/middleware.ts`, merge #2364)
// protège la zone dashboard via le cookie httpOnly `leopardo_token`, posé
// par le proxy Next.js `src/app/api/v1/auth/login/route.ts` après un vrai
// login. Les tests e2e mockés court-circuitent ce proxy : ils doivent donc
// poser le cookie eux-mêmes (exactement comme le ferait le backend réel),
// sinon le middleware redirige toute navigation dashboard vers /auth/login.

export const SESSION_COOKIE_NAME = 'leopardo_token';
export const E2E_SESSION_TOKEN = 'e2e-mocked-session-token';

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
  ]);
}
