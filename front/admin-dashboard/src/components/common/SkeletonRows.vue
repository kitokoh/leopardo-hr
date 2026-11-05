<template>
  <!--
    SkeletonRows — squelette de chargement de table (audit UX 2026-11).

    Avant : DataTable affichait un simple spinner centré. Un squelette aux
    dimensions des lignes réelles réduit le saut de mise en page perçu et
    donne une meilleure perception de vitesse.
  -->
  <div class="divide-y divide-slate-200/50 dark:divide-slate-800/50" aria-hidden="true">
    <div v-for="row in rows" :key="row" class="flex items-center gap-4 px-6 py-4">
      <div class="h-4 w-4 shrink-0 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
      <div class="h-4 flex-1 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
      <div class="h-4 w-24 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
      <div class="h-4 w-16 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  rows: { type: Number, default: 6 },
})
</script>
