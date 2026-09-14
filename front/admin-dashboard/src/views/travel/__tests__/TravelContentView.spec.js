import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

/**
 * #7433 — régression recette admin (Vue) :
 *  1. une suppression de ligne passe par ConfirmDialog, jamais par un DELETE
 *     immédiat ni par `window.confirm` ;
 *  2. un échec d'enregistrement CONSERVE la saisie (la modale reste ouverte)
 *     et affiche l'erreur (avant : la modale se fermait en silence).
 */
const deleteTravel = vi.fn()
const createTravel = vi.fn()
const listTravel = vi.fn()

vi.mock('@/services/travel', () => ({
  listTravel: (...args) => listTravel(...args),
  createTravel: (...args) => createTravel(...args),
  updateTravel: vi.fn(),
  deleteTravel: (...args) => deleteTravel(...args),
  travelAction: vi.fn(),
  travelList: (response) => response?.data?.data ?? response?.data ?? [],
  travelItem: (response) => response?.data?.data ?? {},
}))

vi.mock('@/services/api', () => ({
  default: { get: vi.fn(() => Promise.resolve({ data: {} })), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

vi.mock('vue-toastification', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() }),
}))

import TravelContentView from '@/views/travel/TravelContentView.vue'
import { useTravelStore } from '@/stores/travel'

function mountView() {
  setActivePinia(createPinia())
  const travelStore = useTravelStore()
  // Flag travelagency actif : `TravelGate` laisse passer le contenu.
  travelStore.flagActive = true
  travelStore.flagChecked = true

  return mount(TravelContentView, {
    global: {
      stubs: { Teleport: true },
      mocks: { $t: (key, fallback) => fallback ?? key },
    },
  })
}

async function openSitesTab(wrapper) {
  const buttons = wrapper.findAll('button')
  const sites = buttons.find((b) => b.text().includes('Sites touristiques'))
  expect(sites, 'onglet « Sites touristiques » introuvable').toBeTruthy()
  await sites.trigger('click')
  await flushPromises()
}

describe('TravelContentView (#7433)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    // La console est en français dans la recette : on fixe la locale du store
    // (sinon `navigator.language` de jsdom = en-US → libellés anglais).
    localStorage.setItem('admin_locale', 'fr')
    listTravel.mockResolvedValue({ data: { data: [] } })
    createTravel.mockResolvedValue({ data: { data: {} } })
    deleteTravel.mockResolvedValue({ data: {} })
  })

  it('demande confirmation avant de supprimer une ligne, puis supprime', async () => {
    listTravel.mockImplementation((resource) =>
      resource === 'tourist-sites'
        ? Promise.resolve({ data: { data: [{ id: 7, name: 'Casbah' }] } })
        : Promise.resolve({ data: { data: [] } })
    )

    const wrapper = mountView()
    await flushPromises()
    await openSitesTab(wrapper)

    const deleteButton = wrapper.findAll('button').find((b) => b.text() === 'Supprimer')
    expect(deleteButton, 'bouton Supprimer introuvable').toBeTruthy()
    await deleteButton.trigger('click')
    await flushPromises()

    // Le dialogue s'ouvre, AUCUNE suppression n'est envoyée à ce stade.
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
    expect(deleteTravel).not.toHaveBeenCalled()

    const confirm = wrapper.find('[role="dialog"]').findAll('button').at(-1)
    await confirm.trigger('click')
    await flushPromises()

    expect(deleteTravel).toHaveBeenCalledWith('tourist-sites', 7)
  })

  it("conserve la saisie et affiche l'erreur quand l'enregistrement échoue", async () => {
    createTravel.mockRejectedValue({ response: { data: { message: 'Nom déjà utilisé' } } })

    const wrapper = mountView()
    await flushPromises()
    await openSitesTab(wrapper)

    const createButton = wrapper.findAll('button').find((b) => b.text() === 'Créer')
    await createButton.trigger('click')
    await flushPromises()

    const input = wrapper.find('#field-name')
    expect(input.exists()).toBe(true)
    await input.setValue('Mon site')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    // La modale est TOUJOURS ouverte et la saisie est conservée.
    expect(wrapper.find('#field-name').exists()).toBe(true)
    expect(wrapper.find('#field-name').element.value).toBe('Mon site')
    expect(wrapper.text()).toContain('Nom déjà utilisé')
  })

  it('#7434 — les actions de ligne restent conditionnées par le statut', async () => {
    listTravel.mockImplementation((resource) => {
      if (resource === 'adverts') {
        return Promise.resolve({
          data: {
            data: [
              { id: 1, title: 'Payée', status: 'paid', price_minor: 1000, currency: 'XOF' },
              { id: 2, title: 'Brouillon', status: 'draft', price_minor: 1000, currency: 'XOF' },
            ],
          },
        })
      }
      return Promise.resolve({ data: { data: [] } })
    })

    const wrapper = mountView()
    await flushPromises()

    // Onglet principal « Annonces » puis sous-onglet « Annonces payantes ».
    await wrapper.findAll('button').find((b) => b.text() === 'Annonces').trigger('click')
    await flushPromises()
    const subTab = wrapper.findAll('button').find((b) => b.text() === 'Annonces payantes')
    expect(subTab, 'sous-onglet « Annonces payantes » introuvable').toBeTruthy()
    await subTab.trigger('click')
    await flushPromises()

    const labels = wrapper.findAll('button').map((b) => b.attributes('aria-label')).filter(Boolean)
    expect(labels).toContain('Valider') // annonce payée
    expect(labels).toContain('Rejeter') // annonce payée
    expect(labels).toContain('Payer') // annonce brouillon
    expect(labels).not.toContain('Renouveler') // aucun statut validé/expiré
  })

  it('un échec de chargement affiche un état d’erreur, jamais « aucune donnée »', async () => {
    listTravel.mockRejectedValue({ response: { data: { message: 'API indisponible' } } })

    const wrapper = mountView()
    await flushPromises()
    await openSitesTab(wrapper)

    expect(wrapper.text()).toContain('API indisponible')
  })
})
