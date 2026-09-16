import { onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useThemeStore } from '@/stores/theme'
import { useAuthStore } from '@/stores/auth'
// #7554 — les raccourcis de navigation sont dérivés de la source de vérité
// unique de navigation (plus de liste de chemins recopiée ici).
import { navShortcuts } from '@/navigation/navigation.js'

const NAV_SHORTCUTS = navShortcuts()

export function useKeyboardShortcuts() {
  const router = useRouter()
  const themeStore = useThemeStore()
  const authStore = useAuthStore()

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
        // #3865 : Ctrl+K est possédé par CommandPalette (toggle palette) —
        // le double-binding précédent (focus #search ici) ouvrait la palette
        // ET focusait la recherche en même temps.
      }
      return
    }

    // Alt + key shortcuts for navigation (#7554 : table dérivée de
    // `src/navigation/navigation.js`, donc plus de chemins en dur ici).
    if (e.altKey) {
      const target = NAV_SHORTCUTS.find(
        (shortcut) => shortcut.key.toLowerCase() === e.key.toLowerCase()
      )
      if (target) {
        e.preventDefault()
        // Un raccourci ne doit pas ouvrir un écran que le compte courant n'a
        // pas le droit de consulter (même filtre que le menu et la palette).
        if (authStore.hasPermission(target.permission)) {
          router.push(target.path)
        }
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
    shortcuts: KEYBOARD_SHORTCUTS,
  }
}

// #3275/#7554 : raccourcis de navigation dérivés de la source de vérité
// (`navShortcuts()`) — le libellé affichable se compose en `shortcut.label`,
// la description reste une clé de catalogue (`titleKey`) traduite à
// l'affichage, plus une liste parallèle de textes français.
export const KEYBOARD_SHORTCUTS = NAV_SHORTCUTS.map((shortcut) => ({
  keys: shortcut.label,
  titleKey: shortcut.titleKey,
  path: shortcut.path,
}))
