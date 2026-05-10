<template>
  <div class="space-y-3">
    <div
      v-for="task in tasks"
      :key="task.id"
      class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50"
    >
      <div class="flex-1 min-w-0">
        <div class="flex items-center space-x-2">
          <input
            type="checkbox"
            :checked="task.enabled"
            @change="$emit('toggle', task.id)"
            class="h-4 w-4 rounded border-gray-300 text-indigo-600"
          />
          <div class="flex-1">
            <p class="text-sm font-medium text-gray-900">{{ task.name }}</p>
            <p class="text-xs text-gray-500">{{ task.description }}</p>
            <div class="flex items-center space-x-3 mt-1 text-xs text-gray-500">
              <span>{{ task.schedule }}</span>
              <span>•</span>
              <span>Prochaine: {{ formatDate(task.nextRun) }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="flex items-center space-x-3 ml-4">
        <!-- Status badge -->
        <span
          :class="[
            'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium',
            task.status === 'success' ? 'bg-green-100 text-green-800' :
            task.status === 'failed' ? 'bg-red-100 text-red-800' :
            'bg-yellow-100 text-yellow-800'
          ]"
        >
          {{ getStatusLabel(task.status) }}
        </span>

        <!-- Actions -->
        <button
          @click="$emit('edit', task)"
          class="p-1 text-gray-400 hover:text-gray-600"
          title="Modifier"
        >
          <PencilIcon class="h-4 w-4" />
        </button>
        <button
          @click="$emit('delete', task.id)"
          class="p-1 text-gray-400 hover:text-red-600"
          title="Supprimer"
        >
          <TrashIcon class="h-4 w-4" />
        </button>
      </div>
    </div>

    <!-- Empty state -->
    <div v-if="tasks.length === 0" class="text-center py-6">
      <CogIcon class="mx-auto h-8 w-8 text-gray-400" />
      <p class="mt-2 text-sm text-gray-500">Aucune tâche automatisée</p>
    </div>
  </div>
</template>

<script setup>
import { PencilIcon, TrashIcon, CogIcon } from '@heroicons/vue/24/outline'

defineProps({
  tasks: {
    type: Array,
    default: () => []
  }
})

defineEmits(['toggle', 'edit', 'delete'])

function getStatusLabel(status) {
  const labels = {
    success: 'Succès',
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
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>