<template>
  <div class="space-y-3">
    <div
      v-for="backup in backups"
      :key="backup.id"
      class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50"
    >
      <div class="flex-1 min-w-0">
        <div class="flex items-center space-x-2">
          <CloudArrowDownIcon class="h-5 w-5 text-gray-400 flex-shrink-0" />
          <div class="flex-1">
            <p class="text-sm font-medium text-gray-900">{{ backup.name }}</p>
            <div class="flex items-center space-x-3 mt-1 text-xs text-gray-500">
              <span>{{ backup.size }}</span>
              <span>•</span>
              <span>{{ formatDate(backup.createdAt) }}</span>
              <span>•</span>
              <span class="capitalize">{{ backup.type }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="flex items-center space-x-3 ml-4">
        <!-- Status badge -->
        <span
          :class="[
            'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium',
            backup.status === 'completed' ? 'bg-green-100 text-green-800' :
            backup.status === 'failed' ? 'bg-red-100 text-red-800' :
            'bg-yellow-100 text-yellow-800'
          ]"
        >
          {{ getStatusLabel(backup.status) }}
        </span>

        <!-- Actions -->
        <button
          @click="$emit('restore', backup)"
          class="p-1 text-gray-400 hover:text-indigo-600"
          title="Restaurer"
        >
          <ArrowUturnLeftIcon class="h-4 w-4" />
        </button>
        <button
          @click="$emit('download', backup)"
          class="p-1 text-gray-400 hover:text-blue-600"
          title="Télécharger"
        >
          <ArrowDownTrayIcon class="h-4 w-4" />
        </button>
        <button
          @click="$emit('delete', backup.id)"
          class="p-1 text-gray-400 hover:text-red-600"
          title="Supprimer"
        >
          <TrashIcon class="h-4 w-4" />
        </button>
      </div>
    </div>

    <!-- Empty state -->
    <div v-if="backups.length === 0" class="text-center py-6">
      <CloudArrowDownIcon class="mx-auto h-8 w-8 text-gray-400" />
      <p class="mt-2 text-sm text-gray-500">Aucune sauvegarde disponible</p>
    </div>
  </div>
</template>

<script setup>
import {
  CloudArrowDownIcon,
  ArrowUturnLeftIcon,
  ArrowDownTrayIcon,
  TrashIcon
} from '@heroicons/vue/24/outline'

defineProps({
  backups: {
    type: Array,
    default: () => []
  }
})

defineEmits(['restore', 'download', 'delete'])

function getStatusLabel(status) {
  const labels = {
    completed: 'Complétée',
    failed: 'Erreur',
    pending: 'En attente',
    running: 'En cours'
  }
  return labels[status] || status
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>