<template>
  <div class="space-y-6">
    <div class="bg-white shadow rounded-lg p-6">
      <h1 class="text-2xl font-bold text-gray-900">Detail Utilisateur</h1>
      <p class="mt-1 text-sm text-gray-500">
        Vue de synthese du compte utilisateur selectionne.
      </p>
    </div>

    <div class="bg-white shadow rounded-lg p-6">
      <UserDetailModal
        :user="selectedUser"
        :show="true"
        @close="goBack"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UserDetailModal from '@/components/users/UserDetailModal.vue'
import { useDashboardStore } from '@/stores/dashboard'

const route = useRoute()
const router = useRouter()
const dashboardStore = useDashboardStore()

const selectedUser = computed(() => {
  const id = Number(route.params.id)
  return dashboardStore.users.find(user => user.id === id) || dashboardStore.users[0] || null
})

function goBack() {
  router.push('/users')
}
</script>
