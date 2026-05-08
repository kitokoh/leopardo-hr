import axios from 'axios'
import { useToast } from 'vue-toastification'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('admin_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    if (config.method === 'get') {
      config.params = {
        ...config.params,
        _t: Date.now(),
      }
    }

    return config
  },
  (error) => Promise.reject(error),
)

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const toast = useToast()
    const originalRequest = error.config

    if (error.response) {
      const { status, data } = error.response

      switch (status) {
        case 401:
          if (!originalRequest?._retry) {
            originalRequest._retry = true
            localStorage.removeItem('admin_token')
            delete api.defaults.headers.common.Authorization

            if (window.location.pathname !== '/login') {
              window.location.href = '/login'
            }
          }
          break

        case 403:
          toast.error('Acces refuse. Permissions insuffisantes.')
          break

        case 404:
          toast.error('Ressource non trouvee.')
          break

        case 422:
          if (data.errors) {
            Object.values(data.errors)
              .flat()
              .forEach((message) => toast.error(message))
          } else {
            toast.error(data.message || 'Donnees invalides.')
          }
          break

        case 429:
          toast.error('Trop de requetes. Veuillez patienter.')
          break

        case 500:
          toast.error('Erreur serveur. Veuillez reessayer plus tard.')
          break

        default:
          toast.error(data.message || 'Une erreur est survenue.')
      }
    } else if (error.request) {
      toast.error('Erreur de connexion. Verifiez votre connexion internet.')
    } else {
      toast.error('Une erreur inattendue est survenue.')
    }

    return Promise.reject(error)
  },
)

export default api
