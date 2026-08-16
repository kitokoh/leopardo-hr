/**
 * Registre multi-pays canonique pour le formulaire d'essai guidé (#4476).
 *
 * Source de vérité : `GET /api/v1/supported-countries` (public depuis #4217,
 * registre `docs/payroll/*_COMPLIANCE.md` + CountryDefaults). Ce fichier est le
 * fallback offline/statique du sélecteur de pays du SignupForm — la liste
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
  { code: 'CF', label: 'Centrafrique' },
  { code: 'GQ', label: 'Guinée Équatoriale' },
  { code: 'FR', label: 'France' },
  { code: 'TR', label: 'Turquie' },
  { code: 'CA', label: 'Canada' },
]

/**
 * Récupère les pays supportés (disponibles) via le proxy same-origin.
 * En cas d'échec (hors ligne, cold-start Render), retombe sur le registre
 * statique ci-dessus — le funnel ne doit jamais être bloqué par le réseau.
 */
export async function fetchSupportedCountries(): Promise<SupportedCountryOption[]> {
  try {
    const res = await fetch('/api/v1/supported-countries', {
      headers: { Accept: 'application/json' },
    })
    if (!res.ok) return SUPPORTED_COUNTRIES_FALLBACK
    const payload = (await res.json()) as { data?: Array<{ country: string; label: string; available?: boolean }> }
    const list = (payload.data ?? [])
      .filter((c) => c.available !== false && typeof c.country === 'string' && c.country.length === 2)
      .map((c) => ({ code: c.country.toUpperCase(), label: c.label || c.country }))
    return list.length > 0 ? list : SUPPORTED_COUNTRIES_FALLBACK
  } catch {
    return SUPPORTED_COUNTRIES_FALLBACK
  }
}
