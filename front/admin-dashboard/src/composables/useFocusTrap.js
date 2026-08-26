import { onBeforeUnmount, ref, watch, type Ref } from 'vue'

/**
 * useFocusTrap — WCAG 2.1.1/2.1.2 (audit WCAG 2026-05, issue #5622).
 *
 * Piège le focus clavier à l'intérieur d'un modal/dialogue : Tab/Shift+Tab
 * cyclent entre les éléments focusables du conteneur, le focus est déplacé
 * dans le dialogue à l'ouverture et restauré sur l'élément précédent à la
 * fermeture. `Escape` reste géré par le composant appelant.
 *
 * Usage :
 *   const open = ref(false)
 *   const { containerRef } = useFocusTrap(open)
 *   <div ref="containerRef" v-if="open" ...>...</div>
 *
 * @param isActive Ref<boolean> — état d'ouverture du dialogue.
 */
export function useFocusTrap(isActive: Ref<boolean>) {
  const containerRef = ref<HTMLElement | null>(null)
  let previouslyFocused: HTMLElement | null = null

  const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'textarea:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
  ].join(', ')

  function getFocusable(): HTMLElement[] {
    const el = containerRef.value
    if (!el) return []
    return Array.from(el.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR)).filter(
      (node) => node.offsetParent !== null || node === document.activeElement,
    )
  }

  function onKeydown(e: KeyboardEvent) {
    if (e.key !== 'Tab') return

    const items = getFocusable()
    if (items.length === 0) {
      e.preventDefault()
      return
    }

    const first = items[0]
    const last = items[items.length - 1]

    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault()
      last.focus()
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault()
      first.focus()
    }
  }

  function activate() {
    previouslyFocused = document.activeElement instanceof HTMLElement
      ? document.activeElement
      : null

    const el = containerRef.value
    if (!el) return

    el.setAttribute('tabindex', '-1')
    el.addEventListener('keydown', onKeydown)

    // Focus initial : premier élément focusable, sinon le conteneur.
    const items = getFocusable()
    ;(items[0] ?? el).focus()
  }

  function deactivate() {
    const el = containerRef.value
    el?.removeEventListener('keydown', onKeydown)
    previouslyFocused?.focus?.()
    previouslyFocused = null
  }

  watch(
    isActive,
    (active) => {
      if (active) {
        // nextTick : le v-if a rendu le conteneur avant de piéger le focus.
        requestAnimationFrame(activate)
      } else {
        deactivate()
      }
    },
    { immediate: true },
  )

  onBeforeUnmount(() => {
    if (isActive.value) deactivate()
  })

  return { containerRef }
}
