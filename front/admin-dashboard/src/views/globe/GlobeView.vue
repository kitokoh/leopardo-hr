<template>
  <div class="space-y-6">
    <div class="bg-white shadow rounded-lg p-6">
      <h1 class="text-2xl font-bold text-gray-900">Globe Temps Reel</h1>
      <p class="mt-1 text-sm text-gray-500">
        Visualisation des connexions et evenements actifs par zone geographique.
      </p>
    </div>

    <div class="h-[640px] overflow-hidden rounded-lg bg-slate-950 shadow">
      <MiniGlobe />
    </div>
  </div>
</template>

<script setup>
import MiniGlobe from '@/components/globe/MiniGlobe.vue'
</script>
