/**
 * #3806 (audit 360° 2026-08-15) — préserver `?lang=` dans la navigation interne.
 *
 * La locale vitrine est portée par `?lang=` (URL) ET par localStorage. Quand un
 * visiteur arrive sur `/?lang=en` et clique un lien Navbar/Footer, le href cible
 * (`/pricing`, `/about`, …) ne portait pas `?lang=` → la locale ne survivait pas
 * au partage/refresh de l'URL (résiduel #3735, qui ne couvrait que le <select>).
 *
 * Règles :
 *  - Liens externes / ancres seules / vides : inchangés.
 *  - Pas de `lang` dans la recherche courante : inchangé (on n'impose jamais
 *    `?lang=` quand l'utilisateur n'en a pas).
 *  - `lang` présent : ajouté à la cible en conservant les query params et
 *    l'ancre existants.
 */

const EXTERNAL_RE = /^(https?:|mailto:|tel:|sms:|whatsapp:)/i;

export function withLocaleHref(href: string, search: string): string {
  if (!href || href.startsWith('#') || EXTERNAL_RE.test(href)) {
    return href;
  }

  const lang = new URLSearchParams(search).get('lang');
  if (!lang) {
    return href;
  }

  const [pathAndQuery, anchor] = href.split('#');
  const [path, query = ''] = pathAndQuery.split('?');

  const params = new URLSearchParams(query);
  params.set('lang', lang);
  const qs = params.toString();

  const base = qs ? `${path}?${qs}` : path;
  return anchor ? `${base}#${anchor}` : base;
}
