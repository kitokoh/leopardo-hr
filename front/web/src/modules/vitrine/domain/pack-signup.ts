/**
 * #8462 (vague 4) — « pack-aware signup » : le métier choisi sur la vitrine
 * suit le visiteur jusqu'à son espace, sans être re-demandé.
 *
 * Une page pack (`/business/packs/[vertical]`, `/business/restaurateur`) déclare le métier du
 * visiteur dans l'URL d'inscription : `/business/signup?pack=<slug>`. Le formulaire
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
  // domestique rejoint l'offre d'entrée de la vitrine (`/business/packs/trade`).
  'trade',
  // #9614 — vitrines publiques Retail, Hôtellerie et Pharmacie : leur CTA
  // « Devenir tenant » doit provisionner la bonne verticale. Sans ces slugs,
  // `packSolutionCode` renvoie `null` et l'espace serait créé sans métier.
  'retail',
  'hospitality',
  'pharmacy',
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
  // #9614 — codes canoniques des manifests Retail, HospitalityManager et
  // Pharmacy (`RetailManifest::CODE`, `HospitalityManagerManifest::code()`,
  // `PharmacyManifest::code()`). Un écart ici ferait échouer l'inscription en
  // 422 (allowlist `SolutionCatalogue`, fail-closed).
  retail: 'retail',
  hospitality: 'hospitality',
  pharmacy: 'pharmacy',
};

export function isPackSignupSlug(value: string | null | undefined): value is PackSignupSlug {
  return Boolean(value) && (PACK_SIGNUP_SLUGS as readonly string[]).includes(value as string);
}

/** Code catalogue pour un slug vitrine, `null` si inconnu (fail-closed). */
export function packSolutionCode(value: string | null | undefined): string | null {
  return isPackSignupSlug(value) ? PACK_SOLUTION_CODES[value] : null;
}

/**
 * `/business/signup?pack=<slug>` : le slug est fusionné à la query courante (`lang`
 * notamment — la locale vitrine survit au tunnel, même règle que
 * `withLocaleHref`).
 */
export function packSignupHref(pack: PackSignupSlug, currentSearch = ''): string {
  const params = new URLSearchParams(currentSearch);
  params.set('pack', pack);
  const query = params.toString();
  return query ? `/business/signup?${query}` : '/business/signup';
}

/**
 * #9788 — secteur de l'entretien de préparation associé à un pack (miroir
 * strict de `PACK_SECTORS` côté vitrine et de
 * `SetupInterviewPlanner::QUESTIONS['sector']` côté serveur).
 */
export const PACK_SIGNUP_SECTORS: Record<PackSignupSlug, string> = {
  restaurant: 'restaurant',
  'station-service': 'fuel_station',
  ecole: 'education',
  'agence-de-voyage': 'travel',
  cagnottes: 'association',
  vtc: 'services',
  trade: 'commerce',
  retail: 'commerce',
  hospitality: 'hospitality',
  pharmacy: 'pharmacy',
};

/**
 * #9788 — CTA des pages pack : `/business/signup?pack=<slug>&sector=<secteur>`.
 *
 * Le `?pack=` décide de la verticale provisionnée, le `?sector=` évite que
 * l'entretien de préparation repose la question du métier au visiteur qui vient
 * de le déclarer (cf. `tenantSignupHref`, qui applique la même règle).
 *
 * Distinct de `packSignupHref` (sans secteur) : les appelants historiques
 * gardent leur comportement, et la query courante (`lang`, `utm_*`) est
 * préservée.
 */
export function packSignupHrefWithSector(pack: PackSignupSlug, currentSearch = ''): string {
  const params = new URLSearchParams(currentSearch);
  params.set('pack', pack);
  params.set('sector', PACK_SIGNUP_SECTORS[pack]);
  const query = params.toString();
  return query ? `/business/signup?${query}` : '/business/signup';
}
