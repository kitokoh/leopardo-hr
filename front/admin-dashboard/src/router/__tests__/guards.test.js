import { describe, expect, it, vi } from 'vitest'

import { FORBIDDEN_PATH, LOGIN_PATH, createAuthGuard } from '../guards'

/**
 * Tests des guards RBAC du router admin-dashboard.
 *
 * ## Pourquoi ce fichier existe
 *
 * Le guard décide qui voit quoi. Il vivait en ligne dans `router/index.js`, dans
 * un `router.beforeEach` qui n'était atteignable qu'en montant un router complet
 * avec un vrai historique de navigateur — donc **aucun test unitaire** ne le
 * couvrait, alors que 42 000 lignes de JavaScript en dépendent.
 *
 * ## Le manquement que ces tests figent
 *
 * Les routes déclarent `meta.permission` (`recruitment.view`,
 * `search_intel.view`…) depuis #7553, et le store expose `hasPermission()`.
 * Mais le guard **ne lisait jamais `meta.permission`** : il ne vérifiait que
 * `requiresAuth`. Un compte connecté sans la permission atteignait l'écran par
 * URL directe, et ne se fermait qu'au premier 403 de l'API — après montage du
 * composant, avec un écran vide plutôt qu'un refus clair.
 *
 * Le filtrage du menu masquait le lien : le défaut ne se voyait qu'en tapant
 * l'URL ou en suivant un favori.
 *
 * ## Stratégie
 *
 * Le guard prend ses dépendances en paramètre. On lui passe des doubles et on
 * lit sa valeur de retour, exactement comme Vue Router le fait : `true` pour
 * laisser passer, une chaîne pour rediriger.
 */

function makeAuthStore(overrides = {}) {
  return {
    isAuthenticated: true,
    token: 'jwt',
    checkAuth: vi.fn().mockResolvedValue(true),
    hasPermission: vi.fn().mockReturnValue(true),
    ...overrides,
  }
}

function makeGuard(authStore, extra = {}) {
  return createAuthGuard({
    useAuthStore: () => authStore,
    useLocaleStore: () => ({ current: 'fr' }),
    translate: (_locale, key) => key,
    setTitle: vi.fn(),
    ...extra,
  })
}

function route(meta = {}, name = 'some-route') {
  return { name, meta }
}

describe('createAuthGuard — authentification', () => {
  it('laisse passer une route publique sans session', async () => {
    const authStore = makeAuthStore({ isAuthenticated: false, token: null })
    const guard = makeGuard(authStore)

    await expect(guard(route({ requiresAuth: false }))).resolves.toBe(true)
  })

  it('redirige vers /login une route protégée sans token', async () => {
    const authStore = makeAuthStore({ isAuthenticated: false, token: null })
    const guard = makeGuard(authStore)

    await expect(guard(route({ requiresAuth: true }))).resolves.toBe(LOGIN_PATH)
    expect(authStore.checkAuth).not.toHaveBeenCalled()
  })

  it('revalide un token présent mais une session non établie', async () => {
    const authStore = makeAuthStore({ isAuthenticated: false, token: 'jwt' })
    const guard = makeGuard(authStore)

    await expect(guard(route({ requiresAuth: true }))).resolves.toBe(true)
    expect(authStore.checkAuth).toHaveBeenCalledTimes(1)
  })

  it('redirige vers /login quand la revalidation échoue', async () => {
    const authStore = makeAuthStore({
      isAuthenticated: false,
      token: 'jwt',
      checkAuth: vi.fn().mockResolvedValue(false),
    })
    const guard = makeGuard(authStore)

    await expect(guard(route({ requiresAuth: true }))).resolves.toBe(LOGIN_PATH)
  });

  it('renvoie un utilisateur connecté du /login vers le tableau de bord', async () => {
    const guard = makeGuard(makeAuthStore())

    await expect(guard(route({ requiresAuth: false }, 'login'))).resolves.toBe('/')
  })

  it('laisse un visiteur anonyme sur /login', async () => {
    const guard = makeGuard(makeAuthStore({ isAuthenticated: false, token: null }))

    await expect(guard(route({ requiresAuth: false }, 'login'))).resolves.toBe(true)
  })
})

describe('createAuthGuard — autorisation (meta.permission)', () => {
  it('laisse passer quand la permission est accordée', async () => {
    const authStore = makeAuthStore({ hasPermission: vi.fn().mockReturnValue(true) })
    const guard = makeGuard(authStore)

    await expect(
      guard(route({ requiresAuth: true, permission: 'search_intel.view' })),
    ).resolves.toBe(true)
    expect(authStore.hasPermission).toHaveBeenCalledWith('search_intel.view')
  })

  it('redirige vers /forbidden quand la permission manque', async () => {
    const authStore = makeAuthStore({ hasPermission: vi.fn().mockReturnValue(false) })
    const guard = makeGuard(authStore)

    await expect(
      guard(route({ requiresAuth: true, permission: 'search_intel.view' })),
    ).resolves.toBe(FORBIDDEN_PATH)
  })

  it('ne consulte pas les permissions sur une route sans meta.permission', async () => {
    const authStore = makeAuthStore()
    const guard = makeGuard(authStore)

    await expect(guard(route({ requiresAuth: true }))).resolves.toBe(true)
    expect(authStore.hasPermission).not.toHaveBeenCalled()
  })

  it('ne renvoie pas un anonyme vers /forbidden — /login prime', async () => {
    // Une route publique qui déclarerait une permission par erreur ne doit pas
    // envoyer un visiteur anonyme vers un écran « accès refusé » : il n'est pas
    // refusé, il n'est pas connecté.
    const authStore = makeAuthStore({
      isAuthenticated: false,
      token: null,
      hasPermission: vi.fn().mockReturnValue(false),
    })
    const guard = makeGuard(authStore)

    await expect(
      guard(route({ requiresAuth: true, permission: 'search_intel.view' })),
    ).resolves.toBe(LOGIN_PATH)
  })

  it('applique la permission à chaque route qui en déclare une', async () => {
    const authStore = makeAuthStore({
      hasPermission: vi.fn((permission) => permission === 'recruitment.view'),
    })
    const guard = makeGuard(authStore)

    await expect(
      guard(route({ requiresAuth: true, permission: 'recruitment.view' })),
    ).resolves.toBe(true)
    await expect(
      guard(route({ requiresAuth: true, permission: 'search_intel.view' })),
    ).resolves.toBe(FORBIDDEN_PATH)
  })
})

describe('createAuthGuard — titre de page', () => {
  it('écrit le titre traduit quand la route en déclare un', async () => {
    const setTitle = vi.fn()
    const guard = makeGuard(makeAuthStore(), {
      translate: (_locale, key) => `T:${key}`,
      setTitle,
    })

    await guard(route({ requiresAuth: true, title: 'navigation.dashboard' }))

    expect(setTitle).toHaveBeenCalledWith('T:navigation.dashboard - Leopardo Admin')
  })

  it('ne touche pas au titre quand la route n’en déclare pas', async () => {
    const setTitle = vi.fn()
    const guard = makeGuard(makeAuthStore(), { setTitle })

    await guard(route({ requiresAuth: true }))

    expect(setTitle).not.toHaveBeenCalled()
  })
})

describe('createAuthGuard — barre de progression', () => {
  it('démarre la progression à chaque navigation', async () => {
    const nprogress = { start: vi.fn(), done: vi.fn() }
    const guard = makeGuard(makeAuthStore(), { nprogress })

    await guard(route({ requiresAuth: true }))

    expect(nprogress.start).toHaveBeenCalledTimes(1)
  })

  it('fonctionne sans barre de progression injectée', async () => {
    const guard = makeGuard(makeAuthStore())

    await expect(guard(route({ requiresAuth: true }))).resolves.toBe(true)
  })
})
