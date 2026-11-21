import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * Tests de la couche API du dashboard admin (`services/api.js`).
 *
 * ## Ce que ces tests protègent
 *
 * `api.js` porte quatre mécanismes dont chacun a déjà coûté un incident :
 *
 *  1. **Le retry cold-start** (PA2-QA-007) — l'API Render s'endort et renvoie un
 *     502/503/504 transitoire au réveil. Le front web et les apps mobiles
 *     réessayaient déjà ; le dashboard, non. Le retry ne doit porter que sur des
 *     requêtes **rejouables** : #4620 a montré qu'un retry de POST peut exécuter
 *     deux fois une mutation (webhook dupliqué, impersonation en double).
 *  2. **L'idempotence RTMX** (#5446) — une mutation porte une `Idempotency-Key`
 *     stable par action logique, ce qui rend le retry cold-start sûr pour
 *     POST/PUT/PATCH : le serveur rejoue la première 2xx au lieu de dupliquer.
 *  3. **Le cache ETag** (#5446) — un GET conditionnel (`If-None-Match`) dont le
 *     304 est un SUCCÈS, le corps étant servi depuis le cache de session.
 *  4. **La destruction de session sur 401** (#3929) — sans remise à zéro du
 *     store Pinia, `isAuthenticated` restait vrai et le guard rebondissait
 *     `/login` → `/` : SPA figée jusqu'au rechargement complet.
 *
 * ## Stratégie
 *
 * On mocke `axios.create` pour capturer les fonctions d'interception, puis on
 * les invoque directement. Aucun réseau, aucun serveur : chaque cas est
 * déterministe et instantané.
 */

const h = vi.hoisted(() => {
  const handlers = { request: null, response: null, responseError: null }
  const toast = {
    error: vi.fn(),
    warning: vi.fn(),
    info: vi.fn(),
    success: vi.fn(),
  }

  // L'instance axios factice : appelable (le retry fait `api(config)`) et
  // porteuse des `interceptors` que `api.js` enregistre au chargement.
  //
  // `vi.fn` et non une simple fonction : les tests de retry doivent pouvoir
  // compter les rejeux (`expect(instance).toHaveBeenCalled()`).
  const instance = Object.assign(
    vi.fn((config) => Promise.resolve({ config, status: 200, data: {} })),
    {
      defaults: { headers: { common: {} } },
      interceptors: {
        request: {
          use: (fn) => {
            handlers.request = fn
          },
        },
        response: {
          use: (ok, err) => {
            handlers.response = ok
            handlers.responseError = err
          },
        },
      },
    },
  )

  return { handlers, toast, instance }
})

vi.mock('axios', () => ({ default: { create: () => h.instance } }))
vi.mock('vue-toastification', () => ({ useToast: () => h.toast }))

const clearSession = vi.fn()
const routerPush = vi.fn()

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ isAuthenticated: true, clearSession }),
}))
vi.mock('@/router', () => ({
  default: { currentRoute: { value: { path: '/' } }, push: routerPush },
}))

const removeAuthToken = vi.fn()
let currentToken = null

vi.mock('@/services/token-storage', () => ({
  getAuthToken: () => currentToken,
  setAuthToken: (value) => {
    currentToken = value
  },
  removeAuthToken: (...args) => {
    currentToken = null
    removeAuthToken(...args)
  },
}))

/** Charge `api.js` avec un état de module neuf et les mocks réinitialisés. */
async function loadApi() {
  vi.resetModules()
  const mod = await import('../api.js')
  return mod
}

/** Construit une config de requête minimale. */
function requestConfig(overrides = {}) {
  return {
    method: 'get',
    url: '/platform/companies',
    headers: {},
    ...overrides,
  }
}

/** Construit une erreur axios avec réponse. */
function httpError(status, config, data = {}) {
  return { response: { status, data }, config, request: {} }
}

describe('api — intercepteur de requête', () => {
  beforeEach(() => {
    currentToken = null
    sessionStorage.clear()
    h.toast.error.mockClear()
    h.toast.warning.mockClear()
    h.toast.info.mockClear()
  })

  afterEach(() => {
    vi.restoreAllMocks()
    sessionStorage.clear()
  })

  it('ajoute le bearer quand un token est présent', async () => {
    currentToken = 'bearer-abc'
    await loadApi()

    const config = h.handlers.request(requestConfig())

    expect(config.headers.Authorization).toBe('Bearer bearer-abc')
  })

  it('n’ajoute pas d’en-tête Authorization sans token', async () => {
    await loadApi()

    const config = h.handlers.request(requestConfig())

    expect(config.headers.Authorization).toBeUndefined()
  })

  it('transmet la locale active au backend', async () => {
    await loadApi()

    const config = h.handlers.request(requestConfig())

    // Le middleware SetLocale du backend s'appuie sur cet en-tête.
    expect(config.headers['Accept-Language']).toBeTruthy()
  })

  it('ajoute un cache-buster `_t` sur les GET', async () => {
    await loadApi()

    const config = h.handlers.request(requestConfig())

    expect(config.params._t).toBeTypeOf('number')
  })

  it('n’ajoute pas de cache-buster sur les mutations', async () => {
    await loadApi()

    const config = h.handlers.request(requestConfig({ method: 'post', data: {} }))

    expect(config.params).toBeUndefined()
  })

  it('pose une Idempotency-Key sur les mutations', async () => {
    await loadApi()

    const config = h.handlers.request(requestConfig({ method: 'post', data: { name: 'X' } }))

    expect(config.headers['Idempotency-Key']).toBeTruthy()
  })

  it('produit une clé d’idempotence STABLE pour une même action logique', async () => {
    await loadApi()

    const first = h.handlers.request(requestConfig({ method: 'post', data: { name: 'X' } }))
    const second = h.handlers.request(requestConfig({ method: 'post', data: { name: 'X' } }))

    // C'est ce qui rend le retry cold-start sûr : un double-clic ou un rejeu
    // porte la MÊME clé, donc le serveur rejoue la première réponse au lieu
    // d'exécuter la mutation deux fois.
    expect(second.headers['Idempotency-Key']).toBe(first.headers['Idempotency-Key'])
  })

  it('produit des clés DIFFÉRENTES pour des actions distinctes', async () => {
    await loadApi()

    const first = h.handlers.request(requestConfig({ method: 'post', data: { name: 'X' } }))
    const second = h.handlers.request(requestConfig({ method: 'post', data: { name: 'Y' } }))

    expect(second.headers['Idempotency-Key']).not.toBe(first.headers['Idempotency-Key'])
  })

  it('respecte l’opt-out `_idempotent: false`', async () => {
    await loadApi()

    const config = h.handlers.request(
      requestConfig({ method: 'post', data: {}, _idempotent: false }),
    )

    expect(config.headers['Idempotency-Key']).toBeUndefined()
  })

  it('n’envoie pas d’If-None-Match au premier GET', async () => {
    await loadApi()

    const config = h.handlers.request(requestConfig())

    expect(config.headers['If-None-Match']).toBeUndefined()
  })

  it('renvoie l’ETag connu au GET suivant', async () => {
    await loadApi()

    // Premier GET : la réponse porte un ETag, l'intercepteur de réponse le
    // mémorise.
    h.handlers.response({
      status: 200,
      data: { ok: true },
      headers: { etag: 'W/"abc"' },
      config: requestConfig(),
    })

    const config = h.handlers.request(requestConfig())

    expect(config.headers['If-None-Match']).toBe('W/"abc"')
  })

  it('ignore le cache ETag sous `_cacheBust`', async () => {
    await loadApi()
    h.handlers.response({
      status: 200,
      data: { ok: true },
      headers: { etag: 'W/"abc"' },
      config: requestConfig(),
    })

    const config = h.handlers.request(requestConfig({ _cacheBust: true }))

    expect(config.headers['If-None-Match']).toBeUndefined()
  })
})

describe('api — intercepteur de réponse', () => {
  beforeEach(() => {
    currentToken = null
    sessionStorage.clear()
    h.toast.error.mockClear()
    h.toast.warning.mockClear()
    h.toast.info.mockClear()
    clearSession.mockClear()
    routerPush.mockClear()
    removeAuthToken.mockClear()
  })

  afterEach(() => {
    vi.restoreAllMocks()
    sessionStorage.clear()
  })

  it('mémorise l’ETag d’une réponse 200', async () => {
    await loadApi()

    const response = h.handlers.response({
      status: 200,
      data: { ok: true },
      headers: { etag: 'W/"abc"' },
      config: requestConfig(),
    })

    expect(response.status).toBe(200)
    // Le cache est un JSON indexé par chemin : on lit la valeur, pas la chaîne
    // brute (les guillemets y sont échappés).
    const cache = JSON.parse(sessionStorage.getItem('rtmx_etag_v1'))
    expect(cache['/platform/companies'].etag).toBe('W/"abc"')
  })

  it('sert le corps caché sur un 304 et le présente comme un 200', async () => {
    await loadApi()
    h.handlers.response({
      status: 200,
      data: { ok: true, name: 'Leopardo' },
      headers: { etag: 'W/"abc"' },
      config: requestConfig(),
    })

    const response = h.handlers.response({
      status: 304,
      data: '',
      headers: {},
      config: requestConfig(),
    })

    // 304 est un SUCCÈS : l'appelant reçoit les données, pas une erreur.
    expect(response.status).toBe(200)
    expect(response.data).toEqual({ ok: true, name: 'Leopardo' })
    expect(response.rtmxCached).toBe(true)
  })

  it('laisse passer un 304 sans cache connu', async () => {
    await loadApi()

    const response = h.handlers.response({
      status: 304,
      data: '',
      headers: {},
      config: requestConfig(),
    })

    expect(response.status).toBe(304)
  })

  it('n’enregistre pas l’ETag sous `_cacheBust`', async () => {
    await loadApi()

    h.handlers.response({
      status: 200,
      data: { ok: true },
      headers: { etag: 'W/"abc"' },
      config: requestConfig({ _cacheBust: true }),
    })

    expect(sessionStorage.getItem('rtmx_etag_v1')).toBeNull()
  })
})

describe('api — gestion des erreurs', () => {
  beforeEach(() => {
    currentToken = 'bearer-abc'
    sessionStorage.clear()
    h.toast.error.mockClear()
    h.toast.warning.mockClear()
    h.toast.info.mockClear()
    clearSession.mockClear()
    routerPush.mockClear()
    removeAuthToken.mockClear()
  })

  afterEach(() => {
    vi.restoreAllMocks()
    sessionStorage.clear()
  })

  it('détruit la session sur 401 et redirige vers /login (#3929)', async () => {
    await loadApi()

    await expect(
      h.handlers.responseError(httpError(401, requestConfig())),
    ).rejects.toBeTruthy()

    expect(removeAuthToken).toHaveBeenCalled()
    // Le point de #3929 : le store Pinia doit être remis à zéro, sinon le guard
    // rebondit /login → / et la SPA reste figée.
    await vi.waitFor(() => expect(clearSession).toHaveBeenCalled())
    await vi.waitFor(() => expect(routerPush).toHaveBeenCalledWith('/login'))
  })

  it('ne détruit PAS la session sur 401 quand `_skipAuthRedirect` est posé', async () => {
    await loadApi()

    await expect(
      h.handlers.responseError(httpError(401, requestConfig({ _skipAuthRedirect: true }))),
    ).rejects.toBeTruthy()

    // Les appels non critiques (polling de notifications du super-admin, dont le
    // token ne s'authentifie pas sur les routes tenant) ne doivent pas
    // déconnecter l'admin.
    expect(removeAuthToken).not.toHaveBeenCalled()
    expect(clearSession).not.toHaveBeenCalled()
  })

  it('affiche les erreurs de validation d’un 422', async () => {
    await loadApi()

    await expect(
      h.handlers.responseError(
        httpError(422, requestConfig({ method: 'post' }), {
          errors: { email: ['Email invalide.', 'Email déjà pris.'] },
        }),
      ),
    ).rejects.toBeTruthy()

    expect(h.toast.error).toHaveBeenCalledWith('Email invalide.')
    expect(h.toast.error).toHaveBeenCalledWith('Email déjà pris.')
  })

  it('n’affiche AUCUN toast sur 422 sous `_skipToast` (#7478)', async () => {
    await loadApi()

    await expect(
      h.handlers.responseError(
        httpError(422, requestConfig({ method: 'post', _skipToast: true }), {
          errors: { email: ['Email invalide.'] },
        }),
      ),
    ).rejects.toBeTruthy()

    // Les vues qui gèrent leur propre erreur de validation (modale ouverte,
    // saisie conservée) ne doivent pas recevoir le toast global en double.
    expect(h.toast.error).not.toHaveBeenCalled()
  })

  it('n’affiche aucun toast générique sous `_skipToast` (#4713)', async () => {
    await loadApi()

    await expect(
      h.handlers.responseError(
        httpError(500, requestConfig({ _skipToast: true }), { message: 'Boom' }),
      ),
    ).rejects.toBeTruthy()

    expect(h.toast.error).not.toHaveBeenCalled()
  })

  it('affiche une information neutre quand l’IA n’est pas activée (#6690)', async () => {
    await loadApi()

    await expect(
      h.handlers.responseError(
        httpError(403, requestConfig(), {
          error: 'AI_CONVERSATIONS_UNAVAILABLE',
          message: 'IA non activée.',
        }),
      ),
    ).rejects.toBeTruthy()

    // État de configuration normal, pas un incident : jamais une erreur.
    expect(h.toast.info).toHaveBeenCalledWith('IA non activée.')
    expect(h.toast.error).not.toHaveBeenCalled()
  })

  it('signale une erreur réseau sans réponse', async () => {
    await loadApi()

    await expect(
      h.handlers.responseError({ request: {}, config: requestConfig() }),
    ).rejects.toBeTruthy()

    expect(h.toast.error).toHaveBeenCalled()
  })

  it('signale une erreur inattendue sans requête ni réponse', async () => {
    await loadApi()

    await expect(
      h.handlers.responseError({ message: 'boum', config: requestConfig() }),
    ).rejects.toBeTruthy()

    expect(h.toast.error).toHaveBeenCalled()
  })

  it('enregistre des breadcrumbs pour le diagnostic', async () => {
    const { getErrorBreadcrumbs } = await loadApi()

    await expect(
      h.handlers.responseError(httpError(500, requestConfig(), { message: 'Boom' })),
    ).rejects.toBeTruthy()

    const breadcrumbs = getErrorBreadcrumbs()

    expect(breadcrumbs.length).toBeGreaterThan(0)
    expect(breadcrumbs.at(-1).category).toBe('api.error')
    expect(breadcrumbs.at(-1).data.status).toBe(500)
  })

  it('renvoie une copie des breadcrumbs, pas la référence interne', async () => {
    const { getErrorBreadcrumbs } = await loadApi()

    const first = getErrorBreadcrumbs()
    first.push({ forged: true })

    expect(getErrorBreadcrumbs()).not.toContainEqual({ forged: true })
  })
})

describe('api — retry cold-start Render', () => {
  beforeEach(() => {
    currentToken = 'bearer-abc'
    sessionStorage.clear()
    h.toast.error.mockClear()
    h.instance.mockClear()
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
    sessionStorage.clear()
  })

  it('rejoue un GET sur 502', async () => {
    await loadApi()
    const config = requestConfig()

    const promise = h.handlers.responseError(httpError(502, config))
    await vi.advanceTimersByTimeAsync(10_000)
    await promise

    // Le retry est ce qui distingue le dashboard du front web et des apps
    // mobiles, qui réessayaient déjà (PA2-QA-007).
    expect(h.instance).toHaveBeenCalled()
  })

  it('rejoue aussi sur 503 et 504', async () => {
    for (const status of [503, 504]) {
      h.instance.mockClear()
      await loadApi()
      const promise = h.handlers.responseError(httpError(status, requestConfig()))
      await vi.advanceTimersByTimeAsync(10_000)
      await promise

      expect(h.instance).toHaveBeenCalled()
    }
  })

  it('NE rejoue PAS un POST sans clé d’idempotence (#4620)', async () => {
    await loadApi()
    // Une mutation sans `Idempotency-Key` (opt-out explicite) n'est pas
    // rejouable : le serveur a pu traiter la requête et perdre la réponse.
    const config = requestConfig({ method: 'post', data: {}, _idempotent: false })

    await expect(h.handlers.responseError(httpError(502, config))).rejects.toBeTruthy()

    expect(h.instance).not.toHaveBeenCalled()
  })

  it('rejoue un POST porteur d’une clé d’idempotence (#5446)', async () => {
    await loadApi()
    // Le serveur rejoue la première 2xx au lieu d'exécuter deux fois : le retry
    // devient sûr pour les mutations.
    const config = requestConfig({ method: 'post', data: { name: 'X' } })
    const prepared = h.handlers.request(config)

    const promise = h.handlers.responseError(httpError(502, prepared))
    await vi.advanceTimersByTimeAsync(10_000)
    await promise

    expect(h.instance).toHaveBeenCalled()
  })

  it('abandonne après le nombre maximal de tentatives', async () => {
    await loadApi()
    const config = requestConfig()
    config._coldStartAttempt = 2

    await expect(h.handlers.responseError(httpError(502, config))).rejects.toBeTruthy()

    expect(h.instance).not.toHaveBeenCalled()
  })

  it('ne rejoue pas un statut qui n’est pas un cold-start', async () => {
    await loadApi()

    await expect(
      h.handlers.responseError(httpError(500, requestConfig())),
    ).rejects.toBeTruthy()

    expect(h.instance).not.toHaveBeenCalled()
  })
})
