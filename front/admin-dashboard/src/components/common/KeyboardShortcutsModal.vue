<template>
  <Teleport to="body">
    <div
      v-if="visible"
      class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm"
      @click.self="visible = false"
      @keydown.escape="visible = false"
    >
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden animate-fade-in">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
          <h2 class="text-lg font-bold text-gray-900 dark:text-white">Raccourcis Clavier</h2>
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
          Appuyez sur <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-700 rounded text-xs font-mono">?</kbd> pour afficher ce menu
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const visible = ref(false)

const shortcuts = [
  { keys: 'Ctrl + D', description: 'Basculer mode sombre' },
  { keys: 'Ctrl + K', description: 'Rechercher' },
  { keys: 'Alt + H', description: 'Tableau de bord' },
  { keys: 'Alt + U', description: 'Utilisateurs' },
  { keys: 'Alt + C', description: 'Entreprises' },
  { keys: 'Alt + S', description: 'Abonnements' },
  { keys: 'Alt + R', description: 'Recrutement' },
  { keys: 'Escape', description: 'Fermer les modales' },
  { keys: '?', description: 'Aide raccourcis' },
]

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
