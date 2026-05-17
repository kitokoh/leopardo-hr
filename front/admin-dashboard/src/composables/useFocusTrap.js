import { onMounted, onUnmounted, watch, ref } from 'vue'

/**
 * W2 — Focus trap composable for modals and dialogs
 * Traps focus within a container element when active.
 * Handles Tab, Shift+Tab, and Escape key.
 */
export function useFocusTrap(containerRef, isActive, onEscape) {
  const previousFocus = ref(null)

  const FOCUSABLE_SELECTORS = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
    '[contenteditable="true"]',
  ].join(', ')

  function getFocusableElements() {
    if (!containerRef.value) return []
    return Array.from(containerRef.value.querySelectorAll(FOCUSABLE_SELECTORS))
      .filter(el => el.offsetParent !== null)
  }

  function handleKeydown(e) {
    if (!isActive.value || !containerRef.value) return

    if (e.key === 'Escape') {
      e.preventDefault()
      if (onEscape) onEscape()
      return
    }

    if (e.key !== 'Tab') return

    const focusable = getFocusableElements()
    if (focusable.length === 0) return

    const first = focusable[0]
    const last = focusable[focusable.length - 1]

    if (e.shiftKey) {
      if (document.activeElement === first) {
        e.preventDefault()
        last.focus()
      }
    } else {
      if (document.activeElement === last) {
        e.preventDefault()
        first.focus()
      }
    }
  }

  function activate() {
    previousFocus.value = document.activeElement
    const focusable = getFocusableElements()
    if (focusable.length > 0) {
      requestAnimationFrame(() => focusable[0].focus())
    }
  }

  function deactivate() {
    if (previousFocus.value && typeof previousFocus.value.focus === 'function') {
      previousFocus.value.focus()
    }
  }

  watch(isActive, (val) => {
    if (val) {
      activate()
    } else {
      deactivate()
    }
  })

  onMounted(() => {
    document.addEventListener('keydown', handleKeydown)
    if (isActive.value) activate()
  })

  onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown)
  })

  return { activate, deactivate }
}
