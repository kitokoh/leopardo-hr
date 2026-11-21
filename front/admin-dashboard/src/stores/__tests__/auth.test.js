import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * Tests du store d'authentification plateforme.
 *
 * ## Pourquoi ce fichier existe
 *
 * `stores/auth.js` décide qui est connecté (`isAuthenticated`), quel rôle
 * interne porte le compte (`platformRole`), et quels écrans sont visibles
 * (`hasPermission`). Il n'était couvert par **aucun** test unitaire : les 33
 * specs Playwright e2e ne l'exercent qu'à travers un navigateur et un backend.
 *
 * ## Les régressions que ces tests figent
 *
 *  - **#3929** — un 401 en cours de session devait remettre le store à zéro.
 *    Sans `clearSession()`, `isAuthenticated` restait vrai et le guard du router
 *    rebondissait `/login` → `/` : SPA figée jusqu'au rechargement complet.
 *  - **#4515** — `checkAuth` ne devait détruire la session que sur une vraie
 *    invalidation (401/403/410). Un blip réseau ou un 5xx transitoire au
 *    démarrage déconnectait l'admin et supprimait son token pour rien.
 *  - **#7553/#7557** — `role` reste `'super_admin'` pour tous les comptes
 *    plateforme (rétrocompatibilité) ; c'est `platform_role` qui porte la
 *    délégation et `permissions` le périmètre effectif. Une session héritée sans
 *    `platform_role` doit retomber sur la sémantique permissive de `role`.
 *
 * ## Stratégie de mock
 *
 * On mocke `@/services/api` (le client axios) plutôt que le réseau : le store
 * ne fait que traduire des réponses HTTP en état. Cela rend chaque cas
 * déterministe et instantané, sans serveur ni interception d'axios.
 */

const post = vi.fn()
const get = vi.fn()
const patch = vi.fn()

vi.mock('@/services/api', () => ({
  default: {
    post: (...args) => post(...args),
    get: (...args) => get(...args),
    patch: (...args) => patch(...args),
    defaults: { headers: { common: {} } },
  },
}))

// Le store de locale est monté par `login()` pour synchroniser la langue de
// l'utilisateur. On le neutralise : il touche `localStorage` et `document`, et
// n'a rien à voir avec ce qu'on vérifie ici.
vi.mock('@/stores/locale.js', () => ({
  useLocaleStore: () => ({ current: 'fr', initFromUser: vi.fn() }),
}))

/** Réponse de connexion réussie, telle que la renvoie `/platform/auth/login`. */
function loginResponse(overrides = {}) {
  return {
    status: 200,
    data: {
      token: 'bearer-abc',
      data: {
        id: 1,
        name: 'Ada Lovelace',
        email: 'ada@leopardo.app',
        role: 'super_admin',
        platform_role: 'super_admin',
        permissions: ['companies.view', 'users.view'],
        ...overrides,
      },
    },
  }
}

async function loadStore() {
  vi.resetModules()
  const { useAuthStore } = await import('../auth.js')
  return useAuthStore()
}

describe('useAuthStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    post.mockReset()
    get.mockReset()
    patch.mockReset()
    sessionStorage.clear()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  describe('login', () => {
    it('expose l’identité et le token après une connexion réussie', async () => {
      post.mockResolvedValue(loginResponse())
      const store = await loadStore()

      const result = await store.login({ email: 'ada@leopardo.app', password: 'secret' })

      expect(result).toEqual({ success: true })
      expect(store.isAuthenticated).toBe(true)
      expect(store.userName).toBe('Ada Lovelace')
      expect(store.userEmail).toBe('ada@leopardo.app')
      expect(store.token).toBe('bearer-abc')
    })

    it('envoie le nom d’appareil par défaut quand l’appelant n’en fournit pas', async () => {
      post.mockResolvedValue(loginResponse())
      const store = await loadStore()

      await store.login({ email: 'ada@leopardo.app', password: 'secret' })

      expect(post).toHaveBeenCalledWith('/platform/auth/login', {
        email: 'ada@leopardo.app',
        password: 'secret',
        device_name: 'leo-admin-dashboard',
      })
    })

    it('respecte un nom d’appareil explicite', async () => {
      post.mockResolvedValue(loginResponse())
      const store = await loadStore()

      await store.login({ email: 'a@b.c', password: 'x', device_name: 'poste-bureau' })

      expect(post.mock.calls[0][1].device_name).toBe('poste-bureau')
    })

    it('signale le 2FA requis sur un 202 sans authentifier', async () => {
      post.mockResolvedValue({
        status: 202,
        data: { error: 'TWO_FA_REQUIRED', message: 'Code requis.' },
      })
      const store = await loadStore()

      const result = await store.login({ email: 'a@b.c', password: 'x' })

      expect(result.success).toBe(false)
      expect(result.requiresTwoFactor).toBe(true)
      expect(store.isAuthenticated).toBe(false)
    })

    it('signale le 2FA requis quand le corps porte l’erreur, même sur un 200', async () => {
      // Le contrat a déjà varié sur ce point : on accepte les deux formes.
      post.mockResolvedValue({ status: 200, data: { error: 'TWO_FA_REQUIRED' } })
      const store = await loadStore()

      const result = await store.login({ email: 'a@b.c', password: 'x' })

      expect(result.requiresTwoFactor).toBe(true)
      expect(store.isAuthenticated).toBe(false)
    })

    it('refuse une réponse sans token ni utilisateur', async () => {
      post.mockResolvedValue({ status: 200, data: { token: 'bearer-abc' } })
      const store = await loadStore()

      const result = await store.login({ email: 'a@b.c', password: 'x' })

      expect(result.success).toBe(false)
      expect(result.requiresTwoFactor).toBe(false)
      expect(store.isAuthenticated).toBe(false)
    })

    it('remonte le message localisé du serveur en cas d’échec', async () => {
      post.mockRejectedValue({
        response: { status: 401, data: { localized_message: 'Identifiants invalides.' } },
      })
      const store = await loadStore()

      const result = await store.login({ email: 'a@b.c', password: 'x' })

      expect(result.success).toBe(false)
      expect(result.message).toBe('Identifiants invalides.')
    })

    it('retombe sur un message générique quand le serveur n’en fournit aucun', async () => {
      post.mockRejectedValue({ response: { status: 500, data: {} } })
      const store = await loadStore()

      const result = await store.login({ email: 'a@b.c', password: 'x' })

      expect(result.success).toBe(false)
      expect(result.message).toBeTruthy()
    })

    it('libère l’indicateur de chargement même en cas d’échec', async () => {
      post.mockRejectedValue({ response: { status: 500, data: {} } })
      const store = await loadStore()

      await store.login({ email: 'a@b.c', password: 'x' })

      expect(store.isLoading).toBe(false)
    })

    it('accepte un compte délégué (platform_role non super_admin)', async () => {
      // #7553 : le compte reste un compte PLATEFORME, mais `role` n'est plus le
      // seul signal. Un compte `support` doit pouvoir se connecter.
      post.mockResolvedValue(
        loginResponse({ platform_role: 'support', permissions: ['users.view'] }),
      )
      const store = await loadStore()

      const result = await store.login({ email: 'a@b.c', password: 'x' })

      expect(result.success).toBe(true)
      expect(store.platformRole).toBe('support')
      expect(store.isSuperAdmin).toBe(false)
    })
  })

  describe('permissions', () => {
    it('laisse passer une entrée sans permission déclarée', async () => {
      post.mockResolvedValue(loginResponse({ platform_role: 'support', permissions: [] }))
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })

      // `null`/`undefined` = écran non restreint : il reste visible.
      expect(store.hasPermission(null)).toBe(true)
      expect(store.hasPermission(undefined)).toBe(true)
    })

    it('refuse une permission absente du périmètre', async () => {
      post.mockResolvedValue(loginResponse({ platform_role: 'support', permissions: ['users.view'] }))
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })

      expect(store.hasPermission('users.view')).toBe(true)
      expect(store.hasPermission('companies.delete')).toBe(false)
    })

    it('accorde TOUTES les permissions à un super_admin', async () => {
      post.mockResolvedValue(loginResponse({ platform_role: 'super_admin', permissions: [] }))
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })

      expect(store.hasPermission('companies.delete')).toBe(true)
    })

    it('retombe sur `role` quand `platform_role` n’est pas exposé (session héritée)', async () => {
      // Réponse d'API antérieure au déploiement de #7553 : seul `role` existe.
      post.mockResolvedValue(loginResponse({ platform_role: undefined }))
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })

      expect(store.platformRole).toBeNull()
      expect(store.isSuperAdmin).toBe(true)
    })

    it('ne considère pas un compte sans rôle comme super_admin', async () => {
      post.mockResolvedValue(loginResponse({ role: 'admin', platform_role: undefined }))
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })

      expect(store.isSuperAdmin).toBe(false)
    })

    it('ignore une charge `permissions` qui n’est pas un tableau', async () => {
      post.mockResolvedValue(loginResponse({ permissions: 'companies.view' }))
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })

      expect(store.permissions).toEqual([])
    })
  })

  describe('clearSession', () => {
    it('remet l’état à zéro de façon synchrone (#3929)', async () => {
      post.mockResolvedValue(loginResponse())
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })
      expect(store.isAuthenticated).toBe(true)

      store.clearSession()

      // Le point de #3929 : `isAuthenticated` doit devenir faux IMMÉDIATEMENT,
      // sans attendre un rechargement — sinon le guard rebondit vers `/`.
      expect(store.isAuthenticated).toBe(false)
      expect(store.token).toBeNull()
      expect(store.user).toBeNull()
      expect(store.permissions).toEqual([])
    })
  })

  describe('logout', () => {
    it('appelle l’API puis purge la session', async () => {
      post.mockResolvedValueOnce(loginResponse())
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })
      post.mockResolvedValueOnce({ status: 200, data: {} })

      await store.logout()

      expect(post).toHaveBeenLastCalledWith('/platform/auth/logout')
      expect(store.isAuthenticated).toBe(false)
    })

    it('purge la session même si l’appel de déconnexion échoue', async () => {
      post.mockResolvedValueOnce(loginResponse())
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })
      post.mockRejectedValueOnce(new Error('réseau indisponible'))

      await store.logout()

      // Un logout qui échoue côté serveur ne doit jamais laisser l'admin
      // connecté localement.
      expect(store.isAuthenticated).toBe(false)
      expect(store.token).toBeNull()
    })

    it('n’appelle pas l’API quand aucun token n’est présent', async () => {
      const store = await loadStore()

      await store.logout()

      expect(post).not.toHaveBeenCalled()
    })
  })

  describe('checkAuth', () => {
    it('renvoie false sans token, sans appeler l’API', async () => {
      const store = await loadStore()

      await expect(store.checkAuth()).resolves.toBe(false)
      expect(get).not.toHaveBeenCalled()
    })

    it('recharge l’identité et les permissions depuis /me', async () => {
      post.mockResolvedValue(loginResponse())
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })
      get.mockResolvedValue({
        data: {
          data: {
            id: 1,
            name: 'Ada Lovelace',
            email: 'ada@leopardo.app',
            role: 'super_admin',
            platform_role: 'finance',
            permissions: ['accounting.view'],
          },
        },
      })

      await expect(store.checkAuth()).resolves.toBe(true)

      expect(store.platformRole).toBe('finance')
      expect(store.hasPermission('accounting.view')).toBe(true)
      expect(store.hasPermission('users.delete')).toBe(false)
    })

    it('détruit la session sur 401 (#4515)', async () => {
      post.mockResolvedValue(loginResponse())
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })
      get.mockRejectedValue({ response: { status: 401 } })
      post.mockResolvedValue({ status: 200, data: {} })

      await expect(store.checkAuth()).resolves.toBe(false)

      expect(store.isAuthenticated).toBe(false)
    })

    it('détruit la session sur 403 et 410', async () => {
      for (const status of [403, 410]) {
        setActivePinia(createPinia())
        post.mockResolvedValue(loginResponse())
        const store = await loadStore()
        await store.login({ email: 'a@b.c', password: 'x' })
        get.mockRejectedValue({ response: { status } })
        post.mockResolvedValue({ status: 200, data: {} })

        await expect(store.checkAuth()).resolves.toBe(false)
        expect(store.isAuthenticated).toBe(false)
      }
    })

    it('CONSERVE la session sur une erreur transitoire (#4515)', async () => {
      post.mockResolvedValue(loginResponse())
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })
      // Blip réseau : pas de `response` du tout.
      get.mockRejectedValue(new Error('timeout'))

      await expect(store.checkAuth()).resolves.toBe(true)

      // C'est le cœur de #4515 : un 5xx transitoire au démarrage ne doit pas
      // déconnecter l'admin ni supprimer son token.
      expect(store.isAuthenticated).toBe(true)
      expect(store.token).toBe('bearer-abc')
    })

    it('CONSERVE la session sur un 500 transitoire', async () => {
      post.mockResolvedValue(loginResponse())
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })
      get.mockRejectedValue({ response: { status: 500 } })

      await expect(store.checkAuth()).resolves.toBe(true)

      expect(store.isAuthenticated).toBe(true)
    })
  })

  describe('updateProfile', () => {
    it('applique l’identité renvoyée par l’API', async () => {
      post.mockResolvedValue(loginResponse())
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })
      patch.mockResolvedValue({
        data: {
          data: {
            id: 1,
            name: 'Ada L.',
            email: 'ada@leopardo.app',
            role: 'super_admin',
            platform_role: 'ops',
            permissions: ['fleet.view'],
          },
        },
      })

      const result = await store.updateProfile({ name: 'Ada L.' })

      expect(result.success).toBe(true)
      expect(store.userName).toBe('Ada L.')
      expect(store.platformRole).toBe('ops')
    })

    it('remonte l’erreur sans casser l’état courant', async () => {
      post.mockResolvedValue(loginResponse())
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })
      patch.mockRejectedValue({
        response: { status: 422, data: { localized_message: 'Email déjà utilisé.' } },
      })

      const result = await store.updateProfile({ email: 'pris@leopardo.app' })

      expect(result.success).toBe(false)
      expect(result.message).toBe('Email déjà utilisé.')
      expect(store.userEmail).toBe('ada@leopardo.app')
    })
  })

  describe('changePassword', () => {
    it('renvoie un succès sans toucher à la session', async () => {
      post.mockResolvedValueOnce(loginResponse())
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })
      post.mockResolvedValueOnce({ status: 200, data: {} })

      const result = await store.changePassword({ current_password: 'x', password: 'y' })

      expect(result.success).toBe(true)
      expect(store.isAuthenticated).toBe(true)
    })

    it('remonte le message d’erreur du serveur', async () => {
      post.mockResolvedValueOnce(loginResponse())
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })
      post.mockRejectedValueOnce({
        response: { status: 422, data: { localized_message: 'Mot de passe actuel incorrect.' } },
      })

      const result = await store.changePassword({ current_password: 'faux', password: 'y' })

      expect(result.success).toBe(false)
      expect(result.message).toBe('Mot de passe actuel incorrect.')
    })
  })

  describe('2FA', () => {
    it('setup2fa renvoie le secret', async () => {
      post.mockResolvedValueOnce(loginResponse())
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })
      post.mockResolvedValueOnce({ data: { data: { secret: 'JBSWY3DPEHPK3PXP' } } })

      const result = await store.setup2fa()

      expect(result.success).toBe(true)
      expect(result.data.secret).toBe('JBSWY3DPEHPK3PXP')
    })

    it('enable2fa marque le compte comme protégé', async () => {
      post.mockResolvedValueOnce(loginResponse())
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })
      post.mockResolvedValueOnce({ status: 200, data: {} })

      const result = await store.enable2fa('123456')

      expect(result.success).toBe(true)
      expect(store.user.two_fa_enabled).toBe(true)
    })

    it('disable2fa démarque le compte', async () => {
      post.mockResolvedValueOnce(loginResponse())
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })
      post.mockResolvedValueOnce({ status: 200, data: {} })

      const result = await store.disable2fa('motdepasse')

      expect(result.success).toBe(true)
      expect(store.user.two_fa_enabled).toBe(false)
    })

    it('remonte un code 2FA invalide sans modifier l’état', async () => {
      post.mockResolvedValueOnce(loginResponse())
      const store = await loadStore()
      await store.login({ email: 'a@b.c', password: 'x' })
      post.mockRejectedValueOnce({
        response: { status: 422, data: { localized_message: 'Code invalide.' } },
      })

      const result = await store.enable2fa('000000')

      expect(result.success).toBe(false)
      expect(result.message).toBe('Code invalide.')
      expect(store.user.two_fa_enabled).toBeUndefined()
    })
  })
})
