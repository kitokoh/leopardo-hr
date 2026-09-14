import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'

/**
 * #7433 — ConfirmDialog est le SEUL chemin de confirmation autorisé
 * (remplace `window.confirm`). Ces tests verrouillent son contrat.
 */
function mountDialog(props = {}) {
  return mount(ConfirmDialog, {
    props: { open: true, title: 'Supprimer cet élément ?', ...props },
    global: {
      stubs: { Teleport: true },
      mocks: { $t: (key, fallback) => fallback ?? key },
    },
  })
}

describe('ConfirmDialog', () => {
  it('ne rend rien quand il est fermé', () => {
    const wrapper = mountDialog({ open: false })
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
  })

  it('affiche le titre, le message et les libellés', () => {
    const wrapper = mountDialog({ message: 'Action irréversible', confirmLabel: 'Supprimer' })
    const dialog = wrapper.find('[role="dialog"]')
    expect(dialog.exists()).toBe(true)
    expect(dialog.text()).toContain('Supprimer cet élément ?')
    expect(dialog.text()).toContain('Action irréversible')
    expect(dialog.text()).toContain('Supprimer')
  })

  it('émet confirm une seule fois au clic', async () => {
    const wrapper = mountDialog()
    await wrapper.findAll('button').at(-1).trigger('click')
    expect(wrapper.emitted('confirm')).toHaveLength(1)
    expect(wrapper.emitted('cancel')).toBeUndefined()
  })

  it('émet cancel au clic sur Annuler', async () => {
    const wrapper = mountDialog()
    await wrapper.findAll('button').at(0).trigger('click')
    expect(wrapper.emitted('cancel')).toHaveLength(1)
    expect(wrapper.emitted('confirm')).toBeUndefined()
  })

  it("n'émet pas confirm quand l'action est en cours (busy)", async () => {
    const wrapper = mountDialog({ busy: true })
    await wrapper.findAll('button').at(-1).trigger('click')
    expect(wrapper.emitted('confirm')).toBeUndefined()
  })
})
