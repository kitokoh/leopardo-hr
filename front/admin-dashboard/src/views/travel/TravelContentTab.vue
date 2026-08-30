<template>
  <div class="space-y-8">
    <!-- ── Quiz ─────────────────────────────────────────────── -->
    <TravelCrudSection
      ref="quizSection"
      :config="quizConfig"
      @action="onQuizAction"
    />

      <!-- Questions d'un quiz (gestion admin : la bonne réponse est visible ici uniquement) -->
      <TravelModal
        :open="questionsOpen"
        :title="questionsTitle"
        wide
        @close="closeQuestions"
      >
        <div v-if="questionsError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert">
          {{ questionsError }}
        </div>
        <div class="space-y-3">
          <div
            v-for="question in questions"
            :key="question.id"
            class="rounded-lg border border-slate-200/60 p-3 dark:border-slate-800/60"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                  {{ question.position + 1 }}. {{ question.question }}
                </p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  {{ question.options.map((opt, idx) => `${idx}: ${opt}`).join(' · ') }}
                </p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  {{ $t('travel.quiz.correctIndex', 'Bonne réponse') }} : {{ question.correct_option_index }}
                  · {{ $t('travel.quiz.points', 'Points') }} : {{ question.points }}
                </p>
              </div>
              <div class="flex shrink-0 items-center gap-1">
                <button
                  class="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-800 dark:hover:bg-slate-800"
                  type="button"
                  :aria-label="$t('travel.action.edit', 'Modifier')"
                  @click="editQuestion(question)"
                >
                  <PencilSquareIcon class="h-4 w-4" />
                </button>
                <button
                  class="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600"
                  type="button"
                  :aria-label="$t('travel.action.delete', 'Supprimer')"
                  @click="deleteQuestion(question)"
                >
                  <TrashIcon class="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>
          <p v-if="questions.length === 0" class="py-4 text-center text-xs text-slate-400">
            {{ $t('travel.quiz.emptyQuestions', 'Aucune question pour ce quiz.') }}
          </p>
        </div>
        <form class="mt-4 space-y-3 rounded-lg border border-slate-200/60 p-3 dark:border-slate-800/60" @submit.prevent="saveQuestion">
          <p class="text-sm font-bold text-slate-700 dark:text-slate-300">
            {{ questionEditingId ? $t('travel.quiz.editQuestion', 'Modifier la question') : $t('travel.quiz.addQuestion', 'Ajouter une question') }}
          </p>
          <div v-if="questionError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert">
            {{ questionError }}
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="quiz-question-text">
              {{ $t('travel.quiz.questionLabel', 'Question') }}
            </label>
            <input
              id="quiz-question-text"
              v-model="questionDraft.question"
              class="form-input mt-1"
              type="text"
              maxlength="500"
              required
            />
          </div>
          <div>
            <span class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              {{ $t('travel.quiz.optionsLabel', 'Options (2 à 10)') }}
            </span>
            <div class="mt-2 space-y-2">
              <div v-for="(opt, idx) in questionDraft.options" :key="idx" class="flex items-center gap-2">
                <span class="w-6 shrink-0 text-xs font-semibold text-slate-400">{{ idx }}</span>
                <input
                  :value="optionValue(idx)"
                  class="form-input"
                  type="text"
                  maxlength="200"
                  required
                  @input="setOption(idx, $event)"
                />
                <button
                  class="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600"
                  type="button"
                  :aria-label="$t('travel.quiz.removeOption', 'Retirer l\u0027option')"
                  @click="removeOption(idx)"
                >
                  <XMarkIcon class="h-4 w-4" />
                </button>
              </div>
            </div>
            <button
              class="mt-2 text-xs font-semibold text-brand-600 hover:text-brand-700"
              type="button"
              @click="addOption"
            >
              + {{ $t('travel.quiz.addOption', 'Ajouter une option') }}
            </button>
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="quiz-correct-index">
                {{ $t('travel.quiz.correctIndex', 'Bonne réponse') }}
              </label>
              <select
                id="quiz-correct-index"
                v-model="questionDraft.correct_option_index"
                class="form-input mt-1"
              >
                <option v-for="(opt, idx) in questionDraft.options" :key="idx" :value="idx">{{ idx }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="quiz-points">
                {{ $t('travel.quiz.points', 'Points') }}
              </label>
              <input
                id="quiz-points"
                v-model="questionDraft.points"
                class="form-input mt-1"
                type="number"
                min="1"
                max="100"
              />
            </div>
            <div>
              <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="quiz-position">
                {{ $t('travel.quiz.position', 'Position') }}
              </label>
              <input
                id="quiz-position"
                v-model="questionDraft.position"
                class="form-input mt-1"
                type="number"
                min="0"
              />
            </div>
          </div>
          <div class="flex justify-end gap-2">
            <button class="btn-secondary" type="button" @click="resetQuestionDraft">
              {{ $t('common.cancel', 'Annuler') }}
            </button>
            <button class="btn-primary" type="submit" :disabled="savingQuestion">
              {{ savingQuestion ? $t('common.busy', 'En cours…') : $t('travel.action.save', 'Enregistrer') }}
            </button>
          </div>
        </form>
      </TravelModal>

      <!-- Résultats d'un quiz -->
      <TravelModal :open="resultsOpen" :title="resultsTitle" wide @close="closeResults">
        <DataTable
          :columns="resultsColumns"
          :rows="results"
          :loading="resultsLoading"
          :error="resultsError"
          :default-sort="scoreSortKey"
          :default-sort-dir="descSortDir"
          :caption="$t('travel.quiz.resultsTitle', 'Résultats')"
        />
      </TravelModal>

    <!-- ── Annonces payantes ─────────────────────────────────── -->
    <section class="space-y-4 border-t border-slate-200/50 pt-8 dark:border-slate-800/50">
      <div>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">
          {{ $t('travel.advert.title', 'Annonces payantes') }}
        </h2>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('travel.advert.subtitle', 'Types, positions, grille tarifaire et cycle de vie des annonces.') }}
        </p>
      </div>

      <TravelCrudSection
        ref="advertTypesSection"
        :config="advertTypeConfig"
      />
      <TravelCrudSection
        ref="advertPositionsSection"
        :config="advertPositionConfig"
      />
      <TravelCrudSection
        ref="advertPricesSection"
        :config="advertPriceConfig"
        :lookups="{ advertTypes: advertTypeOptions, advertPositions: advertPositionOptions }"
        :column-display="{ advert_type_id: advertTypeName, advert_position_id: advertPositionName }"
      />

      <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h3 class="text-lg font-bold text-slate-900 dark:text-white">
            {{ $t('travel.advert.advertsTitle', 'Annonces') }}
          </h3>
          <div class="flex items-center gap-2">
            <select v-model="advertStatusFilter" class="form-input text-sm" :aria-label="$t('travel.advert.filterStatus', 'Filtrer par statut')" @change="loadAdverts">
              <option value="">{{ $t('travel.advert.allStatuses', 'Tous les statuts') }}</option>
              <option v-for="status in advertStatusOptions" :key="status.value" :value="status.value">
                {{ status.label }}
              </option>
            </select>
            <button class="btn-primary" type="button" @click="openAdvertCreate">
              {{ $t('travel.action.create', 'Créer') }}
            </button>
          </div>
        </div>

        <DataTable
          :columns="advertColumns"
          :rows="adverts"
          :loading="advertsLoading"
          :error="advertsError"
          :search-keys="['title', 'status']"
          :search-placeholder="$t('travel.advert.searchPlaceholder', 'Rechercher une annonce…')"
          :default-sort="idSortKey"
          :default-sort-dir="descSortDir"
          :caption="$t('travel.advert.advertsTitle', 'Annonces')"
        >
          <template #cell-status="{ value }">
            <StatusBadge :status="value" :map="advertStatusMap" />
          </template>
          <template #row-actions="{ row }">
            <div class="flex items-center justify-end gap-1">
              <button
                v-for="action in advertActionsFor(row)"
                :key="action.key"
                class="rounded-md px-2 py-1.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                type="button"
                :aria-label="action.label"
                :title="action.label"
                @click="onAdvertAction(action.key, row)"
              >
                {{ action.label }}
              </button>
            </div>
          </template>
        </DataTable>
      </div>
    </section>

    <!-- ── Sites touristiques ────────────────────────────────── -->
    <div class="space-y-4 border-t border-slate-200/50 pt-8 dark:border-slate-800/50">
      <div class="flex justify-end">
        <select v-model="siteCityFilter" class="form-input text-sm" :aria-label="$t('travel.sites.cityFilter', 'Filtrer par ville')" @change="reloadSites">
          <option value="">{{ $t('travel.sites.allCities', 'Toutes les villes') }}</option>
          <option v-for="city in cityOptions" :key="city.value" :value="city.value">
            {{ city.label }}
          </option>
        </select>
      </div>

      <TravelCrudSection
        ref="sitesSection"
        :config="siteConfig"
        :lookups="{ cities: cityOptions }"
        :column-display="{ city_id: cityName }"
      />
    </div>

    <!-- Création d'une annonce -->
    <TravelModal :open="advertCreateOpen" :title="$t('travel.advert.createTitle', 'Nouvelle annonce')" wide @close="closeAdvertCreate">
      <div v-if="advertCreateError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert">
        {{ advertCreateError }}
      </div>
      <form class="grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="createAdvert">
        <div>
          <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="advert-type">
            {{ $t('travel.advert.fieldType', 'Type') }}
          </label>
          <select id="advert-type" v-model="advertDraft.advert_type_id" class="form-input mt-1" required>
            <option value="">{{ $t('travel.form.selectPlaceholder', '— Sélectionner —') }}</option>
            <option v-for="type in advertTypeOptions" :key="type.value" :value="type.value">
              {{ type.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="advert-position">
            {{ $t('travel.advert.fieldPosition', 'Position') }}
          </label>
          <select id="advert-position" v-model="advertDraft.advert_position_id" class="form-input mt-1" required>
            <option value="">{{ $t('travel.form.selectPlaceholder', '— Sélectionner —') }}</option>
            <option v-for="position in advertPositionOptions" :key="position.value" :value="position.value">
              {{ position.label }}
            </option>
          </select>
        </div>
        <div class="sm:col-span-2">
          <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="advert-title">
            {{ $t('travel.advert.fieldTitle', 'Titre') }}
          </label>
          <input id="advert-title" v-model="advertDraft.title" class="form-input mt-1" type="text" maxlength="160" required />
        </div>
        <div class="sm:col-span-2">
          <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="advert-content">
            {{ $t('travel.advert.fieldContent', 'Contenu') }}
          </label>
          <textarea id="advert-content" v-model="advertDraft.content" class="form-input mt-1" rows="4" maxlength="2000" required></textarea>
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="advert-image">
            {{ $t('travel.advert.fieldImageAsset', 'Image (asset id)') }}
          </label>
          <input id="advert-image" v-model="advertDraft.image_asset_id" class="form-input mt-1" type="number" min="1" />
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="advert-validity">
            {{ $t('travel.advert.fieldValidityDays', 'Durée de validité (jours)') }}
          </label>
          <input id="advert-validity" v-model="advertDraft.validity_days" class="form-input mt-1" type="number" min="1" max="365" />
        </div>
        <div class="flex justify-end gap-2 sm:col-span-2">
          <button class="btn-secondary" type="button" @click="closeAdvertCreate">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button class="btn-primary" type="submit" :disabled="advertSaving">
            {{ advertSaving ? $t('common.busy', 'En cours…') : $t('travel.action.save', 'Enregistrer') }}
          </button>
        </div>
      </form>
    </TravelModal>

    <!-- Rejet d'une annonce (motif) -->
    <TravelModal :open="advertRejectOpen" :title="$t('travel.advert.rejectTitle', 'Rejeter l\u0027annonce')" @close="closeAdvertReject">
      <div v-if="advertRejectError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert">
        {{ advertRejectError }}
      </div>
      <form class="space-y-3" @submit.prevent="rejectAdvert">
        <div>
          <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="advert-reject-reason">
            {{ $t('travel.advert.rejectReason', 'Motif du rejet') }}
          </label>
          <textarea id="advert-reject-reason" v-model="advertRejectReason" class="form-input mt-1" rows="3" maxlength="500" required></textarea>
        </div>
        <div class="flex justify-end gap-2">
          <button class="btn-secondary" type="button" @click="closeAdvertReject">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button class="btn-primary" type="submit" :disabled="advertRejectSaving">
            {{ advertRejectSaving ? $t('common.busy', 'En cours…') : $t('travel.advert.rejectSubmit', 'Rejeter') }}
          </button>
        </div>
      </form>
    </TravelModal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { PencilSquareIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import TravelCrudSection from '@/components/travel/TravelCrudSection.vue'
import TravelModal from '@/components/travel/TravelModal.vue'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const toast = useToast()

const scoreSortKey = 'score'
const idSortKey = 'id'
const descSortDir = 'desc' 

/* ── lookups partagés ──────────────────────────────────────── */
const cities = ref([])
const advertTypes = ref([])
const advertPositions = ref([])

const cityOptions = computed(() =>
  cities.value.map((c) => ({ value: c.id, label: `${c.name}${c.country_iso2 ? ` (${c.country_iso2})` : ''}` }))
)
const advertTypeOptions = computed(() => advertTypes.value.map((item) => ({ value: item.id, label: `${item.code} — ${item.label}` })))
const advertPositionOptions = computed(() =>
  advertPositions.value.map((item) => ({ value: item.id, label: `${item.code} — ${item.label}` }))
)

function cityName(_row, value) {
  const city = cities.value.find((c) => String(c.id) === String(value))
  return city ? `${city.name}${city.country_iso2 ? ` (${city.country_iso2})` : ''}` : value
}
function advertTypeName(_row, value) {
  const item = advertTypes.value.find((c) => String(c.id) === String(value))
  return item ? `${item.code} — ${item.label}` : value
}
function advertPositionName(_row, value) {
  const item = advertPositions.value.find((c) => String(c.id) === String(value))
  return item ? `${item.code} — ${item.label}` : value
}

async function loadLookups() {
  try {
    const [cRes, tRes, pRes] = await Promise.all([
      api.get('/travel/cities', { params: { per_page: 100 }, _skipAuthRedirect: true }),
      api.get('/travel/advert-types', { params: { per_page: 100 }, _skipAuthRedirect: true }),
      api.get('/travel/advert-positions', { params: { per_page: 100 }, _skipAuthRedirect: true })
    ])
    cities.value = cRes.data?.data || []
    advertTypes.value = tRes.data?.data || []
    advertPositions.value = pRes.data?.data || []
  } catch {
    // lookups silencieux
  }
}

/* ── statuts ───────────────────────────────────────────────── */
const quizStatusMap = {
  draft: { labelKey: 'travel.quizStatus.draft', color: 'gray' },
  active: { labelKey: 'travel.quizStatus.active', color: 'green' },
  closed: { labelKey: 'travel.quizStatus.closed', color: 'yellow' }
}

const advertStatusMap = {
  draft: { labelKey: 'travel.advertStatus.draft', color: 'gray' },
  submitted: { labelKey: 'travel.advertStatus.submitted', color: 'blue' },
  paid: { labelKey: 'travel.advertStatus.paid', color: 'yellow' },
  validated: { labelKey: 'travel.advertStatus.validated', color: 'green' },
  rejected: { labelKey: 'travel.advertStatus.rejected', color: 'red' },
  expired: { labelKey: 'travel.advertStatus.expired', color: 'gray' },
  archived: { labelKey: 'travel.advertStatus.archived', color: 'gray' }
}

const recordStatusMap = {
  active: { labelKey: 'travel.status.active', color: 'green' },
  disabled: { labelKey: 'travel.status.disabled', color: 'gray' }
}

const quizStatusOptions = [
  { value: 'draft', label: t('travel.quizStatus.draft', 'Brouillon') },
  { value: 'active', label: t('travel.quizStatus.active', 'Actif') },
  { value: 'closed', label: t('travel.quizStatus.closed', 'Clôturé') }
]

const recordStatusOptions = [
  { value: 'active', label: t('travel.status.active', 'Actif') },
  { value: 'disabled', label: t('travel.status.disabled', 'Désactivé') }
]

/* ── Quiz ──────────────────────────────────────────────────── */
const quizSection = ref(null)

const quizConfig = computed(() => ({
  resource: 'quizzes',
  titleKey: 'travel.quiz.title',
  subtitleKey: 'travel.quiz.subtitle',
  searchPlaceholderKey: 'travel.search.quiz',
  searchKeys: ['title', 'status'],
  defaultSort: 'id',
  defaultSortDir: 'desc',
  statusField: 'status',
  statusMap: quizStatusMap,
  rowActions: [
    { key: 'questions', label: 'travel.quiz.questionsAction' },
    { key: 'results', label: 'travel.quiz.resultsAction' }
  ],
  columns: [
    { key: 'title', label: 'travel.field.title', sortable: true },
    { key: 'status', label: 'travel.field.status', sortable: true },
    { key: 'starts_at', label: 'travel.field.startsAt', sortable: true },
    { key: 'ends_at', label: 'travel.field.endsAt', sortable: true },
    { key: 'max_participations_per_contact', label: 'travel.quiz.maxParticipation', sortable: true }
  ],
  fields: [
    { key: 'title', label: 'travel.field.title', type: 'text', required: true, max: 160 },
    { key: 'description', label: 'travel.field.description', type: 'textarea' },
    { key: 'starts_at', label: 'travel.field.startsAt', type: 'date' },
    { key: 'ends_at', label: 'travel.field.endsAt', type: 'date' },
    { key: 'max_participations_per_contact', label: 'travel.quiz.maxParticipation', type: 'number', min: 1, max: 100 },
    { key: 'status', label: 'travel.field.status', type: 'select', options: quizStatusOptions }
  ],
  defaults: { status: 'draft', max_participations_per_contact: 1 }
}))

const questionsOpen = ref(false)
const questionsError = ref('')
const questions = ref([])
const questionsQuiz = ref(null)
const questionDraft = ref({ question: '', options: ['', ''], correct_option_index: 0, points: 1, position: 0 })
const questionEditingId = ref(null)
const questionError = ref('')
const savingQuestion = ref(false)

const questionsTitle = computed(() =>
  questionsQuiz.value
    ? `${t('travel.quiz.questionsTitle', 'Questions')} — ${questionsQuiz.value.title || `#${questionsQuiz.value.id}`}`
    : t('travel.quiz.questionsTitle', 'Questions')
)

async function onQuizAction({ key, row }) {
  if (key === 'questions') await openQuestions(row)
  if (key === 'results') await openResults(row)
}

async function openQuestions(row) {
  questionsQuiz.value = row
  questionsOpen.value = true
  questionsError.value = ''
  questionEditingId.value = null
  resetQuestionDraft()
  try {
    const res = await api.get(`/travel/quizzes/${row.id}/questions`, { _skipAuthRedirect: true })
    questions.value = res.data?.data || []
  } catch (err) {
    questionsError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  }
}

function closeQuestions() {
  questionsOpen.value = false
}

function addOption() {
  if (questionDraft.value.options.length >= 10) return
  questionDraft.value.options.push('')
}

function optionValue(idx) {
  return questionDraft.value.options[idx]
}

function setOption(idx, event) {
  questionDraft.value.options[idx] = event.target.value
}

function removeOption(idx) {
  if (questionDraft.value.options.length <= 2) return
  questionDraft.value.options.splice(idx, 1)
  if (questionDraft.value.correct_option_index >= questionDraft.value.options.length) {
    questionDraft.value.correct_option_index = questionDraft.value.options.length - 1
  }
}

function resetQuestionDraft() {
  questionEditingId.value = null
  questionError.value = ''
  questionDraft.value = { question: '', options: ['', ''], correct_option_index: 0, points: 1, position: questions.value.length }
}

function editQuestion(question) {
  questionEditingId.value = question.id
  questionError.value = ''
  questionDraft.value = {
    question: question.question,
    options: [...(question.options || [])],
    correct_option_index: question.correct_option_index,
    points: question.points,
    position: question.position
  }
}

async function saveQuestion() {
  if (!questionsQuiz.value) return
  savingQuestion.value = true
  questionError.value = ''
  try {
    const payload = {
      question: questionDraft.value.question,
      options: questionDraft.value.options.filter((opt) => String(opt).trim() !== ''),
      correct_option_index: Number(questionDraft.value.correct_option_index),
      points: Number(questionDraft.value.points || 1),
      position: Number(questionDraft.value.position ?? 0)
    }
    if (questionEditingId.value) {
      await api.put(`/travel/quizzes/${questionsQuiz.value.id}/questions/${questionEditingId.value}`, payload, { _skipAuthRedirect: true })
    } else {
      await api.post(`/travel/quizzes/${questionsQuiz.value.id}/questions`, payload, { _skipAuthRedirect: true })
    }
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    resetQuestionDraft()
    const res = await api.get(`/travel/quizzes/${questionsQuiz.value.id}/questions`, { _skipAuthRedirect: true })
    questions.value = res.data?.data || []
  } catch (err) {
    questionError.value = err.response?.data?.message || t('travel.error.saveFailed', "Échec de l'enregistrement.")
  } finally {
    savingQuestion.value = false
  }
}

async function deleteQuestion(question) {
  if (!window.confirm(t('travel.confirm.deleteMessage', 'Supprimer définitivement cet élément ?'))) return
  try {
    await api.delete(`/travel/quizzes/${questionsQuiz.value.id}/questions/${question.id}`, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.deleted', 'Supprimé.'))
    const res = await api.get(`/travel/quizzes/${questionsQuiz.value.id}/questions`, { _skipAuthRedirect: true })
    questions.value = res.data?.data || []
  } catch (err) {
    toast.error(err.response?.data?.message || t('travel.error.deleteFailed', 'Échec de la suppression.'))
  }
}

const resultsOpen = ref(false)
const results = ref([])
const resultsLoading = ref(false)
const resultsError = ref('')
const resultsQuiz = ref(null)

const resultsTitle = computed(() =>
  resultsQuiz.value
    ? `${t('travel.quiz.resultsTitle', 'Résultats')} — ${resultsQuiz.value.title || `#${resultsQuiz.value.id}`}`
    : t('travel.quiz.resultsTitle', 'Résultats')
)

const resultsColumns = computed(() => [
  { key: 'participant_name', label: t('travel.quiz.colParticipant', 'Participant') },
  { key: 'participant_email', label: t('travel.quiz.colEmail', 'Email') },
  { key: 'score', label: t('travel.quiz.colScore', 'Score'), sortable: true },
  { key: 'bonus', label: t('travel.quiz.colBonus', 'Bonus'), sortable: true },
  { key: 'submitted_at', label: t('travel.quiz.colSubmitted', 'Soumis le') }
])

async function openResults(row) {
  resultsQuiz.value = row
  resultsOpen.value = true
  resultsLoading.value = true
  resultsError.value = ''
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

/* ── Référentiels annonces ─────────────────────────────────── */
const advertTypesSection = ref(null)
const advertPositionsSection = ref(null)
const advertPricesSection = ref(null)

const advertReferenceConfig = (resource, titleKey, subtitleKey) => ({
  resource,
  titleKey,
  subtitleKey,
  searchPlaceholderKey: 'travel.search.placeholder',
  searchKeys: ['code', 'label'],
  defaultSort: 'code',
  columns: [
    { key: 'code', label: 'travel.field.code', sortable: true },
    { key: 'label', label: 'travel.field.label', sortable: true }
  ],
  fields: [
    { key: 'code', label: 'travel.field.code', type: 'text', required: true, max: 40 },
    { key: 'label', label: 'travel.field.label', type: 'text', required: true, max: 120 }
  ]
})

const advertTypeConfig = computed(() =>
  advertReferenceConfig('advert-types', 'travel.advert.typesTitle', 'travel.advert.typesSubtitle')
)
const advertPositionConfig = computed(() =>
  advertReferenceConfig('advert-positions', 'travel.advert.positionsTitle', 'travel.advert.positionsSubtitle')
)

const advertPriceConfig = computed(() => ({
  resource: 'advert-prices',
  titleKey: 'travel.advert.pricesTitle',
  subtitleKey: 'travel.advert.pricesSubtitle',
  searchPlaceholderKey: 'travel.search.placeholder',
  searchKeys: ['advert_type', 'advert_position', 'currency'],
  defaultSort: 'id',
  columns: [
    { key: 'advert_type_id', label: 'travel.advert.colType', sortable: true },
    { key: 'advert_position_id', label: 'travel.advert.colPosition', sortable: true },
    { key: 'price_per_image_minor', label: 'travel.advert.colPriceImage', type: 'money' },
    { key: 'price_per_character_minor', label: 'travel.advert.colPriceChar', type: 'money' },
    { key: 'currency', label: 'travel.field.currency', sortable: true }
  ],
  fields: [
    { key: 'advert_type_id', label: 'travel.advert.fieldType', type: 'select', source: 'advertTypes', required: true },
    { key: 'advert_position_id', label: 'travel.advert.fieldPosition', type: 'select', source: 'advertPositions', required: true },
    { key: 'price_per_image_minor', label: 'travel.advert.colPriceImage', type: 'money', required: true, min: 1 },
    { key: 'price_per_character_minor', label: 'travel.advert.colPriceChar', type: 'money', required: true, min: 1 },
    { key: 'currency', label: 'travel.field.currency', type: 'text', required: true, max: 3 }
  ],
  defaults: { currency: 'XAF' }
}))

/* ── Annonces (cycle de vie) ───────────────────────────────── */
const adverts = ref([])
const advertsLoading = ref(false)
const advertsError = ref('')
const advertStatusFilter = ref('')

const advertStatusOptions = computed(() => [
  { value: 'draft', label: t('travel.advertStatus.draft', 'Brouillon') },
  { value: 'submitted', label: t('travel.advertStatus.submitted', 'Soumise') },
  { value: 'paid', label: t('travel.advertStatus.paid', 'Payée') },
  { value: 'validated', label: t('travel.advertStatus.validated', 'Validée') },
  { value: 'rejected', label: t('travel.advertStatus.rejected', 'Rejetée') },
  { value: 'expired', label: t('travel.advertStatus.expired', 'Expirée') },
  { value: 'archived', label: t('travel.advertStatus.archived', 'Archivée') }
])

const advertColumns = computed(() => [
  { key: 'title', label: t('travel.advert.fieldTitle', 'Titre'), sortable: true },
  { key: 'advert_type_id', label: t('travel.advert.colType', 'Type') },
  { key: 'advert_position_id', label: t('travel.advert.colPosition', 'Position') },
  { key: 'status', label: t('travel.field.status', 'Statut'), sortable: true },
  { key: 'price_minor', label: t('travel.advert.colPrice', 'Prix'), type: 'money' },
  { key: 'paid_at', label: t('travel.advert.colPaidAt', 'Payé le') },
  { key: 'expires_at', label: t('travel.advert.colExpiresAt', 'Expire le') }
])

function advertActionsFor(row) {
  const actions = []
  if (row.status === 'submitted') {
    actions.push({ key: 'pay', label: t('travel.advert.payAction', 'Payer') })
  }
  if (row.status === 'paid') {
    actions.push({ key: 'validate', label: t('travel.advert.validateAction', 'Valider') })
    actions.push({ key: 'reject', label: t('travel.advert.rejectAction', 'Rejeter') })
  }
  if (row.status === 'validated') {
    actions.push({ key: 'renew', label: t('travel.advert.renewAction', 'Renouveler') })
  }
  if (row.status === 'rejected') {
    actions.push({ key: 'renew', label: t('travel.advert.renewAction', 'Renouveler') })
  }
  return actions
}

async function loadAdverts() {
  advertsLoading.value = true
  advertsError.value = ''
  try {
    const params = { per_page: 100 }
    if (advertStatusFilter.value) params.status = advertStatusFilter.value
    const res = await api.get('/travel/adverts/manage', { params, _skipAuthRedirect: true })
    adverts.value = res.data?.data || []
  } catch (err) {
    advertsError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    advertsLoading.value = false
  }
}

const advertCreateOpen = ref(false)
const advertCreateError = ref('')
const advertSaving = ref(false)
const advertDraft = ref({})

function openAdvertCreate() {
  advertDraft.value = { advert_type_id: '', advert_position_id: '', title: '', content: '', image_asset_id: '', validity_days: 30 }
  advertCreateError.value = ''
  advertCreateOpen.value = true
}

function closeAdvertCreate() {
  advertCreateOpen.value = false
}

async function createAdvert() {
  advertSaving.value = true
  advertCreateError.value = ''
  try {
    const payload = {
      advert_type_id: Number(advertDraft.value.advert_type_id),
      advert_position_id: Number(advertDraft.value.advert_position_id),
      title: advertDraft.value.title,
      content: advertDraft.value.content,
      image_asset_id: advertDraft.value.image_asset_id ? Number(advertDraft.value.image_asset_id) : null,
      validity_days: advertDraft.value.validity_days ? Number(advertDraft.value.validity_days) : 30
    }
    await api.post('/travel/adverts', payload, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    advertCreateOpen.value = false
    await loadAdverts()
  } catch (err) {
    const data = err.response?.data || {}
    advertCreateError.value = data.message || data.localized_message || t('travel.error.saveFailed', "Échec de l'enregistrement.")
  } finally {
    advertSaving.value = false
  }
}

const advertRejectOpen = ref(false)
const advertRejectError = ref('')
const advertRejectSaving = ref(false)
const advertRejectTarget = ref(null)
const advertRejectReason = ref('')

function openAdvertReject(row) {
  advertRejectTarget.value = row
  advertRejectReason.value = ''
  advertRejectError.value = ''
  advertRejectOpen.value = true
}

function closeAdvertReject() {
  advertRejectOpen.value = false
}

async function rejectAdvert() {
  if (!advertRejectTarget.value) return
  advertRejectSaving.value = true
  advertRejectError.value = ''
  try {
    await api.post(`/travel/adverts/${advertRejectTarget.value.id}/reject`, {
      reason: advertRejectReason.value.trim()
    }, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    advertRejectOpen.value = false
    await loadAdverts()
  } catch (err) {
    advertRejectError.value = err.response?.data?.message || t('travel.error.actionFailed', "L'action a échoué.")
  } finally {
    advertRejectSaving.value = false
  }
}

async function onAdvertAction(key, row) {
  const confirmMessages = {
    pay: t('travel.advert.confirmPay', 'Confirmer le paiement de cette annonce ?'),
    validate: t('travel.advert.confirmValidate', 'Valider cette annonce ? Elle sera publiée.'),
    renew: t('travel.advert.confirmRenew', 'Renouveler cette annonce (nouveau paiement) ?')
  }
  if (key === 'reject') {
    openAdvertReject(row)
    return
  }
  if (!window.confirm(confirmMessages[key])) return
  try {
    await api.post(`/travel/adverts/${row.id}/${key}`, {}, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    await loadAdverts()
  } catch (err) {
    toast.error(err.response?.data?.message || t('travel.error.actionFailed', "L'action a échoué."))
  }
}

/* ── Sites touristiques ────────────────────────────────────── */
const sitesSection = ref(null)
const siteCityFilter = ref('')

const siteConfig = computed(() => ({
  resource: 'tourist-sites',
  titleKey: 'travel.sites.title',
  subtitleKey: 'travel.sites.subtitle',
  searchPlaceholderKey: 'travel.search.site',
  searchKeys: ['name', 'city_id', 'status'],
  defaultSort: 'name',
  statusField: 'status',
  statusMap: recordStatusMap,
  extraParams: siteCityFilter.value ? { city_id: siteCityFilter.value } : {},
  columns: [
    { key: 'name', label: 'travel.field.name', sortable: true },
    { key: 'city_id', label: 'travel.field.city', sortable: true },
    { key: 'status', label: 'travel.field.status', sortable: true }
  ],
  fields: [
    { key: 'name', label: 'travel.field.name', type: 'text', required: true, max: 160 },
    { key: 'description', label: 'travel.field.description', type: 'textarea' },
    { key: 'city_id', label: 'travel.field.city', type: 'select', source: 'cities', required: true },
    { key: 'latitude', label: 'travel.sites.fieldLatitude', type: 'number', step: 'any' },
    { key: 'longitude', label: 'travel.sites.fieldLongitude', type: 'number', step: 'any' },
    { key: 'image_asset_id', label: 'travel.sites.fieldImageAsset', type: 'number', min: 1 },
    { key: 'status', label: 'travel.field.status', type: 'select', options: recordStatusOptions }
  ],
  defaults: { status: 'active' }
}))

function reloadSites() {
  sitesSection.value?.load()
}

onMounted(() => {
  loadLookups()
  loadAdverts()
})
</script>
