import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

/**
 * #7433 — « un échec de chargement n'est pas un zéro ».
 *
 * Avant : `loadHealth`/`loadMonitoring` faisaient `console.warn` et l'écran
 * affichait « IA désactivée » et des KPI à 0 — une supervision qui ment sur
 * son propre état (même classe que le « mailer muet » #7389).
 */
const get = vi.fn()

vi.mock('@/services/api', () => ({
  default: {
    get: (...args) => get(...args),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}))

vi.mock('vue-toastification', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() }),
}))

import AiAssistantView from '@/views/settings/AiAssistantView.vue'

function mountView() {
  setActivePinia(createPinia())
  localStorage.setItem('admin_locale', 'fr')
  return mount(AiAssistantView, {
    global: { stubs: { Teleport: true }, mocks: { $t: (key, fallback) => fallback ?? key } },
  })
}

describe('AiAssistantView (#7433)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('affiche les KPI réels quand le suivi répond', async () => {
    get.mockImplementation((url) => {
      if (url.endsWith('/monitoring')) {
        return Promise.resolve({ data: { data: { totals: { requests: 12, errors: 1 } } } })
      }
      if (url.endsWith('/health')) {
        return Promise.resolve({ data: { data: { enabled: true, driver: 'openai' } } })
      }
      return Promise.resolve({ data: { data: { catalog: {}, settings: {} } } })
    })

    const wrapper = mountView()
    await flushPromises()
    // Onglet « Suivi »
    await wrapper.findAll('button')[1].trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('12')
    expect(wrapper.text()).not.toContain('indisponible')
  })

  it('affiche un état d’erreur explicite au lieu de « 0 requête »', async () => {
    get.mockImplementation((url) => {
      if (url.endsWith('/monitoring') || url.endsWith('/health')) {
        return Promise.reject({ response: { data: { message: 'API AI indisponible' }, status: 503 } })
      }
      return Promise.reject({ response: { data: { message: 'boom' }, status: 503 } })
    })

    const wrapper = mountView()
    await flushPromises()

    // Bandeau d'état : « indisponible », jamais « désactivée ».
    expect(wrapper.text()).toContain('indisponible')
    expect(wrapper.text()).not.toContain('désactivée')

    await wrapper.findAll('button').at(-1).trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('API AI indisponible')
  })
})
