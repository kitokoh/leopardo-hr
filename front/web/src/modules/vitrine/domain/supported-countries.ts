/**
 * Registre multi-pays canonique pour le formulaire d'essai guidé (#4476).
 *
 * Source de vérité : `GET /api/v1/supported-countries` (public depuis #4217,
 * registre `docs/payroll/*_COMPLIANCE.md` + CountryDefaults). Ce fichier est le
 * fallback hors ligne/statique du sélecteur de pays du SignupForm — la liste
 * reflète les pays `available: true` du registre au 2026-08-16.
 *
 * NB : les libellés sont ceux du registre backend (français international) —
 * les noms de pays restent stables quelle que soit la locale de l'UI.
 */
export type SupportedCountryOption = { code: string; label: string }

export const SUPPORTED_COUNTRIES_FALLBACK: SupportedCountryOption[] = [
  { code: 'DZ', label: 'Algérie' },
  { code: 'MA', label: 'Maroc' },
  { code: 'TN', label: 'Tunisie' },
  { code: 'SN', label: 'Sénégal' },
  { code: 'CI', label: "Côte d'Ivoire" },
  { code: 'ML', label: 'Mali' },
  { code: 'BF', label: 'Burkina Faso' },
  { code: 'TG', label: 'Togo' },
  { code: 'BJ', label: 'Bénin' },
  { code: 'NE', label: 'Niger' },
  { code: 'CM', label: 'Cameroun' },
  { code: 'GA', label: 'Gabon' },
  { code: 'CG', label: 'Congo' },
  { code: 'TD', label: 'Tchad' },
  // #7299 — aligné sur le libellé canonique du registre API (`CountryDefaults`).
  { code: 'CF', label: 'République Centrafricaine' },
  { code: 'GQ', label: 'Guinée Équatoriale' },
  { code: 'FR', label: 'France' },
  { code: 'TR', label: 'Turquie' },
  { code: 'CA', label: 'Canada' },
]

/**
 * Normalise la charge utile de `GET /api/v1/supported-countries` en options de
 * sélecteur.
 *
 * **Pure** : elle ne fait aucun appel réseau, elle décide seulement ce qu'une
 * réponse signifie. C'est ce qui permet de la tester sans serveur ni mock de
 * `fetch` — et c'est la partie qui porte réellement une règle (filtrer les pays
 * indisponibles, ignorer un code qui n'est pas un ISO-3166 alpha-2, retomber sur
 * le registre statique plutôt que de rendre une liste vide).
 *
 * Extraite de `fetchSupportedCountries` en #9658 : le `fetch` est parti en
 * `infrastructure/supported-countries-client.ts`, la règle est restée ici.
 */
export function normalizeSupportedCountries(
  payload: { data?: Array<{ country: string; label: string; available?: boolean }> } | null | undefined,
): SupportedCountryOption[] {
  const list = (payload?.data ?? [])
    .filter((c) => c.available !== false && typeof c.country === 'string' && c.country.length === 2)
    .map((c) => ({ code: c.country.toUpperCase(), label: c.label || c.country }))
  return list.length > 0 ? list : SUPPORTED_COUNTRIES_FALLBACK
}
