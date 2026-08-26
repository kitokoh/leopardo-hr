<template>
  <Teleport to="body">
    <div
      v-if="open"
      ref="trapRef"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4 outline-none"
      role="dialog"
      aria-modal="true"
      :aria-label="title"
      @click.self="cancel"
      @keydown.escape="cancel"
    >
      <div class="w-full max-w-md glass-card p-6">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ title }}</h3>
        <p v-if="message" class="mt-1 text-sm text-gray-500 dark:text-slate-400">{{ message }}</p>
        <div class="mt-4 flex justify-end gap-2">
          <button class="btn-secondary" @click="cancel">{{ cancelLabel || $t('common.cancel', 'Annuler') }}</button>
          <button class="btn-danger" @click="confirm" :disabled="busy">
            {{ busy ? (busyLabel || $t('common.busy', 'En cours…')) : (confirmLabel || $t('common.confirm', 'Confirmer')) }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
// QA #3937 — remplace window.confirm() (non i18n, bloque le rendu) par un
// dialogue in-app cohérent avec WebhooksView/GrowthDashboardView (#3494/#3493).
import { computed, ref, watch } from 'vue'
import { useFocusTrap } from '@/composables/useFocusTrap'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  message: { type: String, default: '' },
  confirmLabel: { type: String, default: '' },
  cancelLabel: { type: String, default: '' },
  busyLabel: { type: String, default: '' },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['confirm', 'cancel'])

const localOpen = ref(props.open)
watch(() => props.open, (v) => { localOpen.value = v })

// WCAG 2.1.1/2.1.2 (issue #5622) : piéger le focus dans le dialogue.
const { containerRef: trapRef } = useFocusTrap(computed(() => props.open))

function confirm() {
  if (props.busy) return
  emit('confirm')
}

function cancel() {
  emit('cancel')
}
</script>
