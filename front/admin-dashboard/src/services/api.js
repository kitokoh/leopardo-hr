import axios from 'axios'
import { normalizeLocale, translate } from '@/i18n/index.js'
import { getAuthToken, removeAuthToken } from '@/services/token-storage'

/** Résout la locale active depuis localStorage ou navigator. */
function resolveAdminLocale() {
  try {
    const stored = localStorage.getItem('admin_locale')
    if (stored) return normalizeLocale(stored)
  } catch (e) {
    // localStorage indisponible (SSR ou sandboxé) — fallback navigator.
    console.warn('[admin] locale storage unavailable', e)
  }
  const nav = (typeof navigator !== 'undefined' && navigator.language) || 'fr'
  return normalizeLocale(nav)
}
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
  // Le backend fournit `localized_message` (clé traduite) : toujours prioritaire.
  const localized = data?.localized_message || ''
  const locale = resolveAdminLocale()
  const t = (key, fallback) => translate(locale, key, fallback)
  switch (status) {
    case 401:
      return localized || t('api.sessionExpired', 'Session expirée. Reconnexion en cours...')
    case 403:
      return (
        localized ||
        t('api.accessDenied', 'Accès refusé sur :endpoint. Permissions insuffisantes.').replace(
          ':endpoint',
          endpoint,
        )
      )
    case 404:
      return t('api.notFound', 'Ressource introuvable : :endpoint').replace(':endpoint', endpoint)
    case 422:
      return null
    case 429:
      return t('api.tooManyRequests', 'Trop de requêtes. Veuillez patienter quelques secondes.')
    case 500:
      return t('api.serverError', 'Erreur serveur sur :endpoint. :detail')
        .replace(':endpoint', endpoint)
        .replace(':detail', serverMsg ? `(${serverMsg})` : t('api.serverErrorRetry', 'Réessayez plus tard.'))
    case 502:
    case 503:
    case 504:
      return t('api.serverUnavailable', 'Le serveur est temporairement indisponible (:status). Réessayez dans quelques instants.').replace(
        ':status',
        String(status),
      )
    default:
      return serverMsg || t('api.genericError', 'Erreur :status sur :endpoint.').replace(':status', String(status)).replace(':endpoint', endpoint)
  }
}

// Render cold-start handling: the free/starter Render tier used for the API
// can take several seconds to wake up from an idle instance, returning a
// transient 502/503/504 during that window. `front/web/src/lib/api-client.ts`
// (Next.js vitrine) and `leopardo_core/lib/core/api/api_client.dart` (mobile
// apps) both already retry those specific statuses with the same progressive
// backoff; the admin dashboard only ever showed a "try again" toast without
// retrying automatically. Mirrors the same constants so behaviour stays
// consistent across every surface. See PA2-QA-007.
const COLD_START_STATUSES = [502, 503, 504]
const COLD_START_MAX_RETRIES = 2

function coldStartBackoffMs(attempt) {
  return Math.min(3000 * (attempt + 1), 10000)
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

// QA 2026-08-15 (#2659) : tout build sans VITE_API_URL embarquait localhost
// (déploiement GitHub Actions cassé, dev silencieusement pointé ailleurs).
// Le défaut est désormais l'URL de production ; localhost reste utilisable
// explicitement via VITE_API_URL pour le dev local.
const apiBaseURL = import.meta.env.VITE_API_URL || 'https://gestionemployerbackend.onrender.com/api/v1'

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


// ── RTMX client (#5446) — GET conditionnels (ETag/304) + Idempotency-Key ──
// Le socle serveur (#5277) expose ETag (sha1 du corps) + rejeu idempotent
// 24 h des écritures. Ce client exploite les deux : GET avec If-None-Match
// (304 = succès, corps caché servi depuis sessionStorage) et POST/PUT/PATCH
// avec une clé d'idempotence stable par action logique (double-clic, retry
// cold-start → le serveur rejoue la 1re réponse 2xx au lieu de dupliquer).
const RTMX_ETAG_KEY = 'rtmx_etag_v1'
const RTMX_IDEM_KEY = 'rtmx_idem_v1'

function rtmxSessionGet(key) {
  try {
    return JSON.parse(sessionStorage.getItem(key) || 'null')
  } catch {
    return null
  }
}

function rtmxSessionSet(key, value) {
  try {
    sessionStorage.setItem(key, JSON.stringify(value))
  } catch {
    // sessionStorage indisponible (SSR/sandbox) — cache mémoire uniquement.
  }
}

/** Clé de cache stable par URL : le cache-buster `_t` est ignoré. */
function rtmxCacheKey(url) {
  try {
    const u = new URL(url, apiBaseURL)
    u.searchParams.delete('_t')
    return u.pathname + u.search
  } catch {
    return url
  }
}

/** UUID v4 (crypto.randomUUID si dispo, sinon fallback déterministe). */
function rtmxUuid() {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    const v = c === 'x' ? r : (r & 0x3) | 0x8
    return v.toString(16)
  })
}

/** Clé d'idempotence stable par action logique (méthode + URL + corps). */
function rtmxIdempotencyKey(method, url, body) {
  const store = rtmxSessionGet(RTMX_IDEM_KEY) || {}
  const logical = `${method}:${url}:${typeof body === 'string' ? body : JSON.stringify(body || {})}`
  // hash 32 bits (FNV-1a) — suffisant pour dédupliquer les actions identiques.
  let h = 0x811c9dc5
  for (let i = 0; i < logical.length; i++) {
    h ^= logical.charCodeAt(i)
    h = Math.imul(h, 0x01000193)
  }
  const storeKey = `v1:${h >>> 0}`
  if (!store[storeKey]) {
    store[storeKey] = rtmxUuid()
  }
  rtmxSessionSet(RTMX_IDEM_KEY, store)
  return store[storeKey]
}

const api = axios.create({
  baseURL: apiBaseURL,
  timeout: 30000,
  // RTMX (#5446) : 304 Not Modified est un succès (corps servi depuis le
  // cache ETag — l'intercepteur de réponse le reconstruit).
  validateStatus: (status) => (status >= 200 && status < 300) || status === 304,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

api.interceptors.request.use(
  (config) => {
    config.url = normalizeApiPath(config.url, config.baseURL || apiBaseURL)

    const token = getAuthToken()
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    // Envoie la locale active au backend (SetLocale middleware)
    config.headers['Accept-Language'] = resolveAdminLocale()

    if (config.method === 'get') {
      config.params = {
        ...config.params,
        _t: Date.now(),
      }
      // RTMX (#5446) : GET conditionnel — renvoyer l'ETag connu (hors
      // opt-out explicite _cacheBust pour forcer une réponse fraîche).
      if (config._cacheBust !== true) {
        const cached = (rtmxSessionGet(RTMX_ETAG_KEY) || {})[rtmxCacheKey(config.url)]
        if (cached?.etag) {
          config.headers['If-None-Match'] = cached.etag
        }
      }
    } else if (['post', 'put', 'patch'].includes(config.method)) {
      // RTMX (#5446) : clé d'idempotence par action logique (hors opt-out
      // explicite _idempotent=false) — le serveur rejoue la 1re 2xx 24 h.
      if (config._idempotent !== false) {
        config.headers['Idempotency-Key'] = rtmxIdempotencyKey(
          config.method,
          config.url,
          config.data,
        )
      }
    }

    return config
  },
  (error) => Promise.reject(error),
)

api.interceptors.response.use(
  (response) => {
    const method = (response.config.method || 'get').toLowerCase()
    if (method === 'get') {
      const cacheKey = rtmxCacheKey(response.config.url)
      if (response.status === 304) {
        const cached = (rtmxSessionGet(RTMX_ETAG_KEY) || {})[cacheKey]
        if (cached) {
          response.data = cached.body
          response.status = 200
          response.rtmxCached = true
        }
      } else if (response.headers.etag && response.config._cacheBust !== true) {
        const store = rtmxSessionGet(RTMX_ETAG_KEY) || {}
        store[cacheKey] = { etag: response.headers.etag, body: response.data, ts: Date.now() }
        rtmxSessionSet(RTMX_ETAG_KEY, store)
      }
    }
    return response
  },
  async (error) => {
    const toast = useToast()
    const originalRequest = error.config

    // #4713 (audit 360° 2026-08-16) : opt-out _skipToast — les vues qui
    // gèrent leur propre état d'erreur + retry (EdgeNodesView, AnalyticsView,
    // ExportsView, GrowthDashboardView) évitent le double toast.
    const skipToast = originalRequest?._skipToast === true

    if (error.response) {
      const { status, data } = error.response
      const requestUrl = originalRequest?.url || ''

      if (COLD_START_STATUSES.includes(status) && originalRequest) {
        // #4620 : ne rejouer que les méthodes idempotentes (GET/HEAD) — un
        // retry de POST/PUT peut exécuter deux fois une mutation (webhook
        // dupliqué, session d'impersonation en double, désactivation rejouée)
        // quand le serveur a traité la requête mais que la réponse s'est
        // perdue (fenêtre cold-start Render).
        const method = (originalRequest.method || 'get').toUpperCase()
        // RTMX (#5446) : une mutation porte désormais une Idempotency-Key —
        // le serveur rejoue la 1re 2xx au lieu d'exécuter deux fois → le
        // retry cold-start devient sûr pour POST/PUT/PATCH aussi.
        const hasIdempotencyKey = !!originalRequest?.headers?.['Idempotency-Key']
        if (!['GET', 'HEAD', 'OPTIONS'].includes(method) && !hasIdempotencyKey) {
          console.warn(`api: cold-start ${status} sur ${method} ${requestUrl} — non rejoué (non idempotent)`)
          return Promise.reject(error)
        }
        const attempt = originalRequest._coldStartAttempt || 0
        if (attempt < COLD_START_MAX_RETRIES) {
          originalRequest._coldStartAttempt = attempt + 1
          addBreadcrumb('api.cold_start_retry', `HTTP ${status} on ${originalRequest.method?.toUpperCase() || 'GET'} ${requestUrl}, retrying (${attempt + 1}/${COLD_START_MAX_RETRIES})`, {
            status,
            url: requestUrl,
            method: originalRequest.method,
          })
          await sleep(coldStartBackoffMs(attempt))
          return api(originalRequest)
        }
      }

      addBreadcrumb('api.error', `HTTP ${status} on ${originalRequest?.method?.toUpperCase() || 'GET'} ${requestUrl}`, {
        status,
        url: requestUrl,
        method: originalRequest?.method,
        serverMessage: data?.message,
      })

      switch (status) {
        case 401:
          // Certaines requêtes non critiques (ex. polling notifications du
          // super-admin, dont le token ne s'authentifie pas sur les routes
          // tenant) ne doivent PAS détruire la session : elles portent
          // `_skipAuthRedirect` et laissent l'appelant gérer l'erreur.
          if (!originalRequest?._retry && !originalRequest?._skipAuthRedirect) {
            originalRequest._retry = true
            removeAuthToken()
            delete api.defaults.headers.common.Authorization

            // Issue #3929 : sans remise à zéro du store Pinia, `isAuthenticated`
            // restait vrai et le guard rebondissait /login → / (SPA figée).
            import('@/stores/auth').then(({ useAuthStore }) => {
              const authStore = useAuthStore()
              if (authStore.isAuthenticated) {
                authStore.clearSession()
              }
            }).catch(() => {
              // Store non encore monté (premier 401 avant l'app) : le reload
              // du guard / sessionStorage suffira.
            })

            if (window.location.pathname !== '/login') {
              toast.warning(contextualErrorMessage(401, data, requestUrl))
              // Navigation SPA (plus de reload complet) : le router redirige
              // déjà vers /login via le guard ; ce push couvre les appels hors
              // guard (intercepteur).
              import('@/router').then(({ default: router }) => {
                if (router.currentRoute.value.path !== '/login') {
                  router.push('/login')
                }
              })
            }
          }
          break

        case 422:
          if (data.errors) {
            Object.values(data.errors)
              .flat()
              .forEach((message) => toast.error(message))
          } else {
            toast.error(
              data.message ||
                translate(resolveAdminLocale(), 'api.invalidData', 'Données invalides.'),
            )
          }
          break

        default: {
          // _skipToast opt-out (#4713) : permet aux vues qui gèrent
          // l'erreur localement de ne pas afficher un second toast.
          if (skipToast) break
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
      toast.error(
        translate(
          resolveAdminLocale(),
          'api.connectionError',
          'Erreur de connexion. Vérifiez votre connexion internet.',
        ),
      )
    } else {
      addBreadcrumb('api.unexpected', error.message || 'Unknown error')
      toast.error(
        translate(resolveAdminLocale(), 'api.unexpectedError', 'Une erreur inattendue est survenue.'),
      )
    }

    return Promise.reject(error)
  },
)

export async function downloadApiFile(path, filename = null, options = {}) {
  // #4170 : options._skipAuthRedirect permet d'appeler des routes tenant
  // (401 attendu pour le super-admin) sans que l'intercepteur détruise la
  // session admin.
  const response = await api.get(path, { responseType: 'blob', ...options })
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
