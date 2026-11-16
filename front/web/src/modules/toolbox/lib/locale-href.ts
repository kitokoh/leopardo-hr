import { TOOLBOX_LOCALES, type ToolboxLocale } from '../data/copy';

/**
 * Propagation de la locale dans les liens de la Toolbox.
 *
 * ## Le problème que ce module résout
 *
 * La Toolbox suit la convention vitrine `?lang=` (aucune route préfixée) : le
 * proxy normalise le paramètre en `x-vitrine-lang`, que les layouts lisent pour
 * rendre le HTML déjà dans la bonne langue.
 *
 * Or `?lang=` n'est **pas** mémorisé entre deux navigations : il ne vit que
 * dans l'URL courante. Un `<Link href="/toolbox/imc">` écrit en dur fait donc
 * repartir le visiteur en français dès le premier clic, quel que soit
 * `Accept-Language` — le choix de langue était perdu à chaque page.
 *
 * ## Règle
 *
 * - `withLang(href, locale)` : ajoute `?lang=` à un lien interne. `fr` est la
 *   valeur par défaut (aucun paramètre) — même convention que
 *   `PublicPortalNav` et `sitemap.ts`, qui n'émettent jamais `?lang=fr`.
 * - `localeSwitchHref(pathname, search, locale)` : conserve les filtres et le
 *   paramètre de partage `s=?s=…` (l'état d'un calcul partagé) et ne remplace
 *   que `lang`.
 *
 * Fonctions PURES : aucun accès réseau, aucun état, testées directement.
 */

/** Locale par défaut du module — la valeur qui ne s'écrit pas dans l'URL. */
export const DEFAULT_TOOLBOX_LOCALE: ToolboxLocale = 'fr';

/** Vrai si la valeur est une locale supportée par le module. */
export function isToolboxLocale(value: unknown): value is ToolboxLocale {
  return typeof value === 'string' && (TOOLBOX_LOCALES as readonly string[]).includes(value);
}

/**
 * Ajoute `?lang=` à un lien interne de la Toolbox.
 *
 * `fr` est omis (URL canonique) ; les autres locales sont ajoutées. Un `href`
 * absolu (`https://…`) ou un lien d'ancre (`#…`) est retourné tel quel : ce
 * module ne réécrit que les chemins internes.
 */
export function withLang(href: string, locale: ToolboxLocale): string {
  if (locale === DEFAULT_TOOLBOX_LOCALE) return href;
  if (!isToolboxLocale(locale)) return href;
  if (/^[a-z]+:/i.test(href) || href.startsWith('//') || href.startsWith('#')) return href;

  const [path, existingQuery = ''] = href.split('?');
  const params = new URLSearchParams(existingQuery);
  params.set('lang', locale);
  const qs = params.toString();
  return qs ? `${path}?${qs}` : path;
}

/**
 * URL de bascule de langue depuis la page courante.
 *
 * Conserve TOUT le reste de la requête (filtres du catalogue, `s=` d'un calcul
 * partagé) et remplace seulement `lang`. Repasser en `fr` supprime le
 * paramètre au lieu d'écrire `lang=fr` : l'URL redevient canonique, ce que
 * `sitemap.ts` et les `alternates.hreflang` attendent.
 */
export function localeSwitchHref(
  pathname: string,
  search: string,
  locale: ToolboxLocale,
): string {
  const params = new URLSearchParams(search.startsWith('?') ? search.slice(1) : search);
  if (locale === DEFAULT_TOOLBOX_LOCALE) {
    params.delete('lang');
  } else {
    params.set('lang', locale);
  }
  const qs = params.toString();
  return qs ? `${pathname}?${qs}` : pathname;
}
