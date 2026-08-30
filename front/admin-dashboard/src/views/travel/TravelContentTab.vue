<template>
  <div class="space-y-6">
    <!-- Sous-sections : Quiz / Annonces / Sites touristiques (TRAVEL-911, #6416) -->
    <div class="flex flex-wrap gap-2" role="tablist" :aria-label="$t('travel.content.tabsLabel', 'Sous-sections contenu & monétisation')">
      <button
        v-for="sub in subTabs"
        :key="sub.key"
        type="button"
        role="tab"
        :aria-selected="activeSub === sub.key"
        :class="[
          'rounded-md px-3.5 py-1.5 text-sm font-medium transition-colors',
          activeSub === sub.key
            ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
        ]"
        @click="selectSub(sub)"
      >
        {{ sub.label }}
      </button>
    </div>

    <!-- ══════════════ QUIZ (TRAVEL-904/#6107) ══════════════ -->
    <div v-if="activeSub === 'quiz'" class="space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">
            {{ $t('travel.quiz.title', 'Quiz & jeu-concours') }}
          </h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            {{ $t('travel.quiz.subtitle', 'Créez des quiz, gérez les questions (la bonne réponse n\u2019est jamais exposée côté client) et consultez les résultats triés par score.') }}
          </p>
        </div>
        <button type="button" class="btn-primary" @click="openQuizCreate">
          <PlusIcon class="mr-1 inline h-4 w-4" />
          {{ $t('travel.quiz.create', 'Nouveau quiz') }}
        </button>
      </div>

      <DataTable
        :columns="quizColumns"
        :rows="quizzes"
        :loading="loadingQuizzes"
        :error="errors.quizzes"
        :search-keys="['title']"
        :search-placeholder="$t('travel.quiz.search', 'Rechercher un quiz…')"
        default-sort="id"
        :caption="$t('travel.quiz.listCaption', 'Quiz')"
        key-field="id"
      >
        <template #cell-status="{ value }">
          <StatusBadge :status="value" :map="quizStatusMap" />
        </template>
        <template #row-actions="{ row }">
          <button
            type="button"
            class="btn-secondary"
            @click="openQuizDetail(row)"
          >
            <EyeIcon class="mr-1 inline h-3.5 w-3.5" />{{ $t('travel.quiz.manage', 'Gérer') }}
          </button>
          <button
            type="button"
            class="btn-secondary"
            @click="openQuizResults(row)"
          >
            {{ $t('travel.quiz.results', 'Résultats') }}
          </button>
        </template>
      </DataTable>

      <!-- Modale création quiz -->
      <TravelModal
        :open="quizFormOpen"
        :title="t('travel.quiz.createTitle', 'Nouveau quiz')"
        wide
        @close="closeQuizForm"
      >
        <form class="grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="saveQuiz">
          <FormField
            id="travel-quiz-title"
            :label="t('travel.field.title', 'Titre')"
            :error="quizFormErrors.title"
            required
          >
            <template #default="{ ariaInvalid, describedBy }">
              <input
                v-model="quizForm.title"
                type="text"
                class="form-input"
                maxlength="160"
                :aria-invalid="ariaInvalid"
                :aria-describedby="describedBy"
                required
              />
            </template>
          </FormField>
          <FormField
            id="travel-quiz-status"
            :label="t('travel.field.status', 'Statut')"
            :error="quizFormErrors.status"
          >
            <template #default="{ ariaInvalid, describedBy }">
              <select v-model="quizForm.status" class="form-input" :aria-invalid="ariaInvalid" :aria-describedby="describedBy">
                <option v-for="opt in quizStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </template>
          </FormField>
          <FormField
            id="travel-quiz-description"
            :label="t('travel.field.description', 'Description')"
            :error="quizFormErrors.description"
          >
            <template #default="{ ariaInvalid, describedBy }">
              <textarea v-model="quizForm.description" rows="3" maxlength="2000" class="form-input" :aria-invalid="ariaInvalid" :aria-describedby="describedBy"></textarea>
            </template>
          </FormField>
          <FormField
            id="travel-quiz-max"
            :label="t('travel.quiz.maxParticipations', 'Participations max / contact')"
            :error="quizFormErrors.max_participations_per_contact"
          >
            <template #default="{ ariaInvalid, describedBy }">
              <input v-model.number="quizForm.max_participations_per_contact" type="number" min="1" max="100" class="form-input" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" />
            </template>
          </FormField>
          <FormField
            id="travel-quiz-starts"
            :label="t('travel.quiz.startsAt', 'Début')"
            :error="quizFormErrors.starts_at"
          >
            <template #default="{ ariaInvalid, describedBy }">
              <input v-model="quizForm.starts_at" type="datetime-local" class="form-input" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" />
            </template>
          </FormField>
          <FormField
            id="travel-quiz-ends"
            :label="t('travel.quiz.endsAt', 'Fin')"
            :error="quizFormErrors.ends_at"
          >
            <template #default="{ ariaInvalid, describedBy }">
              <input v-model="quizForm.ends_at" type="datetime-local" class="form-input" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" />
            </template>
          </FormField>

          <p v-if="quizGlobalError" class="col-span-full rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-400" role="alert">
            {{ quizGlobalError }}
          </p>

          <div class="col-span-full flex justify-end gap-2 pt-2">
            <button type="button" class="btn-secondary" @click="closeQuizForm">
              {{ $t('common.cancel', 'Annuler') }}
            </button>
            <button type="submit" class="btn-primary" :disabled="quizSaving">
              {{ quizSaving ? $t('common.busy', 'En cours…') : $t('common.save', 'Enregistrer') }}
            </button>
          </div>
        </form>
      </TravelModal>

      <!-- Modale détail quiz : questions -->
      <TravelModal
        :open="quizDetailOpen"
        :title="t('travel.quiz.detailTitle', 'Gérer le quiz')"
        wide
        @close="closeQuizDetail"
      >
        <div v-if="quizDetail" class="space-y-6">
          <div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ quizDetail.title }}</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
              {{ quizDetail.description || $t('travel.quiz.noDescription', 'Aucune description.') }}
            </p>
            <div class="mt-2">
              <StatusBadge :status="quizDetail.status" :map="quizStatusMap" />
            </div>
          </div>

          <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h4 class="font-semibold text-slate-900 dark:text-white">
                {{ $t('travel.quiz.questions', 'Questions') }}
              </h4>
              <button type="button" class="btn-secondary" @click="openQuestionCreate">
                <PlusIcon class="mr-1 inline h-3.5 w-3.5" />
                {{ $t('travel.quiz.addQuestion', 'Ajouter une question') }}
              </button>
            </div>

            <ul v-if="questions.length" class="mt-3 space-y-3">
              <li
                v-for="(q, qi) in questions"
                :key="questionKey(q, qi)"
                class="rounded-lg border border-slate-200 p-3 dark:border-slate-800"
              >
                <div class="flex items-start justify-between gap-3">
                  <p class="font-medium text-slate-900 dark:text-white">
                    {{ qi + 1 }}. {{ q.question }}
                  </p>
                  <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    {{ $t('travel.quiz.points', 'pts') }} {{ q.points ?? 1 }}
                  </span>
                </div>
                <ul class="mt-2 list-inside list-disc text-sm text-slate-600 dark:text-slate-300">
                  <li v-for="(opt, oi) in q.options" :key="oi">{{ opt }}</li>
                </ul>
                <p class="mt-1 text-xs text-slate-400">
                  {{ $t('travel.quiz.correctHidden', 'Bonne réponse gérée côté serveur — jamais exposée.') }}
                </p>
              </li>
            </ul>
            <p v-else class="mt-3 text-sm text-slate-500 dark:text-slate-400">
              {{ $t('travel.quiz.noQuestions', 'Aucune question. Ajoutez-en pour activer le quiz.') }}
            </p>
          </div>
        </div>
      </TravelModal>

      <!-- Modale ajout question -->
      <TravelModal
        :open="questionFormOpen"
        :title="t('travel.quiz.addQuestionTitle', 'Ajouter une question')"
        wide
        @close="closeQuestionForm"
      >
        <form class="grid grid-cols-1 gap-4" @submit.prevent="saveQuestion">
          <FormField
            id="travel-question-text"
            :label="t('travel.quiz.question', 'Question')"
            :error="questionErrors.question"
            required
          >
            <template #default="{ ariaInvalid, describedBy }">
              <input v-model="questionForm.question" type="text" maxlength="500" class="form-input" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" required />
            </template>
          </FormField>

          <fieldset class="space-y-2">
            <legend class="text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ $t('travel.quiz.options', 'Options (2 à 10)') }}
            </legend>
            <div
              v-for="(opt, oi) in questionForm.options"
              :key="oi"
              class="flex items-center gap-2"
            >
              <input
                :id="`travel-question-option-${oi}`"
                v-model="questionForm.correct_option_index"
                type="radio"
                name="correct_option_index"
                :value="oi"
                class="h-4 w-4 shrink-0"
              />
              <input
                :value="questionOption(oi)" @input="setQuestionOption(oi, $event)"
                type="text"
                maxlength="200"
                class="form-input"
                :placeholder="`${$t('travel.quiz.option', 'Option')} ${oi + 1}`"
                required
              />
              <button
                type="button"
                class="rounded-md p-1 text-slate-400 hover:text-red-500"
                :aria-label="$t('travel.quiz.removeOption', 'Retirer cette option')"
                :disabled="minOptionsReached"
                @click="removeOption(oi)"
              >
                <TrashIcon class="h-4 w-4" />
              </button>
            </div>
            <button
              v-if="!maxOptionsReached"
              type="button"
              class="btn-secondary"
              @click="questionForm.options.push('')"
            >
              <PlusIcon class="mr-1 inline h-3.5 w-3.5" />{{ $t('travel.quiz.addOption', 'Ajouter une option') }}
            </button>
            <p class="text-xs text-slate-400">
              {{ $t('travel.quiz.correctHint', 'Cochez la bonne réponse (radio). Elle n\u2019est jamais renvoyée par l\u2019API.') }}
            </p>
          </fieldset>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField
              id="travel-question-points"
              :label="t('travel.quiz.points', 'Points')"
              :error="questionErrors.points"
            >
              <template #default="{ ariaInvalid, describedBy }">
                <input v-model.number="questionForm.points" type="number" min="1" max="100" class="form-input" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" />
              </template>
            </FormField>
            <FormField
              id="travel-question-position"
              :label="t('travel.quiz.position', 'Position')"
              :error="questionErrors.position"
            >
              <template #default="{ ariaInvalid, describedBy }">
                <input v-model.number="questionForm.position" type="number" min="0" class="form-input" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" />
              </template>
            </FormField>
          </div>

          <p v-if="questionGlobalError" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-400" role="alert">
            {{ questionGlobalError }}
          </p>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="btn-secondary" @click="closeQuestionForm">
              {{ $t('common.cancel', 'Annuler') }}
            </button>
            <button type="submit" class="btn-primary" :disabled="questionSaving">
              {{ questionSaving ? $t('common.busy', 'En cours…') : $t('common.save', 'Enregistrer') }}
            </button>
          </div>
        </form>
      </TravelModal>

      <!-- Modale résultats quiz -->
      <TravelModal
        :open="quizResultsOpen"
        :title="t('travel.quiz.resultsTitle', 'Résultats du quiz')"
        wide
        @close="closeQuizResults"
      >
        <DataTable
          :columns="resultColumns"
          :rows="quizResults"
          :loading="loadingResults"
          :error="errors.results"
          :search-keys="['participant_email', 'participant_name']"
          :search-placeholder="$t('travel.quiz.searchResults', 'Rechercher un participant…')"
          default-sort="score"
          default-sort-dir="desc"
          :caption="$t('travel.quiz.resultsCaption', 'Participations (triées par score)')"
          key-field="id"
        />
      </TravelModal>
    </div>

    <!-- ══════════════ ANNONCES (TRAVEL-905..908/#6108..#6111) ══════════════ -->
    <div v-else-if="activeSub === 'annonces'" class="space-y-6">
      <div class="flex flex-wrap gap-2" role="tablist" :aria-label="$t('travel.content.adsTabsLabel', 'Sous-sections annonces')">
        <button
          v-for="ad in adSubTabs"
          :key="ad.key"
          type="button"
          role="tab"
          :aria-selected="activeAdSub === ad.key"
          :class="[
            'rounded-md px-3.5 py-1.5 text-sm font-medium transition-colors',
            activeAdSub === ad.key
              ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
              : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
          ]"
          @click="selectAdSub(ad)"
        >
          {{ ad.label }}
        </button>
      </div>

      <!-- Types & positions (référentiels) -->
      <div v-if="activeAdSub === 'refs'" class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <TravelCrudSection :config="advertTypeConfig" @saved="loadAdvertLookups" />
        <TravelCrudSection :config="advertPositionConfig" @saved="loadAdvertLookups" />
      </div>

      <!-- Grille tarifaire -->
      <TravelCrudSection
        v-else-if="activeAdSub === 'prices'"
        :config="advertPriceConfig"
        :lookups="{ advert_types: advertTypeOptions, advert_positions: advertPositionOptions }"
      />

      <!-- Annonces (cycle de vie) -->
      <TravelCrudSection
        v-else
        :key="`adverts-${advertsReloadKey}`"
        :config="advertConfig"
        :lookups="{ advert_types: advertTypeOptions, advert_positions: advertPositionOptions }"
        @action="handleAdvertAction"
      />

      <!-- Modale action annonce (pay / validate / reject / renew) -->
      <TravelModal
        :open="advertActionOpen"
        :title="advertActionTitle"
        @close="closeAdvertAction"
      >
        <form class="grid grid-cols-1 gap-4" @submit.prevent="confirmAdvertAction">
          <p class="text-sm text-slate-600 dark:text-slate-300">
            <template v-if="advertActionKey === 'pay'">
              {{ $t('travel.ads.confirmPayBody', 'Marquer cette annonce comme payée ? Elle restera en attente de validation.') }}
            </template>
            <template v-else-if="advertActionKey === 'validate'">
              {{ $t('travel.ads.confirmValidateBody', 'Valider cette annonce ? Elle deviendra visible publiquement jusqu\u2019à expiration.') }}
            </template>
            <template v-else-if="advertActionKey === 'renew'">
              {{ $t('travel.ads.confirmRenewBody', 'Renouveler cette annonce ? Sa visibilité sera prolongée.') }}
            </template>
            <template v-else>
              {{ $t('travel.ads.confirmRejectBody', 'Rejeter cette annonce ? Elle ne sera pas publiée.') }}
            </template>
          </p>
          <FormField
            v-if="advertActionKey === 'reject'"
            id="travel-advert-reject-reason"
            :label="t('travel.ads.rejectReason', 'Motif du rejet')"
            :error="advertActionError"
            required
          >
            <template #default="{ ariaInvalid, describedBy }">
              <textarea v-model="advertActionMessage" rows="3" maxlength="500" class="form-input" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" required></textarea>
            </template>
          </FormField>
          <p v-if="advertActionError && advertActionKey !== 'reject'" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-400" role="alert">
            {{ advertActionError }}
          </p>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="btn-secondary" @click="closeAdvertAction">
              {{ $t('common.cancel', 'Annuler') }}
            </button>
            <button type="submit" class="btn-primary" :disabled="advertActionSaving">
              {{ advertActionSaving ? $t('common.busy', 'En cours…') : $t('common.confirm', 'Confirmer') }}
            </button>
          </div>
        </form>
      </TravelModal>
    </div>

    <!-- ══════════════ SITES TOURISTIQUES (TRAVEL-909/#6112) ══════════════ -->
    <div v-else-if="activeSub === 'sites'" class="space-y-6">
      <div class="flex flex-wrap items-center gap-3">
        <label class="text-sm font-medium text-slate-700 dark:text-slate-300" for="travel-sites-city-filter">
          {{ $t('travel.sites.filterCity', 'Filtrer par ville') }}
        </label>
        <select
          id="travel-sites-city-filter"
          v-model="siteCityFilter"
          class="form-input max-w-xs"
          :aria-label="$t('travel.sites.filterCity', 'Filtrer par ville')"
        >
          <option value="">{{ $t('travel.form.selectPlaceholder', '— Sélectionner —') }}</option>
          <option v-for="opt in cityOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>
      <TravelCrudSection
        :config="touristSiteConfig"
        :lookups="{ cities: cityOptions }"
        :extra-params="siteParams"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { PlusIcon, TrashIcon, EyeIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import FormField from '@/components/common/FormField.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import TravelModal from '@/components/travel/TravelModal.vue'
import TravelCrudSection from '@/components/travel/TravelCrudSection.vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const toast = useToast()

const activeSub = ref('quiz')
const activeAdSub = ref('refs')

const subTabs = computed(() => [
  { key: 'quiz', label: t('travel.content.tabQuiz', 'Quiz') },
  { key: 'annonces', label: t('travel.content.tabAds', 'Annonces') },
  { key: 'sites', label: t('travel.content.tabSites', 'Sites touristiques') }
])

function selectSub(sub) {
  activeSub.value = sub.key
}

function selectAdSub(ad) {
  activeAdSub.value = ad.key
}

function closeQuizForm() {
  quizFormOpen.value = false
}

function closeQuizDetail() {
  quizDetailOpen.value = false
}

function closeQuestionForm() {
  questionFormOpen.value = false
}

function closeQuizResults() {
  quizResultsOpen.value = false
}

function closeAdvertAction() {
  advertActionOpen.value = false
}

function questionKey(q, qi) {
  return q.id ?? qi
}

function questionOption(index) {
  return questionForm.value.options[index]
}

function setQuestionOption(index, event) {
  questionForm.value.options[index] = event.target.value
}

const minOptionsReached = computed(() => questionForm.value.options.length <= 2)
const maxOptionsReached = computed(() => questionForm.value.options.length >= 10)

/* ── statuts ───────────────────────────────────────────────── */
const quizStatusMap = {
  draft: { labelKey: 'travel.quizStatus.draft', color: 'gray' },
  active: { labelKey: 'travel.quizStatus.active', color: 'green' },
  closed: { labelKey: 'travel.quizStatus.closed', color: 'red' }
}
const quizStatusOptions = [
  { value: 'draft', label: t('travel.quizStatus.draft', 'Brouillon') },
  { value: 'active', label: t('travel.quizStatus.active', 'Actif') },
  { value: 'closed', label: t('travel.quizStatus.closed', 'Clôturé') }
]

/* ── quiz : liste ──────────────────────────────────────────── */
const quizzes = ref([])
const loadingQuizzes = ref(false)
const errors = ref({ quizzes: '', results: '' })

const quizColumns = computed(() => [
  { key: 'id', label: t('travel.field.id', 'ID'), sortable: true },
  { key: 'title', label: t('travel.field.title', 'Titre'), sortable: true },
  { key: 'status', label: t('travel.field.status', 'Statut'), sortable: true },
  { key: 'starts_at', label: t('travel.quiz.startsAt', 'Début'), sortable: true },
  { key: 'ends_at', label: t('travel.quiz.endsAt', 'Fin'), sortable: true }
])

async function loadQuizzes() {
  loadingQuizzes.value = true
  errors.value.quizzes = ''
  try {
    const res = await api.get('/travel/quizzes', { params: { per_page: 100 }, _skipAuthRedirect: true })
    quizzes.value = res.data?.data || []
  } catch (err) {
    errors.value.quizzes = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    loadingQuizzes.value = false
  }
}

/* ── quiz : création ───────────────────────────────────────── */
const quizFormOpen = ref(false)
const quizSaving = ref(false)
const quizForm = ref({})
const quizFormErrors = ref({})
const quizGlobalError = ref('')

function openQuizCreate() {
  quizForm.value = { title: '', description: '', status: 'draft', max_participations_per_contact: 1, starts_at: '', ends_at: '' }
  quizFormErrors.value = {}
  quizGlobalError.value = ''
  quizFormOpen.value = true
}

async function saveQuiz() {
  quizSaving.value = true
  quizGlobalError.value = ''
  quizFormErrors.value = {}
  try {
    const payload = { ...quizForm.value }
    if (payload.starts_at) payload.starts_at = new Date(payload.starts_at).toISOString()
    if (payload.ends_at) payload.ends_at = new Date(payload.ends_at).toISOString()
    await api.post('/travel/quizzes', payload, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    quizFormOpen.value = false
    await loadQuizzes()
  } catch (err) {
    const data = err.response?.data || {}
    if (data.errors) {
      quizFormErrors.value = Object.fromEntries(
        Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
      )
    }
    quizGlobalError.value = data.message || data.localized_message || t('travel.error.saveFailed', "Échec de l'enregistrement.")
  } finally {
    quizSaving.value = false
  }
}

/* ── quiz : détail + questions ─────────────────────────────── */
const quizDetailOpen = ref(false)
const quizDetail = ref(null)
const questions = ref([])

const questionFormOpen = ref(false)
const questionSaving = ref(false)
const questionForm = ref({})
const questionErrors = ref({})
const questionGlobalError = ref('')

async function openQuizDetail(row) {
  try {
    const res = await api.get(`/travel/quizzes/${row.id}`, { _skipAuthRedirect: true })
    quizDetail.value = res.data?.data
    questions.value = res.data?.data?.questions || []
    questionErrors.value = {}
    questionGlobalError.value = ''
    quizDetailOpen.value = true
  } catch (err) {
    toast.error(err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.'))
  }
}

function openQuestionCreate() {
  questionForm.value = { question: '', options: ['', ''], correct_option_index: 0, points: 1, position: null }
  questionErrors.value = {}
  questionGlobalError.value = ''
  questionFormOpen.value = true
}

function removeOption(index) {
  if (questionForm.value.options.length <= 2) return
  const removed = questionForm.value.options[index]
  questionForm.value.options.splice(index, 1)
  if (questionForm.value.correct_option_index === index) {
    questionForm.value.correct_option_index = 0
  } else if (questionForm.value.correct_option_index > index) {
    questionForm.value.correct_option_index -= 1
  }
  if (removed === '' && questionForm.value.options.length > 1) {
    // garde un champ vide en fin de liste si l'utilisateur retire une ligne vide
  }
}

async function saveQuestion() {
  if (!quizDetail.value) return
  questionSaving.value = true
  questionGlobalError.value = ''
  questionErrors.value = {}
  try {
    const payload = {
      question: questionForm.value.question,
      options: questionForm.value.options.filter((o) => String(o).trim() !== ''),
      correct_option_index: Number(questionForm.value.correct_option_index ?? 0),
      points: Number(questionForm.value.points ?? 1),
      position: questionForm.value.position === null || questionForm.value.position === ''
        ? undefined
        : Number(questionForm.value.position)
    }
    await api.post(`/travel/quizzes/${quizDetail.value.id}/questions`, payload, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    questionFormOpen.value = false
    await openQuizDetail({ id: quizDetail.value.id })
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

/* ── quiz : résultats ──────────────────────────────────────── */
const quizResultsOpen = ref(false)
const quizResults = ref([])
const loadingResults = ref(false)

const resultColumns = computed(() => [
  { key: 'participant_email', label: t('travel.quiz.participantEmail', 'Email'), sortable: true },
  { key: 'participant_name', label: t('travel.quiz.participantName', 'Nom'), sortable: true },
  { key: 'score', label: t('travel.quiz.score', 'Score'), sortable: true },
  { key: 'bonus', label: t('travel.quiz.bonus', 'Bonus'), sortable: true },
  { key: 'submitted_at', label: t('travel.quiz.submittedAt', 'Soumis le'), sortable: true }
])

async function openQuizResults(row) {
  quizResults.value = []
  quizResultsOpen.value = true
  loadingResults.value = true
  errors.value.results = ''
  try {
    const res = await api.get(`/travel/quizzes/${row.id}/results`, { params: { per_page: 100 }, _skipAuthRedirect: true })
    quizResults.value = res.data?.data || []
  } catch (err) {
    errors.value.results = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    loadingResults.value = false
  }
}

/* ── annonces : lookups & configs ──────────────────────────── */
const advertTypes = ref([])
const advertPositions = ref([])

const advertTypeOptions = computed(() =>
  advertTypes.value.map((r) => ({ value: r.id, label: `${r.code} — ${r.label}` }))
)
const advertPositionOptions = computed(() =>
  advertPositions.value.map((r) => ({ value: r.id, label: `${r.code} — ${r.label}` }))
)

async function loadAdvertLookups() {
  try {
    const [tRes, pRes] = await Promise.all([
      api.get('/travel/advert-types', { params: { per_page: 100 }, _skipAuthRedirect: true }),
      api.get('/travel/advert-positions', { params: { per_page: 100 }, _skipAuthRedirect: true })
    ])
    advertTypes.value = tRes.data?.data || []
    advertPositions.value = pRes.data?.data || []
  } catch {
    advertTypes.value = []
    advertPositions.value = []
  }
}

const advertTypeConfig = computed(() => ({
  resource: 'advert-types',
  titleKey: 'travel.ads.types',
  searchPlaceholderKey: 'travel.search.placeholder',
  searchKeys: ['code', 'label'],
  defaultSort: 'code',
  columns: [
    { key: 'code', label: 'travel.field.code', sortable: true },
    { key: 'label', label: 'travel.field.label', sortable: true }
  ],
  fields: [
    { key: 'code', label: 'travel.field.code', type: 'text', required: true, max: 60 },
    { key: 'label', label: 'travel.field.label', type: 'text', required: true, max: 120 }
  ]
}))

const advertPositionConfig = computed(() => ({
  resource: 'advert-positions',
  titleKey: 'travel.ads.positions',
  searchPlaceholderKey: 'travel.search.placeholder',
  searchKeys: ['code', 'label'],
  defaultSort: 'code',
  columns: [
    { key: 'code', label: 'travel.field.code', sortable: true },
    { key: 'label', label: 'travel.field.label', sortable: true }
  ],
  fields: [
    { key: 'code', label: 'travel.field.code', type: 'text', required: true, max: 60 },
    { key: 'label', label: 'travel.field.label', type: 'text', required: true, max: 120 }
  ]
}))

const advertPriceConfig = computed(() => ({
  resource: 'advert-prices',
  titleKey: 'travel.ads.prices',
  searchPlaceholderKey: 'travel.search.placeholder',
  searchKeys: ['advert_type_id', 'advert_position_id', 'currency'],
  defaultSort: 'id',
  columns: [
    { key: 'advert_type_id', label: 'travel.field.advertType', sortable: true },
    { key: 'advert_position_id', label: 'travel.field.advertPosition', sortable: true },
    { key: 'price_per_image_minor', label: 'travel.ads.pricePerImage', type: 'money', sortable: true },
    { key: 'price_per_character_minor', label: 'travel.ads.pricePerCharacter', type: 'money', sortable: true },
    { key: 'currency', label: 'travel.field.currency', sortable: true }
  ],
  fields: [
    { key: 'advert_type_id', label: 'travel.field.advertType', type: 'select', source: 'advert_types', required: true },
    { key: 'advert_position_id', label: 'travel.field.advertPosition', type: 'select', source: 'advert_positions', required: true },
    { key: 'price_per_image_minor', label: 'travel.ads.pricePerImage', type: 'money', required: true, min: 0 },
    { key: 'price_per_character_minor', label: 'travel.ads.pricePerCharacter', type: 'money', required: true, min: 0 },
    { key: 'currency', label: 'travel.field.currency', type: 'text', required: true, max: 3 }
  ],
  defaults: { currency: 'XAF' }
}))

const advertStatusMap = {
  draft: { labelKey: 'travel.adsStatus.draft', color: 'gray' },
  submitted: { labelKey: 'travel.adsStatus.submitted', color: 'blue' },
  paid: { labelKey: 'travel.adsStatus.paid', color: 'purple' },
  validated: { labelKey: 'travel.adsStatus.validated', color: 'green' },
  rejected: { labelKey: 'travel.adsStatus.rejected', color: 'red' },
  expired: { labelKey: 'travel.adsStatus.expired', color: 'red' },
  archived: { labelKey: 'travel.adsStatus.archived', color: 'gray' }
}

const advertConfig = computed(() => ({
  resource: 'adverts',
  titleKey: 'travel.ads.list',
  searchPlaceholderKey: 'travel.search.placeholder',
  searchKeys: ['title', 'status'],
  defaultSort: 'id',
  statusField: 'status',
  statusMap: advertStatusMap,
  columns: [
    { key: 'title', label: 'travel.field.title', sortable: true },
    { key: 'status', label: 'travel.field.status', sortable: true },
    { key: 'price_minor', label: 'travel.field.price', type: 'money', sortable: true },
    { key: 'paid_at', label: 'travel.ads.paidAt', sortable: true },
    { key: 'expires_at', label: 'travel.ads.expiresAt', sortable: true }
  ],
  fields: [
    { key: 'advert_type_id', label: 'travel.field.advertType', type: 'select', source: 'advert_types', required: true },
    { key: 'advert_position_id', label: 'travel.field.advertPosition', type: 'select', source: 'advert_positions', required: true },
    { key: 'title', label: 'travel.field.title', type: 'text', required: true, max: 160 },
    { key: 'content', label: 'travel.field.content', type: 'textarea', required: true, max: 2000 },
    { key: 'validity_days', label: 'travel.ads.validityDays', type: 'number', min: 1, max: 365 }
  ],
  defaults: { validity_days: 30 },
  rowActions: [
    { key: 'pay', label: 'travel.action.pay', condition: (row) => ['submitted', 'draft'].includes(row.status) },
    { key: 'validate', label: 'travel.action.validate', condition: (row) => row.status === 'paid' },
    { key: 'reject', label: 'travel.action.reject', condition: (row) => ['submitted', 'paid'].includes(row.status) },
    { key: 'renew', label: 'travel.action.renew', condition: (row) => ['validated', 'expired'].includes(row.status) }
  ]
}))

/* ── annonces : actions cycle de vie ───────────────────────── */
const advertActionOpen = ref(false)
const advertActionRow = ref(null)
const advertActionKey = ref('')
const advertActionSaving = ref(false)
const advertActionMessage = ref('')
const advertActionError = ref('')
const advertsReloadKey = ref(0)

function handleAdvertAction({ key, row }) {
  advertActionRow.value = row
  advertActionKey.value = key
  advertActionMessage.value = ''
  advertActionError.value = ''
  advertActionOpen.value = true
}

const advertActionTitle = computed(() => {
  const labels = {
    pay: t('travel.ads.confirmPayTitle', 'Confirmer le paiement'),
    validate: t('travel.ads.confirmValidateTitle', "Valider l'annonce"),
    reject: t('travel.ads.confirmRejectTitle', 'Rejeter avec motif'),
    renew: t('travel.ads.confirmRenewTitle', 'Renouveler')
  }
  return labels[advertActionKey.value] || ''
})

async function confirmAdvertAction() {
  if (!advertActionRow.value) return
  advertActionSaving.value = true
  advertActionError.value = ''
  try {
    const url = `/travel/adverts/${advertActionRow.value.id}/${advertActionKey.value}`
    const payload = advertActionKey.value === 'reject' && advertActionMessage.value
      ? { reason: advertActionMessage.value }
      : {}
    await api.post(url, payload, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.actionDone', 'Action effectuée.'))
    advertActionOpen.value = false
    advertsReloadKey.value += 1
  } catch (err) {
    advertActionError.value = err.response?.data?.message || t('travel.error.actionFailed', "L'action a échoué.")
  } finally {
    advertActionSaving.value = false
  }
}

/* ── sites touristiques ────────────────────────────────────── */
const cities = ref([])
const siteCityFilter = ref('')

const cityOptions = computed(() =>
  cities.value.map((c) => ({ value: c.id, label: `${c.name}${c.country_iso2 ? ` (${c.country_iso2})` : ''}` }))
)

const siteParams = computed(() => (siteCityFilter.value ? { city_id: siteCityFilter.value } : {}))

const siteStatusMap = {
  active: { labelKey: 'travel.status.active', color: 'green' },
  disabled: { labelKey: 'travel.status.disabled', color: 'gray' }
}

const touristSiteConfig = computed(() => ({
  resource: 'tourist-sites',
  titleKey: 'travel.sites.title',
  searchPlaceholderKey: 'travel.search.site',
  searchKeys: ['name', 'city_id', 'status'],
  defaultSort: 'name',
  statusField: 'status',
  statusMap: siteStatusMap,
  columns: [
    { key: 'name', label: 'travel.field.name', sortable: true },
    { key: 'city_id', label: 'travel.field.city', sortable: true },
    { key: 'latitude', label: 'travel.field.latitude', sortable: true },
    { key: 'longitude', label: 'travel.field.longitude', sortable: true },
    { key: 'status', label: 'travel.field.status', sortable: true }
  ],
  fields: [
    { key: 'name', label: 'travel.field.name', type: 'text', required: true, max: 160 },
    { key: 'description', label: 'travel.field.description', type: 'textarea' },
    { key: 'city_id', label: 'travel.field.city', type: 'select', source: 'cities', required: true },
    { key: 'latitude', label: 'travel.field.latitude', type: 'number', step: '0.000001' },
    { key: 'longitude', label: 'travel.field.longitude', type: 'number', step: '0.000001' },
    {
      key: 'status', label: 'travel.field.status', type: 'select',
      options: [
        { value: 'active', label: t('travel.status.active', 'Actif') },
        { value: 'disabled', label: t('travel.status.disabled', 'Désactivé') }
      ]
    }
  ],
  defaults: { status: 'active' }
}))

async function loadCities() {
  try {
    const res = await api.get('/travel/cities', { params: { per_page: 100 }, _skipAuthRedirect: true })
    cities.value = res.data?.data || []
  } catch {
    cities.value = []
  }
}

onMounted(() => {
  loadQuizzes()
  loadAdvertLookups()
  loadCities()
})
</script>
