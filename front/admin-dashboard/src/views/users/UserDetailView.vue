<template>
  <div class="space-y-6">
    <div class="glass-card p-6">
      <h1 class="text-2xl font-bold text-gray-900">Detail Utilisateur</h1>
      <p class="mt-1 text-sm text-gray-500">
        Vue de synthese du compte utilisateur selectionne.
      </p>
    </div>

    <div class="glass-card p-6">
      <div v-if="isLoading" class="text-sm text-slate-500">
        Chargement...
      </div>
      <div v-else-if="loadError" class="text-sm text-red-600">
        {{ loadError }}
      </div>
      <UserDetailModal
        v-else-if="user"
        :user="user"
        :show="true"
        @close="goBack"
      />
      <div v-else class="text-sm text-slate-500">
        Utilisateur introuvable.
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UserDetailModal from '@/components/users/UserDetailModal.vue'
import api from '@/services/api'

// Issue #2238/#2269 : la fiche lisait `dashboardStore.users` (inexistant →
// TypeError). Elle charge désormais le détail via GET /admin/users/{id}.
const route = useRoute()
const router = useRouter()
const isLoading = ref(true)
const loadError = ref('')
const user = ref(null)

function normalize(row) {
  return {
    id: row.id,
    name: [row.first_name, row.last_name].filter(Boolean).join(' ') || row.email,
    email: row.email,
    status: row.status === 'disabled' ? 'inactive' : row.status,
    rawStatus: row.status,
    role: null,
    segment: null,
    company: row.company ? { id: row.company.id, name: row.company.name, employee_id: row.company.employee_id } : null,
    createdAt: row.created_at,
    lastLoginAt: row.last_login_at,
    avatar: `https://ui-avatars.com/api/?name=${encodeURIComponent([row.first_name, row.last_name].filter(Boolean).join(' '))}&background=0ea5e9&color=fff`
  }
}

onMounted(async () => {
  const id = Number(route.params.id)
  try {
    const res = await api.get(`/v1/admin/users/${id}`)
    user.value = normalize(res.data?.data)
  } catch (error) {
    loadError.value = error?.response?.status === 404
      ? 'Utilisateur introuvable.'
      : "Erreur lors du chargement de l'utilisateur."
  } finally {
    isLoading.value = false
  }
})

function goBack() {
  router.push('/users')
}
</script>
