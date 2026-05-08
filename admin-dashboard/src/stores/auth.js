import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import api from '@/services/api'

const PLATFORM_AUTH_BASE = '/platform/auth'
const PLATFORM_DEVICE_NAME = 'leo-admin-dashboard'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('admin_token'))
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
      localStorage.setItem('admin_token', authToken)
      api.defaults.headers.common.Authorization = `Bearer ${authToken}`

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
      localStorage.removeItem('admin_token')
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
  }
})
