<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
      <StatsCard title="Postes ouverts" :value="stats.open_jobs" icon="ChartBarIcon" color="blue" />
      <StatsCard title="Candidatures" :value="stats.total_applicants" icon="UsersIcon" color="green" />
      <StatsCard title="Entretiens planifies" :value="stats.interviews" icon="ChartBarIcon" color="purple" />
      <StatsCard title="Embauches ce mois" :value="stats.hired" icon="ChartBarIcon" color="green" />
    </div>

    <div class="flex gap-2">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        :class="[
          'rounded-md px-4 py-2 text-sm font-medium',
          activeTab === tab.key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50'
        ]"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <DataTable
      v-if="activeTab === 'jobs'"
      :columns="jobColumns"
      :rows="jobs"
      :loading="loading"
      :error="error"
      :search-keys="['title', 'department', 'location']"
      search-placeholder="Rechercher un poste..."
      default-sort="created_at"
      default-sort-dir="desc"
    >
      <template #cell-status="{ value }">
        <StatusBadge :status="value" :map="jobStatusMap" />
      </template>
      <template #row-actions="{ row }">
        <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800" @click="viewJobPipeline(row)">
          Pipeline
        </button>
      </template>
    </DataTable>

    <div v-else-if="activeTab === 'pipeline'" class="rounded-lg bg-white p-6 shadow">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900">
          Pipeline{{ selectedJob ? ' — ' + selectedJob.title : '' }}
        </h2>
        <select v-if="jobs.length > 0" v-model="selectedJobId" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
          <option value="">Tous les postes</option>
          <option v-for="job in jobs" :key="job.id" :value="job.id">{{ job.title }}</option>
        </select>
      </div>
      <KanbanBoard
        :columns="pipelineStages"
        :items="filteredApplicants"
        status-field="stage"
        @item-click="viewApplicant"
      >
        <template #card="{ item }">
          <p class="text-sm font-medium text-gray-900">{{ item.first_name }} {{ item.last_name }}</p>
          <p class="mt-0.5 text-xs text-gray-500">{{ item.email }}</p>
          <p class="mt-1 text-xs text-gray-400">{{ item.job_title || 'Non assigne' }}</p>
        </template>
      </KanbanBoard>
    </div>

    <DataTable
      v-else
      :columns="applicantColumns"
      :rows="applicants"
      :loading="loading"
      :error="error"
      :search-keys="['first_name', 'last_name', 'email']"
      search-placeholder="Rechercher un candidat..."
      default-sort="applied_at"
      default-sort-dir="desc"
    >
      <template #cell-stage="{ value }">
        <StatusBadge :status="value" :map="stageStatusMap" />
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import KanbanBoard from '@/components/common/KanbanBoard.vue'

const loading = ref(false)
const error = ref('')
const jobs = ref([])
const applicants = ref([])
const activeTab = ref('pipeline')
const selectedJobId = ref('')

const stats = ref({ open_jobs: 0, total_applicants: 0, interviews: 0, hired: 0 })

const tabs = [
  { key: 'pipeline', label: 'Pipeline Kanban' },
  { key: 'jobs', label: 'Postes' },
  { key: 'applicants', label: 'Candidats' },
]

const pipelineStages = [
  { key: 'applied', label: 'Candidature' },
  { key: 'screening', label: 'Pre-selection' },
  { key: 'interview', label: 'Entretien' },
  { key: 'offer', label: 'Offre' },
  { key: 'hired', label: 'Embauche' },
  { key: 'rejected', label: 'Refuse' },
]

const jobColumns = [
  { key: 'title', label: 'Poste', sortable: true },
  { key: 'department', label: 'Departement', sortable: true },
  { key: 'location', label: 'Lieu', sortable: true },
  { key: 'applicants_count', label: 'Candidats', sortable: true },
  { key: 'status', label: 'Statut', sortable: true },
  { key: 'created_at', label: 'Publie', sortable: true },
]

const applicantColumns = [
  { key: 'first_name', label: 'Prenom', sortable: true },
  { key: 'last_name', label: 'Nom', sortable: true },
  { key: 'email', label: 'Email', sortable: true },
  { key: 'job_title', label: 'Poste', sortable: true },
  { key: 'stage', label: 'Etape', sortable: true },
  { key: 'applied_at', label: 'Date', sortable: true },
]

const jobStatusMap = {
  open: { label: 'Ouvert', color: 'green' },
  closed: { label: 'Ferme', color: 'gray' },
  draft: { label: 'Brouillon', color: 'yellow' },
  filled: { label: 'Pourvu', color: 'blue' },
}

const stageStatusMap = {
  applied: { label: 'Candidature', color: 'blue' },
  screening: { label: 'Pre-selection', color: 'yellow' },
  interview: { label: 'Entretien', color: 'purple' },
  offer: { label: 'Offre', color: 'indigo' },
  hired: { label: 'Embauche', color: 'green' },
  rejected: { label: 'Refuse', color: 'red' },
}

const selectedJob = computed(() => jobs.value.find(j => j.id === selectedJobId.value))

const filteredApplicants = computed(() => {
  if (!selectedJobId.value) return applicants.value
  return applicants.value.filter(a => a.job_posting_id === selectedJobId.value)
})

function viewJobPipeline(job) {
  selectedJobId.value = job.id
  activeTab.value = 'pipeline'
}

function viewApplicant(item) { /* TODO: detail modal */ }

async function fetchData() {
  loading.value = true
  error.value = ''
  try {
    const [jobsRes, applicantsRes] = await Promise.all([
      api.get('/v1/job-postings'),
      api.get('/v1/applicants'),
    ])
    jobs.value = jobsRes.data.data || jobsRes.data || []
    applicants.value = applicantsRes.data.data || applicantsRes.data || []
    stats.value = {
      open_jobs: jobs.value.filter(j => j.status === 'open').length,
      total_applicants: applicants.value.length,
      interviews: applicants.value.filter(a => a.stage === 'interview').length,
      hired: applicants.value.filter(a => a.stage === 'hired').length,
    }
  } catch {
    error.value = 'Impossible de charger les donnees de recrutement.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)
</script>
