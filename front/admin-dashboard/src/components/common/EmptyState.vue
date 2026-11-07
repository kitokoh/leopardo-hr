<template>
  <!--
    EmptyState — état « vide » réutilisable (audit UX 2026-11).

    Avant : chaque vue réinventait son message vide (ou n'en avait aucun, la
    table restant blanche). Ce composant donne un état vide homogène, avec
    icône, titre, description et action optionnelle.
  -->
  <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800">
      <component :is="icon" v-if="icon" class="h-6 w-6 text-slate-500 dark:text-slate-400" aria-hidden="true" />
      <InboxIcon v-else class="h-6 w-6 text-slate-500 dark:text-slate-400" aria-hidden="true" />
    </div>
    <h3 class="mt-4 text-sm font-bold text-slate-900 dark:text-white">
      {{ title || $t('common.emptyTitle', 'Aucun élément') }}
    </h3>
    <p v-if="description" class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">
      {{ description }}
    </p>
    <slot name="action" />
  </div>
</template>

<script setup>
import { InboxIcon } from '@heroicons/vue/24/outline'

defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  icon: { type: [Object, Function], default: null },
})
</script>
