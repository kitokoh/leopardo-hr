<template>
  <div class="space-y-6">
    <div class="glass-card p-6">
      <h1 class="text-2xl font-bold text-gray-900">Detail Utilisateur</h1>
      <p class="mt-1 text-sm text-gray-500">
        Vue de synthese du compte utilisateur selectionne.
      </p>
    </div>

    <div class="glass-card p-6">
      <UserDetailModal
        :user="selectedUser"
        :show="true"
        @close="goBack"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UserDetailModal from '@/components/users/UserDetailModal.vue'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()

// QA #2238 : le crash venait de `dashboardStore.users` (store inexistant).
// Le détail est désormais chargé depuis l'API réelle /platform/users/{id}.
const selectedUser = ref(null)

onMounted(async () => {
  const id = Number(route.params.id)
  try {
    const res = await api.get(`/platform/users/${id}`)
    const user = res.data?.data ?? null
    if (user) {
      selectedUser.value = {
        id: user.id,
        name: user.name,
        email: user.email,
        status: user.status,
        createdAt: user.created_at,
        lastLoginAt: user.last_login_at
      }
    }
  } catch (error) {
    console.error('Failed to load user detail:', error)
  }
})

function goBack() {
  router.push('/users')
}
</script>
