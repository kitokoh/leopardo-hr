<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('travel.quiz.title', 'Quiz & jeux-concours') }}
      </h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        {{ t('travel.quiz.subtitle', 'Création des quiz, gestion des questions et résultats (score calculé serveur).') }}
      </p>
    </div>

    <TravelGate :mode="gateMode" :message="loadError" @retry="init" />

    <template v-if="!gateMode">
      <div v-if="selectedQuiz" class="mb-4">
        <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="selectedQuiz = null">
          ← {{ t('travel.quiz.back', 'Retour aux quiz') }}
        </button>
      </div>

      <!-- ── Liste des quiz ─────────────────────────────────────────────── -->
      <template v-if="!selectedQuiz">
        <div class="flex items-center justify-between gap-3">
          <div class="flex flex-wrap gap-2">
            <button
              v-for="status in statuses"
              :key="status.value"
              class="rounded-full px-3 py-1 text-xs font-medium"
              :class="statusFilter === status.value
                ? 'bg-brand-500 text-white'
                : 'glass-card text-slate-600 ring-1 ring-slate-200 dark:text-slate-400 dark:ring-slate-700'"
              @click="setStatusFilter(status.value)"
            >
              {{ status.label }}
            </button>
          </div>
          <button class="btn-primary inline-flex items-center gap-1.5" @click="openCreate">
            {{ t('travel.quiz.createQuiz', 'Créer un quiz') }}
          </button>
        </div>

        <DataTable
          :columns="quizColumns"
          :rows="quizzes"
          :loading="loading"
          :error="listError"
          :search-keys="['title']"
          :search-placeholder="t('travel.common.search', 'Rechercher…')"
          :empty-message="t('travel.common.noData', 'Aucun quiz')"
          key-field="id"
        >
          <template #cell-status="{ value }">
            <StatusBadge :status="value" :map="statusMap" />
          </template>
          <template #row-actions="{ row }">
            <div class="flex justify-end gap-2">
              <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="openQuiz(row)">
                {{ t('travel.quiz.manage', 'Gérer') }}
              </button>
              <button class="text-sm font-medium text-slate-600 hover:text-slate-800 dark:text-slate-400" @click="openEdit(row)">
                {{ t('travel.common.edit', 'Modifier') }}
              </button>
              <button class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="askDelete(row)">
                {{ t('travel.common.delete', 'Supprimer') }}
              </button>
            </div>
          </template>
        </DataTable>
      </template>

      <!-- ── Détail quiz : questions + résultats ────────────────────────── -->
      <template v-else>
        <div class="flex items-center justify-between gap-3">
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ selectedQuiz.title }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">
              {{ t('travel.quiz.maxAttempts', 'Tentatives max') }} : {{ selectedQuiz.max_attempts }}
            </p>
          </div>
          <button class="btn-primary inline-flex items-center gap-1.5" @click="openQuestionCreate">
            {{ t('travel.quiz.addQuestion', 'Ajouter une question') }}
          </button>
        </div>

        <DataTable
          :columns="questionColumns"
          :rows="questions"
          :loading="questionsLoading"
          :error="questionsError"
          :search-keys="['question']"
          :search-placeholder="t('travel.common.search', 'Rechercher…')"
          :empty-message="t('travel.quiz.noQuestions', 'Aucune question')"
          key-field="id"
        >
          <template #cell-options="{ value }">
            <span class="text-sm">{{ Array.isArray(value) ? value.length : 0 }} {{ t('travel.quiz.options', 'options') }}</span>
          </template>
          <template #row-actions="{ row }">
            <div class="flex justify-end gap-2">
              <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="openQuestionEdit(row)">
                {{ t('travel.common.edit', 'Modifier') }}
              </button>
              <button class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="askQuestionDelete(row)">
                {{ t('travel.common.delete', 'Supprimer') }}
              </button>
            </div>
          </template>
        </DataTable>

        <div class="flex items-center justify-between gap-3 pt-4">
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
            {{ t('travel.quiz.results', 'Résultats') }}
          </h3>
          <button class="btn-secondary text-sm" @click="loadParticipations">
            {{ t('travel.quiz.refresh', 'Rafraîchir') }}
          </button>
        </div>
        <DataTable
          :columns="participationColumns"
          :rows="participations"
          :loading="participationsLoading"
          :error="participationsError"
          :search-keys="[]"
          :empty-message="t('travel.quiz.noParticipations', 'Aucune participation')"
          key-field="id"
        />
      </template>

      <!-- Modales -->
      <TravelFormModal
        :open="quizModalOpen"
        :title="quizEditing ? t('travel.common.edit', 'Modifier') : t('travel.quiz.createQuiz', 'Créer un quiz')"
        :fields="quizFormFields"
        :values="quizEditing || {}"
        :busy="saving"
        :error="formError"
        @save="saveQuiz"
        @close="quizModalOpen = false"
      />
      <TravelFormModal
        :open="questionModalOpen"
        :title="questionEditing ? t('travel.common.edit', 'Modifier') : t('travel.quiz.addQuestion', 'Ajouter une question')"
        :fields="questionFormFields"
        :values="questionValues"
        :busy="saving"
        :error="formError"
        @save="saveQuestion"
        @close="questionModalOpen = false"
      />
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/ui/DataTable.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import TravelFormModal from '@/components/travel/TravelFormModal.vue'
import TravelGate from '@/components/travel/TravelGate.vue'
import {
  createTravel, deleteTravel, getTravel, listTravel, updateTravel, travelList,
  createQuizQuestion, updateQuizQuestion, deleteQuizQuestion, quizParticipations,
} from '@/services/travel'

const { t } = useI18n()

const gateMode = ref('')
const loadError = ref('')
const statusFilter = ref('all')
const quizzes = ref([])
const loading = ref(false)
const listError = ref('')
const selectedQuiz = ref(null)
const questions = ref([])
const questionsLoading = ref(false)
const questionsError = ref('')
const participations = ref([])
const participationsLoading = ref(false)
const participationsError = ref('')

const statuses = [
  { value: 'all', label: t('travel.quiz.statusAll', 'Tous') },
  { value: 'draft', label: t('travel.quiz.statusDraft', 'Brouillon') },
  { value: 'published', label: t('travel.quiz.statusPublished', 'Publié') },
  { value: 'archived', label: t('travel.quiz.statusArchived', 'Archivé') },
]

const statusMap = Object.fromEntries(
  statuses.filter((s) => s.value !== 'all').map((s) => [s.value, { label: s.label }]),
)

const quizColumns = [
  { key: 'title', label: t('travel.quiz.title', 'Titre'), sortable: true },
  { key: 'status', label: t('travel.common.status', 'Statut'), sortable: true },
  { key: 'max_attempts', label: t('travel.quiz.maxAttempts', 'Tentatives max'), sortable: true },
]

function setStatusFilter(value) {
  statusFilter.value = value
  loadQuizzes()
}

async function loadQuizzes() {
  loading.value = true
  listError.value = ''
  try {
    const params = statusFilter.value === 'all' ? {} : { status: statusFilter.value }
    const res = await listTravel('quizzes', params)
    quizzes.value = travelList(res)
  } catch (err) {
    listError.value = err?.response?.data?.message || String(err)
  } finally {
    loading.value = false
  }
}

async function openQuiz(row) {
  selectedQuiz.value = row
  await loadQuestions()
  await loadParticipations()
}

async function loadQuestions() {
  if (!selectedQuiz.value) return
  questionsLoading.value = true
  questionsError.value = ''
  try {
    const res = await getTravel('quizzes', selectedQuiz.value.id)
    questions.value = travelList(res)?.questions ?? []
  } catch (err) {
    questionsError.value = err?.response?.data?.message || String(err)
  } finally {
    questionsLoading.value = false
  }
}

async function loadParticipations() {
  if (!selectedQuiz.value) return
  participationsLoading.value = true
  participationsError.value = ''
  try {
    const res = await quizParticipations(selectedQuiz.value.id)
    participations.value = travelList(res)
  } catch (err) {
    participationsError.value = err?.response?.data?.message || String(err)
  } finally {
    participationsLoading.value = false
  }
}

const questionColumns = [
  { key: 'question', label: t('travel.quiz.question', 'Question'), sortable: true },
  { key: 'options', label: t('travel.quiz.options', 'Options') },
  { key: 'points', label: t('travel.quiz.points', 'Points'), sortable: true },
  { key: 'sort_order', label: t('travel.quiz.order', 'Ordre'), sortable: true },
]

const participationColumns = [
  { key: 'participant_id', label: t('travel.quiz.participant', 'Participant'), sortable: true },
  { key: 'score', label: t('travel.quiz.score', 'Score'), sortable: true },
  { key: 'status', label: t('travel.common.status', 'Statut') },
  { key: 'completed_at', label: t('travel.quiz.completedAt', 'Terminé le') },
]

// ── Modales ───────────────────────────────────────────────────────────────

const saving = ref(false)
const formError = ref('')
const quizModalOpen = ref(false)
const quizEditing = ref(null)
const questionModalOpen = ref(false)
const questionEditing = ref(null)
const questionValues = ref({})

const quizFormFields = [
  { key: 'title', label: 'Titre', type: 'text', required: true, maxlength: 200 },
  { key: 'description_redacted', label: 'Description', type: 'textarea' },
  { key: 'max_attempts', label: 'Tentatives max', type: 'number', min: 1, max: 100 },
  {
    key: 'status', label: 'Statut', type: 'select', required: true,
    options: [
      { value: 'draft', label: 'Brouillon' },
      { value: 'published', label: 'Publié' },
      { value: 'archived', label: 'Archivé' },
    ],
  },
]

function openCreate() {
  quizEditing.value = null
  formError.value = ''
  quizModalOpen.value = true
}

function openEdit(row) {
  quizEditing.value = row
  formError.value = ''
  quizModalOpen.value = true
}

async function saveQuiz(values) {
  saving.value = true
  formError.value = ''
  try {
    if (quizEditing.value) {
      await updateTravel('quizzes', quizEditing.value.id, values)
    } else {
      await createTravel('quizzes', values)
    }
    quizModalOpen.value = false
    await loadQuizzes()
  } catch (err) {
    formError.value = err?.response?.data?.message || String(err)
  } finally {
    saving.value = false
  }
}

async function askDelete(row) {
  if (!window.confirm(t('travel.common.confirmDelete', 'Supprimer ce quiz ?'))) return
  try {
    await deleteTravel('quizzes', row.id)
    await loadQuizzes()
  } catch (err) {
    window.alert(err?.response?.data?.message || String(err))
  }
}

const questionFormFields = computed(() => [
  { key: 'question', label: t('travel.quiz.question', 'Question'), type: 'text', required: true, maxlength: 500 },
  {
    key: 'options', label: t('travel.quiz.options', 'Options (une par ligne)'), type: 'textarea', required: true,
  },
  { key: 'correct_option_index', label: t('travel.quiz.correctIndex', 'Index de la bonne réponse (0..n-1)'), type: 'number', min: 0, required: true },
  { key: 'points', label: t('travel.quiz.points', 'Points'), type: 'number', min: 1, max: 1000 },
  { key: 'sort_order', label: t('travel.quiz.order', 'Ordre'), type: 'number', min: 0 },
])

function openQuestionCreate() {
  questionEditing.value = null
  questionValues.value = { points: 1, sort_order: questions.value.length + 1 }
  formError.value = ''
  questionModalOpen.value = true
}

function openQuestionEdit(row) {
  questionEditing.value = row
  questionValues.value = {
    question: row.question,
    options: Array.isArray(row.options) ? row.options.join('\n') : '',
    correct_option_index: row.correct_option_index,
    points: row.points,
    sort_order: row.sort_order,
  }
  formError.value = ''
  questionModalOpen.value = true
}

async function saveQuestion(values) {
  const quizId = selectedQuiz.value?.id
  if (!quizId) return
  const options = String(values.options ?? '')
    .split('\n')
    .map((o) => o.trim())
    .filter(Boolean)
  if (options.length < 2) {
    formError.value = t('travel.quiz.optionsMin', 'Au moins 2 options requises.')
    return
  }
  const payload = {
    question: values.question,
    options,
    correct_option_index: Number(values.correct_option_index),
    points: Number(values.points ?? 1),
    sort_order: Number(values.sort_order ?? 0),
  }
  saving.value = true
  formError.value = ''
  try {
    if (questionEditing.value) {
      await updateQuizQuestion(quizId, questionEditing.value.id, payload)
    } else {
      await createQuizQuestion(quizId, payload)
    }
    questionModalOpen.value = false
    await loadQuestions()
  } catch (err) {
    formError.value = err?.response?.data?.message || String(err)
  } finally {
    saving.value = false
  }
}

async function askQuestionDelete(row) {
  if (!selectedQuiz.value) return
  if (!window.confirm(t('travel.common.confirmDelete', 'Supprimer cette question ?'))) return
  try {
    await deleteQuizQuestion(selectedQuiz.value.id, row.id)
    await loadQuestions()
  } catch (err) {
    window.alert(err?.response?.data?.message || String(err))
  }
}

function init() {
  gateMode.value = ''
  loadError.value = ''
  loadQuizzes()
}

onMounted(init)
</script>
