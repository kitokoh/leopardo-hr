<template>
  <div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-6">
      <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">
        {{ t('travel.content.title', 'Contenu & annonces') }}
      </h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        {{ t('travel.content.subtitle', 'Quiz, annonces payantes et sites touristiques.') }}
      </p>
    </div>

    <TravelGate :mode="gateMode">
      <!-- Onglets principaux -->
      <div class="mb-4 flex flex-wrap gap-2">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
          :class="activeTab === tab.key
            ? 'bg-indigo-600 text-white'
            : 'bg-white text-slate-600 dark:bg-slate-800 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700'"
          @click="switchTab(tab.key)"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- ══════════ QUIZ ══════════ -->
      <section v-if="activeTab === 'quiz'">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-lg font-medium text-slate-800 dark:text-slate-100">
            {{ t('travel.quiz.title', 'Quiz & jeux-concours') }}
          </h2>
          <button type="button" class="btn-primary" @click="openQuizCreate">
            {{ t('travel.common.create', 'Créer') }}
          </button>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
          <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ t('travel.quiz.title', 'Titre') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ t('travel.common.status', 'Statut') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ t('travel.quiz.endsAt', 'Fin') }}</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ t('travel.common.actions', 'Actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
              <tr v-for="quiz in quizzes" :key="quiz.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                <td class="px-4 py-3 text-sm text-slate-800 dark:text-slate-100">{{ quiz.title }}</td>
                <td class="px-4 py-3"><StatusBadge :value="quiz.status" /></td>
                <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">{{ quiz.ends_at ? new Date(quiz.ends_at).toLocaleDateString() : '—' }}</td>
                <td class="px-4 py-3 text-right">
                  <button type="button" class="btn-secondary mr-2" @click="openQuizQuestions(quiz)">
                    {{ t('travel.quiz.questions', 'Questions') }}
                  </button>
                  <button type="button" class="btn-secondary" @click="openQuizResults(quiz)">
                    {{ t('travel.quiz.results', 'Résultats') }}
                  </button>
                </td>
              </tr>
              <tr v-if="quizzes.length === 0">
                <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">
                  {{ t('travel.common.empty', 'Aucune donnée.') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ══════════ ANNONCES ══════════ -->
      <section v-if="activeTab === 'adverts'">
        <div class="mb-4 flex flex-wrap gap-2">
          <button
            v-for="sub in advertTabs"
            :key="sub.key"
            type="button"
            class="rounded-lg px-3 py-1.5 text-xs font-medium"
            :class="advertTab === sub.key
              ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200'
              : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
            @click="advertTab = sub.key"
          >
            {{ sub.label }}
          </button>
        </div>

        <!-- Référentiels CRUD génériques -->
        <div v-if="['types', 'positions', 'prices'].includes(advertTab)">
          <div class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-medium text-slate-800 dark:text-slate-100">{{ advertTabs.find((x) => x.key === advertTab).label }}</h2>
            <button v-if="entityConfigs[advertTab].canCreate" type="button" class="btn-primary" @click="openCreate(advertTab)">
              {{ t('travel.common.create', 'Créer') }}
            </button>
          </div>
          <DataTable
            :columns="entityConfigs[advertTab].columns()"
            :rows="lists[advertTab]"
            :search-keys="entityConfigs[advertTab].searchKeys"
          >
            <template #actions="{ row }">
              <button v-if="entityConfigs[advertTab].canDelete" type="button" class="btn-secondary" @click="removeRow(advertTab, row)">
                {{ t('travel.common.delete', 'Supprimer') }}
              </button>
            </template>
          </DataTable>
        </div>

        <!-- Annonces : cycle de vie -->
        <div v-if="advertTab === 'adverts'">
          <div class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-medium text-slate-800 dark:text-slate-100">
              {{ t('travel.adverts.title', 'Annonces') }}
            </h2>
            <div class="flex items-center gap-2">
              <select v-model="advertStatusFilter" class="input" @change="loadAdverts">
                <option value="">{{ t('travel.adverts.allStatuses', 'Tous les statuts') }}</option>
                <option v-for="s in advertStatusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
              <button type="button" class="btn-primary" @click="openAdvertCreate">
                {{ t('travel.common.create', 'Créer') }}
              </button>
            </div>
          </div>
          <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
              <thead class="bg-slate-50 dark:bg-slate-800">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('travel.adverts.title', 'Titre') }}</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('travel.common.status', 'Statut') }}</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('travel.adverts.price', 'Prix') }}</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('travel.adverts.expiresAt', 'Expiration') }}</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('travel.common.actions', 'Actions') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                <tr v-for="ad in adverts" :key="ad.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                  <td class="px-4 py-3 text-sm text-slate-800 dark:text-slate-100">{{ ad.title }}</td>
                  <td class="px-4 py-3"><StatusBadge :value="ad.status" /></td>
                  <td class="px-4 py-3 text-sm text-slate-500">{{ (ad.price_minor / 100).toFixed(2) }} {{ ad.currency }}</td>
                  <td class="px-4 py-3 text-sm text-slate-500">{{ ad.expires_at ? new Date(ad.expires_at).toLocaleDateString() : '—' }}</td>
                  <td class="px-4 py-3 text-right">
                    <button v-if="ad.status === 'submitted' || ad.status === 'draft'" type="button" class="btn-secondary mr-2" @click="advertAction(ad, 'pay')">
                      {{ t('travel.adverts.pay', 'Payer') }}
                    </button>
                    <button v-if="ad.status === 'paid'" type="button" class="btn-secondary mr-2" @click="advertAction(ad, 'validate')">
                      {{ t('travel.adverts.validate', 'Valider') }}
                    </button>
                    <button v-if="ad.status === 'paid'" type="button" class="btn-secondary mr-2" @click="openAdvertReject(ad)">
                      {{ t('travel.adverts.reject', 'Rejeter') }}
                    </button>
                    <button v-if="ad.status === 'validated' || ad.status === 'expired'" type="button" class="btn-secondary" @click="advertAction(ad, 'renew')">
                      {{ t('travel.adverts.renew', 'Renouveler') }}
                    </button>
                  </td>
                </tr>
                <tr v-if="adverts.length === 0">
                  <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">{{ t('travel.common.empty', 'Aucune donnée.') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- ══════════ CONTACTS ══════════ -->
      <section v-if="activeTab === 'contacts'">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-lg font-medium text-slate-800 dark:text-slate-100">
            {{ t('travel.contacts.title', 'Contacts voyageurs') }}
          </h2>
          <input v-model="contactSearch" type="text" class="input w-64" :placeholder="t('travel.contacts.search', 'Rechercher par email')" @keyup.enter="loadContacts" />
        </div>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
          <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('travel.contacts.name', 'Nom') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Email</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('travel.contacts.consents', 'Consentements') }}</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('travel.common.actions', 'Actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
              <tr v-for="c in contacts" :key="c.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                <td class="px-4 py-3 text-sm text-slate-800 dark:text-slate-100">{{ c.first_name || '' }} {{ c.last_name || '' }}</td>
                <td class="px-4 py-3 text-sm text-slate-500">{{ c.email }}</td>
                <td class="px-4 py-3 text-sm">
                  <label v-for="ch in consentChannels" :key="ch.key" class="mr-3 inline-flex cursor-pointer items-center gap-1">
                    <input type="checkbox" class="h-4 w-4 rounded" :checked="c[ch.key + '_consent_given']" @change="toggleConsent(c, ch.key, $event.target.checked)" />
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ ch.label }}</span>
                  </label>
                </td>
                <td class="px-4 py-3 text-right">
                  <button type="button" class="btn-secondary" @click="openContactNotify(c)">{{ t('travel.contacts.notify', 'Notifier') }}</button>
                </td>
              </tr>
              <tr v-if="contacts.length === 0">
                <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">{{ t('travel.common.empty', 'Aucune donnée.') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>


      <!-- ══════════ FORMULAIRE DE CONTACT ══════════ -->
      <section v-if="activeTab === 'form'" class="mx-auto max-w-2xl">
        <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
          <h2 class="text-lg font-medium text-slate-800 dark:text-slate-100">
            {{ t('travel.contacts.form.title', 'Formulaire de contact') }}
          </h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            {{ t('travel.contacts.form.subtitle', 'Une demande transmise à l\u2019agence via l\u2019API réelle (consentement obligatoire).') }}
          </p>

          <form v-if="!contactFormSent" class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="submitContactForm">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ t('travel.contacts.firstName', 'Prénom') }}</label>
              <input v-model="contactForm.first_name" type="text" maxlength="120" class="input w-full" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ t('travel.contacts.lastName', 'Nom') }}</label>
              <input v-model="contactForm.last_name" type="text" maxlength="120" class="input w-full" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ t('travel.contacts.email', 'Email') }} *</label>
              <input v-model="contactForm.email" type="email" required maxlength="190" class="input w-full" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ t('travel.contacts.phone', 'Téléphone') }}</label>
              <input v-model="contactForm.phone" type="text" maxlength="40" class="input w-full" />
            </div>
            <div class="sm:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ t('travel.contacts.message', 'Message') }} *</label>
              <textarea v-model="contactForm.message" required maxlength="2000" rows="4" class="input w-full"></textarea>
            </div>
            <div class="sm:col-span-2">
              <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input v-model="contactForm.consent_email" type="checkbox" class="mt-0.5 h-4 w-4 rounded" />
                <span>{{ t('travel.contacts.form.consentLabel', 'J\u2019accepte d\u2019être recontacté(e) par email au sujet de ma demande.') }}</span>
              </label>
            </div>
            <p v-if="contactFormError" class="sm:col-span-2 text-sm text-red-600" role="alert">{{ contactFormError }}</p>
            <div class="sm:col-span-2 flex justify-end">
              <button type="submit" class="btn-primary" :disabled="contactFormSending">
                {{ contactFormSending ? t('travel.common.saving', 'Envoi…') : t('travel.contacts.form.send', 'Envoyer la demande') }}
              </button>
            </div>
          </form>

          <div v-else class="rounded-lg border border-emerald-200 bg-emerald-50 px-5 py-6 text-center dark:border-emerald-800 dark:bg-emerald-900/20" role="status">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ t('travel.contacts.form.receivedTitle', 'Demande reçue') }}</h3>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ t('travel.contacts.form.receivedBody', 'Merci, votre demande a bien été transmise à l\u2019agence.') }}</p>
            <button type="button" class="btn-secondary mt-4" @click="resetContactForm">{{ t('travel.contacts.form.newRequest', 'Nouvelle demande') }}</button>
          </div>
        </div>
      </section>

      <!-- ══════════ SITES TOURISTIQUES ══════════ -->
      <section v-if="activeTab === 'sites'">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-lg font-medium text-slate-800 dark:text-slate-100">
            {{ t('travel.sites.title', 'Sites touristiques') }}
          </h2>
          <button type="button" class="btn-primary" @click="openCreate('sites')">
            {{ t('travel.common.create', 'Créer') }}
          </button>
        </div>
        <DataTable
          :columns="entityConfigs.sites.columns()"
          :rows="lists.sites"
          :search-keys="entityConfigs.sites.searchKeys"
        >
          <template #actions="{ row }">
            <button type="button" class="btn-secondary" @click="openEdit('sites', row)">{{ t('travel.common.edit', 'Modifier') }}</button>
            <button type="button" class="btn-secondary ml-2" @click="removeRow('sites', row)">{{ t('travel.common.delete', 'Supprimer') }}</button>
          </template>
        </DataTable>
      </section>
    </TravelGate>

    <!-- Modale générique -->
    <TravelFormModal
      v-if="modalOpen"
      :title="modalTitle"
      :fields="modalFields"
      :initial="editing"
      @save="saveRow"
      @cancel="modalOpen = false"
    />

    <!-- Modale questions quiz -->
    <div v-if="quizQuestionsOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="quizQuestionsOpen = false">
      <div class="max-h-[80vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 dark:bg-slate-800">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-lg font-semibold text-slate-800 dark:text-white">{{ selectedQuiz?.title }} — {{ t('travel.quiz.questions', 'Questions') }}</h3>
          <button type="button" class="text-slate-400 hover:text-slate-600" @click="quizQuestionsOpen = false">✕</button>
        </div>
        <ul class="mb-4 space-y-2">
          <li v-for="q in selectedQuizQuestions" :key="q.id" class="rounded-lg bg-slate-50 p-3 text-sm text-slate-700 dark:bg-slate-700/50 dark:text-slate-200">
            <div class="font-medium">{{ q.question }}</div>
            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
              {{ t('travel.quiz.options', 'Options') }}: {{ q.options.join(' · ') }} — {{ q.points }} pt(s)
            </div>
          </li>
          <li v-if="selectedQuizQuestions.length === 0" class="text-sm text-slate-400">{{ t('travel.common.empty', 'Aucune donnée.') }}</li>
        </ul>
        <button type="button" class="btn-primary" @click="openQuestionCreate">{{ t('travel.quiz.addQuestion', 'Ajouter une question') }}</button>
      </div>
    </div>

    <!-- Modale question -->
    <TravelFormModal
      v-if="questionModalOpen"
      :title="t('travel.quiz.addQuestion', 'Ajouter une question')"
      :fields="questionFields"
      :initial="null"
      @save="saveQuestion"
      @cancel="questionModalOpen = false"
    />

    <!-- Modale résultats -->
    <div v-if="resultsOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="resultsOpen = false">
      <div class="max-h-[80vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 dark:bg-slate-800">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-lg font-semibold text-slate-800 dark:text-white">{{ selectedQuiz?.title }} — {{ t('travel.quiz.results', 'Résultats') }}</h3>
          <button type="button" class="text-slate-400 hover:text-slate-600" @click="resultsOpen = false">✕</button>
        </div>
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
          <thead>
            <tr>
              <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">{{ t('travel.quiz.participant', 'Participant') }}</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">{{ t('travel.quiz.score', 'Score') }}</th>
              <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">{{ t('travel.quiz.bonus', 'Bonus') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            <tr v-for="r in quizResults" :key="r.id">
              <td class="px-3 py-2 text-sm text-slate-700 dark:text-slate-200">{{ r.participant_name || r.participant_email }}</td>
              <td class="px-3 py-2 text-sm text-slate-700 dark:text-slate-200">{{ r.score }}</td>
              <td class="px-3 py-2 text-sm text-slate-700 dark:text-slate-200">{{ r.bonus }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modale notification contact -->
    <TravelFormModal
      v-if="notifyOpen"
      :title="t('travel.contacts.notify', 'Notifier le contact')"
      :fields="notifyFields"
      :initial="null"
      @save="saveNotify"
      @cancel="notifyOpen = false"
    />

    <!-- Modale rejet annonce -->
    <TravelFormModal
      v-if="rejectOpen"
      :title="t('travel.adverts.reject', 'Rejeter')"
      :fields="rejectFields"
      :initial="null"
      @save="saveReject"
      @cancel="rejectOpen = false"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { useTravelStore } from '@/stores/travel'
import TravelGate from '@/components/travel/TravelGate.vue'
import TravelFormModal from '@/components/travel/TravelFormModal.vue'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { listTravel, createTravel, updateTravel, deleteTravel, travelList, travelItem } from '@/services/travel'
import api from '@/services/api'
import { travelAction } from '@/services/travel'

const localeStore = useLocaleStore()
const travelStore = useTravelStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const activeTab = ref('quiz')
const advertTab = ref('types')

const gateMode = computed(() => {
  if (!travelStore.isReady) return ''
  if (travelStore.noTenantContext) return 'tenant'
  if (!travelStore.flagActive) return 'feature'
  return ''
})

const tabs = computed(() => [
  { key: 'quiz', label: t('travel.content.tabs.quiz', 'Quiz') },
  { key: 'adverts', label: t('travel.content.tabs.adverts', 'Annonces') },
  { key: 'sites', label: t('travel.content.tabs.sites', 'Sites touristiques') },
  { key: 'contacts', label: t('travel.content.tabs.contacts', 'Contacts') },
  { key: 'form', label: t('travel.content.tabs.form', 'Formulaire de contact') },
])

const advertTabs = computed(() => [
  { key: 'types', label: t('travel.adverts.types', 'Types') },
  { key: 'positions', label: t('travel.adverts.positions', 'Positions') },
  { key: 'prices', label: t('travel.adverts.prices', 'Tarifs') },
  { key: 'adverts', label: t('travel.adverts.title', 'Annonces') },
])

// ── Quiz ─────────────────────────────────────────────────────────────
const quizzes = ref([])
const selectedQuiz = ref(null)
const selectedQuizQuestions = ref([])
const quizQuestionsOpen = ref(false)
const questionModalOpen = ref(false)
const questionFields = computed(() => [
  { key: 'question', label: 'travel.quiz.question', type: 'text', required: true, max: 500 },
  { key: 'options', label: 'travel.quiz.optionsInput', type: 'text', required: true },
  { key: 'correct_option_index', label: 'travel.quiz.correctIndex', type: 'number', required: true, min: 0 },
  { key: 'points', label: 'travel.quiz.points', type: 'number', min: 1 },
])
const resultsOpen = ref(false)
const quizResults = ref([])
const modalOpen = ref(false)
const modalTitle = ref('')
const modalFields = ref([])
const editing = ref(null)

async function loadQuizzes() {
  try {
    const response = await listTravel('quizzes')
    quizzes.value = travelList(response)
  } catch {
    quizzes.value = []
  }
}

function openQuizCreate() {
  editing.value = null
  modalTitle.value = t('travel.quiz.create', 'Créer un quiz')
  modalFields.value = [
    { key: 'title', label: 'travel.quiz.title', type: 'text', required: true, max: 160 },
    { key: 'description', label: 'travel.quiz.description', type: 'text', max: 2000 },
    { key: 'status', label: 'travel.common.status', type: 'select', options: quizStatusOptions },
  ]
  modalOpen.value = true
}

async function openQuizQuestions(quiz) {
  selectedQuiz.value = quiz
  quizQuestionsOpen.value = true
  try {
    const response = await listTravel(`quizzes/${quiz.id}`)
    const payload = travelItem(response)
    selectedQuizQuestions.value = payload.questions ?? []
  } catch {
    selectedQuizQuestions.value = []
  }
}

function openQuestionCreate() {
  questionModalOpen.value = true
}

async function saveQuestion(payload) {
  const options = String(payload.options ?? '')
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean)
  try {
    await createTravel(`quizzes/${selectedQuiz.value.id}/questions`, {
      question: payload.question,
      options,
      correct_option_index: Number(payload.correct_option_index),
      points: payload.points ? Number(payload.points) : 1,
    })
    questionModalOpen.value = false
    await openQuizQuestions(selectedQuiz.value)
  } catch {
    questionModalOpen.value = false
  }
}

async function openQuizResults(quiz) {
  selectedQuiz.value = quiz
  resultsOpen.value = true
  try {
    const response = await listTravel(`quizzes/${quiz.id}/results`)
    quizResults.value = travelList(response)
  } catch {
    quizResults.value = []
  }
}

// ── Référentiels annonces + sites (CRUD générique) ───────────────────
const lists = reactive({ types: [], positions: [], prices: [], sites: [] })
const advertStatusFilter = ref('')
const adverts = ref([])

const advertStatusOptions = computed(() => [
  { value: 'draft', label: t('travel.adverts.status.draft', 'Brouillon') },
  { value: 'submitted', label: t('travel.adverts.status.submitted', 'Soumise') },
  { value: 'paid', label: t('travel.adverts.status.paid', 'Payée') },
  { value: 'validated', label: t('travel.adverts.status.validated', 'Validée') },
  { value: 'rejected', label: t('travel.adverts.status.rejected', 'Rejetée') },
  { value: 'expired', label: t('travel.adverts.status.expired', 'Expirée') },
])

const quizStatusOptions = computed(() => [
  { value: 'draft', label: t('travel.quiz.status.draft', 'Brouillon') },
  { value: 'active', label: t('travel.quiz.status.active', 'Actif') },
  { value: 'closed', label: t('travel.quiz.status.closed', 'Clôturé') },
])

const siteStatusOptions = computed(() => [
  { value: 'active', label: t('travel.common.active', 'Actif') },
  { value: 'disabled', label: t('travel.common.disabled', 'Inactif') },
])

const entityConfigs = {
  types: {
    resource: 'advert-types',
    labelField: 'label',
    canCreate: true,
    canDelete: true,
    searchKeys: ['code', 'label'],
    columns: () => [
      { key: 'code', label: 'Code', sortable: true },
      { key: 'label', label: t('travel.adverts.label', 'Libellé'), sortable: true },
    ],
    fields: () => [
      { key: 'code', label: 'travel.adverts.code', type: 'text', required: true, max: 40 },
      { key: 'label', label: 'travel.adverts.label', type: 'text', required: true, max: 120 },
    ],
  },
  positions: {
    resource: 'advert-positions',
    labelField: 'label',
    canCreate: true,
    canDelete: true,
    searchKeys: ['code', 'label'],
    columns: () => [
      { key: 'code', label: 'Code', sortable: true },
      { key: 'label', label: t('travel.adverts.label', 'Libellé'), sortable: true },
    ],
    fields: () => [
      { key: 'code', label: 'travel.adverts.code', type: 'text', required: true, max: 40 },
      { key: 'label', label: 'travel.adverts.label', type: 'text', required: true, max: 120 },
    ],
  },
  prices: {
    resource: 'advert-prices',
    labelField: 'id',
    canCreate: true,
    canDelete: true,
    searchKeys: ['currency'],
    columns: () => [
      { key: 'advert_type', label: t('travel.adverts.type', 'Type'), sortable: true },
      { key: 'advert_position', label: t('travel.adverts.position', 'Position'), sortable: true },
      { key: 'price_per_image_minor', label: t('travel.adverts.priceImage', 'Prix image'), sortable: true },
      { key: 'price_per_character_minor', label: t('travel.adverts.priceChar', 'Prix caractère'), sortable: true },
      { key: 'currency', label: t('travel.adverts.currency', 'Devise'), sortable: true },
    ],
    fields: () => [
      { key: 'advert_type_id', label: 'travel.adverts.type', type: 'number', required: true },
      { key: 'advert_position_id', label: 'travel.adverts.position', type: 'number', required: true },
      { key: 'price_per_image_minor', label: 'travel.adverts.priceImage', type: 'number', required: true, min: 1 },
      { key: 'price_per_character_minor', label: 'travel.adverts.priceChar', type: 'number', required: true, min: 1 },
      { key: 'currency', label: 'travel.adverts.currency', type: 'text', required: true, max: 3 },
    ],
  },
  adverts: {
    resource: 'adverts',
    labelField: 'title',
    canCreate: true,
    canDelete: false,
    searchKeys: ['title'],
    columns: () => [],
    fields: () => [
      { key: 'advert_type_id', label: 'travel.adverts.type', type: 'number', required: true },
      { key: 'advert_position_id', label: 'travel.adverts.position', type: 'number', required: true },
      { key: 'title', label: 'travel.adverts.title', type: 'text', required: true, max: 160 },
      { key: 'content', label: 'travel.adverts.content', type: 'text', required: true, max: 2000 },
      { key: 'validity_days', label: 'travel.adverts.validityDays', type: 'number', min: 1, max: 365 },
    ],
  },
  sites: {
    resource: 'tourist-sites',
    labelField: 'name',
    canCreate: true,
    canEdit: true,
    canDelete: true,
    searchKeys: ['name'],
    columns: () => [
      { key: 'name', label: t('travel.sites.name', 'Nom'), sortable: true },
      { key: 'city_id', label: t('travel.common.city', 'Ville'), sortable: false },
      { key: 'status', label: t('travel.common.status', 'Statut'), sortable: true },
    ],
    fields: () => [
      { key: 'name', label: 'travel.sites.name', type: 'text', required: true, max: 160 },
      { key: 'description', label: 'travel.sites.description', type: 'text', max: 2000 },
      { key: 'city_id', label: 'travel.common.city', type: 'number' },
      { key: 'latitude', label: 'travel.sites.latitude', type: 'number' },
      { key: 'longitude', label: 'travel.sites.longitude', type: 'number' },
      { key: 'status', label: 'travel.common.status', type: 'select', options: siteStatusOptions },
    ],
  },
}

async function loadList(key) {
  const cfg = entityConfigs[key]
  try {
    const response = await listTravel(cfg.resource)
    lists[key] = travelList(response)
  } catch {
    lists[key] = []
  }
}

function switchTab(key) {
  activeTab.value = key
  if (key === 'quiz') loadQuizzes()
  if (key === 'adverts' && advertTab.value === 'adverts') loadAdverts()
  if (key === 'sites') loadList('sites')
  if (key === 'contacts') loadContacts()
}

function openCreate(key) {
  editing.value = null
  modalTitle.value = t('travel.common.create', 'Créer')
  modalFields.value = entityConfigs[key].fields()
  modalOpen.value = true
  saveTargetKey.value = key
}

function openEdit(key, row) {
  editing.value = { ...row }
  modalTitle.value = t('travel.common.edit', 'Modifier')
  modalFields.value = entityConfigs[key].fields()
  modalOpen.value = true
  saveTargetKey.value = key
}

const saveTargetKey = ref('types')

async function saveRow(payload) {
  const key = saveTargetKey.value
  const cfg = entityConfigs[key]
  try {
    if (editing.value?.id) {
      await updateTravel(cfg.resource, editing.value.id, payload)
    } else {
      await createTravel(cfg.resource, payload)
    }
    modalOpen.value = false
    await loadList(key)
  } catch {
    modalOpen.value = false
  }
}

async function removeRow(key, row) {
  const cfg = entityConfigs[key]
  try {
    await deleteTravel(cfg.resource, row.id)
    await loadList(key)
  } catch {
    // best-effort
  }
}

// ── Annonces : cycle de vie ──────────────────────────────────────────
async function loadAdverts() {
  try {
    const response = await listTravel('adverts', { status: advertStatusFilter.value || undefined })
    adverts.value = travelList(response)
  } catch {
    adverts.value = []
  }
}

function openAdvertCreate() {
  editing.value = null
  modalTitle.value = t('travel.adverts.create', 'Soumettre une annonce')
  modalFields.value = entityConfigs.adverts.fields()
  modalOpen.value = true
  saveTargetKey.value = 'adverts'
}

async function advertAction(ad, action) {
  try {
    await travelAction('adverts', ad.id, action)
    await loadAdverts()
  } catch {
    // best-effort
  }
}

const rejectOpen = ref(false)
const rejectTarget = ref(null)
const rejectFields = computed(() => [
  { key: 'reason', label: 'travel.adverts.rejectReason', type: 'text', required: true, max: 500 },
])

function openAdvertReject(ad) {
  rejectTarget.value = ad
  rejectOpen.value = true
}

async function saveReject(payload) {
  try {
    await travelAction('adverts', rejectTarget.value.id, 'reject', payload)
    rejectOpen.value = false
    await loadAdverts()
  } catch {
    rejectOpen.value = false
  }
}

// ── Contacts voyageurs (TRAVEL-912/#6417) ─────────────────────────────
const contacts = ref([])
const contactSearch = ref('')
const notifyOpen = ref(false)
const notifyTarget = ref(null)
const notifyFields = computed(() => [
  { key: 'message', label: 'travel.contacts.message', type: 'text', required: true, max: 2000 },
])
const consentChannels = computed(() => [
  { key: 'email', label: t('travel.contacts.channelEmail', 'Email') },
  { key: 'sms', label: t('travel.contacts.channelSms', 'SMS') },
  { key: 'whatsapp', label: t('travel.contacts.channelWhatsapp', 'WhatsApp') },
])

async function loadContacts() {
  try {
    const response = await listTravel('contacts', { search: contactSearch.value || undefined })
    contacts.value = travelList(response)
  } catch {
    contacts.value = []
  }
}

async function toggleConsent(contact, channel, given) {
  try {
    await travelAction('contacts', contact.id, 'consent', { [`${channel}_consent`]: given })
    contact[`${channel}_consent_given`] = given
  } catch {
    // best-effort
  }
}

function openContactNotify(contact) {
  notifyTarget.value = contact
  notifyOpen.value = true
}

async function saveNotify(payload) {
  try {
    await travelAction('contacts', notifyTarget.value.id, 'notify', payload)
    notifyOpen.value = false
  } catch {
    notifyOpen.value = false
  }
}


/* ── formulaire de contact (TRAVEL-912/#6417) ─────────────────── */
const contactForm = reactive({ first_name: '', last_name: '', email: '', phone: '', message: '', consent_email: false })
const contactFormError = ref('')
const contactFormSending = ref(false)
const contactFormSent = ref(false)

function resetContactForm() {
  contactForm.first_name = ''
  contactForm.last_name = ''
  contactForm.email = ''
  contactForm.phone = ''
  contactForm.message = ''
  contactForm.consent_email = false
  contactFormError.value = ''
  contactFormSent.value = false
}

async function submitContactForm() {
  contactFormSending.value = true
  contactFormError.value = ''
  try {
    if (!contactForm.consent_email) {
      contactFormError.value = t('travel.contacts.form.consentRequired', 'Le consentement de contact est obligatoire.')
      return
    }
    if (!contactForm.email || !String(contactForm.email).includes('@')) {
      contactFormError.value = t('travel.contacts.form.emailInvalid', 'Adresse email invalide.')
      return
    }
    if (!contactForm.message || String(contactForm.message).trim() === '') {
      contactFormError.value = t('travel.contacts.form.messageRequired', 'Le message est requis.')
      return
    }
    await api.post('/travel/contact', {
      first_name: contactForm.first_name || null,
      last_name: contactForm.last_name || null,
      email: String(contactForm.email).trim(),
      phone: contactForm.phone || null,
      message: String(contactForm.message).trim(),
      consent_email: true,
    })
    contactFormSent.value = true
  } catch (error) {
    contactFormError.value = error?.response?.data?.message || error?.message || t('travel.common.loadErrorBody', 'Une erreur est survenue.')
  } finally {
    contactFormSending.value = false
  }
}

onMounted(() => {
  loadQuizzes()
  loadList('types')
  loadList('positions')
  loadList('prices')
})
</script>
