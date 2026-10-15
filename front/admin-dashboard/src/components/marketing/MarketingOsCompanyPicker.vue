<script setup>
/**
 * Bandeau de démarrage de l'espace Marketing OS : sélection de l'entreprise
 * cliente. Les données marketing appartiennent à un tenANT : on demande donc
 * explicitement sur quelle entreprise on travaille, au lieu de deviner.
 */
import { computed } from 'vue'

const props = defineProps({
  companies: { type: Array, default: () => [] },
  modelValue: { type: [String, Number], default: '' },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'reload'])

const selected = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})
</script>

<template>
  <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white p-3">
    <label class="text-sm font-medium text-gray-700" for="marketing-os-company">
      Entreprise cliente
    </label>
    <select
      id="marketing-os-company"
      v-model="selected"
      class="min-w-[16rem] rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
      :disabled="loading"
    >
      <option value="">— Sélectionner une entreprise —</option>
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
      Recharger
    </button>
    <slot name="extra" />
  </div>
</template>
