<template>
  <div
    v-if="open"
    class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4"
    role="dialog"
    aria-modal="true"
    :aria-label="title"
  >
    <div class="w-full max-w-lg rounded-2xl glass-card p-6 shadow-premium">
      <div class="flex items-start justify-between">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ title }}</h3>
        <button
          class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800"
          :aria-label="t('travel.common.close', 'Fermer')"
          @click="$emit('cancel')"
        >
          <XMarkIcon class="h-5 w-5" />
        </button>
      </div>

      <form class="mt-4 space-y-4" novalidate @submit.prevent="$emit('save', form)">
        <div v-for="field in fields" :key="field.key" class="space-y-1">
          <FormField :id="`field-${field.key}`" :label="fieldLabel(field)" :required="field.required" :error="fieldError(field.key)">
            <template v-if="field.type === 'select'">
              <select
                :id="`field-${field.key}`"
                :value="formValue(field.key)"
                class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                :required="field.required"
                @change="formValue(field.key, $event.target.value)"
              >
                <option value="">{{ t('travel.common.selectPlaceholder', '— Sélectionner —') }}</option>
                <option
                  v-for="option in resolveOptions(field)"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option>
              </select>
            </template>

            <template v-else-if="field.type === 'checkbox'">
              <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input
                  :id="`field-${field.key}`"
                  :checked="formValue(field.key)"
                  type="checkbox"
                  @change="formValue(field.key, $event.target.checked)"
                  class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                />
                <span>{{ fieldLabel(field) }}</span>
              </label>
            </template>

            <template v-else-if="field.type === 'textarea'">
              <textarea
                :id="`field-${field.key}`"
                :value="formValue(field.key)"
                :rows="field.rows || 3"
                @input="formValue(field.key, $event.target.value)"
                :required="field.required"
                :maxlength="field.max"
                class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
              ></textarea>
            </template>

            <template v-else>
              <input
                :id="`field-${field.key}`"
                :value="formValue(field.key)"
                :type="field.type === 'number' ? 'number' : 'text'"
                @input="formValue(field.key, $event.target.value)"
                :required="field.required"
                :min="field.min"
                :max="field.max"
                :maxlength="field.max"
                :placeholder="field.placeholder"
                class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
              />
            </template>
          </FormField>
        </div>

        <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-400" role="alert">
          {{ error }}
        </p>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="$emit('cancel')">
            {{ t('travel.common.cancel', 'Annuler') }}
          </button>
          <button type="submit" class="btn-primary" :disabled="busy">
            {{ busy ? t('travel.common.saving', 'Enregistrement…') : t('travel.common.save', 'Enregistrer') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, watch } from 'vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import FormField from '@/components/common/FormField.vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

/**
 * Modal de formulaire générique de la verticale TravelAgency.
 *
 * Props :
 * - open: booléen d'ouverture.
 * - title: titre du modal.
 * - fields: schéma [{ key, label, type: text|number|select|checkbox|textarea,
 *   required, options (array|fn), min, max, maxlength, placeholder }].
 * - values: valeurs initiales (réinitialise le formulaire à l'ouverture).
 * - busy: désactive la sauvegarde pendant l'appel API.
 * - error: message d'erreur global.
 *
 * Événements : save(values) / cancel.
 */
const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  fields: { type: Array, default: () => [] },
  values: { type: Object, default: () => ({}) },
  busy: { type: Boolean, default: false },
  error: { type: String, default: '' }
})

defineEmits(['save', 'cancel'])

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const form = reactive({})
const fieldErrors = reactive({})

function resetForm() {
  for (const key of Object.keys(form)) {
    delete form[key]
  }
  for (const key of Object.keys(fieldErrors)) {
    delete fieldErrors[key]
  }
  for (const field of props.fields) {
    const value = props.values[field.key]
    form[field.key] = value === undefined || value === null
      ? (field.type === 'checkbox' ? false : '')
      : value
  }
}

function fieldLabel(field) {
  return t(field.label, field.label)
}

/** Lecture/écriture `form[key]` via méthode (garde PA2-I18N-014 : pas
 *  d'accès par crochets dans les templates). */
function formValue(key, value) {
  if (arguments.length > 1) {
    form[key] = value
    return value
  }
  return form[key]
}

/** Lecture `fieldErrors[key]` via méthode (même garde). */
function fieldError(key) {
  return fieldErrors[key] || ''
}

function resolveOptions(field) {
  let options = field.options
  // Dépaquète une ref computed (options: computedRef) passée telle quelle.
  if (options && typeof options === 'object' && 'value' in options) {
    options = options.value
  }
  return typeof options === 'function' ? options() : (options || [])
}

watch(
  () => props.open,
  (open) => {
    if (open) resetForm()
  },
  { immediate: true }
)
</script>
