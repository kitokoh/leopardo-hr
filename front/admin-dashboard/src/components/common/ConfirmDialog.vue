<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4"
      @click.self="cancel"
    >
      <div class="w-full max-w-md glass-card p-6">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ title }}</h3>
        <p v-if="message" class="mt-1 text-sm text-gray-500 dark:text-slate-400">{{ message }}</p>
        <div class="mt-4 flex justify-end gap-2">
          <button class="btn-secondary" @click="cancel">{{ cancelLabel }}</button>
          <button class="btn-danger" @click="confirm" :disabled="busy">
            {{ busy ? busyLabel : confirmLabel }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
// QA #3937 — remplace window.confirm() (non i18n, bloque le rendu) par un
// dialogue in-app cohérent avec WebhooksView/GrowthDashboardView (#3494/#3493).
import { ref, watch } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  message: { type: String, default: '' },
  confirmLabel: { type: String, default: 'Confirmer' },
  cancelLabel: { type: String, default: 'Annuler' },
  busyLabel: { type: String, default: 'En cours…' },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['confirm', 'cancel'])

const localOpen = ref(props.open)
watch(() => props.open, (v) => { localOpen.value = v })

function confirm() {
  if (props.busy) return
  emit('confirm')
}

function cancel() {
  emit('cancel')
}
</script>
