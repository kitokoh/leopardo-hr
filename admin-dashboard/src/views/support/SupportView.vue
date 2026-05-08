<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Demandes clients</h1>
        <p class="mt-1 text-sm text-gray-500">
          Intake commercial, qualification et validation des nouvelles entreprises.
        </p>
      </div>
      <button class="btn-secondary" :disabled="isLoading" @click="loadRequests">
        Actualiser
      </button>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
      <StatsCard title="Total demandes" :value="totalRequests" icon="ChatBubbleLeftRightIcon" color="blue" />
      <StatsCard title="A traiter" :value="statusCounts.pending" icon="ChartBarIcon" color="yellow" />
      <StatsCard title="Approuvees" :value="statusCounts.approved" icon="BuildingOfficeIcon" color="green" />
      <StatsCard title="Rejetees" :value="statusCounts.rejected" icon="CreditCardIcon" color="red" />
    </div>

    <div class="rounded-lg bg-white shadow">
      <div class="flex flex-col gap-4 border-b border-gray-200 px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-gray-900">File de qualification</h2>
          <p class="text-sm text-gray-500">Les demandes en attente restent en haut pour accelerer le suivi commercial.</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="option in filters"
            :key="option.value"
            :class="activeStatus === option.value ? 'btn-primary' : 'btn-secondary'"
            @click="setFilter(option.value)"
          >
            {{ option.label }}
          </button>
        </div>
      </div>

      <div v-if="isLoading" class="p-6 text-sm text-gray-500">Chargement des demandes...</div>
      <div v-else-if="errorMessage" class="p-6 text-sm text-red-600">{{ errorMessage }}</div>
      <div v-else-if="requests.length === 0" class="p-6 text-sm text-gray-500">
        Aucune demande pour ce filtre.
      </div>
      <div v-else class="divide-y divide-gray-200">
        <article v-for="request in sortedRequests" :key="request.id" class="p-6">
          <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-3">
                <h3 class="text-lg font-semibold text-gray-900">{{ request.company_name }}</h3>
                <span :class="statusClass(request.status)">{{ statusLabel(request.status) }}</span>
              </div>
              <p class="mt-1 text-sm text-gray-500">
                {{ request.sector || 'Secteur non precise' }} · {{ request.city || 'Ville inconnue' }}, {{ request.country || 'Pays inconnu' }}
              </p>
              <p class="mt-3 max-w-4xl text-sm text-gray-700">
                {{ request.description || 'Aucune description fournie.' }}
              </p>
              <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                <div>
                  <dt class="font-medium text-gray-500">Contact</dt>
                  <dd class="mt-1 text-gray-900">{{ request.email || request.user?.email || 'Non renseigne' }}</dd>
                </div>
                <div>
                  <dt class="font-medium text-gray-500">Telephone</dt>
                  <dd class="mt-1 text-gray-900">{{ request.phone || 'Non renseigne' }}</dd>
                </div>
                <div>
                  <dt class="font-medium text-gray-500">Cree le</dt>
                  <dd class="mt-1 text-gray-900">{{ formatDate(request.created_at) }}</dd>
                </div>
              </dl>
            </div>

            <div class="w-full space-y-3 xl:w-80">
              <textarea
                v-model="notesByRequest[request.id]"
                :disabled="request.status !== 'pending' || savingId === request.id"
                rows="3"
                class="block w-full rounded-md border-gray-300 text-sm shadow-sm"
                placeholder="Notes internes avant decision"
              ></textarea>
              <div v-if="request.status === 'pending'" class="grid grid-cols-2 gap-2">
                <button class="btn-secondary justify-center" :disabled="savingId === request.id" @click="reviewRequest(request, 'rejected')">
                  Rejeter
                </button>
                <button class="btn-primary justify-center" :disabled="savingId === request.id" @click="reviewRequest(request, 'approved')">
                  Approuver
                </button>
              </div>
              <p v-else class="text-xs text-gray-500">
                Traitee {{ formatDate(request.reviewed_at) }}
              </p>
            </div>
          </div>
        </article>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'

const toast = useToast()
const isLoading = ref(false)
const errorMessage = ref('')
const savingId = ref(null)
const activeStatus = ref('all')
const requests = ref([])
const meta = ref({ total: 0, current_page: 1, last_page: 1 })
const notesByRequest = reactive({})
const statusCounts = ref({ pending: 0, approved: 0, rejected: 0 })

const filters = [
  { value: 'all', label: 'Toutes' },
  { value: 'pending', label: 'A traiter' },
  { value: 'approved', label: 'Approuvees' },
  { value: 'rejected', label: 'Rejetees' },
]

const sortedRequests = computed(() => {
  const rank = { pending: 0, approved: 1, rejected: 2 }
  return [...requests.value].sort((a, b) => (rank[a.status] ?? 3) - (rank[b.status] ?? 3))
})
const totalRequests = computed(() => (
  statusCounts.value.pending + statusCounts.value.approved + statusCounts.value.rejected
))

async function loadRequests() {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const params = activeStatus.value === 'all' ? {} : { status: activeStatus.value }
    const [currentResponse, pendingResponse, approvedResponse, rejectedResponse] = await Promise.all([
      api.get('/platform/company-requests', { params }),
      api.get('/platform/company-requests', { params: { status: 'pending' } }),
      api.get('/platform/company-requests', { params: { status: 'approved' } }),
      api.get('/platform/company-requests', { params: { status: 'rejected' } }),
    ])

    requests.value = currentResponse.data?.data || []
    meta.value = currentResponse.data?.meta || meta.value
    statusCounts.value = {
      pending: pendingResponse.data?.meta?.total || 0,
      approved: approvedResponse.data?.meta?.total || 0,
      rejected: rejectedResponse.data?.meta?.total || 0,
    }

    requests.value.forEach((request) => {
      notesByRequest[request.id] = request.admin_notes || notesByRequest[request.id] || ''
    })
  } catch (error) {
    console.error('Failed to load company requests:', error)
    errorMessage.value = 'Impossible de charger les demandes clients.'
  } finally {
    isLoading.value = false
  }
}

async function reviewRequest(request, status) {
  savingId.value = request.id

  try {
    await api.patch(`/platform/company-requests/${request.id}`, {
      status,
      admin_notes: notesByRequest[request.id] || null,
    })
    toast.success(status === 'approved' ? 'Demande approuvee.' : 'Demande rejetee.')
    await loadRequests()
  } catch (error) {
    console.error('Failed to review company request:', error)
  } finally {
    savingId.value = null
  }
}

function setFilter(status) {
  activeStatus.value = status
  loadRequests()
}

function statusClass(status) {
  const classes = {
    pending: 'rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-semibold text-yellow-800',
    approved: 'rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700',
    rejected: 'rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700',
  }
  return classes[status] || 'rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700'
}

function statusLabel(status) {
  const labels = {
    pending: 'A traiter',
    approved: 'Approuvee',
    rejected: 'Rejetee',
  }
  return labels[status] || status
}

function formatDate(value) {
  if (!value) return 'Non renseigne'

  return new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

onMounted(loadRequests)
</script>
