/**
 * useConfirmDialog — confirmation destructive in-app (issue #7433).
 *
 * Toute action destructive (suppression, désactivation massive, …) doit passer
 * par ce composable + `ConfirmDialog` : jamais `window.confirm` (non i18n,
 * bloque le rendu, non stylable — garde `check-admin-destructive-actions`).
 *
 * Usage :
 *
 *   const confirm = useConfirmDialog()
 *   async function onDelete(row) {
 *     const ok = await confirm.ask({
 *       title: t('travel.common.confirmDeleteTitle', 'Supprimer cet élément ?'),
 *     })
 *     if (!ok) return
 *     ...
 *   }
 *
 *   <ConfirmDialog
 *     :open="confirm.state.open"
 *     :title="confirm.state.title"
 *     :message="confirm.state.message"
 *     :confirm-label="confirm.state.confirmLabel"
 *     @confirm="confirm.resolve(true)"
 *     @cancel="confirm.resolve(false)"
 *   />
 */
import { reactive } from 'vue'

export function useConfirmDialog() {
  const state = reactive({
    open: false,
    title: '',
    message: '',
    confirmLabel: '',
  })

  let settle = null

  function ask({ title = '', message = '', confirmLabel = '' } = {}) {
    // Une demande précédente non résolue est annulée (pas de promesse orpheline).
    close(false)
    state.title = title
    state.message = message
    state.confirmLabel = confirmLabel
    state.open = true

    return new Promise((resolve) => {
      settle = resolve
    })
  }

  function close(answer) {
    const pending = settle
    settle = null
    state.open = false
    if (pending) {
      pending(answer)
    }
  }

  return {
    state,
    ask,
    resolve: (answer) => close(Boolean(answer)),
  }
}
