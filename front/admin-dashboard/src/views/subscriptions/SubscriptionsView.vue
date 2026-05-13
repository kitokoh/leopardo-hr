<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Abonnements</h1>
        <p class="mt-1 text-sm text-gray-500">
          Packaging, MRR et contrats clients disponibles pour le cockpit super-admin.
        </p>
      </div>
      <button class="btn-secondary" :disabled="isLoading" @click="loadSubscriptions">
        Actualiser
      </button>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
      <StatsCard title="MRR portefeuille" :value="formattedMrr" icon="CurrencyEuroIcon" color="purple" />
      <StatsCard title="Abonnements actifs" :value="subscriptionMetrics.active" icon="CreditCardIcon" color="green" />
      <StatsCard title="Past due" :value="subscriptionMetrics.past_due" icon="ChartBarIcon" color="yellow" />
      <StatsCard title="Impayes" :value="formatCurrency(revenue.overdue_total, revenue.currency)" icon="CreditCardIcon" color="red" />
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
      <section class="rounded-lg bg-white shadow xl:col-span-2">
        <div class="border-b border-gray-200 px-6 py-4">
          <h2 class="text-lg font-semibold text-gray-900">Catalogue plans</h2>
          <p class="text-sm text-gray-500">Source de verite API pour les upgrades et suspensions.</p>
        </div>
        <div v-if="isLoading" class="p-6 text-sm text-gray-500">Chargement...</div>
        <div v-else class="grid grid-cols-1 gap-4 p-6 lg:grid-cols-2">
          <article v-for="plan in plans" :key="plan.id" class="rounded-lg border border-gray-200 p-4">
            <div class="flex items-start justify-between gap-3">
              <div>
                <h3 class="font-semibold text-gray-900">{{ plan.name }}</h3>
                <p class="text-sm text-gray-500">
                  {{ plan.max_employees || 'Illimite' }} employes · {{ plan.trial_days }} jours essai
                </p>
              </div>
              <span :class="plan.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'" class="rounded-full px-2.5 py-1 text-xs font-semibold">
                {{ plan.is_active ? 'Actif' : 'Inactif' }}
              </span>
            </div>
            <div class="mt-4 flex items-baseline gap-2">
              <span class="text-2xl font-bold text-gray-900">{{ formatCurrency(plan.price_monthly) }}</span>
              <span class="text-sm text-gray-500">/ mois</span>
            </div>
            <p class="mt-1 text-sm text-gray-500">{{ formatCurrency(plan.price_yearly) }} / an</p>
            <div class="mt-4 flex flex-wrap gap-2">
              <span
                v-for="feature in enabledFeatures(plan)"
                :key="feature"
                class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700"
              >
                {{ feature }}
              </span>
              <span v-if="enabledFeatures(plan).length === 0" class="text-xs text-gray-400">Aucune feature declaree</span>
            </div>
          </article>
        </div>
      </section>

      <section class="rounded-lg bg-white shadow">
        <div class="border-b border-gray-200 px-6 py-4">
          <h2 class="text-lg font-semibold text-gray-900">Clients a traiter</h2>
          <p class="text-sm text-gray-500">Priorite abonnement et retention.</p>
        </div>
        <div class="divide-y divide-gray-200">
          <div class="p-4">
            <dl class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <dt class="text-gray-500">ARR estime</dt>
                <dd class="mt-1 font-semibold text-gray-900">{{ formatCurrency(revenue.arr, revenue.currency) }}</dd>
              </div>
              <div>
                <dt class="text-gray-500">Encaisse 30j</dt>
                <dd class="mt-1 font-semibold text-gray-900">{{ formatCurrency(revenue.collected_30d, revenue.currency) }}</dd>
              </div>
              <div>
                <dt class="text-gray-500">Abonnements total</dt>
                <dd class="mt-1 font-semibold text-gray-900">{{ subscriptionMetrics.total }}</dd>
              </div>
              <div>
                <dt class="text-gray-500">Trials</dt>
                <dd class="mt-1 font-semibold text-gray-900">{{ subscriptionMetrics.trial }}</dd>
              </div>
            </dl>
          </div>
          <div v-for="item in priorityClients" :key="item.company.id" class="p-4">
            <div class="flex items-center justify-between gap-3">
              <div>
                <p class="font-medium text-gray-900">{{ item.company.name }}</p>
                <p class="text-sm text-gray-500">{{ item.plan.name || 'Sans plan' }} · {{ item.risk_level }}</p>
              </div>
              <router-link class="text-sm font-medium text-indigo-600 hover:text-indigo-800" :to="`/companies/${item.company.id}`">
                Gerer
              </router-link>
            </div>
            <p v-if="item.next_action" class="mt-2 text-sm text-gray-600">{{ item.next_action.label }}</p>
          </div>
          <div v-if="priorityClients.length === 0 && !isLoading" class="p-6 text-sm text-gray-500">
            Aucun client prioritaire.
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'

const isLoading = ref(false)
const plans = ref([])
const portfolioItems = ref([])
const summary = ref({
  active_companies: 0,
  mrr: 0,
  risk: { high: 0, medium: 0, low: 0 },
})
const platformMetrics = ref({
  revenue: {
    currency: 'EUR',
    mrr: 0,
    arr: 0,
    collected_30d: 0,
    overdue_total: 0,
  },
  subscriptions: {
    total: 0,
    active: 0,
    trial: 0,
    past_due: 0,
    cancelled_30d: 0,
  },
})

const revenue = computed(() => platformMetrics.value.revenue || {})
const subscriptionMetrics = computed(() => platformMetrics.value.subscriptions || {})
const formattedMrr = computed(() => formatCurrency(revenue.value.mrr || summary.value.mrr, revenue.value.currency))
const priorityClients = computed(() => {
  const rank = { high: 0, medium: 1, low: 2 }
  return [...portfolioItems.value]
    .sort((a, b) => (rank[a.risk_level] ?? 3) - (rank[b.risk_level] ?? 3))
    .slice(0, 6)
})

async function loadSubscriptions() {
  isLoading.value = true

  try {
    const [plansResponse, portfolioResponse, metricsResponse] = await Promise.all([
      api.get('/platform/plans'),
      api.get('/platform/companies/health'),
      api.get('/platform/metrics/overview'),
    ])

    plans.value = plansResponse.data?.data?.items || []
    summary.value = portfolioResponse.data?.data?.summary || summary.value
    portfolioItems.value = portfolioResponse.data?.data?.items || []
    platformMetrics.value = metricsResponse.data?.data || platformMetrics.value
  } catch (error) {
    console.error('Failed to load subscriptions cockpit:', error)
  } finally {
    isLoading.value = false
  }
}

function enabledFeatures(plan) {
  return Object.entries(plan.features || {})
    .filter(([, enabled]) => Boolean(enabled))
    .map(([feature]) => feature)
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

onMounted(loadSubscriptions)
</script>
