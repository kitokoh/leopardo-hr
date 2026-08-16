/**
 * @jest-environment node
 *
 * #4393 — la locale dérivée d'Accept-Language doit alimenter x-vitrine-lang
 * quand ?lang= est absent (sinon metadata FR sur html lang=EN).
 */
import { middleware } from '@/middleware';

function fakeRequest({
  pathname,
  search,
  acceptLanguage,
}: {
  pathname: string;
  search: string;
  acceptLanguage: string | null;
}) {
  const url = `https://x.example${pathname}${search}`;
  return {
    url,
    nextUrl: new URL(url),
    headers: new Headers(acceptLanguage ? { 'accept-language': acceptLanguage } : {}),
    cookies: { get: () => undefined },
  } as unknown as Parameters<typeof middleware>[0];
}

describe('middleware x-vitrine-lang (#4393)', () => {
  it.each([
    ['/pricing', '', 'en-US,en;q=0.9', 'en'],
    ['/pricing', '', 'fr-FR,fr;q=0.9', 'fr'],
    ['/pricing', '', 'ar-SA,ar;q=0.8', 'ar'],
    ['/pricing', '', 'tr-TR,tr;q=0.8', 'tr'],
    // Locale non supportée → défaut fr (même règle que resolveSsrLang).
    ['/pricing', '', 'de-DE,de;q=0.9', 'fr'],
    // ?lang= explicite reste prioritaire sur Accept-Language.
    ['/pricing', '?lang=ar', 'en-US,en;q=0.9', 'ar'],
    // Pas d'en-tête → fr.
    ['/pricing', '', null, 'fr'],
  ])('%s%s accept=%s → %s', (pathname, search, accept, expected) => {
    const res = middleware(fakeRequest({ pathname, search, acceptLanguage: accept }));
    expect(res.headers.get('x-vitrine-lang')).toBe(expected);
  });
});
