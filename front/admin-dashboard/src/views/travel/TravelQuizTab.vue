<template>
  <div class="space-y-6">
    <TravelCrudSection
      :config="quizConfig"
      :column-display="columnsDisplay"
      @action="onRowAction"
    />

    <!-- Modale questions d'un quiz (TRAVEL-904/#6107) : la bonne réponse
         (correct_option_index) n'est jamais exposée par l'API et n'est pas
         affichée ici. -->
    <TravelModal
      :open="questionsOpen"
      :title="t('travel.quiz.questionsTitle', 'Questions du quiz')"
      wide
      @close="closeQuestions"
    >
      <div v-if="questionsError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
        {{ questionsError }}
      </div>

      <div class="overflow-x-auto rounded-lg border border-slate-200/50 dark:border-slate-800/50">
        <table class="min-w-full divide-y divide-slate-200/50 text-sm dark:divide-slate-800/50">
          <thead class="bg-slate-50/50 dark:bg-slate-800/50">
            <tr>
              <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ t('travel.quiz.field.question', 'Question') }}
              </th>
              <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ t('travel.quiz.field.options', 'Choix') }}
              </th>
              <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ t('travel.quiz.field.points', 'Points') }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr v-for="q in questions" :key="q.id" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/70">
              <td class="px-4 py-2 align-top font-medium text-slate-700 dark:text-slate-300">{{ q.question }}</td>
              <td class="px-4 py-2 align-top text-slate-600 dark:text-slate-400">
                <ol class="list-inside list-decimal space-y-0.5">
                  <li v-for="(opt, i) in q.options" :key="i">{{ opt }}</li>
                </ol>
              </td>
              <td class="whitespace-nowrap px-4 py-2 text-slate-700 dark:text-slate-300">{{ q.points }}</td>
            </tr>
            <tr v-if="questions.length === 0 && !questionsLoading">
              <td :colspan="3" class="px-4 py-4 text-center text-xs text-slate-400">
                {{ t('travel.table.emptyNested', 'Aucun élément.') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <button type="button" class="btn-secondary mt-3 text-sm" @click="openAddQuestion">
        <PlusIcon class="mr-1.5 h-4 w-4" />
        {{ t('travel.quiz.action.addQuestion', 'Ajouter une question') }}
      </button>
    </TravelModal>

    <!-- Ajout d'une question -->
    <TravelModal
      :open="addQuestionOpen"
      :title="t('travel.quiz.action.addQuestion', 'Ajouter une question')"
      @close="closeAddQuestion"
    >
      <form class="grid grid-cols-1 gap-4" @submit.prevent="saveQuestion">
        <FormField
          :id="'travel-quiz-question'"
          :label="t('travel.quiz.field.question', 'Question')"
          :error="questionErrors.question"
          required
        >
          <input v-model.trim="questionForm.question" type="text" class="form-input" required maxlength="500" />
        </FormField>

        <FormField
          :id="'travel-quiz-options'"
          :label="t('travel.quiz.field.options', 'Choix')"
          :hint="t('travel.quiz.hint.options', 'Une option par ligne.')"
          :error="questionErrors.options"
          required
        >
          <textarea v-model="questionForm.optionsText" class="form-input" rows="4" required></textarea>
        </FormField>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormField
            :id="'travel-quiz-correct'"
            :label="t('travel.quiz.field.correctIndex', 'Index de la bonne réponse')"
            :hint="t('travel.quiz.hint.correctIndex', 'Commence à 0 (première ligne = 0).')"
            :error="questionErrors.correct_option_index"
            required
          >
            <input v-model.number="questionForm.correct_option_index" type="number" class="form-input" min="0" required />
          </FormField>
          <FormField
            :id="'travel-quiz-points'"
            :label="t('travel.quiz.field.points', 'Points')"
            :error="questionErrors.points"
          >
            <input v-model.number="questionForm.points" type="number" class="form-input" min="1" max="100" />
          </FormField>
        </div>

        <div v-if="questionGlobalError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
          {{ questionGlobalError }}
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="closeAddQuestion">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button type="submit" class="btn-primary" :disabled="questionSaving">
            {{ questionSaving ? $t('common.busy', 'En cours…') : $t('travel.action.save', 'Enregistrer') }}
          </button>
        </div>
      </form>
    </TravelModal>

    <!-- Résultats (triés par score, serveur) -->
    <TravelModal
      :open="resultsOpen"
      :title="t('travel.quiz.resultsTitle', 'Résultats')"
      wide
      @close="closeResults"
    >
      <div v-if="resultsError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
        {{ resultsError }}
      </div>
      <DataTable
        :columns="resultsColumns"
        :rows="results"
        :loading="resultsLoading"
        :caption="t('travel.quiz.resultsTitle', 'Résultats')"
      />
    </TravelModal>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { PlusIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import FormField from '@/components/common/FormField.vue'
import TravelCrudSection from '@/components/travel/TravelCrudSection.vue'
import TravelModal from '@/components/travel/TravelModal.vue'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const toast = useToast()

/* ── statuts quiz ───────────────────────────────────────────── */
const statusMap = {
  draft: { labelKey: 'travel.quizStatus.draft', color: 'gray' },
  active: { labelKey: 'travel.quizStatus.active', color: 'green' },
  closed: { labelKey: 'travel.quizStatus.closed', color: 'blue' }
}

const quizConfig = computed(() => ({
  resource: 'quizzes',
  titleKey: 'travel.quiz.title',
  titleFallback: 'Quiz & jeux-concours',
  subtitleKey: 'travel.quiz.subtitle',
  searchPlaceholderKey: 'travel.search.quiz',
  searchKeys: ['title'],
  defaultSort: 'id',
  statusField: 'status',
  statusMap,
  canEdit: false,
  canDelete: false,
  columns: [
    { key: 'title', label: 'travel.field.title', sortable: true },
    { key: 'status', label: 'travel.field.status', sortable: true },
    { key: 'starts_at', label: 'travel.field.startDate', sortable: true },
    { key: 'ends_at', label: 'travel.field.endDate', sortable: true }
  ],
  fields: [
    { key: 'title', label: 'travel.field.title', type: 'text', required: true, max: 160 },
    { key: 'description', label: 'travel.field.description', type: 'textarea' },
    { key: 'starts_at', label: 'travel.field.startDate', type: 'datetime-local' },
    { key: 'ends_at', label: 'travel.field.endDate', type: 'datetime-local' },
    { key: 'max_participations_per_contact', label: 'travel.quiz.field.maxParticipations', type: 'number', min: 1, max: 100 },
    {
      key: 'status', label: 'travel.field.status', type: 'select',
      options: [
        { value: 'draft', label: t('travel.quizStatus.draft', 'Brouillon') },
        { value: 'active', label: t('travel.quizStatus.active', 'Actif') },
        { value: 'closed', label: t('travel.quizStatus.closed', 'Clôturé') }
      ]
    }
  ],
  defaults: { status: 'draft', max_participations_per_contact: 1 },
  rowActions: [
    { key: 'questions', label: 'travel.quiz.action.questions', labelFallback: 'Questions' },
    { key: 'results', label: 'travel.quiz.action.results', labelFallback: 'Résultats' }
  ]
}))

const columnsDisplay = computed(() => ({
  starts_at: (row, value) => formatDate(value),
  ends_at: (row, value) => formatDate(value)
}))

function formatDate(iso) {
  if (!iso) return '-'
  try {
    return new Date(iso).toLocaleString(localeStore.current)
  } catch {
    return String(iso)
  }
}

/* ── actions de ligne : questions / résultats ─────────────── */
const activeQuiz = ref(null)
const questionsOpen = ref(false)
const questions = ref([])
const questionsLoading = ref(false)
const questionsError = ref('')

const addQuestionOpen = ref(false)
const questionForm = ref({ question: '', optionsText: '', correct_option_index: 0, points: 1 })
const questionErrors = ref({})
const questionGlobalError = ref('')
const questionSaving = ref(false)

const resultsOpen = ref(false)
const results = ref([])
const resultsLoading = ref(false)
const resultsError = ref('')

const resultsColumns = computed(() => [
  { key: 'participant_name', label: t('travel.quiz.field.participantName', 'Nom') },
  { key: 'participant_email', label: t('travel.quiz.field.participantEmail', 'Email') },
  { key: 'score', label: t('travel.quiz.field.score', 'Score') },
  { key: 'bonus', label: t('travel.quiz.field.bonus', 'Bonus') },
  { key: 'submitted_at', label: t('travel.quiz.field.submittedAt', 'Soumis le') }
])

function onRowAction({ key, row }) {
  if (key === 'questions') {
    openQuestions(row)
  } else if (key === 'results') {
    openResults(row)
  }
}

async function openQuestions(row) {
  activeQuiz.value = row
  questionsOpen.value = true
  questionsError.value = ''
  questionsLoading.value = true
  try {
    const res = await api.get(`/travel/quizzes/${row.id}`, { _skipAuthRedirect: true })
    questions.value = res.data?.data?.questions || []
  } catch (err) {
    questionsError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    questionsLoading.value = false
  }
}

function closeQuestions() {
  questionsOpen.value = false
  addQuestionOpen.value = false
  activeQuiz.value = null
}

function openAddQuestion() {
  questionForm.value = { question: '', optionsText: '', correct_option_index: 0, points: 1 }
  questionErrors.value = {}
  questionGlobalError.value = ''
  addQuestionOpen.value = true
}

function closeAddQuestion() {
  addQuestionOpen.value = false
}

async function saveQuestion() {
  if (!activeQuiz.value) return
  questionSaving.value = true
  questionErrors.value = {}
  questionGlobalError.value = ''
  const options = (questionForm.value.optionsText || '')
    .split('\n')
    .map((s) => s.trim())
    .filter(Boolean)
  try {
    await api.post(
      `/travel/quizzes/${activeQuiz.value.id}/questions`,
      {
        question: questionForm.value.question,
        options,
        correct_option_index: questionForm.value.correct_option_index,
        points: questionForm.value.points ?? 1
      },
      { _skipAuthRedirect: true }
    )
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    addQuestionOpen.value = false
    await openQuestions(activeQuiz.value)
  } catch (err) {
    const data = err.response?.data || {}
    if (data.errors) {
      questionErrors.value = Object.fromEntries(
        Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
      )
    }
    questionGlobalError.value = data.message || data.localized_message || t('travel.error.saveFailed', "Échec de l'enregistrement.")
  } finally {
    questionSaving.value = false
  }
}

async function openResults(row) {
  activeQuiz.value = row
  resultsOpen.value = true
  resultsError.value = ''
  resultsLoading.value = true
  try {
    const res = await api.get(`/travel/quizzes/${row.id}/results`, { _skipAuthRedirect: true })
    results.value = res.data?.data || []
  } catch (err) {
    resultsError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    resultsLoading.value = false
  }
}

function closeResults() {
  resultsOpen.value = false
  activeQuiz.value = null
}
</script>
