<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
      <StatsCard title="Formations actives" :value="stats.active_courses" icon="ChartBarIcon" color="blue" />
      <StatsCard title="Sessions planifiees" :value="stats.upcoming_sessions" icon="ChartBarIcon" color="purple" />
      <StatsCard title="Inscrits" :value="stats.total_enrollments" icon="UsersIcon" color="green" />
      <StatsCard title="Taux completion" :value="stats.completion_rate + '%'" icon="ChartBarIcon" color="green" />
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

    <div v-if="activeTab === 'catalogue'" class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
      <div v-if="loading" class="col-span-full py-8 text-center text-sm text-gray-500">Chargement...</div>
      <div v-else-if="courses.length === 0" class="col-span-full py-8 text-center text-sm text-gray-400">Aucune formation.</div>
      <div
        v-for="course in courses"
        :key="course.id"
        class="cursor-pointer rounded-lg bg-white p-5 shadow ring-1 ring-gray-200 transition hover:shadow-md hover:ring-indigo-300"
        @click="viewCourse(course)"
      >
        <div class="flex items-start justify-between">
          <div>
            <h3 class="text-sm font-semibold text-gray-900">{{ course.title }}</h3>
            <p class="mt-1 text-xs text-gray-500">{{ course.category || 'Sans categorie' }}</p>
          </div>
          <StatusBadge :status="course.is_active ? 'active' : 'draft'" />
        </div>
        <p class="mt-2 line-clamp-2 text-sm text-gray-600">{{ course.description || 'Pas de description.' }}</p>
        <div class="mt-3 flex items-center justify-between text-xs text-gray-400">
          <span>{{ course.duration_hours || '-' }}h</span>
          <span>{{ course.sessions_count || 0 }} sessions</span>
        </div>
        <div v-if="course.cost_per_participant" class="mt-1 text-xs text-gray-400">
          {{ formatCurrency(course.cost_per_participant, course.currency) }} / participant
        </div>
      </div>
    </div>

    <DataTable
      v-else-if="activeTab === 'sessions'"
      :columns="sessionColumns"
      :rows="sessions"
      :loading="loading"
      :error="error"
      :search-keys="['course_title', 'instructor', 'location']"
      search-placeholder="Rechercher une session..."
      default-sort="start_date"
      default-sort-dir="desc"
    >
      <template #cell-status="{ value }">
        <StatusBadge :status="value" :map="sessionStatusMap" />
      </template>
    </DataTable>

    <DataTable
      v-else
      :columns="enrollmentColumns"
      :rows="enrollments"
      :loading="loading"
      :error="error"
      :search-keys="['employee_name', 'course_title']"
      search-placeholder="Rechercher une inscription..."
      default-sort="enrolled_at"
      default-sort-dir="desc"
    >
      <template #cell-status="{ value }">
        <StatusBadge :status="value" :map="enrollmentStatusMap" />
      </template>
      <template #cell-progress="{ value }">
        <div class="flex items-center gap-2">
          <div class="h-1.5 w-20 rounded-full bg-gray-200">
            <div class="h-1.5 rounded-full bg-indigo-600" :style="{ width: (value || 0) + '%' }" />
          </div>
          <span class="text-xs text-gray-500">{{ value || 0 }}%</span>
        </div>
      </template>
    </DataTable>

    <!-- Course Detail Panel -->
    <div v-if="selectedCourse" class="fixed inset-0 z-50 overflow-hidden" @click.self="closeDetail">
      <div class="absolute inset-0 bg-gray-500/50 transition-opacity" @click="closeDetail" />
      <div class="absolute inset-y-0 right-0 flex max-w-full pl-10">
        <div class="w-screen max-w-lg">
          <div class="flex h-full flex-col overflow-y-auto bg-white shadow-xl">
            <div class="border-b border-gray-200 px-6 py-4">
              <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">{{ selectedCourse.title }}</h2>
                <button class="rounded-md text-gray-400 hover:text-gray-600" @click="closeDetail">
                  <span class="text-xl">&times;</span>
                </button>
              </div>
            </div>
            <div class="flex-1 px-6 py-4">
              <dl class="space-y-4">
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">Categorie</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ selectedCourse.category || '-' }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">Type</dt>
                  <dd>
                    <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-800">
                      {{ (selectedCourse.type || '').toUpperCase() }}
                    </span>
                  </dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">Statut</dt>
                  <dd><StatusBadge :status="selectedCourse.is_active ? 'active' : 'draft'" /></dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">Duree</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ selectedCourse.duration_hours || '-' }} heures</dd>
                </div>
                <div v-if="selectedCourse.provider" class="flex justify-between">
                  <dt class="text-sm text-gray-500">Prestataire</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ selectedCourse.provider }}</dd>
                </div>
                <div v-if="selectedCourse.max_participants" class="flex justify-between">
                  <dt class="text-sm text-gray-500">Places max</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ selectedCourse.max_participants }}</dd>
                </div>
                <div v-if="selectedCourse.cost_per_participant" class="flex justify-between">
                  <dt class="text-sm text-gray-500">Cout / participant</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ formatCurrency(selectedCourse.cost_per_participant, selectedCourse.currency) }}</dd>
                </div>
                <div v-if="selectedCourse.description" class="border-t pt-4">
                  <dt class="mb-1 text-sm text-gray-500">Description</dt>
                  <dd class="text-sm text-gray-700">{{ selectedCourse.description }}</dd>
                </div>
              </dl>

              <div v-if="courseSessions.length > 0" class="mt-6">
                <h3 class="mb-3 text-sm font-semibold text-gray-900">Sessions ({{ courseSessions.length }})</h3>
                <div class="space-y-2">
                  <div v-for="s in courseSessions" :key="s.id" class="rounded-md border border-gray-100 p-3">
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-gray-700">{{ s.start_date }} - {{ s.end_date }}</span>
                      <StatusBadge :status="s.status" :map="sessionStatusMap" />
                    </div>
                    <p v-if="s.location" class="mt-1 text-xs text-gray-500">{{ s.location }}</p>
                    <p v-if="s.instructor" class="text-xs text-gray-400">Formateur: {{ s.instructor }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'

const loading = ref(false)
const error = ref('')
const courses = ref([])
const sessions = ref([])
const enrollments = ref([])
const activeTab = ref('catalogue')
const selectedCourse = ref(null)

const stats = ref({ active_courses: 0, upcoming_sessions: 0, total_enrollments: 0, completion_rate: 0 })

const tabs = [
  { key: 'catalogue', label: 'Catalogue' },
  { key: 'sessions', label: 'Sessions' },
  { key: 'enrollments', label: 'Inscriptions' },
]

const sessionColumns = [
  { key: 'course_title', label: 'Formation', sortable: true },
  { key: 'instructor', label: 'Formateur', sortable: true },
  { key: 'start_date', label: 'Debut', sortable: true },
  { key: 'end_date', label: 'Fin', sortable: true },
  { key: 'location', label: 'Lieu', sortable: true },
  { key: 'enrolled_count', label: 'Inscrits', sortable: true },
  { key: 'status', label: 'Statut', sortable: true },
]

const enrollmentColumns = [
  { key: 'employee_name', label: 'Employe', sortable: true },
  { key: 'course_title', label: 'Formation', sortable: true },
  { key: 'session_date', label: 'Session', sortable: true },
  { key: 'progress', label: 'Progression' },
  { key: 'status', label: 'Statut', sortable: true },
]

const sessionStatusMap = {
  scheduled: { label: 'Planifiee', color: 'blue' },
  in_progress: { label: 'En cours', color: 'yellow' },
  completed: { label: 'Terminee', color: 'green' },
  cancelled: { label: 'Annulee', color: 'red' },
}

const enrollmentStatusMap = {
  enrolled: { label: 'Inscrit', color: 'blue' },
  in_progress: { label: 'En cours', color: 'yellow' },
  completed: { label: 'Termine', color: 'green' },
  dropped: { label: 'Abandonne', color: 'red' },
}

const courseSessions = computed(() => {
  if (!selectedCourse.value) return []
  return sessions.value.filter(s => s.training_course_id === selectedCourse.value.id || s.course_title === selectedCourse.value.title)
})

function formatCurrency(value, currency = 'EUR') {
  if (!value && value !== 0) return '-'
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: currency || 'EUR' }).format(value)
}

function viewCourse(course) {
  selectedCourse.value = course
}

function closeDetail() {
  selectedCourse.value = null
}

async function fetchData() {
  loading.value = true
  error.value = ''
  try {
    const [coursesRes, sessionsRes, enrollRes] = await Promise.all([
      api.get('/v1/training/courses'),
      api.get('/v1/training/sessions').catch(() => ({ data: { data: [] } })),
      api.get('/v1/training/enrollments').catch(() => ({ data: { data: [] } })),
    ])
    courses.value = coursesRes.data.data || coursesRes.data || []
    sessions.value = sessionsRes.data.data || sessionsRes.data || []
    enrollments.value = enrollRes.data.data || enrollRes.data || []
    const completed = enrollments.value.filter(e => e.status === 'completed')
    stats.value = {
      active_courses: courses.value.filter(c => c.is_active).length,
      upcoming_sessions: sessions.value.filter(s => s.status === 'scheduled').length,
      total_enrollments: enrollments.value.length,
      completion_rate: enrollments.value.length > 0 ? Math.round(completed.length / enrollments.value.length * 100) : 0,
    }
  } catch {
    error.value = 'Impossible de charger les donnees de formation.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)
</script>
