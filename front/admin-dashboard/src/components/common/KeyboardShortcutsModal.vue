<template>
  <Teleport to="body">
    <div
      v-if="visible"
      ref="trapRef"
      class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm outline-none"
      role="dialog"
      aria-modal="true"
      :aria-label="t('adminShortcuts.title', 'Raccourcis Clavier')"
      @click.self="visible = false"
      @keydown.escape="visible = false"
    >
      <div class="glass-card max-w-md w-full mx-4 overflow-hidden animate-fade-in">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
          <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('adminShortcuts.title', 'Raccourcis Clavier') }}</h2>
          <button
            @click="visible = false"
            class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            <XMarkIcon class="h-5 w-5" />
          </button>
        </div>

        <div class="px-6 py-4 space-y-3 max-h-80 overflow-y-auto">
          <div
            v-for="shortcut in shortcuts"
            :key="shortcut.keys"
            class="flex items-center justify-between py-1.5"
          >
            <span class="text-sm text-gray-600 dark:text-gray-300">{{ shortcut.description }}</span>

            <kbd class="inline-flex items-center gap-1 px-2 py-1 text-xs font-mono font-semibold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-md">
              {{ shortcut.keys }}
            </kbd>
          </div>
        </div>

        <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900 text-xs text-gray-500 dark:text-gray-400 text-center">
          {{ t('adminShortcuts.footerPress', 'Appuyez sur') }} <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-700 rounded text-xs font-mono">?</kbd> {{ t('adminShortcuts.footerToShow', 'pour afficher ce menu') }}
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useFocusTrap } from '@/composables/useFocusTrap'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'

// #5628 — i18n : libellés via adminShortcuts.* (fallback FR). Les touches
// (Ctrl+D, Alt+H…) restent techniques et non traduites (règle #2755).
const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const visible = ref(false)
const { containerRef: trapRef } = useFocusTrap(visible)

const shortcuts = computed(() =>
  [
    { keys: 'Ctrl + D', descKey: 'descToggleDark' },
    { keys: 'Ctrl + K', descKey: 'descSearch' },
    { keys: 'Alt + H', descKey: 'descDashboard' },
    { keys: 'Alt + U', descKey: 'descUsers' },
    { keys: 'Alt + C', descKey: 'descCompanies' },
    { keys: 'Alt + S', descKey: 'descSubscriptions' },
    { keys: 'Escape', descKey: 'descCloseModals' },
    { keys: '?', descKey: 'descHelp' },
  ].map((s) => ({ ...s, description: t(`adminShortcuts.${s.descKey}`) }))
)

function onShowHelp() {
  visible.value = true
}

onMounted(() => {
  window.addEventListener('show-shortcuts-help', onShowHelp)
})

onUnmounted(() => {
  window.removeEventListener('show-shortcuts-help', onShowHelp)
})
</script>
