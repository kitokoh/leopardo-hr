import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref(null)
  const token = ref(localStorage.getItem('admin_token'))
  const isLoading = ref(false)

  // Getters
  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const userRole = computed(() => user.value?.role || null)
  const userName = computed(() => user.value?.name || '')
  const userEmail = computed(() => user.value?.email || '')

  // Actions
  async function login(credentials) {
    isLoading.value = true
    try {
      const response = await api.post('/admin/auth/login', credentials)
      const { token: authToken, user: userData } = response.data
      
      // Stocker le token et les données utilisateur
      token.value = authToken
      user.value = userData
      localStorage.setItem('admin_token', authToken)
      
      // Configurer le token par défaut pour les futures requêtes
      api.defaults.headers.common['Authorization'] = `Bearer ${authToken}`
      
      return { success: true }
    } catch (error) {
      console.error('Erreur de connexion:', error)
      return { 
        success: false, 
        message: error.response?.data?.message || 'Erreur de connexion' 
      }
    } finally {
      isLoading.value = false
    }
  }

  async function logout() {
    try {
      await api.post('/admin/auth/logout')
    } catch (error) {
      console.error('Erreur lors de la déconnexion:', error)
    } finally {
      // Nettoyer les données locales
      token.value = null
      user.value = null
      localStorage.removeItem('admin_token')
      delete api.defaults.headers.common['Authorization']
    }
  }

  async function checkAuth() {
    if (!token.value) {
      return false
    }

    try {
      // Configurer le token pour la requête
      api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
      
      // Vérifier la validité du token
      const response = await api.get('/admin/auth/me')
      user.value = response.data.user
      
      return true
    } catch (error) {
      console.error('Token invalide:', error)
      // Token invalide, nettoyer
      await logout()
      return false
    }
  }

  async function refreshToken() {
    try {
      const response = await api.post('/admin/auth/refresh')
      const { token: newToken } = response.data
      
      token.value = newToken
      localStorage.setItem('admin_token', newToken)
      api.defaults.headers.common['Authorization'] = `Bearer ${newToken}`
      
      return true
    } catch (error) {
      console.error('Erreur lors du rafraîchissement du token:', error)
      await logout()
      return false
    }
  }

  return {
    // State
    user,
    token,
    isLoading,
    
    // Getters
    isAuthenticated,
    userRole,
    userName,
    userEmail,
    
    // Actions
    login,
    logout,
    checkAuth,
    refreshToken
  }
})