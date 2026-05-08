<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <router-link to="/companies" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
          Retour aux entreprises
        </router-link>
        <h1 class="mt-2 text-2xl font-bold text-gray-900">
          {{ health?.company?.name || 'Entreprise' }}
        </h1>
        <p class="mt-1 text-sm text-gray-500">
          Health client, abonnement et actions commerciales prioritaires.
        </p>
      </div>
      <button class="btn-secondary" :disabled="isLoading" @click="loadCompany">
        Actualiser
      </button>
    </div>

    <div v-if="isLoading" class="rounded-lg bg-white p-6 text-sm text-gray-500 shadow">
      Chargement du detail client...
    </div>
    <div v-else-if="errorMessage" class="rounded-lg bg-white p-6 text-sm text-red-600 shadow">
      {{ errorMessage }}
    </div>

    <template v-else-if="health">
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
        <StatsCard title="Score sante" :value="health.adoption.health_score" icon="ChartBarIcon" :color="scoreColor" />
        <StatsCard title="Employes actifs" :value="health.adoption.employees.active" icon="UsersIcon" color="green" />
        <StatsCard title="Pointages 30j" :value="health.adoption.attendance.logs_30d" icon="BuildingOfficeIcon" color="blue" />
        <StatsCard title="MRR" :value="formatCurrency(health.subscription.mrr, health.subscription.currency)" icon="CurrencyEuroIcon" color="purple" />
      </div>

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <section class="rounded-lg bg-white p-6 shadow xl:col-span-2">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Adoption terrain</h2>
            <span :class="riskClass(health.adoption.risk_level)">
              {{ health.adoption.risk_level }}
            </span>
          </div>

          <dl class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-md bg-gray-50 p-4">
              <dt class="text-sm text-gray-500">Onboarding</dt>
              <dd class="mt-1 text-xl font-semibold text-gray-900">
                {{ health.adoption.onboarding.progress_percent }}%
              </dd>
            </div>
            <div class="rounded-md bg-gray-50 p-4">
              <dt class="text-sm text-gray-500">Anomalies critiques 30j</dt>
              <dd class="mt-1 text-xl font-semibold text-gray-900">
                {{ health.adoption.anomalies.critical_30d }}
              </dd>
            </div>
            <div class="rounded-md bg-gray-50 p-4">
              <dt class="text-sm text-gray-500">Employes paie prete</dt>
              <dd class="mt-1 text-xl font-semibold text-gray-900">
                {{ health.adoption.employees.payroll_ready }}/{{ health.adoption.employees.total }}
              </dd>
            </div>
            <div class="rounded-md bg-gray-50 p-4">
              <dt class="text-sm text-gray-500">Dernier pointage</dt>
              <dd class="mt-1 text-sm font-medium text-gray-900">
                {{ formatDateTime(health.adoption.attendance.last_punch_at) }}
              </dd>
            </div>
          </dl>

          <div class="mt-6">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Actions prioritaires</h3>
            <ul class="mt-3 space-y-3">
              <li
                v-for="action in health.next_actions"
                :key="action.key"
                class="rounded-md border border-gray-200 p-3 text-sm text-gray-700"
              >
                <span class="font-medium text-gray-900">{{ action.priority }}</span>
                · {{ action.label }}
              </li>
              <li v-if="health.next_actions.length === 0" class="text-sm text-gray-500">
                Aucun blocage prioritaire detecte.
              </li>
            </ul>
          </div>
        </section>

        <section class="rounded-lg bg-white p-6 shadow">
          <h2 class="text-lg font-semibold text-gray-900">Abonnement</h2>
          <form class="mt-5 space-y-4" @submit.prevent="saveSubscription">
            <div>
              <label class="block text-sm font-medium text-gray-700" for="plan">Plan</label>
              <select id="plan" v-model.number="subscriptionForm.plan_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                  {{ plan.name }} - {{ formatCurrency(plan.price_monthly, health.company.currency) }}/mois
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700" for="status">Statut</label>
              <select id="status" v-model="subscriptionForm.status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                <option value="trial">Trial</option>
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
                <option value="expired">Expired</option>
              </select>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label class="block text-sm font-medium text-gray-700" for="subscription_start">Debut</label>
                <input id="subscription_start" v-model="subscriptionForm.subscription_start" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700" for="subscription_end">Fin</label>
                <input id="subscription_end" v-model="subscriptionForm.subscription_end" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700" for="notes">Notes</label>
              <textarea id="notes" v-model="subscriptionForm.notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"></textarea>
            </div>

            <button class="btn-primary w-full justify-center" :disabled="isSaving">
              {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
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
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'

const route = useRoute()
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

const scoreColor = computed(() => {
  const score = health.value?.adoption?.health_score || 0
  if (score >= 75) return 'green'
  if (score >= 50) return 'yellow'
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
    await loadCompany()
  } catch (error) {
    console.error('Failed to save subscription:', error)
  } finally {
    isSaving.value = false
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

function formatDateTime(value) {
  if (!value) return 'Aucun pointage'

  return new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function riskClass(risk) {
  const classes = {
    high: 'rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700',
    medium: 'rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-semibold text-yellow-800',
    low: 'rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700',
  }
  return classes[risk] || classes.medium
}

onMounted(loadCompany)
</script>
