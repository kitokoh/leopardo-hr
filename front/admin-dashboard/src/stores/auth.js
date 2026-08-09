import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import api from '@/services/api'
import { useLocaleStore } from '@/stores/locale.js'

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
          message: response.data?.message || 'Un code de verification est requis.',
        }
      }

      const authToken = response.data?.token
      const userData = response.data?.data

      if (!authToken || !userData) {
        return {
          success: false,
          requiresTwoFactor: false,
          message: 'La reponse de connexion est incomplete.',
        }
      }

      token.value = authToken
      user.value = userData
      storage.setToken(authToken)
      api.defaults.headers.common.Authorization = `Bearer ${authToken}`

      // Synchronise la locale avec la préférence de l'utilisateur
      try { useLocaleStore().initFromUser(userData) } catch { /* store non encore monté */ }

      return { success: true }
    } catch (error) {
      console.error('Erreur de connexion:', error)

      return {
        success: false,
        requiresTwoFactor: false,
        message: error.response?.data?.message || 'Erreur de connexion',
      }
    } finally {
      isLoading.value = false
    }
  }

  async function logout() {
    try {
      if (token.value) {
        await api.post(`${PLATFORM_AUTH_BASE}/logout`)
      }
    } catch (error) {
      console.error('Erreur lors de la deconnexion:', error)
    } finally {
      token.value = null
      user.value = null
      storage.removeToken()
      delete api.defaults.headers.common.Authorization
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
      console.error('Token invalide:', error)
      await logout()
      return false
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
        message: error.response?.data?.message || 'La mise a jour du profil a echoue.',
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
        message: error.response?.data?.message || 'Le changement de mot de passe a echoue.',
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
        message: error.response?.data?.message || 'La generation du secret 2FA a echoue.',
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
        message: error.response?.data?.message || 'Le code 2FA fourni est invalide.',
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
        message: error.response?.data?.message || 'La desactivation du 2FA a echoue.',
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
    checkAuth,
    updateProfile,
    changePassword,
    setup2fa,
    enable2fa,
    disable2fa,
  }
})
