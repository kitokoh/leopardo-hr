import axios from 'axios'
import { useToast } from 'vue-toastification'

// Configuration de base
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Intercepteur de requête
api.interceptors.request.use(
  (config) => {
    // Ajouter le token d'authentification
    const token = localStorage.getItem('admin_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    // Ajouter un timestamp pour éviter le cache
    if (config.method === 'get') {
      config.params = {
        ...config.params,
        _t: Date.now()
      }
    }

    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Intercepteur de réponse
api.interceptors.response.use(
  (response) => {
    return response
  },
  async (error) => {
    const toast = useToast()
    const originalRequest = error.config

    if (error.response) {
      const { status, data } = error.response

      switch (status) {
        case 401:
          // Token expiré ou invalide
          if (!originalRequest._retry) {
            originalRequest._retry = true

            try {
              // Tenter de rafraîchir le token
              const refreshResponse = await api.post('/admin/auth/refresh')
              const { token } = refreshResponse.data

              localStorage.setItem('admin_token', token)
              api.defaults.headers.common['Authorization'] = `Bearer ${token}`

              // Relancer la requête originale
              return api(originalRequest)
            } catch (refreshError) {
              // Échec du rafraîchissement, rediriger vers login
              localStorage.removeItem('admin_token')
              window.location.href = '/login'
              return Promise.reject(refreshError)
            }
          }
          break

        case 403:
          toast.error('Accès refusé. Permissions insuffisantes.')
          break

        case 404:
          toast.error('Ressource non trouvée.')
          break

        case 422:
          // Erreurs de validation
          if (data.errors) {
            Object.values(data.errors).flat().forEach(message => {
              toast.error(message)
            })
          } else {
            toast.error(data.message || 'Données invalides.')
          }
          break

        case 429:
          toast.error('Trop de requêtes. Veuillez patienter.')
          break

        case 500:
          toast.error('Erreur serveur. Veuillez réessayer plus tard.')
          break

        default:
          toast.error(data.message || 'Une erreur est survenue.')
      }
    } else if (error.request) {
      // Erreur réseau
      toast.error('Erreur de connexion. Vérifiez votre connexion internet.')
    } else {
      // Autre erreur
      toast.error('Une erreur inattendue est survenue.')
    }

    return Promise.reject(error)
  }
)

export default api