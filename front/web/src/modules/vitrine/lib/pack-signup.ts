/**
 * #8462 (vague 4) — « pack-aware signup » : le métier choisi sur la vitrine
 * suit le visiteur jusqu'à son espace, sans être re-demandé.
 *
 * Une page pack (`/packs/[vertical]`, `/restaurateur`) déclare le métier du
 * visiteur dans l'URL d'inscription : `/signup?pack=<slug>`. Le formulaire
 * le traduit en code du catalogue de solutions (`solutions[]`, plomberie
 * #7235 / BC-25 #6693) : l'espace est provisionné AVEC sa verticale, et
 * l'entretien de préparation ne redemande pas le secteur (`metadata.vertical`
 * côté société — retour propriétaire : « re-poser la question devient bête »).
 *
 * Fail-closed, comme le catalogue : un slug inconnu est IGNORÉ (jamais de
 * code inventé — un code inconnu ferait échouer l'inscription en 422).
 */

/** Slugs de packs métier portés par la vitrine. */
export const PACK_SIGNUP_SLUGS = [
  'restaurant',
  'station-service',
  'ecole',
  'agence-de-voyage',
  'cagnottes',
  'vtc',
  // BC-36 TRADE (TRADE-009) — la verticale commerce international & B2B
  // domestique rejoint l'offre d'entrée de la vitrine (`/packs/trade`).
  'trade',
] as const;

export type PackSignupSlug = (typeof PACK_SIGNUP_SLUGS)[number];

/**
 * Slug vitrine → code du `SolutionCatalogue` backend.
 * Miroir des manifestes : Restaurant, FuelStation, EduManager, TravelAgency,
 * Fundraising (`code(): string` de chaque manifeste).
 */
const PACK_SOLUTION_CODES: Record<PackSignupSlug, string> = {
  restaurant: 'restaurant',
  'station-service': 'fuel_station',
  ecole: 'edumanager',
  'agence-de-voyage': 'travelagency',
  cagnottes: 'fundraising',
  vtc: 'vtc',
  // Doit correspondre EXACTEMENT au code du TradeManifest (`code(): 'trade'`).
  trade: 'trade',
};

export function isPackSignupSlug(value: string | null | undefined): value is PackSignupSlug {
  return Boolean(value) && (PACK_SIGNUP_SLUGS as readonly string[]).includes(value as string);
}

/** Code catalogue pour un slug vitrine, `null` si inconnu (fail-closed). */
export function packSolutionCode(value: string | null | undefined): string | null {
  return isPackSignupSlug(value) ? PACK_SOLUTION_CODES[value] : null;
}

/**
 * `/signup?pack=<slug>` : le slug est fusionné à la query courante (`lang`
 * notamment — la locale vitrine survit au tunnel, même règle que
 * `withLocaleHref`).
 */
export function packSignupHref(pack: PackSignupSlug, currentSearch = ''): string {
  const params = new URLSearchParams(currentSearch);
  params.set('pack', pack);
  const query = params.toString();
  return query ? `/signup?${query}` : '/signup';
}
