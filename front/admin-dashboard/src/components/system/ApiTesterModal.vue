<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30">
    <div class="rounded-lg bg-white p-6 shadow-xl">
      <h3 class="text-lg font-semibold text-gray-900">Testeur API</h3>
      <button class="mt-4 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white" @click="$emit('close')">Fermer</button>
    </div>
  </div>
</template>

<script setup>
defineProps({ show: Boolean })
defineEmits(['close'])
</script>
