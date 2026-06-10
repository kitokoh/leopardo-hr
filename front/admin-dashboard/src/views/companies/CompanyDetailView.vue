<template>
  <div class="space-y-8 animate-fade-in">
    <div class="rounded-[2rem] border border-white/20 bg-white/80 p-6 shadow-glass backdrop-blur-xl dark:border-slate-800/70 dark:bg-slate-950/80 sm:p-8">
      <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <router-link to="/companies" class="text-sm font-black uppercase tracking-[0.2em] text-brand-600 transition hover:text-brand-800 dark:text-brand-400">
            Retour cockpit
          </router-link>
          <div class="mt-4 flex flex-wrap items-center gap-3">
            <h1 class="text-3xl font-black tracking-tight text-slate-950 dark:text-white sm:text-4xl">
              {{ health?.company?.name || 'Entreprise' }}
            </h1>
            <span :class="statusClass(subscriptionForm.status)">
              {{ subscriptionForm.status || 'unknown' }}
            </span>
          </div>
          <p class="mt-3 max-w-3xl text-sm font-medium text-slate-500 dark:text-slate-400">
            Vue super-admin pour verifier l'adoption terrain, activer le client, ajuster l'abonnement et traiter les risques avant lancement commercial.
          </p>
          <div v-if="health?.company" class="mt-5 flex flex-wrap gap-2 text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">
            <span class="rounded-full border border-slate-200 px-3 py-1.5 dark:border-slate-800">{{ health.company.country }}</span>
            <span class="rounded-full border border-slate-200 px-3 py-1.5 dark:border-slate-800">{{ health.company.currency }}</span>
            <span class="rounded-full border border-slate-200 px-3 py-1.5 dark:border-slate-800">{{ health.company.timezone || 'Timezone API' }}</span>
          </div>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row lg:flex-col xl:flex-row">
          <button
            v-if="isTrial"
            class="btn-primary justify-center"
            :disabled="isSaving"
            @click="activateClient"
          >
            {{ isSaving ? 'Activation...' : 'Activer client' }}
          </button>
          <button class="btn-secondary justify-center" :disabled="isLoading" @click="loadCompany">
            Actualiser
          </button>
        </div>
      </div>
    </div>

    <div v-if="isLoading" class="card p-12 text-center text-sm font-bold text-slate-500 dark:text-slate-400">
      <div class="mx-auto mb-4 h-8 w-8 animate-spin rounded-full border-b-2 border-brand-600"></div>
      Chargement du detail client...
    </div>
    <div v-else-if="errorMessage" class="rounded-3xl border border-red-200 bg-red-50 p-6 text-sm font-bold text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">
      {{ errorMessage }}
    </div>

    <template v-else-if="health">
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
        <StatsCard title="Score sante" :value="healthScore" icon="ChartBarIcon" :color="scoreColor" />
        <StatsCard title="Employes actifs" :value="health.adoption.employees.active" icon="UsersIcon" color="green" />
        <StatsCard title="Pointages 30j" :value="health.adoption.attendance.logs_30d" icon="BuildingOfficeIcon" color="blue" />
        <StatsCard title="MRR" :value="formatCurrency(health.subscription.mrr, health.subscription.currency)" icon="CurrencyEuroIcon" color="purple" />
      </div>

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <section class="card overflow-hidden xl:col-span-2">
          <div class="border-b border-slate-200/60 px-6 py-5 dark:border-slate-800/70">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 class="text-xl font-black text-slate-950 dark:text-white">Adoption terrain</h2>
                <p class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400">Signaux operationnels qui disent si le client est vraiment utilisable.</p>
              </div>
              <span :class="riskClass(health.adoption.risk_level)">
                {{ health.adoption.risk_level }}
              </span>
            </div>
          </div>

          <dl class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">
            <div class="metric-tile">
              <dt>Onboarding</dt>
              <dd>{{ health.adoption.onboarding.progress_percent }}%</dd>
            </div>
            <div class="metric-tile">
              <dt>Anomalies critiques 30j</dt>
              <dd>{{ health.adoption.anomalies.critical_30d }}</dd>
            </div>
            <div class="metric-tile">
              <dt>Employes paie prete</dt>
              <dd>{{ health.adoption.employees.payroll_ready }}/{{ health.adoption.employees.total }}</dd>
            </div>
            <div class="metric-tile">
              <dt>Dernier pointage</dt>
              <dd class="text-base">{{ formatDateTime(health.adoption.attendance.last_punch_at) }}</dd>
            </div>
          </dl>

          <div class="border-t border-slate-200/60 px-6 py-5 dark:border-slate-800/70">
            <h3 class="text-sm font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Actions prioritaires</h3>
            <ul class="mt-4 space-y-3">
              <li
                v-for="action in health.next_actions"
                :key="action.key"
                class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-300"
              >
                <span class="mr-2 rounded-full bg-slate-900 px-2 py-1 text-[10px] font-black uppercase tracking-widest text-white dark:bg-white dark:text-slate-950">
                  {{ action.priority }}
                </span>
                {{ action.label }}
              </li>
              <li v-if="health.next_actions.length === 0" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300">
                Aucun blocage prioritaire detecte.
              </li>
            </ul>
          </div>
        </section>

        <section class="card overflow-hidden">
          <div class="border-b border-slate-200/60 px-6 py-5 dark:border-slate-800/70">
            <h2 class="text-xl font-black text-slate-950 dark:text-white">Abonnement</h2>
            <p class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400">Activation, plan et notes commerciales.</p>
          </div>

          <form class="space-y-4 p-6" @submit.prevent="saveSubscription">
            <label class="field-label" for="plan">
              <span>Plan</span>
              <select id="plan" v-model.number="subscriptionForm.plan_id" class="form-input">
                <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                  {{ plan.name }} - {{ formatCurrency(plan.price_monthly, health.company.currency) }}/mois
                </option>
              </select>
            </label>

            <label class="field-label" for="status">
              <span>Statut</span>
              <select id="status" v-model="subscriptionForm.status" class="form-input">
                <option value="trial">Trial</option>
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
                <option value="expired">Expired</option>
              </select>
            </label>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <label class="field-label" for="subscription_start">
                <span>Debut</span>
                <input id="subscription_start" v-model="subscriptionForm.subscription_start" type="date" class="form-input" />
              </label>
              <label class="field-label" for="subscription_end">
                <span>Fin</span>
                <input id="subscription_end" v-model="subscriptionForm.subscription_end" type="date" class="form-input" />
              </label>
            </div>

            <label class="field-label" for="notes">
              <span>Notes</span>
              <textarea id="notes" v-model="subscriptionForm.notes" rows="4" class="form-input resize-none"></textarea>
            </label>

            <button class="btn-primary w-full justify-center" :disabled="isSaving">
              {{ isSaving ? 'Enregistrement...' : 'Enregistrer abonnement' }}
            </button>
          </form>
        </section>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'

const route = useRoute()
const toast = useToast()
const isLoading = ref(false)
const isSaving = ref(false)
const errorMessage = ref('')
const health = ref(null)
const plans = ref([])
const subscriptionForm = ref({
  plan_id: null,
  status: 'trial',
  subscription_start: '',
  subscription_end: '',
  notes: '',
})

const healthScore = computed(() => health.value?.adoption?.health_score || 0)
const isTrial = computed(() => subscriptionForm.value.status === 'trial')
const scoreColor = computed(() => {
  if (healthScore.value >= 75) return 'green'
  if (healthScore.value >= 50) return 'yellow'
  return 'red'
})

async function loadCompany() {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const [healthResponse, plansResponse, subscriptionResponse] = await Promise.all([
      api.get(`/platform/companies/${route.params.id}/health`),
      api.get('/platform/plans'),
      api.get(`/platform/companies/${route.params.id}/subscription`),
    ])

    health.value = healthResponse.data?.data || null
    plans.value = plansResponse.data?.data?.items || []
    fillSubscriptionForm(subscriptionResponse.data?.data)
  } catch (error) {
    console.error('Failed to load company detail:', error)
    errorMessage.value = 'Impossible de charger le detail entreprise.'
  } finally {
    isLoading.value = false
  }
}

async function saveSubscription() {
  isSaving.value = true

  try {
    await api.patch(`/platform/companies/${route.params.id}/subscription`, subscriptionForm.value)
    toast.success('Abonnement client mis a jour.')
    await loadCompany()
  } catch (error) {
    console.error('Failed to save subscription:', error)
  } finally {
    isSaving.value = false
  }
}

async function activateClient() {
  if (!subscriptionForm.value.plan_id) {
    toast.error('Plan client manquant.')
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

  try {
    return new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: currency || 'EUR',
      maximumFractionDigits: 0,
    }).format(amount)
  } catch {
    return new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'EUR',
      maximumFractionDigits: 0,
    }).format(amount)
  }
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
    high: 'rounded-full border border-red-200 bg-red-100 px-3 py-1.5 text-xs font-black uppercase tracking-widest text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300',
    medium: 'rounded-full border border-yellow-200 bg-yellow-100 px-3 py-1.5 text-xs font-black uppercase tracking-widest text-yellow-800 dark:border-yellow-900/60 dark:bg-yellow-950/40 dark:text-yellow-300',
    low: 'rounded-full border border-green-200 bg-green-100 px-3 py-1.5 text-xs font-black uppercase tracking-widest text-green-700 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-300',
  }
  return classes[risk] || classes.medium
}

function statusClass(status) {
  const classes = {
    active: 'rounded-full border border-emerald-200 bg-emerald-100 px-3 py-1 text-xs font-black uppercase tracking-widest text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300',
    trial: 'rounded-full border border-brand-200 bg-brand-100 px-3 py-1 text-xs font-black uppercase tracking-widest text-brand-700 dark:border-brand-900/60 dark:bg-brand-950/40 dark:text-brand-300',
    suspended: 'rounded-full border border-orange-200 bg-orange-100 px-3 py-1 text-xs font-black uppercase tracking-widest text-orange-700 dark:border-orange-900/60 dark:bg-orange-950/40 dark:text-orange-300',
    expired: 'rounded-full border border-red-200 bg-red-100 px-3 py-1 text-xs font-black uppercase tracking-widest text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300',
  }
  return classes[status] || classes.trial
}

onMounted(loadCompany)
</script>

<style scoped>
.metric-tile {
  @apply rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70;
}

.metric-tile dt {
  @apply text-sm font-bold text-slate-500 dark:text-slate-400;
}

.metric-tile dd {
  @apply mt-2 text-2xl font-black text-slate-950 dark:text-white;
}

.field-label {
  @apply block space-y-1.5 text-sm font-bold text-slate-700 dark:text-slate-200;
}

.form-input {
  @apply mt-1 block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-white;
}
</style>
