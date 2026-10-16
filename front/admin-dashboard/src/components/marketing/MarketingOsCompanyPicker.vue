<script setup>
/**
 * Bandeau de démarrage de l'espace Marketing OS : sélection de l'entreprise
 * cliente. Les données marketing appartiennent à un tenant : on demande donc
 * explicitement sur quelle entreprise on travaille, au lieu de deviner.
 *
 * Le lien de modèle passe par `defineModel()` (Vue 3.5) : aucune constante de
 * nom d'événement Vue n'est donc écrite en dur, ce qui satisfait la garde
 * PA2-I18N-014 sans ajouter d'exception dans le garde-fou partagé (#7482).
 */
import { defineEmits, defineModel } from 'vue'

const props = defineProps({
  companies: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['reload'])

/** Modèle `v-model` : entreprise cliente sélectionnée. */
const selected = defineModel({ type: [String, Number], default: '' })
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
      :disabled="props.loading"
    >
      <option value="">{{ $t('marketingOs.companyPlaceholder') }}</option>
      <option v-for="company in props.companies" :key="company.id" :value="company.id">
        {{ company.name }}
      </option>
    </select>
    <button
      type="button"
      class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
      :disabled="props.loading || !selected"
      @click="emit('reload')"
    >
      {{ $t('marketingOs.reload') }}
    </button>
    <slot name="extra" />
  </div>
</template>
