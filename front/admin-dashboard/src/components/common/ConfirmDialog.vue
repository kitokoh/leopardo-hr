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

        <!-- #7475 — confirmation forte : pour une opération destructrice, un
             « êtes-vous sûr ? » ne suffit pas. L'appelant peut exiger la
             ressaisie d'un texte exact (nom de société, identifiant…). Le
             bouton reste désactivé tant que le texte ne correspond pas, donc
             l'action ne peut pas être déclenchée par un simple double-clic. -->
        <div v-if="requireText" class="mt-4">
          <label :for="textFieldId" class="block text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-slate-400">
            {{ requireTextHint || requireText }}
          </label>
          <input
            :id="textFieldId"
            v-model="typedText"
            type="text"
            class="form-input mt-1 w-full"
            :placeholder="requireText"
            autocomplete="off"
            autocapitalize="off"
            spellcheck="false"
            :aria-describedby="`${textFieldId}-hint`"
          />
          <p :id="`${textFieldId}-hint`" class="mt-1 text-xs text-gray-500 dark:text-slate-400">
            {{ requireTextHint }}
          </p>
        </div>

        <div class="mt-4 flex justify-end gap-2">
          <button class="btn-secondary" @click="cancel">{{ cancelLabel || $t('common.cancel', 'Annuler') }}</button>
          <button class="btn-danger" @click="confirm" :disabled="busy || !textSatisfied">
            {{ busy ? (busyLabel || $t('common.busy', 'En cours…')) : (confirmLabel || $t('common.confirm', 'Confirmer')) }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
// QA #3937 — remplace le confirm() natif du navigateur (non i18n, bloquant) par un
// dialogue in-app cohérent avec WebhooksView/GrowthDashboardView (#3494/#3493).
import { computed, ref, useId, watch } from 'vue'
import { useFocusTrap } from '@/composables/useFocusTrap'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  message: { type: String, default: '' },
  confirmLabel: { type: String, default: '' },
  cancelLabel: { type: String, default: '' },
  busyLabel: { type: String, default: '' },
  busy: { type: Boolean, default: false },
  /**
   * #7475 — texte exact à ressaisir pour débloquer la confirmation.
   * Chaîne vide (défaut) = comportement historique inchangé.
   */
  requireText: { type: String, default: '' },
  requireTextHint: { type: String, default: '' },
})

const emit = defineEmits(['confirm', 'cancel'])

const localOpen = ref(props.open)
const typedText = ref('')
const textFieldId = `confirm-text-${useId()}`

const textSatisfied = computed(
  () => props.requireText === '' || typedText.value.trim() === props.requireText,
)

watch(() => props.open, (v) => {
  localOpen.value = v
  // Jamais de texte résiduel d'une ouverture précédente : la confirmation doit
  // être un acte délibéré à chaque fois.
  typedText.value = ''
})

// WCAG 2.1.1/2.1.2 (issue #5622) : piéger le focus dans le dialogue.
const { containerRef: trapRef } = useFocusTrap(computed(() => props.open))

function confirm() {
  if (props.busy || !textSatisfied.value) return
  emit('confirm')
}

function cancel() {
  emit('cancel')
}
</script>
