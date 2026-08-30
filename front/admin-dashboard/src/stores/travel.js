import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import api from '@/services/api'

/**
 * Store de la verticale TravelAgency (BC-24 TRAVEL, TRAVEL-601 #6078).
 *
 * Le flag `travelagency` (companies.features.travelagency) est porté par le
 * backend : le middleware `module.travelagency` répond 403 FEATURE_NOT_ENABLED
 * sur TOUTE route /travel/* quand le tenant connecté ne l'a pas activé, et
 * 200 sur `GET /travel/ping` quand il est actif. Ce store sonde ce contrat
 * réel (aucun endpoint inventé) pour conditionner l'entrée de navigation :
 * - flag actif   → menu « Agence de voyage » visible, écrans opérationnels ;
 * - flag absent  → menu masqué (critère TRAVEL-601) ;
 * - 401 (aucun contexte tenant, ex. super-admin hors impersonation) → menu
 *   masqué, les écrans affichent un état explicite (pattern FleetView #4710).
 */
export const useTravelStore = defineStore('travel', () => {
  const flagActive = ref(false)
  const flagChecked = ref(false)
  const checking = ref(false)

  /** True si la sonde a échoué faute de contexte tenant (401), pas par flag absent. */
  const noTenantContext = ref(false)

  const isReady = computed(() => flagChecked.value)

  async function checkFlag(force = false) {
    if (flagChecked.value && !force) {
      return flagActive.value
    }
    if (checking.value) {
      return flagActive.value
    }

    checking.value = true
    try {
      // _skipAuthRedirect (#4170) : un 401 (super-admin hors contexte tenant)
      // ne doit pas détruire la session admin — l'appelant gère l'erreur.
      await api.get('/travel/ping', { _skipAuthRedirect: true })
      flagActive.value = true
      noTenantContext.value = false
    } catch (error) {
      flagActive.value = false
      noTenantContext.value = error?.response?.status === 401
    } finally {
      checking.value = false
      flagChecked.value = true
    }

    return flagActive.value
  }

  function reset() {
    flagActive.value = false
    flagChecked.value = false
    checking.value = false
    noTenantContext.value = false
  }

  return { flagActive, flagChecked, checking, noTenantContext, isReady, checkFlag, reset }
})
