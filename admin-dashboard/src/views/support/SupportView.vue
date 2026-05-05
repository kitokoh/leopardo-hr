<template>
  <div class="space-y-6">
    <div class="bg-white shadow rounded-lg p-6">
      <h1 class="text-2xl font-bold text-gray-900">Support</h1>
      <p class="mt-1 text-sm text-gray-500">
        Tickets ouverts, priorites et suivi des demandes clients.
      </p>
    </div>

    <SupportTicketsList />
  </div>
</template>

<script setup>
import SupportTicketsList from '@/components/dashboard/SupportTicketsList.vue'
</script>
