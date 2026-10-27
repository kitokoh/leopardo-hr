import { ref } from 'vue'
import api from '@/services/api'

/**
 * Chargement d'une liste paginee du module Growth & Search Intelligence
 * (GSI-10, #8521).
 *
 * Les six vues de liste partagent exactement le meme cycle : charger, exposer
 * `data` / `meta`, distinguer une erreur d'un etat vide, recharger. Le dupliquer
 * six fois garantirait six comportements legerement differents au premier
 * correctif — c'est precisement ce que la spec interdit (« reutilisation
 * stricte »).
 *
 * `_skipToast: true` : ces ecrans affichent leur propre bandeau d'erreur avec
 * un bouton « Reessayer ». Laisser le toast global s'ajouter produirait deux
 * messages pour une seule panne.
 *
 * @param {string} path chemin relatif a `/platform`, ex. `/search-intel/seo/issues`
 * @param {object} [options]
 * @param {object} [options.params] parametres de requete par defaut
 * @returns {{data: import('vue').Ref<Array>, meta: import('vue').Ref<object>, loading: import('vue').Ref<boolean>, error: import('vue').Ref<string>, load: Function}}
 */
export function useSearchIntelList(path, options = {}) {
  const data = ref([])
  const meta = ref({})
  const loading = ref(false)
  const error = ref('')

  async function load(overrides = {}) {
    loading.value = true
    error.value = ''
    try {
      const response = await api.get(`/platform${path}`, {
        params: { ...(options.params ?? {}), ...overrides },
        _skipToast: true,
      })
      const body = response.data ?? {}
      data.value = Array.isArray(body.data) ? body.data : []
      meta.value = body.meta ?? {}
    } catch (e) {
      // Un 403 n'est pas une panne : c'est un droit manquant. Le distinguer
      // evite d'afficher « reessayer » a un utilisateur qui n'aura jamais acces.
      const status = e?.response?.status
      error.value = status === 403
        ? 'forbidden'
        : e?.response?.data?.message || 'load_error'
      data.value = []
      meta.value = {}
    } finally {
      loading.value = false
    }
  }

  return { data, meta, loading, error, load }
}

/**
 * Formate un montant en centimes vers la devise demandee.
 *
 * Le backend expose des centimes (`*_cents`) partout : diviser a l'affichage
 * plutot qu'a la source evite les arrondis flottants dans les agregats.
 */
export function formatCents(cents, currency = 'EUR') {
  if (cents === null || cents === undefined) return '—'
  const n = Number(cents)
  if (!Number.isFinite(n)) return '—'
  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(n / 100)
}

/** Formate une date ISO en date locale, ou un tiret si absente. */
export function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  return Number.isNaN(d.getTime()) ? value : d.toLocaleDateString()
}
