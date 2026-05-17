import { ref, onMounted, onUnmounted } from 'vue'

/**
 * W4 — Live region announcer for screen readers.
 * Creates a visually hidden aria-live region to announce dynamic changes.
 */
export function useAnnouncer() {
  const message = ref('')
  let el = null

  onMounted(() => {
    el = document.createElement('div')
    el.setAttribute('role', 'status')
    el.setAttribute('aria-live', 'polite')
    el.setAttribute('aria-atomic', 'true')
    el.className = 'sr-only'
    document.body.appendChild(el)
  })

  onUnmounted(() => {
    if (el && el.parentNode) {
      el.parentNode.removeChild(el)
    }
  })

  function announce(text, priority = 'polite') {
    message.value = text
    if (el) {
      el.setAttribute('aria-live', priority)
      el.textContent = ''
      requestAnimationFrame(() => {
        if (el) el.textContent = text
      })
    }
  }

  function announceAssertive(text) {
    announce(text, 'assertive')
  }

  return { message, announce, announceAssertive }
}
