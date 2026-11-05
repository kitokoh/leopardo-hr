<template>
  <!--
    ErrorState — état d'erreur réutilisable (audit UX 2026-11).

    Avant : DataTable affichait l'erreur en texte rouge nu, sans reprise
    possible. Ce composant propose un message clair + un bouton « Réessayer ».
  -->
  <div role="alert" class="flex flex-col items-center justify-center px-6 py-12 text-center">
    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 dark:bg-red-950/40">
      <ExclamationTriangleIcon class="h-6 w-6 text-red-600 dark:text-red-400" aria-hidden="true" />
    </div>
    <h3 class="mt-4 text-sm font-bold text-slate-900 dark:text-white">
      {{ title || $t('common.errorTitle', 'Une erreur est survenue') }}
    </h3>
    <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">
      {{ message || $t('common.errorBody', 'Les données n’ont pas pu être chargées.') }}
    </p>
    <button
      v-if="retryable"
      type="button"
      class="btn-secondary mt-4"
      @click="$emit('retry')"
    >
      {{ $t('common.retry', 'Réessayer') }}
    </button>
  </div>
</template>

<script setup>
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

defineProps({
  title: { type: String, default: '' },
  message: { type: String, default: '' },
  retryable: { type: Boolean, default: true },
})

defineEmits(['retry'])
</script>
