import { ref } from 'vue'
import api from '@/services/api'

/**
 * Source unique des pays supportés (T110 / QA omnichannel 2026-08-15).
 *
 * Avant : 4 tableaux codés en dur incohérents (6/12/7/10 pays) dans les vues
 * paramètres (Holidays, TaxSlabs, TaxRates, SocialContributions) alors que le
 * registre canonique `GET /supported-countries` existe (api.php, #1867).
 *
 * Le registre est chargé une fois (cache module) et mappé sur le contrat des
 * vues ({ code, labelKey, flag }) ; fallback minimal DZ/FR si l'API échoue.
 */

const FLAGS = {
  DZ: '🇩🇿', MA: '🇲🇦', TN: '🇹🇳', CM: '🇨🇲', GA: '🇬🇦', CG: '🇨🇬', CI: '🇨🇮',
  SN: '🇸🇳', BF: '🇧🇫', ML: '🇲🇱', FR: '🇫🇷', TR: '🇹🇷', TG: '🇹🇬', BJ: '🇧🇯', NE: '🇳🇪',
  CF: '🇨🇫', TD: '🇹🇩', GQ: '🇬🇶',
}

const DEFAULT_COUNTRIES = [
  { code: 'DZ', flag: '🇩🇿', labelKey: 'common.countries.DZ' },
  { code: 'FR', flag: '🇫🇷', labelKey: 'common.countries.FR' },
]

const cache = ref(null)

async function load() {
  if (cache.value) return cache.value
  try {
    const { data } = await api.get('/supported-countries')
    const items = data?.data ?? []
    cache.value = items.map((item) => ({
      code: item.country,
      labelKey: `common.countries.${item.country}`,
      flag: FLAGS[item.country] || '🌍',
      available: item.available,
      confidence: item.confidence,
    }))
  } catch {
    cache.value = DEFAULT_COUNTRIES
  }
  return cache.value
}

export function useSupportedCountries() {
  load()
  return cache
}
