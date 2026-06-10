<template>
  <div class="space-y-8 animate-fade-in">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">Demandes clients</h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
          Intake commercial, qualification et validation des nouvelles entreprises.
        </p>
      </div>
      <button class="btn-secondary py-2.5 shadow-glass-sm" :disabled="isLoading" @click="loadRequests">
        <ArrowPathIcon class="mr-2 h-4 w-4" :class="{ 'animate-spin': isLoading }" />
        Actualiser
      </button>
    </div>

    <!-- KPI Summary -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4 animate-slide-up">
      <StatsCard title="Total Demandes" :value="totalRequests" icon="ChatBubbleBottomCenterTextIcon" color="blue" />
      <StatsCard title="À Traiter" :value="statusCounts.pending" icon="ClockIcon" color="yellow" />
      <StatsCard title="Approuvées" :value="statusCounts.approved" icon="CheckCircleIcon" color="green" />
      <StatsCard title="Rejetées" :value="statusCounts.rejected" icon="XCircleIcon" color="red" />
    </div>

    <div class="card animate-slide-up" style="animation-delay: 0.1s">
      <!-- Filter Header -->
      <div class="flex flex-col gap-6 border-b border-slate-200/50 px-6 py-6 dark:border-slate-800/50 lg:flex-row lg:items-center lg:justify-between bg-slate-50/30 dark:bg-slate-900/20">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">File de qualification</h2>
          <p class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400 italic">Priorisez les leads pour accélérer le déploiement.</p>
        </div>
        <div class="flex flex-wrap gap-2 p-1 bg-slate-200/50 dark:bg-slate-800/50 rounded-2xl w-fit">
          <button
            v-for="option in filters"
            :key="option.value"
            :class="[
              'px-4 py-2 text-xs font-black uppercase tracking-widest transition-all rounded-xl',
              activeStatus === option.value
                ? 'bg-white dark:bg-slate-700 text-brand-600 dark:text-white shadow-glass-sm'
                : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
            ]"
            @click="setFilter(option.value)"
          >
            {{ option.label }}
          </button>
        </div>
      </div>

      <!-- Content -->
      <div v-if="isLoading && requests.length === 0" class="flex flex-col items-center justify-center p-20 gap-4">
        <div class="h-10 w-10 animate-spin rounded-full border-4 border-brand-500 border-t-transparent"></div>
        <p class="text-sm font-bold text-slate-500 animate-pulse">Synchronisation des demandes...</p>
      </div>

      <div v-else-if="errorMessage" class="p-12 text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400">
          <ExclamationTriangleIcon class="h-8 w-8" />
        </div>
        <p class="mt-4 text-lg font-bold text-slate-900 dark:text-white">{{ errorMessage }}</p>
        <button class="btn-primary mt-6" @click="loadRequests">Réessayer</button>
      </div>

      <div v-else-if="requests.length === 0" class="p-20 text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400">
          <InboxIcon class="h-8 w-8" />
        </div>
        <p class="mt-4 text-lg font-bold text-slate-400 uppercase tracking-widest">Aucune demande trouvée</p>
      </div>

      <div v-else class="divide-y divide-slate-200/50 dark:divide-slate-800/50">
        <article v-for="request in sortedRequests" :key="request.id" class="group p-6 hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors relative overflow-hidden">
          <div class="flex flex-col gap-8 xl:flex-row xl:items-start xl:justify-between relative z-10">
            <div class="min-w-0 flex-1 space-y-4">
              <div class="flex flex-wrap items-center gap-4">
                <h3 class="text-2xl font-black text-slate-900 dark:text-white group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">
                  {{ request.company_name }}
                </h3>
                <span :class="['px-3 py-1 text-[10px] font-black uppercase tracking-[0.2em] rounded-lg border', statusClass(request.status)]">
                  {{ statusLabel(request.status) }}
                </span>
              </div>

              <div class="flex flex-wrap items-center gap-y-2 gap-x-6 text-sm">
                <span class="flex items-center font-bold text-slate-700 dark:text-slate-300">
                  <TagIcon class="mr-2 h-4 w-4 text-brand-500" />
                  {{ request.sector || 'Secteur non précisé' }}
                </span>
                <span class="flex items-center font-bold text-slate-700 dark:text-slate-300">
                  <MapPinIcon class="mr-2 h-4 w-4 text-brand-500" />
                  {{ request.city || 'Ville inconnue' }}, {{ request.country || 'Pays inconnu' }}
                </span>
                <span class="flex items-center font-bold text-brand-600 dark:text-brand-400">
                  <CalendarIcon class="mr-2 h-4 w-4" />
                  {{ formatDate(request.created_at) }}
                </span>
              </div>

              <div class="rounded-2xl bg-white/50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800 p-4 shadow-sm">
                <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400 italic">
                  "{{ request.description || 'Aucune description fournie.' }}"
                </p>
              </div>

              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 pt-2">
                <div class="flex items-center gap-3">
                  <div class="h-10 w-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500">
                    <EnvelopeIcon class="h-5 w-5" />
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Email Contact</p>
                    <p class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ request.email || request.user?.email || 'N/A' }}</p>
                  </div>
                </div>
                <div class="flex items-center gap-3">
                  <div class="h-10 w-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500">
                    <PhoneIcon class="h-5 w-5" />
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Téléphone</p>
                    <p class="text-sm font-bold text-slate-900 dark:text-white">{{ request.phone || 'Non renseigné' }}</p>
                  </div>
                </div>
                <div v-if="request.status !== 'pending'" class="flex items-center gap-3">
                  <div class="h-10 w-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500">
                    <UserIcon class="h-5 w-5" />
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Traité par</p>
                    <p class="text-sm font-bold text-slate-900 dark:text-white">Admin Système</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Action Panel -->
            <div class="w-full xl:w-96 space-y-4">
              <div class="space-y-2">
                <label :for="'notes-' + request.id" class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 ml-1">Notes Internes</label>
                <textarea
                  :id="'notes-' + request.id"
                  v-model="notesByRequest[request.id]"
                  :disabled="request.status !== 'pending' || savingId === request.id"
                  rows="4"
                  class="form-input text-sm leading-relaxed"
                  placeholder="Évaluez la légitimité du lead, taille de l'entreprise..."
                ></textarea>
              </div>

              <div v-if="request.status === 'pending'" class="grid grid-cols-2 gap-3 pt-2">
                <button
                  class="btn-secondary justify-center border-red-200 dark:border-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/20 py-3"
                  :disabled="savingId === request.id"
                  @click="reviewRequest(request, 'rejected')"
                >
                  <XMarkIcon class="mr-2 h-4 w-4" />
                  Refuser
                </button>
                <button
                  class="btn-primary justify-center bg-brand-600 hover:bg-brand-700 py-3 shadow-premium"
                  :disabled="savingId === request.id"
                  @click="reviewRequest(request, 'approved')"
                >
                  <CheckIcon class="mr-2 h-4 w-4" />
                  Approuver
                </button>
              </div>
              <div v-else class="flex items-center justify-center gap-3 p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800">
                <CheckBadgeIcon class="h-5 w-5 text-emerald-500" />
                <p class="text-xs font-black uppercase tracking-widest text-slate-500">
                  Décision finale : {{ formatDate(request.reviewed_at) }}
                </p>
              </div>
            </div>
          </div>

          <!-- Background Decoration for pending items -->
          <div v-if="request.status === 'pending'" class="absolute -right-4 -bottom-4 h-24 w-24 rounded-full bg-brand-500/5 blur-2xl group-hover:bg-brand-500/10 transition-colors"></div>
        </article>
      </div>

      <!-- Pagination / Footer -->
      <div v-if="requests.length > 0" class="border-t border-slate-200/50 dark:border-slate-800/50 px-6 py-4 bg-slate-50/30 dark:bg-slate-900/20 text-center">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">
          Affichage de {{ requests.length }} sur {{ totalRequests }} demandes
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useToast } from 'vue-toastification'
import {
  ArrowPathIcon,
  ChatBubbleBottomCenterTextIcon,
  ClockIcon,
  CheckCircleIcon,
  XCircleIcon,
  ExclamationTriangleIcon,
  InboxIcon,
  TagIcon,
  MapPinIcon,
  CalendarIcon,
  EnvelopeIcon,
  PhoneIcon,
  UserIcon,
  CheckIcon,
  XMarkIcon,
  CheckBadgeIcon
} from '@heroicons/vue/24/outline'
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'

const toast = useToast()
const isLoading = ref(false)
const errorMessage = ref('')
const savingId = ref(null)
const activeStatus = ref('pending') // Default to pending for better workflow
const requests = ref([])
const meta = ref({ total: 0, current_page: 1, last_page: 1 })
const notesByRequest = reactive({})
const statusCounts = ref({ pending: 0, approved: 0, rejected: 0 })

const filters = [
  { value: 'all', label: 'Toutes' },
  { value: 'pending', label: 'À traiter' },
  { value: 'approved', label: 'Approuvées' },
  { value: 'rejected', label: 'Rejetées' },
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
    // Fetch with meta to get totals efficiently
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
    errorMessage.value = 'Impossible de synchroniser les demandes clients.'
  } finally {
    isLoading.value = false
  }
}

async function reviewRequest(request, status) {
  if (savingId.value) return
  savingId.value = request.id

  try {
    await api.patch(`/platform/company-requests/${request.id}`, {
      status,
      admin_notes: notesByRequest[request.id] || null,
    })

    toast.success(status === 'approved' ? 'Demande approuvée. Provisionnement lancé.' : 'Demande rejetée.')
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
    pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 border-amber-200 dark:border-amber-800',
    approved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
    rejected: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 border-red-200 dark:border-red-800',
  }
  return classes[status] || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700'
}

function statusLabel(status) {
  const labels = {
    pending: 'Attente',
    approved: 'Validé',
    rejected: 'Refusé',
  }
  return labels[status] || status
}

function formatDate(value) {
  if (!value) return 'Non renseigné'

  return new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

onMounted(loadRequests)
</script>

<style scoped>
.form-input {
  @apply block w-full rounded-2xl border border-slate-200 bg-white/50 px-4 py-3 text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-slate-800 dark:bg-slate-950/50 dark:text-white backdrop-blur-sm placeholder:text-slate-400 font-medium;
}
</style>
