import axios from 'axios'
import { useToast } from 'vue-toastification'

const apiBaseURL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'

function baseEndsWithV1(baseURL) {
  return /\/api\/v1\/?$/.test(baseURL || '')
}

function normalizeApiPath(path, baseURL = apiBaseURL) {
  if (!path || /^https?:\/\//i.test(path)) {
    return path
  }

  if (baseEndsWithV1(baseURL) && path.startsWith('/v1/')) {
    return path.slice(3)
  }

  return path
}

const api = axios.create({
  baseURL: apiBaseURL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

api.interceptors.request.use(
  (config) => {
    config.url = normalizeApiPath(config.url, config.baseURL || apiBaseURL)

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

export async function downloadApiFile(path, filename = null) {
  const response = await api.get(path, { responseType: 'blob' })
  const contentType = response.headers['content-type'] || 'application/octet-stream'
  let blob = new Blob([response.data], { type: contentType })
  let downloadName = filename

  if (contentType.includes('application/json')) {
    const text = await response.data.text()
    const payload = JSON.parse(text)
    const data = payload.data || payload

    if (data.content !== undefined) {
      blob = new Blob([data.content], { type: data.format === 'json' ? 'application/json' : 'text/csv;charset=utf-8' })
      downloadName = data.filename || downloadName
    }
  }

  const objectUrl = URL.createObjectURL(blob)
  const link = document.createElement('a')
  const disposition = response.headers['content-disposition'] || ''
  const match = disposition.match(/filename="?([^"]+)"?/i)

  link.href = objectUrl
  link.download = downloadName || match?.[1] || 'export'
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(objectUrl)
}

export default api
