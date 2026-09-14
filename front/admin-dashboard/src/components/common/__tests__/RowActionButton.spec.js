import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { TrashIcon } from '@heroicons/vue/24/outline'
import RowActionButton from '@/components/common/RowActionButton.vue'

/**
 * #7434 — convention UNIQUE d'action de ligne : icône seule, mais toujours un
 * nom accessible (`aria-label`) + une affordance visuelle (`title`).
 */
function mountAction(props = {}) {
  return mount(RowActionButton, {
    props: { icon: TrashIcon, label: 'Supprimer', ...props },
  })
}

describe('RowActionButton', () => {
  it('rend une icône SANS texte visible (règle « icône seule »)', () => {
    const wrapper = mountAction()
    expect(wrapper.element.tagName).toBe('BUTTON')
    expect(wrapper.find('svg').exists()).toBe(true)
    // Le libellé n'est rendu que pour les lecteurs d'écran.
    const srOnly = wrapper.find('span')
    expect(srOnly.classes()).toContain('sr-only')
    expect(srOnly.text()).toBe('Supprimer')
  })

  it('porte un nom accessible et une infobulle', () => {
    const wrapper = mountAction()
    expect(wrapper.attributes('aria-label')).toBe('Supprimer')
    expect(wrapper.attributes('title')).toBe('Supprimer')
  })

  it('émet click', async () => {
    const wrapper = mountAction()
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toHaveLength(1)
  })

  it("n'émet rien quand l'action est désactivée", async () => {
    const wrapper = mountAction({ disabled: true })
    expect(wrapper.attributes('disabled')).toBeDefined()
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toBeUndefined()
  })

  it('applique un ton dédié au destructif', () => {
    expect(mountAction({ tone: 'danger' }).classes().join(' ')).toContain('hover:text-red-600')
    expect(mountAction({ tone: 'primary' }).classes().join(' ')).toContain('hover:text-indigo-600')
  })
})
