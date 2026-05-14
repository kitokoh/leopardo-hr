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

    <form v-if="activeTab === 'jobs'" class="grid gap-3 rounded-lg bg-white p-4 shadow md:grid-cols-4" @submit.prevent="createJob">
      <input
        v-model="jobForm.title"
        type="text"
        required
        class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
        placeholder="Intitule du poste"
      />
      <input
        v-model="jobForm.location"
        type="text"
        class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
        placeholder="Lieu"
      />
      <select v-model="jobForm.contract_type" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="cdi">CDI</option>
        <option value="cdd">CDD</option>
        <option value="stage">Stage</option>
        <option value="freelance">Freelance</option>
      </select>
      <button
        type="submit"
        :disabled="savingJob"
        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
      >
        {{ savingJob ? 'Creation...' : 'Creer le poste' }}
      </button>
    </form>

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
        status-field="status"
        @item-click="viewApplicant"
      >
        <template #card="{ item }">
          <p class="text-sm font-medium text-gray-900">{{ item.first_name }} {{ item.last_name }}</p>
          <p class="mt-0.5 text-xs text-gray-500">{{ item.email }}</p>
          <p class="mt-1 text-xs text-gray-400">{{ item.job_title || 'Non assigne' }}</p>
          <div class="mt-3 flex gap-2">
            <button
              v-if="previousStage(item.status)"
              class="rounded bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200"
              type="button"
              @click.stop="updateApplicantStatus(item, previousStage(item.status))"
            >
              Retour
            </button>
            <button
              v-if="nextStage(item.status)"
              class="rounded bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
              type="button"
              @click.stop="updateApplicantStatus(item, nextStage(item.status))"
            >
              Avancer
            </button>
          </div>
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
      <template #cell-status="{ value }">
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
const savingJob = ref(false)
const jobs = ref([])
const applicants = ref([])
const activeTab = ref('pipeline')
const selectedJobId = ref('')
const jobForm = ref({
  title: '',
  location: '',
  contract_type: 'cdi',
})

const stats = ref({ open_jobs: 0, total_applicants: 0, interviews: 0, hired: 0 })

const tabs = [
  { key: 'pipeline', label: 'Pipeline Kanban' },
  { key: 'jobs', label: 'Postes' },
  { key: 'applicants', label: 'Candidats' },
]

const pipelineStages = [
  { key: 'new', label: 'Candidature' },
  { key: 'screening', label: 'Pre-selection' },
  { key: 'interview', label: 'Entretien' },
  { key: 'offer', label: 'Offre' },
  { key: 'hired', label: 'Embauche' },
  { key: 'rejected', label: 'Refuse' },
]

const stageKeys = pipelineStages.map(stage => stage.key)

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
  { key: 'status', label: 'Etape', sortable: true },
  { key: 'applied_at', label: 'Date', sortable: true },
]

const jobStatusMap = {
  open: { label: 'Ouvert', color: 'green' },
  published: { label: 'Publie', color: 'green' },
  closed: { label: 'Ferme', color: 'gray' },
  archived: { label: 'Archive', color: 'gray' },
  draft: { label: 'Brouillon', color: 'yellow' },
  filled: { label: 'Pourvu', color: 'blue' },
}

const stageStatusMap = {
  new: { label: 'Candidature', color: 'blue' },
  screening: { label: 'Pre-selection', color: 'yellow' },
  interview: { label: 'Entretien', color: 'purple' },
  offer: { label: 'Offre', color: 'indigo' },
  hired: { label: 'Embauche', color: 'green' },
  rejected: { label: 'Refuse', color: 'red' },
}

const selectedJob = computed(() => jobs.value.find(j => String(j.id) === String(selectedJobId.value)))

const filteredApplicants = computed(() => {
  if (!selectedJobId.value) return applicants.value
  return applicants.value.filter(a => String(a.job_posting_id) === String(selectedJobId.value))
})

function viewJobPipeline(job) {
  selectedJobId.value = job.id
  activeTab.value = 'pipeline'
}

function viewApplicant() {}

function normalizePaginated(payload) {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.data?.data)) return payload.data.data
  return []
}

function previousStage(status) {
  const index = stageKeys.indexOf(status)
  if (index <= 0 || status === 'rejected') return null
  return stageKeys[index - 1]
}

function nextStage(status) {
  const index = stageKeys.indexOf(status)
  if (index < 0 || index >= stageKeys.length - 2) return null
  return stageKeys[index + 1]
}

async function createJob() {
  savingJob.value = true
  try {
    await api.post('/v1/recruitment/jobs', {
      ...jobForm.value,
      description: jobForm.value.title,
      remote_policy: 'onsite',
    })
    jobForm.value = { title: '', location: '', contract_type: 'cdi' }
    await fetchData()
  } catch {
    error.value = 'Impossible de creer le poste.'
  } finally {
    savingJob.value = false
  }
}

async function updateApplicantStatus(applicant, status) {
  const previousStatus = applicant.status
  applicant.status = status
  try {
    await api.patch(`/v1/recruitment/applicants/${applicant.id}/status`, { status })
  } catch {
    applicant.status = previousStatus
    error.value = 'Impossible de mettre a jour le candidat.'
  }
}

async function fetchData() {
  loading.value = true
  error.value = ''
  try {
    const jobsRes = await api.get('/v1/recruitment/jobs')
    jobs.value = normalizePaginated(jobsRes.data).map(job => ({
      ...job,
      department: job.department?.name || job.department || '',
      applicants_count: job.applicants_count || 0,
    }))

    const applicantResponses = await Promise.all(
      jobs.value.map(job => api.get(`/v1/recruitment/jobs/${job.id}/applicants`).catch(() => null)),
    )
    applicants.value = applicantResponses.flatMap((response, index) => {
      const job = jobs.value[index]
      return normalizePaginated(response?.data).map(applicant => ({
        ...applicant,
        job_title: job.title,
        job_posting_id: job.id,
      }))
    })

    stats.value = {
      open_jobs: jobs.value.filter(j => ['open', 'published'].includes(j.status)).length,
      total_applicants: applicants.value.length,
      interviews: applicants.value.filter(a => a.status === 'interview').length,
      hired: applicants.value.filter(a => a.status === 'hired').length,
    }
  } catch {
    error.value = 'Impossible de charger les donnees de recrutement.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)
</script>
