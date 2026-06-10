<template>
  <div class="space-y-8 animate-fade-in">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="space-y-1">
        <router-link to="/companies" class="group inline-flex items-center text-sm font-bold text-brand-600 hover:text-brand-700 dark:text-brand-400 transition-colors">
          <ArrowLeftIcon class="mr-2 h-4 w-4 transition-transform group-hover:-translate-x-1" />
          Retour au portefeuille
        </router-link>
        <h1 class="text-3xl font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
          {{ health?.company?.name || 'Chargement...' }}
          <span v-if="health?.company?.status" :class="statusBadgeClass(health.company.status)">
            {{ health.company.status }}
          </span>
        </h1>
        <p class="text-slate-500 dark:text-slate-400 font-medium">
          Santé opérationnelle, abonnement et configuration des modules.
        </p>
      </div>
      <div class="flex gap-3">
        <button class="btn-secondary py-2.5 shadow-glass-sm" :disabled="isLoading" @click="loadCompany">
          <ArrowPathIcon class="mr-2 h-4 w-4" :class="{ 'animate-spin': isLoading }" />
          Actualiser
        </button>
      </div>
    </div>

    <div v-if="isLoading && !health" class="flex h-64 items-center justify-center rounded-3xl border border-slate-200 bg-white/50 dark:border-slate-800 dark:bg-slate-900/50 backdrop-blur-xl">
      <div class="flex flex-col items-center gap-4">
        <div class="h-12 w-12 animate-spin rounded-full border-4 border-brand-500 border-t-transparent"></div>
        <p class="text-sm font-bold text-slate-500">Analyse des données client...</p>
      </div>
    </div>

    <div v-else-if="errorMessage" class="rounded-3xl border border-red-200 bg-red-50 p-8 text-center dark:border-red-900/30 dark:bg-red-950/20">
      <ExclamationCircleIcon class="mx-auto h-12 w-12 text-red-500" />
      <h3 class="mt-4 text-lg font-bold text-red-800 dark:text-red-400">{{ errorMessage }}</h3>
      <button class="btn-primary mt-6" @click="loadCompany">Réessayer</button>
    </div>

    <template v-else-if="health">
      <!-- Top Stats -->
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4 animate-slide-up">
        <StatsCard title="Score Santé" :value="health.adoption.health_score" unit="/100" icon="HeartIcon" :color="scoreColor" />
        <StatsCard title="Équipe Active" :value="health.adoption.employees.active" icon="UsersIcon" color="green" />
        <StatsCard title="Pointages (30j)" :value="health.adoption.attendance.logs_30d" icon="FingerPrintIcon" color="blue" />
        <StatsCard title="Revenu (MRR)" :value="formatCurrency(health.subscription.mrr, health.subscription.currency)" icon="BanknotesIcon" color="purple" />
      </div>

      <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 animate-slide-up" style="animation-delay: 0.1s">
        <!-- Main Content -->
        <div class="space-y-8 lg:col-span-8">
          <!-- Terrain Adoption -->
          <section class="card">
            <div class="border-b border-slate-200/50 px-6 py-5 dark:border-slate-800/50">
              <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">Adoption Terrain</h2>
                <span :class="riskClass(health.adoption.risk_level)">
                  Niveau de risque : {{ health.adoption.risk_level }}
                </span>
              </div>
            </div>

            <div class="p-6">
              <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 transition-transform hover:scale-[1.02]">
                  <dt class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Onboarding</dt>
                  <dd class="mt-2 text-2xl font-black text-slate-900 dark:text-white">{{ health.adoption.onboarding.progress_percent }}%</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 transition-transform hover:scale-[1.02]">
                  <dt class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Anomalies 30j</dt>
                  <dd class="mt-2 text-2xl font-black text-red-600 dark:text-red-400">{{ health.adoption.anomalies.critical_30d }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 transition-transform hover:scale-[1.02]">
                  <dt class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Paie Prête</dt>
                  <dd class="mt-2 text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ health.adoption.employees.payroll_ready }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 transition-transform hover:scale-[1.02]">
                  <dt class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Kiosk</dt>
                  <dd class="mt-2 flex items-center text-sm font-bold text-slate-900 dark:text-white">
                    <div :class="['mr-2 h-2.5 w-2.5 rounded-full', health.adoption.kiosk.active ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]' : 'bg-slate-300']"></div>
                    {{ health.adoption.kiosk.active ? 'Actif' : 'Inactif' }}
                  </dd>
                </div>
              </div>

              <div class="mt-8">
                <h3 class="text-sm font-black uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500 flex items-center gap-2">
                  <BoltIcon class="h-4 w-4 text-brand-500" />
                  Actions Prioritaires
                </h3>
                <div v-if="health.next_actions.length > 0" class="mt-4 space-y-3">
                  <div
                    v-for="action in health.next_actions"
                    :key="action.key"
                    class="flex items-start gap-4 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900/50"
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
                  <p class="mt-2 text-sm font-medium text-slate-500">Aucun blocage prioritaire détecté.</p>
                </div>
              </div>
            </div>
          </section>

          <!-- Module Features Configuration -->
          <section class="card overflow-hidden">
            <div class="border-b border-slate-200/50 bg-slate-50/50 px-6 py-5 dark:border-slate-800/50 dark:bg-slate-800/30">
              <h2 class="text-xl font-bold text-slate-900 dark:text-white">Configuration des Modules</h2>
              <p class="mt-1 text-sm font-medium text-slate-500">Activez ou désactivez les fonctionnalités spécifiques pour ce client.</p>
            </div>

            <div class="p-6">
              <div v-if="isFeaturesLoading" class="flex h-32 items-center justify-center">
                <div class="h-8 w-8 animate-spin rounded-full border-2 border-brand-500 border-t-transparent"></div>
              </div>
              <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div
                  v-for="(enabled, key) in featuresForm"
                  :key="key"
                  class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50/50 p-4 transition-all hover:bg-white hover:shadow-sm dark:border-slate-800 dark:bg-slate-900/30 dark:hover:bg-slate-900/50"
                >
                  <div class="flex items-center gap-3">
                    <div :class="['flex h-10 w-10 items-center justify-center rounded-xl transition-colors', enabled ? 'bg-brand-100 text-brand-700 dark:bg-brand-900/30 dark:text-brand-400' : 'bg-slate-200 text-slate-400 dark:bg-slate-800 dark:text-slate-600']">
                      <component :is="getFeatureIcon(key)" class="h-5 w-5" />
                    </div>
                    <div>
                      <p class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-wide">{{ formatFeatureName(key) }}</p>
                      <p class="text-[10px] font-bold text-slate-500 uppercase">{{ enabled ? 'Module Actif' : 'Module Désactivé' }}</p>
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
                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out'
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
                  {{ isSavingFeatures ? 'Mise à jour...' : 'Sauvegarder la configuration' }}
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
                Abonnement
              </h2>
            </div>

            <form class="p-6 space-y-5" @submit.prevent="saveSubscription">
              <div class="space-y-1.5">
                <label class="text-xs font-black uppercase tracking-widest text-slate-500" for="plan">Plan de services</label>
                <select id="plan" v-model.number="subscriptionForm.plan_id" class="form-input">
                  <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                    {{ plan.name }} — {{ formatCurrency(plan.price_monthly, health.company.currency) }}/m
                  </option>
                </select>
              </div>

              <div class="space-y-1.5">
                <label class="text-xs font-black uppercase tracking-widest text-slate-500" for="status">Statut Commercial</label>
                <select id="status" v-model="subscriptionForm.status" class="form-input">
                  <option value="trial">Essai (Trial)</option>
                  <option value="active">Actif</option>
                  <option value="suspended">Suspendu</option>
                  <option value="expired">Expiré</option>
                </select>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                  <label class="text-xs font-black uppercase tracking-widest text-slate-500" for="subscription_start">Début</label>
                  <input id="subscription_start" v-model="subscriptionForm.subscription_start" type="date" class="form-input" />
                </div>
                <div class="space-y-1.5">
                  <label class="text-xs font-black uppercase tracking-widest text-slate-500" for="subscription_end">Fin</label>
                  <input id="subscription_end" v-model="subscriptionForm.subscription_end" type="date" class="form-input" />
                </div>
              </div>

              <div class="space-y-1.5">
                <label class="text-xs font-black uppercase tracking-widest text-slate-500" for="notes">Notes Internes</label>
                <textarea id="notes" v-model="subscriptionForm.notes" rows="3" class="form-input" placeholder="Détails sur la négociation, remises, etc."></textarea>
              </div>

              <button class="btn-primary w-full justify-center shadow-premium py-3" :disabled="isSavingSubscription">
                <span v-if="isSavingSubscription" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                {{ isSavingSubscription ? 'Enregistrement...' : 'Mettre à jour l\'abonnement' }}
              </button>
            </form>
          </section>

          <!-- System Info -->
          <section class="card bg-slate-900 text-white border-none shadow-premium overflow-hidden relative">
            <div class="absolute -top-12 -right-12 h-40 w-40 rounded-full bg-brand-500/10 blur-3xl"></div>
            <div class="p-6 relative z-10">
              <h2 class="text-lg font-bold">Identité Technique</h2>
              <dl class="mt-6 space-y-4">
                <div class="flex justify-between items-center border-b border-white/5 pb-3">
                  <dt class="text-xs font-bold text-slate-400 uppercase tracking-widest">ID Plateforme</dt>
                  <dd class="text-xs font-mono font-bold">{{ health.company.id.substring(0, 8) }}...</dd>
                </div>
                <div class="flex justify-between items-center border-b border-white/5 pb-3">
                  <dt class="text-xs font-bold text-slate-400 uppercase tracking-widest">Slug</dt>
                  <dd class="text-xs font-bold">{{ health.company.slug }}</dd>
                </div>
                <div class="flex justify-between items-center border-b border-white/5 pb-3">
                  <dt class="text-xs font-bold text-slate-400 uppercase tracking-widest">Pays / Devise</dt>
                  <dd class="text-xs font-bold">{{ health.company.country }} / {{ health.company.currency }}</dd>
                </div>
                <div class="flex justify-between items-center border-b border-white/5 pb-3">
                  <dt class="text-xs font-bold text-slate-400 uppercase tracking-widest">Inscrit le</dt>
                  <dd class="text-xs font-bold">{{ formatDate(health.company.created_at) }}</dd>
                </div>
                <div class="flex justify-between items-center">
                  <dt class="text-xs font-bold text-slate-400 uppercase tracking-widest">Dernière Activité</dt>
                  <dd class="text-xs font-bold text-brand-400">{{ formatDateTime(health.adoption.attendance.last_punch_at) }}</dd>
                </div>
              </dl>

              <button class="mt-8 w-full inline-flex items-center justify-center rounded-xl bg-white/5 py-2.5 text-xs font-black uppercase tracking-widest text-slate-300 hover:bg-white/10 transition-colors border border-white/10">
                <CommandLineIcon class="mr-2 h-4 w-4" />
                Accès Super-Console
              </button>
            </div>
          </section>
        </aside>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import { Switch } from '@headlessui/vue'
import {
  ArrowLeftIcon,
  ArrowPathIcon,
  ExclamationCircleIcon,
  HeartIcon,
  UsersIcon,
  FingerPrintIcon,
  BanknotesIcon,
  BoltIcon,
  CheckBadgeIcon,
  CreditCardIcon,
  CommandLineIcon,
  CloudArrowUpIcon,
  BuildingOfficeIcon,
  SparklesIcon,
  TruckIcon,
  ClipboardDocumentCheckIcon,
  AcademicCapIcon,
  GlobeAltIcon,
  DevicePhoneMobileIcon
} from '@heroicons/vue/24/outline'
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'

const route = useRoute()
const toast = useToast()

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

const scoreColor = computed(() => {
  const score = health.value?.adoption?.health_score || 0
  if (score >= 75) return 'green'
  if (score >= 50) return 'yellow'
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
    errorMessage.value = 'Impossible de charger le detail entreprise. Verifiez que l\'UUID est valide.'
  } finally {
    isLoading.value = false
  }
}

async function saveSubscription() {
  if (isSavingSubscription.value) return
  isSavingSubscription.value = true

  try {
    await api.patch(`/platform/companies/${route.params.id}/subscription`, subscriptionForm.value)
    toast.success('Abonnement mis à jour avec succès.')
    await loadCompany()
  } catch (error) {
    console.error('Failed to save subscription:', error)
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
    toast.success('Configuration des modules enregistrée.')
    originalFeatures.value = { ...featuresForm.value }
  } catch (error) {
    console.error('Failed to save features:', error)
  } finally {
    isSavingFeatures.value = false
  }
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
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: currency || 'EUR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0))
}

function formatDate(value) {
  if (!value) return 'Non renseigné'
  return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' }).format(new Date(value))
}

function formatDateTime(value) {
  if (!value) return 'Aucun pointage'
  return new Intl.DateTimeFormat('fr-FR', {
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
  const names = {
    rh: 'Ressources Humaines',
    finance: 'Finance & Gestion',
    ai: 'Intelligence Artificielle',
    cameras: 'Surveillance Vidéo',
    tracking: 'Suivi de Flotte',
    planning: 'Planning & Equipe',
    training: 'Centre de Formation',
    cabinet: 'Placard Numérique',
  }
  return names[key] || key.toUpperCase()
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
.form-input {
  @apply block w-full rounded-2xl border border-slate-200 bg-white/50 px-4 py-3 text-sm font-bold text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-slate-800 dark:bg-slate-950/50 dark:text-white backdrop-blur-sm;
}
</style>
