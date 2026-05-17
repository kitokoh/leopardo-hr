import { onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useThemeStore } from '@/stores/theme'

export function useKeyboardShortcuts() {
  const router = useRouter()
  const themeStore = useThemeStore()

  function handleKeydown(e) {
    // Ignore if user is typing in an input/textarea/select
    const tag = e.target.tagName
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || e.target.isContentEditable) {
      return
    }

    // Ctrl/Cmd + key shortcuts
    if (e.ctrlKey || e.metaKey) {
      switch (e.key) {
        case 'd':
          e.preventDefault()
          themeStore.toggle()
          break
        case 'k':
          e.preventDefault()
          // Focus search bar
          document.getElementById('search')?.focus()
          break
      }
      return
    }

    // Alt + key shortcuts for navigation
    if (e.altKey) {
      switch (e.key) {
        case 'h':
          e.preventDefault()
          router.push('/')
          break
        case 'u':
          e.preventDefault()
          router.push('/users')
          break
        case 'c':
          e.preventDefault()
          router.push('/companies')
          break
        case 's':
          e.preventDefault()
          router.push('/subscriptions')
          break
        case 'r':
          e.preventDefault()
          router.push('/recruitment')
          break
      }
      return
    }

    // ? key to show shortcuts help
    if (e.key === '?') {
      e.preventDefault()
      const event = new CustomEvent('show-shortcuts-help')
      window.dispatchEvent(event)
    }
  }

  onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
  })

  onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
  })

  return {
    shortcuts: [
      { keys: 'Ctrl+D', description: 'Basculer mode sombre' },
      { keys: 'Ctrl+K', description: 'Rechercher' },
      { keys: 'Alt+H', description: 'Tableau de bord' },
      { keys: 'Alt+U', description: 'Utilisateurs' },
      { keys: 'Alt+C', description: 'Entreprises' },
      { keys: 'Alt+S', description: 'Abonnements' },
      { keys: 'Alt+R', description: 'Recrutement' },
      { keys: '?', description: 'Aide raccourcis' },
    ],
  }
}
