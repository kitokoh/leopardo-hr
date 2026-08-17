<template>
  <div class="space-y-8 animate-fade-in">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="space-y-1">
        <router-link to="/companies" class="group inline-flex items-center text-sm font-bold text-brand-600 hover:text-brand-700 dark:text-brand-400 transition-colors">
          <ArrowLeftIcon class="mr-2 h-4 w-4 transition-transform group-hover:-translate-x-1" />
          {{ t('companyDetail.backToPortfolio', 'Retour au portefeuille') }}
        </router-link>
        <h1 class="text-3xl font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
          {{ health?.company?.name || t('companyDetail.loading', 'Chargement...') }}
          <span v-if="health?.company?.status" :class="statusBadgeClass(health.company.status)">
            {{ health.company.status }}
          </span>
        </h1>
        <p class="text-slate-500 dark:text-slate-400 font-medium">
          {{ t('companyDetail.subtitle', 'Santé opérationnelle, abonnement et configuration des modules.') }}
        </p>
      </div>
      <div class="flex gap-3">
        <button class="btn-secondary py-2.5 shadow-glass-sm" :disabled="isLoading" @click="loadCompany">
          <ArrowPathIcon class="mr-2 h-4 w-4" :class="{ 'animate-spin': isLoading }" />
          {{ t('companyDetail.refresh', 'Actualiser') }}
        </button>
      </div>
    </div>

    <div v-if="isLoading && !health" class="flex h-64 items-center justify-center rounded-3xl border border-slate-200 dark:border-slate-800 dark:bg-slate-900/50 backdrop-blur-xl">
      <div class="flex flex-col items-center gap-4">
        <div class="h-12 w-12 animate-spin rounded-full border-4 border-brand-500 border-t-transparent"></div>
        <p class="text-sm font-bold text-slate-500">{{ t('companyDetail.analyzing') }}</p>
      </div>
    </div>

    <div v-else-if="errorMessage" class="rounded-3xl border border-red-200 bg-red-50 p-8 text-center dark:border-red-900/30 dark:bg-red-950/20">
      <ExclamationCircleIcon class="mx-auto h-12 w-12 text-red-500" />
      <h3 class="mt-4 text-lg font-bold text-red-800 dark:text-red-400">{{ errorMessage }}</h3>
      <button class="btn-primary mt-6" @click="loadCompany">{{ t('companyDetail.retry') }}</button>
    </div>

    <template v-else-if="health">
      <!-- Top Stats -->
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4 animate-slide-up">
        <StatsCard :title="t('companyDetail.scoreHealth', 'Score Santé')" :value="health.adoption.health_score" unit="/100" icon="HeartIcon" :color="scoreColor" />
        <StatsCard :title="t('companyDetail.teamActive', 'Équipe Active')" :value="health.adoption.employees.active" icon="UsersIcon" color="green" />
        <StatsCard :title="t('companies.checkins30d', 'Pointage (30j)')" :value="health.adoption.attendance.logs_30d" icon="FingerPrintIcon" color="blue" />
        <StatsCard :title="t('companyDetail.revenueMrr', 'Revenu (MRR)')" :value="formatCurrency(health.subscription.mrr, health.subscription.currency)" icon="BanknotesIcon" color="purple" />
      </div>

      <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 animate-slide-up" style="animation-delay: 0.1s">
        <!-- Main Content -->
        <div class="space-y-8 lg:col-span-8">
          <!-- Terrain Adoption -->
          <section class="card">
            <div class="border-b border-slate-200/50 px-6 py-5 dark:border-slate-800/50">
              <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ t('companyDetail.fieldAdoption') }}</h2>
                <span :class="riskClass(health.adoption.risk_level)">
                  {{ t('companyDetail.riskLevel', 'Niveau de risque :') }} {{ health.adoption.risk_level }}
                </span>
              </div>
            </div>

            <div class="p-6">
              <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 transition-transform hover:scale-[1.02]">
                  <dt class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">{{ t('companyDetail.onboarding') }}</dt>
                  <dd class="mt-2 text-2xl font-black text-slate-900 dark:text-white">{{ health.adoption.onboarding.progress_percent }}%</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 transition-transform hover:scale-[1.02]">
                  <dt class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">{{ t('companyDetail.anomalies30d') }}</dt>
                  <dd class="mt-2 text-2xl font-black text-red-600 dark:text-red-400">{{ health.adoption.anomalies.critical_30d }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 transition-transform hover:scale-[1.02]">
                  <dt class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">{{ t('companyDetail.payrollReady') }}</dt>
                  <dd class="mt-2 text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ health.adoption.employees.payroll_ready }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 transition-transform hover:scale-[1.02]">
                  <dt class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">{{ t('companyDetail.activeEmployees30d') }}</dt>
                  <dd class="mt-2 text-2xl font-black text-slate-900 dark:text-white">{{ health.adoption.attendance.active_employees_30d ?? 0 }}</dd>
                </div>
              </div>

              <div class="mt-8">
                <h3 class="text-sm font-black uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500 flex items-center gap-2">
                  <BoltIcon class="h-4 w-4 text-brand-500" />
                  {{ t('companyDetail.priorityActions', 'Actions Prioritaires') }}
                </h3>
                <div v-if="health.next_actions.length > 0" class="mt-4 space-y-3">
                  <div
                    v-for="action in health.next_actions"
                    :key="action.key"
                    class="flex items-start gap-4 rounded-2xl border border-slate-100 p-4 shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900/50"
                  >
                    <span :class="['mt-0.5 rounded-lg px-2 py-0.5 text-[10px] font-black uppercase', priorityBadgeClass(action.priority)]">
                      {{ action.priority }}
                    </span>
                    <div class="flex-1">
                      <p class="text-sm font-bold text-slate-900 dark:text-white">{{ action.label }}</p>
                      <p v-if="action.description" class="mt-1 text-xs text-slate-500">{{ action.description }}</p>
                    </div>
                  </div>
                </div>
                <div v-else class="mt-4 flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 py-8 dark:border-slate-800">
                  <CheckBadgeIcon class="h-10 w-10 text-emerald-500/50" />
                  <p class="mt-2 text-sm font-medium text-slate-500">{{ t('companyDetail.noPriorityBlockers') }}</p>
                </div>
              </div>
            </div>
          </section>

          <!-- Module Features Configuration -->
          <section class="card overflow-hidden">
            <div class="border-b border-slate-200/50 bg-slate-50/50 px-6 py-5 dark:border-slate-800/50 dark:bg-slate-800/30">
              <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ t('companyDetail.modulesConfig') }}</h2>
              <p class="mt-1 text-sm font-medium text-slate-500">{{ t('companyDetail.modulesConfigHint', 'Activez ou désactivez les fonctionnalités spécifiques pour ce client.') }}</p>
            </div>

            <div class="p-6">
              <div v-if="isFeaturesLoading" class="flex h-32 items-center justify-center">
                <div class="h-8 w-8 animate-spin rounded-full border-2 border-brand-500 border-t-transparent"></div>
              </div>
              <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div
                  v-for="(enabled, key) in featuresForm"
                  :key="key"
                  class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50/50 p-4 transition-all hover:glass-card hover:shadow-sm dark:border-slate-800 dark:bg-slate-900/30 dark:hover:bg-slate-900/50"
                >
                  <div class="flex items-center gap-3">
                    <div :class="['flex h-10 w-10 items-center justify-center rounded-xl transition-colors', enabled ? 'bg-brand-100 text-brand-700 dark:bg-brand-900/30 dark:text-brand-400' : 'bg-slate-200 text-slate-400 dark:bg-slate-800 dark:text-slate-600']">
                      <component :is="getFeatureIcon(key)" class="h-5 w-5" />
                    </div>
                    <div>
                      <p class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-wide">{{ formatFeatureName(key) }}</p>
                      <p class="text-[10px] font-bold text-slate-500 uppercase">{{ enabled ? t('companyDetail.moduleActive', 'Module Actif') : t('companyDetail.moduleDisabled', 'Module Désactivé') }}</p>
                    </div>
                  </div>
                  <Switch
                    v-model="featuresForm[key]"
                    :disabled="key === 'rh' || isSavingFeatures"
                    :class="[
                      enabled ? 'bg-brand-600' : 'bg-slate-200 dark:bg-slate-700',
                      'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-950',
                      key === 'rh' ? 'opacity-50 cursor-not-allowed' : ''
                    ]"
                  >
                    <span
                      aria-hidden="true"
                      :class="[
                        enabled ? 'translate-x-5' : 'translate-x-0',
                        'pointer-events-none inline-block h-5 w-5 transform rounded-full shadow ring-0 transition duration-200 ease-in-out'
                      ]"
                    />
                  </Switch>
                </div>
              </div>

              <div class="mt-8 flex justify-end">
                <button
                  class="btn-primary"
                  :disabled="isSavingFeatures || !isFeaturesDirty"
                  @click="saveFeatures"
                >
                  <CloudArrowUpIcon v-if="!isSavingFeatures" class="mr-2 h-4 w-4" />
                  <span v-else class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                  {{ isSavingFeatures ? t('companyDetail.updating', 'Mise à jour...') : t('companyDetail.saveConfig', 'Sauvegarder la configuration') }}
                </button>
              </div>
            </div>
          </section>
        </div>

        <!-- Sidebar Actions -->
        <aside class="space-y-8 lg:col-span-4">
          <!-- Subscription -->
          <section class="card">
            <div class="border-b border-slate-200/50 bg-slate-50/50 px-6 py-5 dark:border-slate-800/50 dark:bg-slate-800/30">
              <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <CreditCardIcon class="h-5 w-5 text-purple-500" />
                {{ t('companyDetail.subscription', 'Abonnement') }}
              </h2>
            </div>

            <form class="p-6 space-y-5" @submit.prevent="saveSubscription">
              <div class="space-y-1.5">
                <label class="text-xs font-black uppercase tracking-widest text-slate-500" for="plan">{{ t('companyDetail.servicePlan') }}</label>
                <select id="plan" v-model.number="subscriptionForm.plan_id" class="form-input">
                  <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                    {{ plan.name }} — {{ formatCurrency(plan.price_monthly, health.company.currency) }}/m
                  </option>
                </select>
              </div>

              <div class="space-y-1.5">
                <label class="text-xs font-black uppercase tracking-widest text-slate-500" for="status">{{ t('companyDetail.commercialStatus') }}</label>
                <select id="status" v-model="subscriptionForm.status" class="form-input">
                  <option value="trial">{{ t('companyDetail.statusTrial') }}</option>
                  <option value="active">{{ t('companyDetail.statusActive') }}</option>
                  <option value="suspended">{{ t('companyDetail.statusSuspended') }}</option>
                  <option value="expired">{{ t('companyDetail.statusExpired') }}</option>
                </select>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                  <label class="text-xs font-black uppercase tracking-widest text-slate-500" for="subscription_start">{{ t('companyDetail.startDate') }}</label>
                  <input id="subscription_start" v-model="subscriptionForm.subscription_start" type="date" class="form-input" />
                </div>
                <div class="space-y-1.5">
                  <label class="text-xs font-black uppercase tracking-widest text-slate-500" for="subscription_end">{{ t('companyDetail.endDate', 'Fin') }}</label>
                  <input id="subscription_end" v-model="subscriptionForm.subscription_end" type="date" class="form-input" />
                </div>
              </div>

              <div class="space-y-1.5">
                <label class="text-xs font-black uppercase tracking-widest text-slate-500" for="notes">{{ t('companyDetail.internalNotes') }}</label>
                <textarea id="notes" v-model="subscriptionForm.notes" rows="3" class="form-input" :placeholder="t('companyDetail.notesPlaceholder', 'Détails sur la négociation, remises, etc.')"></textarea>
              </div>

              <!-- Activer client -->
              <button class="btn-primary w-full justify-center shadow-premium py-3" :disabled="isSavingSubscription">
                <span v-if="isSavingSubscription" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                {{ isSavingSubscription ? t('companyDetail.saving', 'Enregistrement...') : t('companyDetail.updateSubscription', "Mettre à jour l'abonnement") }}
              </button>
              <button
                v-if="health?.company?.status === 'trial'"
                id="btn-activer-client"
                type="button"
                class="w-full py-2.5 rounded-xl border border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-xs font-black uppercase tracking-widest hover:bg-emerald-500/20 transition-colors"
                :disabled="isSavingSubscription"
                @click="activateClient"
              >
                {{ t('companyDetail.activateClient', 'Activer client') }}
              </button>
            </form>
          </section>

          <!-- Support Tickets -->
          <section class="card overflow-hidden">
            <div class="border-b border-slate-200/50 bg-slate-50/50 px-6 py-5 dark:border-slate-800/50 dark:bg-slate-800/30 flex items-center justify-between gap-3">
              <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <LifebuoyIcon class="h-5 w-5 text-amber-500" />
                {{ t('companyDetail.support', 'Support') }}
                <span v-if="supportSummary.open_count > 0" class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black uppercase text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                  {{ supportSummary.open_count }} {{ supportSummary.open_count > 1 ? t('companyDetail.openPlural', 'ouverts') : t('companyDetail.openSingular', 'ouvert') }}
                </span>
              </h2>
              <router-link
                :to="{ name: 'support-tickets', query: { company_id: route.params.id, company_name: health?.company?.name || '' } }"
                class="text-xs font-black uppercase tracking-widest text-brand-600 hover:text-brand-700 dark:text-brand-400"
              >
                {{ t('companyDetail.viewAllTickets', 'Voir tous les tickets') }}
              </router-link>
            </div>

            <div class="p-6">
              <div v-if="isSupportLoading" class="flex h-24 items-center justify-center">
                <div class="h-8 w-8 animate-spin rounded-full border-2 border-brand-500 border-t-transparent"></div>
              </div>
              <div v-else-if="supportTickets.length === 0" class="flex flex-col items-center justify-center gap-2 py-6 text-center">
                <CheckBadgeIcon class="h-8 w-8 text-emerald-500/50" />
                <p class="text-sm font-medium text-slate-500">{{ t('companyDetail.noSupportTickets') }}</p>
              </div>
              <ul v-else class="space-y-3">
                <li
                  v-for="ticket in supportTickets"
                  :key="ticket.id"
                  class="rounded-2xl border border-slate-100 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/30"
                >
                  <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ ticket.subject }}</p>
                    <span :class="['shrink-0 rounded-lg border px-2 py-0.5 text-[9px] font-black uppercase tracking-widest', ticketPriorityClass(ticket.priority)]">
                      {{ ticket.priority }}
                    </span>
                  </div>
                  <div class="mt-2 flex items-center justify-between">
                    <span :class="['rounded-lg border px-2 py-0.5 text-[9px] font-black uppercase tracking-widest', ticketStatusClass(ticket.status)]">
                      {{ ticket.status }}
                    </span>
                    <span class="text-[10px] font-semibold text-slate-400">{{ ticket.messages_count }} {{ ticket.messages_count > 1 ? t('companyDetail.messagePlural', 'messages') : t('companyDetail.messageSingular', 'message') }}</span>
                  </div>
                </li>
              </ul>
            </div>
          </section>

          <!-- System Info -->
          <section class="card bg-slate-900 text-white border-none shadow-premium overflow-hidden relative">
            <div class="absolute -top-12 -right-12 h-40 w-40 rounded-full bg-brand-500/10 blur-3xl"></div>
            <div class="p-6 relative z-10">
              <h2 class="text-lg font-bold">{{ t('companyDetail.technicalIdentity') }}</h2>
              <dl class="mt-6 space-y-4">
                <div class="flex justify-between items-center border-b border-white/5 pb-3">
                  <dt class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ t('companyDetail.platformId') }}</dt>
                  <dd class="text-xs font-mono font-bold">{{ health.company.id.substring(0, 8) }}...</dd>
                </div>
                <div class="flex justify-between items-center border-b border-white/5 pb-3">
                  <dt class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ t('companyDetail.slug') }}</dt>
                  <dd class="text-xs font-bold">{{ health.company.slug }}</dd>
                </div>
                <div class="flex justify-between items-center border-b border-white/5 pb-3">
                  <dt class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ t('companyDetail.countryCurrency') }}</dt>
                  <dd class="text-xs font-bold">{{ health.company.country }} / {{ health.company.currency }}</dd>
                </div>
                <div class="flex justify-between items-center border-b border-white/5 pb-3">
                  <dt class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ t('companyDetail.registeredOn') }}</dt>
                  <dd class="text-xs font-bold">{{ formatDate(health.company.created_at) }}</dd>
                </div>
                <div class="flex justify-between items-center">
                  <dt class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ t('companyDetail.lastActivity') }}</dt>
                  <dd class="text-xs font-bold text-brand-400">{{ formatDateTime(health.adoption.attendance.last_punch_at) }}</dd>
                </div>
              </dl>

              
            </div>
          </section>
        </aside>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n'
import { useLocaleStore } from '@/stores/locale'
import { Switch } from '@headlessui/vue'
import {
  ArrowLeftIcon,
  ArrowPathIcon,
  ExclamationCircleIcon,
  BanknotesIcon,
  BoltIcon,
  CheckBadgeIcon,
  CreditCardIcon,
  CloudArrowUpIcon,
  BuildingOfficeIcon,
  SparklesIcon,
  TruckIcon,
  ClipboardDocumentCheckIcon,
  AcademicCapIcon,
  GlobeAltIcon,
  DevicePhoneMobileIcon,
  LifebuoyIcon
} from '@heroicons/vue/24/outline'
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'
import { toIntlLocale } from '@/i18n/index.js'

const route = useRoute()
const toast = useToast()
const localeStore = useLocaleStore()

// #4206 : traduction via le catalogue admin.
function t(key, fallback = '') {
  return translate(localeStore.current, key, fallback)
}

const isLoading = ref(false)
const errorMessage = ref('')
const health = ref(null)
const plans = ref([])

// Subscription form
const isSavingSubscription = ref(false)
const subscriptionForm = ref({
  plan_id: null,
  status: 'trial',
  subscription_start: '',
  subscription_end: '',
  notes: '',
})

// Features form
const isFeaturesLoading = ref(false)
const isSavingFeatures = ref(false)
const featuresForm = ref({})
const originalFeatures = ref({})

// PA2-ADM-003: support tickets summary for this company, so an admin
// looking at a company's file sees its support activity/risk without
// having to jump to the global support center and filter manually.
const isSupportLoading = ref(false)
const supportTickets = ref([])
const supportSummary = ref({ open_count: 0 })

const scoreColor = computed(() => {
  const healthScore = health.value?.adoption?.health_score ?? 0
  if (healthScore >= 75) return 'green'
  if (healthScore >= 50) return 'yellow'
  return 'red'
})

const isFeaturesDirty = computed(() => {
  return JSON.stringify(featuresForm.value) !== JSON.stringify(originalFeatures.value)
})

async function loadCompany() {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const [healthResponse, plansResponse, subscriptionResponse, featuresResponse] = await Promise.all([
      api.get(`/platform/companies/${route.params.id}/health`),
      api.get('/platform/plans'),
      api.get(`/platform/companies/${route.params.id}/subscription`),
      api.get(`/platform/companies/${route.params.id}/features`)
    ])

    health.value = healthResponse.data?.data || null
    plans.value = plansResponse.data?.data?.items || []
    fillSubscriptionForm(subscriptionResponse.data?.data)

    const feats = featuresResponse.data?.data?.features || {}
    featuresForm.value = { ...feats }
    originalFeatures.value = { ...feats }
  } catch (error) {
    console.error('Failed to load company detail:', error)
    errorMessage.value = t('companyDetail.loadFailed', 'Impossible de charger le detail entreprise. Verifiez que l\'UUID est valide.')
    toast.error(translate(localeStore.current, 'companies.toast.load_failed', 'companies.toast.load_failed'))
  } finally {
    isLoading.value = false
  }

  await loadSupportTickets()
}

async function loadSupportTickets() {
  isSupportLoading.value = true

  try {
    const response = await api.get('/platform/support-tickets', {
      params: { company_id: route.params.id, per_page: 5 },
    })
    supportTickets.value = response.data?.data || []
    const counts = response.data?.meta?.status_counts || {}
    supportSummary.value = {
      open_count: (counts.open || 0) + (counts.pending || 0),
    }
  } catch (error) {
    console.error('Failed to load support tickets for company:', error)
    supportTickets.value = []
    toast.error(translate(localeStore.current, 'companies.toast.tickets_failed', 'companies.toast.tickets_failed'))
  } finally {
    isSupportLoading.value = false
  }
}

async function saveSubscription() {
  if (isSavingSubscription.value) return
  isSavingSubscription.value = true

  try {
    await api.patch(`/platform/companies/${route.params.id}/subscription`, subscriptionForm.value)
    toast.success(t('companyDetail.subscriptionUpdated', 'Abonnement mis à jour avec succès.'))
    await loadCompany()
  } catch (error) {
    console.error('Failed to save subscription:', error)
    toast.error(translate(localeStore.current, 'companies.toast.subscription_failed', 'companies.toast.subscription_failed'))
  } finally {
    isSavingSubscription.value = false
  }
}

async function saveFeatures() {
  if (isSavingFeatures.value) return
  isSavingFeatures.value = true

  try {
    await api.patch(`/platform/companies/${route.params.id}/features`, {
      features: featuresForm.value
    })
    toast.success(t('companyDetail.featuresSaved', 'Configuration des modules enregistrée.'))
    originalFeatures.value = { ...featuresForm.value }
  } catch (error) {
    console.error('Failed to save features:', error)
    toast.error(translate(localeStore.current, 'companies.toast.features_failed', 'companies.toast.features_failed'))
  } finally {
    isSavingFeatures.value = false
  }
}

async function activateClient() {
  if (!subscriptionForm.value.plan_id) {
    toast.error(t('companyDetail.planMissing', 'Plan client manquant.'))
    return
  }

  subscriptionForm.value.status = 'active'
  await saveSubscription()
}

function fillSubscriptionForm(subscription) {
  if (!subscription) return

  subscriptionForm.value = {
    plan_id: subscription.plan?.id || null,
    status: subscription.status || 'trial',
    subscription_start: subscription.subscription_start || '',
    subscription_end: subscription.subscription_end || '',
    notes: subscription.notes || '',
  }
}

function formatCurrency(value, currency = 'EUR') {
  const amount = Number(value || 0)
  const intlLocale = toIntlLocale(localeStore.current)

  try {
    return new Intl.NumberFormat(intlLocale, {
      style: 'currency',
      currency: currency || 'EUR',
      maximumFractionDigits: 0,
    }).format(amount)
  } catch {
    return new Intl.NumberFormat(intlLocale, {
      style: 'currency',
      currency: 'EUR',
      maximumFractionDigits: 0,
    }).format(amount)
  }
}

function formatDate(value) {
  if (!value) return t('support.notProvided', 'Non renseigné')
  return new Intl.DateTimeFormat(toIntlLocale(localeStore.current), { dateStyle: 'medium' }).format(new Date(value))
}

function formatDateTime(value) {
  if (!value) return t('companyDetail.noPunch', 'Aucun pointage')
  return new Intl.DateTimeFormat(toIntlLocale(localeStore.current), {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function riskClass(risk) {
  const classes = {
    high: 'rounded-full bg-red-100 dark:bg-red-900/30 px-3 py-1 text-xs font-black uppercase tracking-widest text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800',
    medium: 'rounded-full bg-amber-100 dark:bg-amber-900/30 px-3 py-1 text-xs font-black uppercase tracking-widest text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800',
    low: 'rounded-full bg-emerald-100 dark:bg-emerald-900/30 px-3 py-1 text-xs font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800',
  }
  return classes[risk] || classes.medium
}

function statusBadgeClass(status) {
  const classes = {
    active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
    trial: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800',
    suspended: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 border-red-200 dark:border-red-800',
    expired: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
  }
  return `text-[10px] font-black uppercase tracking-[0.2em] px-2 py-0.5 rounded-md border ${classes[status] || classes.expired}`
}

function priorityBadgeClass(priority) {
  if (priority === 'high' || priority === 'P0') return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 border border-red-200 dark:border-red-800'
  if (priority === 'medium' || priority === 'P1') return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800'
  return 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 border border-blue-200 dark:border-blue-800'
}

function formatFeatureName(key) {
  const featureKeys = {
    rh: 'companyDetail.features.rh',
    finance: 'companyDetail.features.finance',
    ai: 'companyDetail.features.ai',
    cameras: 'companyDetail.features.cameras',
    tracking: 'companyDetail.features.tracking',
    planning: 'companyDetail.features.planning',
    training: 'companyDetail.features.training',
    cabinet: 'companyDetail.features.cabinet',
    biometric: 'subscriptions.features.biometric',
    tasks: 'subscriptions.features.tasks',
    advanced_reports: 'subscriptions.features.advanced_reports',
    excel_export: 'subscriptions.features.excel_export',
    bank_export: 'subscriptions.features.bank_export',
    billing_auto: 'subscriptions.features.billing_auto',
    multi_managers: 'subscriptions.features.multi_managers',
    photo_attendance: 'subscriptions.features.photo_attendance',
    api_public: 'subscriptions.features.api_public',
    evaluations: 'subscriptions.features.evaluations',
    schema_isolation: 'subscriptions.features.schema_isolation',
  }
  const fallbacks = {
    rh: 'Ressources Humaines',
    finance: 'Finance & Gestion',
    ai: 'Intelligence Artificielle',
    cameras: 'Surveillance Vidéo',
    tracking: 'Suivi de Flotte',
    planning: 'Planning & Equipe',
    training: 'Centre de Formation',
    cabinet: 'Placard Numérique',
    biometric: 'Biométrie',
    tasks: 'Tâches',
    advanced_reports: 'Rapports avancés',
    excel_export: 'Export Excel',
    bank_export: 'Export bancaire',
    billing_auto: 'Facturation auto',
    multi_managers: 'Multi-gérants',
    photo_attendance: 'Pointage photo',
    api_public: 'API publique',
    evaluations: 'Évaluations',
    schema_isolation: 'Isolation schéma',
  }
  const catalogKey = featureKeys[key]
  if (!catalogKey) return key.toUpperCase()
  return translate(localeStore.current, catalogKey, fallbacks[key] || catalogKey)
}

function ticketPriorityClass(priority) {
  const classes = {
    urgent: 'border-red-300 bg-red-50 text-red-700 dark:bg-red-950/30 dark:text-red-300',
    high: 'border-orange-300 bg-orange-50 text-orange-700 dark:bg-orange-950/30 dark:text-orange-300',
    normal: 'border-slate-200 bg-slate-50 text-slate-600 dark:bg-slate-900/40 dark:text-slate-300',
    low: 'border-slate-200 bg-slate-50 text-slate-400 dark:bg-slate-900/40 dark:text-slate-500',
  }
  return classes[priority] || classes.normal
}

function ticketStatusClass(status) {
  const classes = {
    open: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800',
    pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 border-amber-200 dark:border-amber-800',
    resolved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
    closed: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
  }
  return classes[status] || classes.closed
}

function getFeatureIcon(key) {
  const icons = {
    rh: BuildingOfficeIcon,
    finance: BanknotesIcon,
    ai: SparklesIcon,
    cameras: GlobeAltIcon,
    tracking: TruckIcon,
    planning: ClipboardDocumentCheckIcon,
    training: AcademicCapIcon,
    cabinet: DevicePhoneMobileIcon,
  }
  return icons[key] || SparklesIcon
}

onMounted(loadCompany)
</script>

<style scoped>
@reference '../../style.css';
.form-input {
  @apply block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-slate-800 dark:bg-slate-950/50 dark:text-white backdrop-blur-sm;
}
</style>

