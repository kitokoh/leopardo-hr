import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * Tests de la résolution de l'URL d'API du dashboard admin (#8707).
 *
 * ## La régression que ces tests figent
 *
 * Avant #8707, `services/api.js` lisait `import.meta.env.VITE_API_URL`, une
 * valeur **inlinée par Vite au build**. Le workflow de déploiement y injectait
 * l'URL brute du service Render de la stack active. Conséquence : l'URL d'un
 * fournisseur était gravée dans le bundle JavaScript.
 *
 * Le 2026-09-20, Render a suspendu la stack dev A. Le bundle admin a continué de
 * l'appeler jusqu'au redéploiement suivant — **un mois de front figé** — et
 * chaque bascule de stack imposait un rebuild complet du front.
 *
 * La règle actuelle : le dashboard ne connaît plus aucune URL de fournisseur, il
 * appelle le proxy same-origin du front web, dont la cible est lue au runtime.
 * Ces tests protègent l'ordre de résolution et le garde-fou de production.
 */

const DEV_FALLBACK = 'http://localhost:8000/api/v1'

async function loadModule() {
  vi.resetModules()
  return import('../api-base-url.js')
}

describe('resolveApiBaseUrl', () => {
  beforeEach(() => {
    vi.unstubAllEnvs()
  })

  afterEach(() => {
    vi.unstubAllEnvs()
    vi.restoreAllMocks()
  })

  it('privilégie le proxy stable quand il est défini', async () => {
    vi.stubEnv('VITE_API_PROXY_BASE_URL', 'https://web.leopardo.app/api/v1')
    vi.stubEnv('VITE_API_URL', 'https://backend.onrender.com/api/v1')
    const { resolveApiBaseUrl } = await loadModule()

    const resolved = resolveApiBaseUrl()

    // Le proxy gagne TOUJOURS, même si une URL directe est aussi présente :
    // c'est ce qui garantit qu'une bascule de stack ne demande aucun rebuild.
    expect(resolved).toEqual({ baseURL: 'https://web.leopardo.app/api/v1', mode: 'proxy' })
  })

  it('accepte une URL directe en l’absence de proxy (dev, environnements sans front web)', async () => {
    vi.stubEnv('VITE_API_PROXY_BASE_URL', '')
    vi.stubEnv('VITE_API_URL', 'https://backend.onrender.com/api/v1')
    const { resolveApiBaseUrl } = await loadModule()

    const resolved = resolveApiBaseUrl()

    expect(resolved).toEqual({ baseURL: 'https://backend.onrender.com/api/v1', mode: 'direct' })
  })

  it('retombe sur le backend local en dev/test, avec un avertissement', async () => {
    vi.stubEnv('VITE_API_PROXY_BASE_URL', '')
    vi.stubEnv('VITE_API_URL', '')
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    const { resolveApiBaseUrl } = await loadModule()

    const resolved = resolveApiBaseUrl()

    expect(resolved).toEqual({ baseURL: DEV_FALLBACK, mode: 'dev-fallback' })
    // Le repli doit être BRUYANT : c'est un repli de poste de dev, jamais un
    // défaut silencieux de production.
    expect(warn).toHaveBeenCalled()
  })

  it('expose le repli dev comme constante nommée', async () => {
    const { DEV_FALLBACK_API_BASE_URL } = await loadModule()

    expect(DEV_FALLBACK_API_BASE_URL).toBe(DEV_FALLBACK)
  })
})

describe('assertProductionInvariant', () => {
  beforeEach(() => {
    vi.unstubAllEnvs()
  })

  afterEach(() => {
    vi.unstubAllEnvs()
    vi.restoreAllMocks()
  })

  it('laisse passer le mode proxy', async () => {
    const { assertProductionInvariant } = await loadModule()

    expect(() =>
      assertProductionInvariant({ baseURL: 'https://web.leopardo.app/api/v1', mode: 'proxy' }),
    ).not.toThrow()
  })

  it('laisse passer le repli dev hors production', async () => {
    const { assertProductionInvariant } = await loadModule()

    expect(() =>
      assertProductionInvariant({ baseURL: DEV_FALLBACK, mode: 'dev-fallback' }),
    ).not.toThrow()
  })

  it('REFUSE un appel direct en production', async () => {
    vi.stubEnv('PROD', true)
    vi.stubEnv('VITE_ALLOW_DIRECT_API_IN_PROD', '')
    const error = vi.spyOn(console, 'error').mockImplementation(() => {})
    const { assertProductionInvariant } = await loadModule()

    // C'est exactement la régression qui a figé le front un mois : on échoue
    // bruyamment plutôt que de la laisser repasser.
    expect(() =>
      assertProductionInvariant({ baseURL: 'https://backend.onrender.com/api/v1', mode: 'direct' }),
    ).toThrow(/appel DIRECT/)

    expect(error).toHaveBeenCalled()
  })

  it('accepte un appel direct en production sous dérogation explicite', async () => {
    vi.stubEnv('PROD', true)
    vi.stubEnv('VITE_ALLOW_DIRECT_API_IN_PROD', 'true')
    const { assertProductionInvariant } = await loadModule()

    expect(() =>
      assertProductionInvariant({ baseURL: 'https://backend.onrender.com/api/v1', mode: 'direct' }),
    ).not.toThrow()
  })

  it('nomme la variable à corriger dans le message d’erreur', async () => {
    vi.stubEnv('PROD', true)
    vi.stubEnv('VITE_ALLOW_DIRECT_API_IN_PROD', '')
    vi.spyOn(console, 'error').mockImplementation(() => {})
    const { assertProductionInvariant } = await loadModule()

    // Le garde-fou doit être actionnable sans lire le code : la variable à
    // poser est nommée dans le message.
    expect(() =>
      assertProductionInvariant({ baseURL: 'https://x.onrender.com/api/v1', mode: 'direct' }),
    ).toThrow(/VITE_API_PROXY_BASE_URL/)
  })
})
