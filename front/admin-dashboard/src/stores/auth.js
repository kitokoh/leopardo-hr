import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import api from '@/services/api'
import { useLocaleStore } from '@/stores/locale.js'
import { translate } from '@/i18n/index.js'

const PLATFORM_AUTH_BASE = '/platform/auth'
const PLATFORM_DEVICE_NAME = 'leo-admin-dashboard'

// Security fix (#1299): migrated from localStorage to sessionStorage.
// sessionStorage is scoped to the browser tab — the token is cleared when
// the tab closes, reducing the persistence window for a stolen token.
// A full httpOnly cookie migration for this SPA requires a server-side
// BFF or a backend /platform/auth/login endpoint that sets the cookie;
// that is tracked as the next step in issue #1299.
// See also: docs/security/AUDIT_API_2026-07-19.md
// Stockage centralisé : src/services/token-storage.js (sessionStorage,
// cf. PR #1299). Ne pas réintroduire localStorage ici (#1575).
import { getAuthToken, setAuthToken, removeAuthToken } from '@/services/token-storage'
const storage = {
  getToken: getAuthToken,
  setToken: setAuthToken,
  removeToken: removeAuthToken,
};

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(storage.getToken())
  const isLoading = ref(false)

  /** Traduit une clé i18n dans la locale courante (#4712). */
  function t(key, fallback = '') {
    try {
      const locale = useLocaleStore().current
      return translate(locale, key, fallback)
    } catch {
      return fallback
    }
  }

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const userRole = computed(() => user.value?.role || null)
  const userName = computed(() => user.value?.name || '')
  const userEmail = computed(() => user.value?.email || '')

  async function login(credentials) {
    isLoading.value = true

    try {
      const response = await api.post(`${PLATFORM_AUTH_BASE}/login`, {
        ...credentials,
        device_name: credentials.device_name || PLATFORM_DEVICE_NAME,
      })

      if (response.status === 202 || response.data?.error === 'TWO_FA_REQUIRED') {
        return {
          success: false,
          requiresTwoFactor: true,
          message: response.data?.localized_message || response.data?.message || t('auth.two_fa_required_msg', 'Un code de vérification est requis.'),
        }
      }

      const authToken = response.data?.token
      const userData = response.data?.data

      if (!authToken || !userData) {
        return {
          success: false,
          requiresTwoFactor: false,
          message: t('auth.incomplete_response', 'La réponse de connexion est incomplète.'),
        }
      }

      token.value = authToken
      user.value = userData
      storage.setToken(authToken)
      api.defaults.headers.common.Authorization = `Bearer ${authToken}`

      // Synchronise la locale avec la préférence de l'utilisateur
      try { useLocaleStore().initFromUser(userData) } catch (e) { console.warn('[admin] locale store not mounted yet', e) }

      return { success: true }
    } catch (error) {
      console.error('Erreur de connexion:', error)

      return {
        success: false,
        requiresTwoFactor: false,
        message: error.response?.data?.localized_message
          || error.response?.data?.message
          || t('auth.connection_error', 'Erreur de connexion.'),
      }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Réinitialisation synchrone de la session (issue #3929).
   *
   * Un 401 en cours de session (token expiré/révoqué) doit rendre /login
   * accessible : sans cette remise à zéro du store, `isAuthenticated`
   * restait vrai (refs `token`/`user` périmées) et le guard rebondissait
   * systématiquement vers `/` — SPA figée jusqu'au reload complet.
   */
  function clearSession() {
    token.value = null
    user.value = null
    storage.removeToken()
    delete api.defaults.headers.common.Authorization
  }

  async function logout() {
    try {
      if (token.value) {
        await api.post(`${PLATFORM_AUTH_BASE}/logout`)
      }
    } catch (error) {
      console.error('Erreur lors de la deconnexion:', error)
    } finally {
      clearSession()
    }
  }

  async function checkAuth() {
    if (!token.value) {
      return false
    }

    try {
      api.defaults.headers.common.Authorization = `Bearer ${token.value}`
      const response = await api.get(`${PLATFORM_AUTH_BASE}/me`)
      user.value = response.data?.data || null

      return !!user.value
    } catch (error) {
      // #4515 : ne détruire la session que sur une vraie invalidation (401/403)
      // — un blip réseau ou un 5xx transitoire au démarrage (App.vue, garde du
      // router) déconnectait l'admin et supprimait son token pour rien.
      const status = error?.response?.status
      if (status === 401 || status === 403 || status === 410) {
        console.error('Token invalide:', error)
        await logout()
        return false
      }
      // Erreur transitoire : conserver la session courante (l'utilisateur
      // pourra retenter via le rafraîchissement / les appels suivants).
      console.warn('checkAuth: erreur transitoire, session conservée', error?.response?.status ?? error?.message)
      return !!user.value
    }
  }

  async function updateProfile(payload) {
    try {
      const response = await api.patch(`${PLATFORM_AUTH_BASE}/profile`, payload)
      user.value = response.data?.data || user.value

      return { success: true, data: response.data?.data }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error,
        message: error.response?.data?.localized_message || error.response?.data?.message || t('auth.profile_update_failed', 'La mise à jour du profil a échoué.'),
      }
    }
  }

  async function changePassword(payload) {
    try {
      await api.post(`${PLATFORM_AUTH_BASE}/change-password`, payload)

      return { success: true }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error,
        message: error.response?.data?.localized_message || error.response?.data?.message || t('auth.password_change_failed', 'Le changement de mot de passe a échoué.'),
      }
    }
  }

  async function setup2fa() {
    try {
      const response = await api.post(`${PLATFORM_AUTH_BASE}/2fa/setup`)

      return { success: true, data: response.data?.data }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error,
        message: error.response?.data?.localized_message || error.response?.data?.message || t('auth.two_fa_setup_failed', 'La génération du secret 2FA a échoué.'),
      }
    }
  }

  async function enable2fa(code) {
    try {
      await api.post(`${PLATFORM_AUTH_BASE}/2fa/enable`, { code })
      if (user.value) {
        user.value = { ...user.value, two_fa_enabled: true }
      }

      return { success: true }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error,
        message: error.response?.data?.localized_message || error.response?.data?.message || t('auth.two_fa_invalid', 'Le code 2FA fourni est invalide.'),
      }
    }
  }

  async function disable2fa(password) {
    try {
      await api.post(`${PLATFORM_AUTH_BASE}/2fa/disable`, { password })
      if (user.value) {
        user.value = { ...user.value, two_fa_enabled: false }
      }

      return { success: true }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error,
        message: error.response?.data?.localized_message || error.response?.data?.message || t('auth.disable_two_fa_failed', 'La désactivation du 2FA a échoué.'),
      }
    }
  }

  return {
    user,
    token,
    isLoading,
    isAuthenticated,
    userRole,
    userName,
    userEmail,
    login,
    logout,
    clearSession,
    checkAuth,
    updateProfile,
    changePassword,
    setup2fa,
    enable2fa,
    disable2fa,
  }
})
