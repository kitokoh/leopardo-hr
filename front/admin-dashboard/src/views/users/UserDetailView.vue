<template>
  <div class="space-y-6">
    <div class="glass-card p-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Detail Utilisateur</h1>
          <p class="mt-1 text-sm text-gray-500">
            Vue de synthese du compte utilisateur selectionne.
          </p>
        </div>
        <button class="btn-secondary py-2.5" @click="goBack">Retour</button>
      </div>
    </div>

    <div class="glass-card p-6">
      <div v-if="isLoading" class="flex h-48 items-center justify-center">
        <div class="h-8 w-8 animate-spin rounded-full border-4 border-brand-500 border-t-transparent"></div>
      </div>

      <div v-else-if="errorMessage" class="rounded-2xl border border-red-200 bg-red-50 p-8 text-center dark:border-red-900/30 dark:bg-red-950/20">
        <h3 class="text-lg font-bold text-red-800 dark:text-red-400">{{ errorMessage }}</h3>
        <button class="btn-primary mt-6" @click="loadUser">Reessayer</button>
      </div>

      <div v-else-if="selectedUser">
        <UserDetailModal
          :user="selectedUser"
          :show="true"
          @close="goBack"
        />
      </div>

      <div v-else class="rounded-2xl border border-slate-200 p-8 text-center dark:border-slate-800">
        <p class="text-sm font-bold text-slate-500">Utilisateur introuvable.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import UserDetailModal from '@/components/users/UserDetailModal.vue'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()
const toast = useToast()

// Issue #2269 : détail réel via GET /admin/users/{id} — plus de dépendance
// au store mock (le crash « dashboardStore.users » est éliminé).
const selectedUser = ref(null)
const isLoading = ref(false)
const errorMessage = ref('')

onMounted(loadUser)

async function loadUser() {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const id = Number(route.params.id)
    if (!Number.isInteger(id) || id <= 0) {
      errorMessage.value = 'Identifiant utilisateur invalide.'
      return
    }

    const response = await api.get(`/admin/users/${id}`)
    const user = response.data?.data
    if (!user) {
      errorMessage.value = 'Utilisateur introuvable.'
      return
    }

    selectedUser.value = {
      ...user,
      avatar: null,
      status: user.is_active ? 'active' : 'inactive',
      role: user.roles?.[0]?.role || null,
      segment: null,
      lastLoginAt: user.last_login_at,
      createdAt: user.created_at
    }
  } catch (error) {
    console.error('Failed to load user detail:', error)
    errorMessage.value = error?.response?.status === 404
      ? 'Utilisateur introuvable.'
      : 'Impossible de charger le detail utilisateur.'
    if (error?.response?.status === 404) {
      toast.error('Utilisateur introuvable.')
    }
  } finally {
    isLoading.value = false
  }
}

function goBack() {
  router.push('/users')
}
</script>
