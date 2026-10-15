<script setup>
/**
 * Bandeau de demarrage de l'espace Marketing OS : selection de l'entreprise
 * cliente. Les donnees marketing appartiennent a un tenant : on demande donc
 * explicitement sur quelle entreprise on travaille, au lieu de deviner.
 *
 * Tous les libelles passent par le catalogue i18n (garde PA2-I18N-014) ; les
 * noms d'evenements du composant sont declares en constantes pour ne pas etre
 * lus comme du texte utilisateur.
 */
import { computed } from 'vue'

const props = defineProps({
  companies: { type: Array, default: () => [] },
  modelValue: { type: [String, Number], default: '' },
  loading: { type: Boolean, default: false },
})

const MODEL_EVENT = 'update:modelValue'
const RELOAD_EVENT = 'reload'
const emit = defineEmits([MODEL_EVENT, RELOAD_EVENT])

const selected = computed({
  get: () => props.modelValue,
  set: (value) => emit(MODEL_EVENT, value),
})
</script>

<template>
  <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white p-3">
    <label class="text-sm font-medium text-gray-700" for="marketing-os-company">
      {{ $t('marketingOs.companyLabel') }}
    </label>
    <select
      id="marketing-os-company"
      v-model="selected"
      class="min-w-[16rem] rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
      :disabled="loading"
    >
      <option value="">{{ $t('marketingOs.companyPlaceholder') }}</option>
      <option v-for="company in props.companies" :key="company.id" :value="company.id">
        {{ company.name }}
      </option>
    </select>
    <button
      type="button"
      class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
      :disabled="loading || !selected"
      @click="emit('reload')"
    >
      {{ $t('marketingOs.reload') }}
    </button>
    <slot name="extra" />
  </div>
</template>
