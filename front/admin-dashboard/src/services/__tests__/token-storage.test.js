import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * Tests du stockage du token admin (#7695).
 *
 * ## Ce que ces tests protègent
 *
 * `token-storage.js` est le module qui décide OÙ vit le bearer super-admin. Il a
 * déjà régressé deux fois dans l'histoire du dépôt :
 *
 *  - #1575 : la migration localStorage → sessionStorage n'avait pas été suivie
 *    par les lecteurs secondaires (intercepteur axios, store realtime), qui
 *    lisaient encore localStorage — le polling de notifications ne démarrait
 *    donc jamais ;
 *  - #7695 : le token ne doit plus être lisible par un `sessionStorage.getItem`
 *    injecté par XSS pendant la vie de la page.
 *
 * Le contrat actuel est un **hand-off éphémère** : le token vit en mémoire
 * volatile, n'est écrit en sessionStorage qu'à `pagehide` (rechargement), et est
 * re-consommé — puis SUPPRIMÉ — au boot et à `pageshow` (bfcache). Ces tests
 * figent ce contrat, y compris la fenêtre d'exposition résiduelle.
 *
 * ## Pourquoi on capture les écouteurs au lieu de dispatcher des événements
 *
 * Le module s'enregistre sur `window` à l'import. En interceptant
 * `addEventListener`, on invoque le handler exact que le module a posé, sans
 * dépendre du comportement de `pagehide`/`pageshow` de jsdom — et on évite
 * d'accumuler des écouteurs entre les tests.
 */

const STORAGE_KEY = 'admin_token'

/** Écouteurs enregistrés par le module au cours du test courant. */
let listeners = []

/**
 * Importe le module avec un état de module neuf.
 *
 * `vi.resetModules()` est indispensable : le module consomme le token persisté
 * au chargement et garde `inMemoryToken` dans une variable de module. Sans
 * réinitialisation, le deuxième test hériterait du token du premier.
 */
async function loadModule() {
  vi.resetModules()
  return import('../token-storage.js')
}

/** Déclenche le handler que le module a enregistré pour `type`. */
function fire(type) {
  for (const [registeredType, handler] of listeners) {
    if (registeredType === type) {
      handler()
    }
  }
}

describe('token-storage', () => {
  beforeEach(() => {
    sessionStorage.clear()
    listeners = []
    // On capture sans réellement enregistrer : rien ne s'accumule entre les
    // tests, et `fire()` reste déterministe.
    vi.spyOn(window, 'addEventListener').mockImplementation((type, handler) => {
      listeners.push([type, handler])
    })
  })

  afterEach(() => {
    vi.restoreAllMocks()
    sessionStorage.clear()
  })

  it('démarre sans token', async () => {
    const { getAuthToken } = await loadModule()

    expect(getAuthToken()).toBeNull()
  })

  it('rend le token posé en mémoire', async () => {
    const { getAuthToken, setAuthToken } = await loadModule()

    setAuthToken('bearer-abc')

    expect(getAuthToken()).toBe('bearer-abc')
  })

  it('ne laisse PAS le token en sessionStorage pendant la session active', async () => {
    const { setAuthToken } = await loadModule()

    setAuthToken('bearer-abc')

    // C'est la garantie centrale de #7695 : un `sessionStorage.getItem` injecté
    // par XSS ne trouve rien tant que la page vit.
    expect(sessionStorage.getItem(STORAGE_KEY)).toBeNull()
  })

  it('consomme le token persisté au boot et le SUPPRIME du sessionStorage', async () => {
    // Simule le rechargement : le token a été écrit par `pagehide` avant la
    // décharge, il est présent au boot du module.
    sessionStorage.setItem(STORAGE_KEY, 'bearer-from-reload')

    const { getAuthToken } = await loadModule()

    expect(getAuthToken()).toBe('bearer-from-reload')
    // Le hand-off est one-shot : le résidu ne doit pas survivre au boot.
    expect(sessionStorage.getItem(STORAGE_KEY)).toBeNull()
  })

  it('réécrit le token en sessionStorage à `pagehide`', async () => {
    const { setAuthToken } = await loadModule()
    setAuthToken('bearer-abc')

    fire('pagehide')

    // Fenêtre d'exposition résiduelle assumée : la décharge de la page.
    expect(sessionStorage.getItem(STORAGE_KEY)).toBe('bearer-abc')
  })

  it('réécrit aussi le token à `beforeunload` (filet pour les navigateurs qui ne tirent que lui)', async () => {
    const { setAuthToken } = await loadModule()
    setAuthToken('bearer-abc')

    fire('beforeunload')

    expect(sessionStorage.getItem(STORAGE_KEY)).toBe('bearer-abc')
  })

  it('re-consomme le token à `pageshow` (retour depuis le bfcache)', async () => {
    const { setAuthToken, getAuthToken } = await loadModule()
    setAuthToken('bearer-abc')
    fire('pagehide')

    // La page revit depuis le bfcache : le module n'est PAS ré-exécuté, donc
    // `inMemoryToken` est toujours là — mais le résidu persisté doit être
    // refermé.
    fire('pageshow')

    expect(getAuthToken()).toBe('bearer-abc')
    expect(sessionStorage.getItem(STORAGE_KEY)).toBeNull()
  })

  it('n’écrit rien à `pagehide` quand aucun token n’est en mémoire', async () => {
    await loadModule()

    fire('pagehide')

    expect(sessionStorage.getItem(STORAGE_KEY)).toBeNull()
  })

  it('purge le résidu persisté à la déconnexion', async () => {
    const { setAuthToken, removeAuthToken, getAuthToken } = await loadModule()
    setAuthToken('bearer-abc')
    fire('pagehide')
    expect(sessionStorage.getItem(STORAGE_KEY)).toBe('bearer-abc')

    removeAuthToken()

    expect(getAuthToken()).toBeNull()
    // Défensif : un logout juste après un boot ne doit pas laisser de trace.
    expect(sessionStorage.getItem(STORAGE_KEY)).toBeNull()
  })

  it('survit à un sessionStorage indisponible', async () => {
    // Safari en navigation privée, iframe sandboxée : `sessionStorage` lève.
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('SecurityError')
    })
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('SecurityError')
    })
    vi.spyOn(Storage.prototype, 'removeItem').mockImplementation(() => {
      throw new Error('SecurityError')
    })

    const { getAuthToken, setAuthToken, removeAuthToken } = await loadModule()

    // Aucune de ces opérations ne doit lever : le module retombe sur la mémoire
    // volatile seule.
    expect(() => setAuthToken('bearer-abc')).not.toThrow()
    expect(getAuthToken()).toBe('bearer-abc')
    expect(() => fire('pagehide')).not.toThrow()
    expect(() => removeAuthToken()).not.toThrow()
    expect(getAuthToken()).toBeNull()
  })
})
