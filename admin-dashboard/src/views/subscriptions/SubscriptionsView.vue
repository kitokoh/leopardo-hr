<template>
  <div class="space-y-6">
    <div class="bg-white shadow rounded-lg p-6">
      <h1 class="text-2xl font-bold text-gray-900">Abonnements</h1>
      <p class="mt-1 text-sm text-gray-500">
        Pilotage des offres, renouvellements et revenus recurrents.
      </p>
    </div>

    <RevenueForecastWidget />
  </div>
</template>

<script setup>
import RevenueForecastWidget from '@/components/analytics/RevenueForecastWidget.vue'
</script>
