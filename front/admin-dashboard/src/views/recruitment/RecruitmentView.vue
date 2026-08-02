<template>
  <div class="space-y-8 animate-fade-in">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">Recrutement (ATS)</h1>
        <p class="mt-1 text-lg font-medium text-slate-500 dark:text-slate-400">
          Pipeline de candidatures, offres publiÃ©es et suivi des entretiens.
        </p>
      </div>
      <button class="btn-secondary py-2.5 shadow-glass-sm" :disabled="loading" @click="fetchData">
        <ArrowPathIcon class="mr-2 h-4 w-4" :class="{ 'animate-spin': loading }" />
        Actualiser
      </button>
    </div>

    <div v-if="error" class="rounded-2xl border border-red-200 bg-red-50/70 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300">
      {{ error }}
    </div>

    <!-- KPI Summary -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4 animate-slide-up">
      <StatsCard title="Postes ouverts" :value="stats.open_jobs" icon="ChartBarIcon" color="blue" />
      <StatsCard title="Candidatures" :value="stats.total_applicants" icon="UsersIcon" color="green" />
      <StatsCard title="Entretiens planifiÃ©s" :value="stats.interviews" icon="ChartBarIcon" color="purple" />
      <StatsCard title="Embauches ce mois" :value="stats.hired" icon="ChartBarIcon" color="green" />
    </div>

    <!-- Tabs -->
    <div class="flex flex-wrap gap-2 p-1 bg-slate-200/50 dark:bg-slate-800/50 rounded-2xl w-fit animate-slide-up" style="animation-delay: 0.05s">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        :class="[
          'px-4 py-2 text-xs font-black uppercase tracking-widest transition-all rounded-xl',
          activeTab === tab.key
            ? 'glass-card dark:bg-slate-700 text-brand-600 dark:text-white shadow-glass-sm'
            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
        ]"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Job creation / publication form -->
    <div v-if="activeTab === 'jobs'" class="card animate-slide-up" style="animation-delay: 0.1s">
      <div class="card-header">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">Nouveau poste</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          Le poste est crÃ©Ã© en brouillon ; publiez-le pour le rendre visible sur le portail carriÃ¨res public.
        </p>
      </div>
      <form class="card-body grid gap-4 md:grid-cols-4" @submit.prevent="createJob">
        <input
          v-model="jobForm.title"
          type="text"
          required
          class="form-input md:col-span-2"
          placeholder="IntitulÃ© du poste"
        />
        <input
          v-model="jobForm.location"
          type="text"
          class="form-input"
          placeholder="Lieu"
        />
        <select v-model="jobForm.contract_type" class="form-select">
          <option value="cdi">CDI</option>
          <option value="cdd">CDD</option>
          <option value="stage">Stage</option>
          <option value="freelance">Freelance</option>
        </select>
        <textarea
          v-model="jobForm.description"
          rows="2"
          class="form-input md:col-span-4"
          placeholder="Description du poste (visible publiquement une fois publiÃ©)"
        />
        <div class="md:col-span-4 flex justify-end">
          <button
            type="submit"
            :disabled="savingJob || !jobForm.title.trim()"
            class="btn-primary"
          >
            <PlusIcon class="mr-2 h-4 w-4" />
            {{ savingJob ? 'CrÃ©ation...' : 'CrÃ©er le poste' }}
          </button>
        </div>
      </form>
    </div>

    <div v-if="activeTab === 'jobs'" class="animate-slide-up" style="animation-delay: 0.15s">
      <DataTable
        :columns="jobColumns"
        :rows="jobs"
        :loading="loading"
        :error="''"
        :search-keys="['title', 'department', 'location']"
        search-placeholder="Rechercher un poste..."
        default-sort="created_at"
        default-sort-dir="desc"
      >
        <template #cell-status="{ value }">
          <StatusBadge :status="value" :map="jobStatusMap" />
        </template>
        <template #row-actions="{ row }">
          <div class="flex justify-end gap-3">
            <button
              v-if="row.status === 'draft'"
              class="text-sm font-bold text-emerald-600 hover:text-emerald-800 dark:text-emerald-400"
              :disabled="jobActionInFlight === row.id"
              @click="publishJob(row)"
            >
              Publier
            </button>
            <button
              v-if="row.status === 'published'"
              class="text-sm font-bold text-slate-500 hover:text-slate-700 dark:text-slate-400"
              :disabled="jobActionInFlight === row.id"
              @click="closeJob(row)"
            >
              Fermer
            </button>
            <button class="text-sm font-bold text-brand-600 hover:text-brand-800 dark:text-brand-400" @click="viewJobPipeline(row)">
              Pipeline
            </button>
          </div>
        </template>
      </DataTable>
    </div>

    <div v-else-if="activeTab === 'pipeline'" class="card animate-slide-up" style="animation-delay: 0.1s">
      <div class="card-header flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">
          Pipeline{{ selectedJob ? ' â€” ' + selectedJob.title : '' }}
        </h2>
        <select v-if="jobs.length > 0" v-model="selectedJobId" class="form-select">
          <option value="">Tous les postes</option>
          <option v-for="job in jobs" :key="job.id" :value="job.id">{{ job.title }}</option>
        </select>
      </div>
      <div class="card-body">
        <KanbanBoard
          :columns="pipelineStages"
          :items="filteredApplicants"
          status-field="status"
          empty-label="Aucun candidat"
          @item-click="viewApplicant"
          @move="onApplicantMoved"
        >
          <template #card="{ item }">
            <p class="text-sm font-bold text-slate-900 dark:text-white">{{ item.first_name }} {{ item.last_name }}</p>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ item.email }}</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ item.job_title || 'Non assignÃ©' }}</p>
            <div class="mt-3 flex gap-2">
              <button
                v-if="previousStage(item.status)"
                class="rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-black uppercase tracking-widest text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300"
                type="button"
                @click.stop="updateApplicantStatus(item, previousStage(item.status))"
              >
                â† Retour
              </button>
              <button
                v-if="nextStage(item.status)"
                class="rounded-lg bg-brand-50 px-2 py-1 text-[10px] font-black uppercase tracking-widest text-brand-700 hover:bg-brand-100 dark:bg-brand-900/30 dark:text-brand-300"
                type="button"
                @click.stop="updateApplicantStatus(item, nextStage(item.status))"
              >
                Avancer â†’
              </button>
            </div>
          </template>
        </KanbanBoard>
        <p class="mt-4 text-xs font-semibold text-slate-400 dark:text-slate-500">
          Glissez une carte vers une autre colonne pour changer l'Ã©tape, ou utilisez les boutons Retour/Avancer.
        </p>
      </div>
    </div>

    <div v-else-if="activeTab === 'applicants'" class="animate-slide-up" style="animation-delay: 0.1s">
      <DataTable
        :columns="applicantColumns"
        :rows="applicants"
        :loading="loading"
        :error="''"
        :search-keys="['first_name', 'last_name', 'email']"
        search-placeholder="Rechercher un candidat..."
        default-sort="applied_at"
        default-sort-dir="desc"
      >
        <template #cell-status="{ value }">
          <StatusBadge :status="value" :map="stageStatusMap" />
        </template>
        <template #row-actions="{ row }">
          <button class="text-sm font-bold text-brand-600 hover:text-brand-800 dark:text-brand-400" @click="viewApplicant(row)">
            DÃ©tail
          </button>
        </template>
      </DataTable>
    </div>

    <ApplicantDetailModal
      v-if="selectedApplicantId"
      :applicant-id="selectedApplicantId"
      @close="selectedApplicantId = null"
      @updated="fetchData"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import { ArrowPathIcon, PlusIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import KanbanBoard from '@/components/common/KanbanBoard.vue'
import ApplicantDetailModal from '@/components/recruitment/ApplicantDetailModal.vue'

const toast = useToast()

const loading = ref(false)
const error = ref('')
const savingJob = ref(false)
const jobActionInFlight = ref(null)
const jobs = ref([])
const applicants = ref([])
const activeTab = ref('pipeline')
const selectedJobId = ref('')
const jobForm = ref({
  title: '',
  location: '',
  contract_type: 'cdi',
  description: '',
})
const selectedApplicantId = ref(null)

const stats = ref({ open_jobs: 0, total_applicants: 0, interviews: 0, hired: 0 })

const tabs = [
  { key: 'pipeline', label: 'Pipeline Kanban' },
  { key: 'jobs', label: 'Postes' },
  { key: 'applicants', label: 'Candidats' },
]

const pipelineStages = [
  { key: 'new', label: 'Candidature' },
  { key: 'screening', label: 'PrÃ©-sÃ©lection' },
  { key: 'interview', label: 'Entretien' },
  { key: 'offer', label: 'Offre' },
  { key: 'hired', label: 'Embauche' },
  { key: 'rejected', label: 'RefusÃ©' },
]

const stageKeys = pipelineStages.map(stage => stage.key)

const jobColumns = [
  { key: 'title', label: 'Poste', sortable: true },
  { key: 'department', label: 'DÃ©partement', sortable: true },
  { key: 'location', label: 'Lieu', sortable: true },
  { key: 'applicants_count', label: 'Candidats', sortable: true },
  { key: 'status', label: 'Statut', sortable: true },
  { key: 'created_at', label: 'CrÃ©Ã© le', sortable: true },
]

const applicantColumns = [
  { key: 'first_name', label: 'PrÃ©nom', sortable: true },
  { key: 'last_name', label: 'Nom', sortable: true },
  { key: 'email', label: 'Email', sortable: true },
  { key: 'job_title', label: 'Poste', sortable: true },
  { key: 'status', label: 'Ã‰tape', sortable: true },
  { key: 'applied_at', label: 'Date', sortable: true },
]

const jobStatusMap = {
  open: { label: 'Ouvert', color: 'green' },
  published: { label: 'PubliÃ©', color: 'green' },
  closed: { label: 'FermÃ©', color: 'gray' },
  archived: { label: 'ArchivÃ©', color: 'gray' },
  draft: { label: 'Brouillon', color: 'yellow' },
  filled: { label: 'Pourvu', color: 'blue' },
}

const stageStatusMap = {
  new: { label: 'Candidature', color: 'blue' },
  screening: { label: 'PrÃ©-sÃ©lection', color: 'yellow' },
  interview: { label: 'Entretien', color: 'purple' },
  offer: { label: 'Offre', color: 'indigo' },
  hired: { label: 'Embauche', color: 'green' },
  rejected: { label: 'RefusÃ©', color: 'red' },
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

function viewApplicant(applicant) {
  selectedApplicantId.value = applicant.id
}

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
  if (!jobForm.value.title.trim()) return
  savingJob.value = true
  try {
    await api.post('/v1/recruitment/jobs', {
      ...jobForm.value,
      description: jobForm.value.description || jobForm.value.title,
      remote_policy: 'onsite',
    })
    toast.success('Poste crÃ©Ã© en brouillon.')
    jobForm.value = { title: '', location: '', contract_type: 'cdi', description: '' }
    await fetchData()
  } catch {
    toast.error('Impossible de crÃ©er le poste.')
  } finally {
    savingJob.value = false
  }
}

async function publishJob(job) {
  jobActionInFlight.value = job.id
  try {
    await api.post(`/v1/recruitment/jobs/${job.id}/publish`)
    toast.success(`Â« ${job.title} Â» est maintenant publiÃ©.`)
    await fetchData()
  } catch {
    toast.error('Impossible de publier ce poste.')
  } finally {
    jobActionInFlight.value = null
  }
}

async function closeJob(job) {
  jobActionInFlight.value = job.id
  try {
    await api.post(`/v1/recruitment/jobs/${job.id}/close`)
    toast.success(`Â« ${job.title} Â» est maintenant fermÃ©.`)
    await fetchData()
  } catch {
    toast.error('Impossible de fermer ce poste.')
  } finally {
    jobActionInFlight.value = null
  }
}

async function onApplicantMoved({ item, to }) {
  await updateApplicantStatus(item, to)
}

async function updateApplicantStatus(applicant, status) {
  if (!status) return
  const previousStatus = applicant.status
  applicant.status = status
  try {
    await api.patch(`/v1/recruitment/applicants/${applicant.id}/status`, { status })
  } catch {
    applicant.status = previousStatus
    toast.error('Impossible de mettre Ã  jour le candidat.')
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
    error.value = 'Impossible de charger les donnÃ©es de recrutement.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)
</script>

<style scoped>
@reference '../../style.css';
.form-input {
  @apply block w-full rounded-2xl border border-slate-200 glass-card/50 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-slate-800 dark:bg-slate-950/50 dark:text-white backdrop-blur-sm placeholder:text-slate-400 font-medium;
}
.form-select {
  @apply rounded-xl border border-slate-200 glass-card/70 px-3 py-2 text-xs font-bold uppercase tracking-widest text-slate-700 outline-none transition focus:border-brand-500 dark:border-slate-800 dark:bg-slate-950/50 dark:text-slate-200;
}
</style>

