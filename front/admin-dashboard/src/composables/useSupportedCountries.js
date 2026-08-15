import { computed } from 'vue'
import api from '@/services/api'

/**
 * Registre unique des pays supportés (issue #2789).
 *
 * Source de vérité : GET /admin/supported-countries (miroir du registre
 * tenant /api/v1/supported-countries, accessible au super-admin sans
 * contexte tenant). Remplace les tableaux codés en dur incohérents
 * (6/10/7/12 pays) des vues Holidays, SocialContributions, TaxRates et
 * TaxSlabs.
 *
 * Le registre est chargé une seule fois (cache module) ; un échec réseau
 * produit un état vide honnête (jamais de fallback silencieux).
 */

const cache = {
  countries: [],
  loaded: false,
  loading: false,
  promise: null,
}

/** Drapeau emoji dérivé du code ISO 3166-1 alpha-2 (🇩🇿, 🇲🇦, …). */
export function flagEmoji(code) {
  if (!/^[A-Za-z]{2}$/.test(code || '')) return ''
  return String.fromCodePoint(...code.toUpperCase().split('').map((c) => 127397 + c.charCodeAt(0)))
}

export function useSupportedCountries({ payrollOnly = false } = {}) {
  if (!cache.loaded && !cache.promise) {
    cache.loading = true
    cache.promise = api
      .get('/admin/supported-countries')
      .then(({ data }) => {
        cache.countries = Array.isArray(data?.data) ? data.data : []
        cache.loaded = true
      })
      .catch(() => {
        // État vide honnête : l'UI affiche « aucun pays » au lieu de mentir.
        cache.countries = []
        cache.loaded = true
      })
      .finally(() => {
        cache.loading = false
      })
  }

  const countries = computed(() => {
    const source = payrollOnly ? cache.countries.filter((c) => c.available) : cache.countries
    return source.map((c) => ({
      code: c.country,
      label: c.label,
      available: c.available ?? true,
      flag: flagEmoji(c.country),
    }))
  })

  return {
    countries,
    loading: computed(() => cache.loading),
    loaded: computed(() => cache.loaded),
  }
}
