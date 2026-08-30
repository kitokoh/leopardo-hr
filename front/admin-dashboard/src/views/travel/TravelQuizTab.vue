<template>
  <section class="space-y-4">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">
          {{ $t('travel.quiz.title', 'Quiz & jeux-concours') }}
        </h2>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('travel.quiz.subtitle', 'Jeux-concours de la verticale : notation serveur, la bonne réponse n\u2019est jamais exposée.') }}
        </p>
      </div>
      <button class="btn-primary" type="button" @click="openCreate">
        <PlusIcon class="mr-2 h-4 w-4" />
        {{ $t('travel.action.create', 'Créer') }}
      </button>
    </div>

    <div v-if="globalError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
      {{ globalError }}
    </div>

    <DataTable
      :columns="columns"
      :rows="quizzes"
      :loading="loading"
      :error="listError"
      :search-keys="['title']"
      :search-placeholder="$t('travel.search.quiz', 'Rechercher un quiz…')"
      default-sort="id"
      default-sort-dir="desc"
      :caption="$t('travel.quiz.title', 'Quiz & jeux-concours')"
    >
      <template #cell-status="{ value }">
        <StatusBadge :status="value" :map="statusMap" />
      </template>
      <template #row-actions="{ row }">
        <div class="flex items-center justify-end gap-1">
          <button
            class="rounded-md px-2 py-1.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
            type="button"
            :aria-label="$t('travel.action.view', 'Voir')"
            @click="openDetail(row)"
          >
            {{ $t('travel.action.view', 'Voir') }}
          </button>
          <button
            class="rounded-md px-2 py-1.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
            type="button"
            :aria-label="$t('travel.quiz.viewResults', 'Résultats')"
            @click="openResults(row)"
          >
            {{ $t('travel.quiz.viewResults', 'Résultats') }}
          </button>
        </div>
      </template>
    </DataTable>

    <!-- Création d'un quiz -->
    <TravelModal :open="createOpen" :title="$t('travel.quiz.createTitle', 'Créer un quiz')" wide @close="closeCreate">
      <form class="grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="saveQuiz">
        <FormField
          id="travel-quiz-title"
          :label="$t('travel.field.title', 'Intitulé')"
          :error="formErrors.title"
          required
        >
          <input v-model="form.title" type="text" class="form-input" required :maxlength="200" />
        </FormField>
        <FormField
          id="travel-quiz-status"
          :label="$t('travel.field.status', 'Statut')"
          :error="formErrors.status"
        >
          <select v-model="form.status" class="form-input">
            <option value="draft">{{ $t('travel.quizStatus.draft', 'Brouillon') }}</option>
            <option value="active">{{ $t('travel.quizStatus.active', 'Actif') }}</option>
            <option value="closed">{{ $t('travel.quizStatus.closed', 'Clôturé') }}</option>
          </select>
        </FormField>
        <FormField
          id="travel-quiz-description"
          :label="$t('travel.field.description', 'Description')"
          class="sm:col-span-2"
          :error="formErrors.description"
        >
          <textarea v-model="form.description" class="form-input" rows="3" :maxlength="2000"></textarea>
        </FormField>
        <FormField id="travel-quiz-starts" :label="$t('travel.field.startDate', 'Début')" :error="formErrors.starts_at">
          <input v-model="form.starts_at" type="datetime-local" class="form-input" />
        </FormField>
        <FormField id="travel-quiz-ends" :label="$t('travel.field.endDate', 'Fin')" :error="formErrors.ends_at">
          <input v-model="form.ends_at" type="datetime-local" class="form-input" />
        </FormField>
        <FormField
          id="travel-quiz-max"
          :label="$t('travel.quiz.maxParticipation', 'Participations max. par contact')"
          :error="formErrors.max_participations_per_contact"
        >
          <input v-model.number="form.max_participations_per_contact" type="number" min="1" step="1" class="form-input" />
        </FormField>
        <div class="col-span-full flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="closeCreate">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button type="submit" class="btn-primary" :disabled="saving">
            {{ saving ? $t('common.busy', 'En cours…') : $t('travel.action.create', 'Créer') }}
          </button>
        </div>
      </form>
    </TravelModal>

    <!-- Détail d'un quiz (questions sans bonne réponse) -->
    <TravelModal
      :open="detailOpen"
      :title="detail?.title || $t('travel.quiz.detailTitle', 'Détail du quiz')"
      wide
      @close="closeDetail"
    >
      <div v-if="detailLoading" class="py-8 text-center text-sm text-slate-400">
        {{ $t('travel.loading', 'Chargement…') }}
      </div>
      <div v-else-if="detailError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
        {{ detailError }}
      </div>
      <div v-else-if="detail" class="space-y-4">
        <div class="flex flex-wrap items-center gap-2 text-sm">
          <StatusBadge :status="detail.status" :map="statusMap" />
          <span class="text-slate-500 dark:text-slate-400">{{ detail.description }}</span>
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-200/50 dark:border-slate-800/50">
          <table class="min-w-full divide-y divide-slate-200/50 text-sm dark:divide-slate-800/50">
            <thead class="bg-slate-50/50 dark:bg-slate-800/50">
              <tr>
                <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                  {{ $t('travel.field.position', 'Position') }}
                </th>
                <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                  {{ $t('travel.field.question', 'Question') }}
                </th>
                <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                  {{ $t('travel.field.points', 'Points') }}
                </th>
                <th class="px-4 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                  {{ $t('travel.table.actions', 'Actions') }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <tr v-for="(q, i) in detail.questions" :key="q.id" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/70">
                <td class="whitespace-nowrap px-4 py-2 text-slate-700 dark:text-slate-300">{{ i + 1 }}</td>
                <td class="px-4 py-2 text-slate-700 dark:text-slate-300">
                  <p class="font-medium">{{ q.question }}</p>
                  <ul class="mt-1 list-inside list-disc text-xs text-slate-500 dark:text-slate-400">
                    <li v-for="(opt, oi) in q.options" :key="oi">{{ opt }}</li>
                  </ul>
                </td>
                <td class="whitespace-nowrap px-4 py-2 text-slate-700 dark:text-slate-300">{{ q.points }}</td>
                <td class="whitespace-nowrap px-4 py-2 text-right">
                  <button
                    class="rounded-md p-1 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600"
                    type="button"
                    :aria-label="$t('travel.action.delete', 'Supprimer')"
                    :disabled="true"
                    :title="$t('travel.quiz.questionDeleteUnavailable', 'Suppression non disponible via l\u2019API actuelle')"
                  >
                    <TrashIcon class="h-4 w-4 opacity-30" />
                  </button>
                </td>
              </tr>
              <tr v-if="detail.questions.length === 0">
                <td colspan="4" class="px-4 py-4 text-center text-xs text-slate-400">
                  {{ $t('travel.quiz.noQuestions', 'Aucune question pour le moment.') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex flex-wrap justify-end gap-2">
          <button type="button" class="btn-secondary text-sm" @click="openResults(detail)">
            {{ $t('travel.quiz.viewResults', 'Résultats') }}
          </button>
          <button type="button" class="btn-primary text-sm" @click="openQuestionForm">
            <PlusIcon class="mr-1.5 h-4 w-4" />
            {{ $t('travel.quiz.addQuestion', 'Ajouter une question') }}
          </button>
        </div>
      </div>
    </TravelModal>

    <!-- Ajout d'une question -->
    <TravelModal
      :open="questionFormOpen"
      :title="$t('travel.quiz.addQuestion', 'Ajouter une question')"
      wide
      @close="closeQuestionForm"
    >
      <form class="grid grid-cols-1 gap-4" @submit.prevent="saveQuestion">
        <FormField id="travel-quiz-q" :label="$t('travel.field.question', 'Question')" :error="questionErrors.question" required>
          <input v-model="questionForm.question" type="text" class="form-input" required :maxlength="500" />
        </FormField>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <FormField
            v-for="i in 4"
            :id="`travel-quiz-opt-${i}`"
            :key="i"
            :label="$t('travel.quiz.option', 'Option') + ' ' + i"
            :error="questionErrors[`options.${i - 1}`]"
            :required="i === 1"
          >
            <input
              v-model="questionForm.options[i - 1]"
              type="text"
              class="form-input"
              :required="i === 1"
              :maxlength="300"
            />
          </FormField>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormField
            id="travel-quiz-correct"
            :label="$t('travel.quiz.correctOption', 'Bonne réponse')"
            :error="questionErrors.correct_option_index"
            required
          >
            <select v-model.number="questionForm.correct_option_index" class="form-input" required>
              <option v-for="(opt, i) in questionForm.options" :key="i" :value="i">
                {{ $t('travel.quiz.option', 'Option') + ' ' + (i + 1) }}
              </option>
            </select>
          </FormField>
          <FormField id="travel-quiz-points" :label="$t('travel.field.points', 'Points')" :error="questionErrors.points">
            <input v-model.number="questionForm.points" type="number" min="1" step="1" class="form-input" />
          </FormField>
        </div>
        <div class="col-span-full flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="closeQuestionForm">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button type="submit" class="btn-primary" :disabled="questionSaving">
            {{ questionSaving ? $t('common.busy', 'En cours…') : $t('travel.action.save', 'Enregistrer') }}
          </button>
        </div>
      </form>
    </TravelModal>

    <!-- Résultats d'un quiz -->
    <TravelModal
      :open="resultsOpen"
      :title="$t('travel.quiz.resultsTitle', 'Résultats du quiz')"
      wide
      @close="closeResults"
    >
      <div v-if="resultsLoading" class="py-8 text-center text-sm text-slate-400">
        {{ $t('travel.loading', 'Chargement…') }}
      </div>
      <div v-else-if="resultsError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
        {{ resultsError }}
      </div>
      <div v-else>
        <DataTable
          :columns="resultColumns"
          :rows="results"
          :loading="false"
          default-sort="score"
          default-sort-dir="desc"
          :caption="$t('travel.quiz.resultsTitle', 'Résultats du quiz')"
        />
      </div>
    </TravelModal>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import FormField from '@/components/common/FormField.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import TravelModal from '@/components/travel/TravelModal.vue'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const toast = useToast()
const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const quizzes = ref([])
const loading = ref(false)
const listError = ref('')
const globalError = ref('')

const createOpen = ref(false)
const saving = ref(false)
const form = ref({})
const formErrors = ref({})

const detailOpen = ref(false)
const detailLoading = ref(false)
const detailError = ref('')
const detail = ref(null)

const questionFormOpen = ref(false)
const questionSaving = ref(false)
const questionForm = ref({})
const questionErrors = ref({})

const resultsOpen = ref(false)
const resultsLoading = ref(false)
const resultsError = ref('')
const results = ref([])

const statusMap = computed(() => ({
  draft: { label: t('travel.quizStatus.draft', 'Brouillon'), color: 'gray' },
  active: { label: t('travel.quizStatus.active', 'Actif'), color: 'green' },
  closed: { label: t('travel.quizStatus.closed', 'Clôturé'), color: 'amber' },
}))

const columns = computed(() => [
  { key: 'id', label: t('travel.field.id', 'ID'), sortable: true },
  { key: 'title', label: t('travel.field.title', 'Intitulé'), sortable: true },
  { key: 'status', label: t('travel.field.status', 'Statut'), sortable: true },
  { key: 'starts_at', label: t('travel.field.startDate', 'Début'), sortable: true },
  { key: 'ends_at', label: t('travel.field.endDate', 'Fin'), sortable: true },
])

const resultColumns = computed(() => [
  { key: 'participant_email', label: t('travel.field.email', 'Email'), sortable: true },
  { key: 'participant_name', label: t('travel.field.fullName', 'Nom complet') },
  { key: 'score', label: t('travel.quiz.score', 'Score'), sortable: true },
  { key: 'bonus', label: t('travel.quiz.bonus', 'Bonus') },
  { key: 'submitted_at', label: t('travel.field.issuedAt', 'Date'), sortable: true },
])

async function load() {
  loading.value = true
  listError.value = ''
  try {
    const res = await api.get('/travel/quizzes', { params: { per_page: 100 }, _skipAuthRedirect: true })
    quizzes.value = res.data?.data || []
  } catch (err) {
    listError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  form.value = {
    title: '',
    description: '',
    starts_at: '',
    ends_at: '',
    max_participations_per_contact: 1,
    status: 'draft',
  }
  formErrors.value = {}
  globalError.value = ''
  createOpen.value = true
}

function closeCreate() {
  createOpen.value = false
}

async function saveQuiz() {
  saving.value = true
  globalError.value = ''
  formErrors.value = {}
  try {
    const payload = { ...form.value }
    if (!payload.starts_at) delete payload.starts_at
    if (!payload.ends_at) delete payload.ends_at
    await api.post('/travel/quizzes', payload, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    createOpen.value = false
    await load()
  } catch (err) {
    const data = err.response?.data || {}
    if (data.errors) {
      formErrors.value = Object.fromEntries(Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]))
    }
    globalError.value = data.message || data.localized_message || t('travel.error.saveFailed', "Échec de l'enregistrement.")
  } finally {
    saving.value = false
  }
}

async function openDetail(row) {
  detail.value = null
  detailError.value = ''
  detailOpen.value = true
  detailLoading.value = true
  try {
    const res = await api.get(`/travel/quizzes/${row.id}`, { _skipAuthRedirect: true })
    detail.value = res.data?.data
  } catch (err) {
    detailError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    detailLoading.value = false
  }
}

function closeDetail() {
  detailOpen.value = false
}

function openQuestionForm() {
  questionForm.value = { question: '', options: ['', '', '', ''], correct_option_index: 0, points: 1 }
  questionErrors.value = {}
  questionFormOpen.value = true
}

function closeQuestionForm() {
  questionFormOpen.value = false
}

async function saveQuestion() {
  if (!detail.value) return
  questionSaving.value = true
  questionErrors.value = {}
  try {
    const options = questionForm.value.options.filter((o) => o && o.trim() !== '')
    const correct = questionForm.value.correct_option_index
    if (correct < 0 || correct >= options.length) {
      questionErrors.value = { correct_option_index: t('travel.quiz.correctRequired', 'La bonne réponse doit être une option remplie.') }
      return
    }
    const payload = {
      question: questionForm.value.question,
      options,
      correct_option_index: correct,
      points: questionForm.value.points || 1,
    }
    await api.post(`/travel/quizzes/${detail.value.id}/questions`, payload, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    questionFormOpen.value = false
    await openDetail(detail.value)
  } catch (err) {
    const data = err.response?.data || {}
    if (data.errors) {
      questionErrors.value = Object.fromEntries(Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]))
    }
    toast.error(data.message || t('travel.error.saveFailed', "Échec de l'enregistrement."))
  } finally {
    questionSaving.value = false
  }
}

async function openResults(row) {
  results.value = []
  resultsError.value = ''
  resultsOpen.value = true
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
}

onMounted(load)
</script>
