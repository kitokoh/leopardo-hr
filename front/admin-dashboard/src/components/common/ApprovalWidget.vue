<template>
  <div class="flex items-center gap-2">
    <button
      class="inline-flex items-center gap-1 rounded-md bg-green-50 px-3 py-1.5 text-sm font-medium text-green-700 hover:bg-green-100"
      :disabled="processing"
      @click="handleAction('approve')"
    >
      <CheckIcon class="h-4 w-4" />
      Approuver
    </button>
    <button
      class="inline-flex items-center gap-1 rounded-md bg-red-50 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-100"
      :disabled="processing"
      @click="showRejectModal = true"
    >
      <XMarkIcon class="h-4 w-4" />
      Refuser
    </button>

    <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-600 bg-opacity-50">
      <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
        <h3 class="text-lg font-semibold text-gray-900">Raison du refus</h3>
        <textarea
          v-model="rejectComment"
          rows="3"
          class="mt-3 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
          placeholder="Commentaire obligatoire..."
        />
        <div class="mt-4 flex justify-end gap-2">
          <button class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="showRejectModal = false">
            Annuler
          </button>
          <button
            class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
            :disabled="!rejectComment.trim() || processing"
            @click="handleAction('reject')"
          >
            Confirmer le refus
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { CheckIcon, XMarkIcon } from '@heroicons/vue/24/outline'

defineProps({
  processing: { type: Boolean, default: false }
})

const emit = defineEmits(['approve', 'reject'])

const showRejectModal = ref(false)
const rejectComment = ref('')

function handleAction(action) {
  if (action === 'approve') {
    emit('approve')
  } else {
    emit('reject', rejectComment.value)
    showRejectModal.value = false
    rejectComment.value = ''
  }
}
</script>
