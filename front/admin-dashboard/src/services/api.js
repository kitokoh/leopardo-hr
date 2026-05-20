import axios from 'axios'
import { useToast } from 'vue-toastification'

const ERROR_BREADCRUMBS = []
const MAX_BREADCRUMBS = 50

function addBreadcrumb(category, message, data = {}) {
  const entry = {
    timestamp: new Date().toISOString(),
    category,
    message,
    data,
  }
  ERROR_BREADCRUMBS.push(entry)
  if (ERROR_BREADCRUMBS.length > MAX_BREADCRUMBS) {
    ERROR_BREADCRUMBS.shift()
  }
  if (typeof window.__SENTRY__ !== 'undefined' && window.Sentry) {
    window.Sentry.addBreadcrumb({
      category,
      message,
      data,
      level: data.status >= 500 ? 'error' : 'warning',
    })
  }
}

export function getErrorBreadcrumbs() {
  return [...ERROR_BREADCRUMBS]
}

function contextualErrorMessage(status, data, url) {
  const endpoint = url || 'inconnu'
  const serverMsg = data?.message || ''
  switch (status) {
    case 401:
      return 'Session expiree. Reconnexion en cours...'
    case 403:
      return `Acces refuse sur ${endpoint}. Permissions insuffisantes.`
    case 404:
      return `Ressource introuvable : ${endpoint}`
    case 422:
      return null
    case 429:
      return 'Trop de requetes. Veuillez patienter quelques secondes.'
    case 500:
      return `Erreur serveur sur ${endpoint}. ${serverMsg ? '(' + serverMsg + ')' : 'Reessayez plus tard.'}`
    case 502:
    case 503:
    case 504:
      return `Le serveur est temporairement indisponible (${status}). Reessayez dans quelques instants.`
    default:
      return serverMsg || `Erreur ${status} sur ${endpoint}.`
  }
}

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
      const requestUrl = originalRequest?.url || ''

      addBreadcrumb('api.error', `HTTP ${status} on ${originalRequest?.method?.toUpperCase() || 'GET'} ${requestUrl}`, {
        status,
        url: requestUrl,
        method: originalRequest?.method,
        serverMessage: data?.message,
      })

      switch (status) {
        case 401:
          if (!originalRequest?._retry) {
            originalRequest._retry = true
            localStorage.removeItem('admin_token')
            delete api.defaults.headers.common.Authorization

            if (window.location.pathname !== '/login') {
              toast.warning(contextualErrorMessage(401, data, requestUrl))
              window.location.href = '/login'
            }
          }
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

        default: {
          const msg = contextualErrorMessage(status, data, requestUrl)
          if (msg) {
            toast.error(msg)
          }
        }
      }
    } else if (error.request) {
      addBreadcrumb('api.network', 'Network error — no response received', {
        url: originalRequest?.url,
        method: originalRequest?.method,
      })
      toast.error('Erreur de connexion. Verifiez votre connexion internet.')
    } else {
      addBreadcrumb('api.unexpected', error.message || 'Unknown error')
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
