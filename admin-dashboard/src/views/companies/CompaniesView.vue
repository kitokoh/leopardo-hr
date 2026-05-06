<template>
  <div class="space-y-6">
    <div class="bg-white shadow rounded-lg p-6">
      <h1 class="text-2xl font-bold text-gray-900">Entreprises</h1>
      <p class="mt-1 text-sm text-gray-500">
        Suivi des organisations clientes et de leur activite.
      </p>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <StatsCard title="Entreprises actives" value="128" change="+8%" change-type="increase" icon="BuildingOfficeIcon" color="blue" />
      <StatsCard title="Nouveaux dossiers" value="14" change="+3" change-type="increase" icon="PlusIcon" color="green" />
      <StatsCard title="A surveiller" value="6" change="-2" change-type="decrease" icon="ExclamationTriangleIcon" color="yellow" />
    </div>
  </div>
</template>

<script setup>
import StatsCard from '@/components/dashboard/StatsCard.vue'
</script>
