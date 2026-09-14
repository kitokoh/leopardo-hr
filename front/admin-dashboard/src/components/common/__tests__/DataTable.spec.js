import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import DataTable from '@/components/common/DataTable.vue'

/**
 * #7434 — le composant partagé par 19 vues ne doit plus écrire ses libellés
 * en dur : l'en-tête d'actions et le placeholder de recherche passent par
 * l'i18n.
 */
describe('DataTable — libellés i18n (#7434)', () => {
  const baseProps = {
    columns: [{ key: 'name', label: 'Nom' }],
    rows: [{ id: 1, name: 'Ada' }],
  }

  function mountTable(props = {}, slots = {}) {
    return mount(DataTable, {
      props: { ...baseProps, ...props },
      slots,
      global: {
        mocks: { $t: (key, fallback) => `${fallback} <${key}>` },
        stubs: { Pagination: true },
      },
    })
  }

  it('traduit l’en-tête d’actions quand un slot row-actions existe', () => {
    const wrapper = mountTable({}, { 'row-actions': '<button aria-label="x">x</button>' })
    expect(wrapper.text()).toContain('Actions <common.actions>')
  })

  it("n'affiche pas d'en-tête d'actions sans slot row-actions", () => {
    const wrapper = mountTable()
    expect(wrapper.text()).not.toContain('common.actions')
  })

  it('utilise le placeholder i18n par défaut', () => {
    const wrapper = mountTable()
    expect(wrapper.find('input[type="search"]').attributes('placeholder')).toContain('common.search')
  })

  it('respecte un placeholder fourni par la vue', () => {
    const wrapper = mountTable({ searchPlaceholder: 'Rechercher un vol…' })
    expect(wrapper.find('input[type="search"]').attributes('placeholder')).toBe('Rechercher un vol…')
  })
})
