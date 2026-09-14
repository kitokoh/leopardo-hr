import { describe, it, expect } from 'vitest'
import { useConfirmDialog } from '@/composables/useConfirmDialog'

/**
 * #7433 — toute action destructive doit passer par une confirmation in-app.
 * Ce composable remplace `window.confirm` : il doit résoudre sa promesse
 * exactement une fois, avec `true` (Confirmer) ou `false` (Annuler).
 */
describe('useConfirmDialog', () => {
  it("n'est pas ouvert par défaut", () => {
    const dialog = useConfirmDialog()
    expect(dialog.state.open).toBe(false)
    expect(dialog.state.title).toBe('')
  })

  it('ouvre le dialogue et résout true sur confirmation', async () => {
    const dialog = useConfirmDialog()
    const answer = dialog.ask({ title: 'Supprimer ?', message: 'Action irréversible', confirmLabel: 'Supprimer' })

    expect(dialog.state.open).toBe(true)
    expect(dialog.state.title).toBe('Supprimer ?')
    expect(dialog.state.message).toBe('Action irréversible')
    expect(dialog.state.confirmLabel).toBe('Supprimer')

    dialog.resolve(true)
    await expect(answer).resolves.toBe(true)
    expect(dialog.state.open).toBe(false)
  })

  it('résout false sur annulation', async () => {
    const dialog = useConfirmDialog()
    const answer = dialog.ask({ title: 'Supprimer ?' })

    dialog.resolve(false)
    await expect(answer).resolves.toBe(false)
    expect(dialog.state.open).toBe(false)
  })

  it('annule la demande précédente non résolue (pas de promesse orpheline)', async () => {
    const dialog = useConfirmDialog()
    const first = dialog.ask({ title: 'Première ?' })
    const second = dialog.ask({ title: 'Seconde ?' })

    await expect(first).resolves.toBe(false)
    expect(dialog.state.title).toBe('Seconde ?')
    expect(dialog.state.open).toBe(true)

    dialog.resolve(true)
    await expect(second).resolves.toBe(true)
  })

  it('un resolve sans demande en attente ne casse rien', () => {
    const dialog = useConfirmDialog()
    expect(() => dialog.resolve(true)).not.toThrow()
    expect(dialog.state.open).toBe(false)
  })
})
