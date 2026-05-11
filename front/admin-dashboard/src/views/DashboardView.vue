<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Cockpit plateforme</h1>
        <p class="mt-1 text-sm text-gray-500">
          Synthese commerciale, adoption terrain et demandes entrantes.
        </p>
      </div>
      <button class="btn-secondary" :disabled="isLoading" @click="loadDashboard">
        Actualiser
      </button>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
      <StatsCard title="MRR portefeuille" :value="formatCurrency(summary.mrr)" icon="CurrencyEuroIcon" color="purple" />
      <StatsCard title="Clients actifs" :value="summary.active_companies" icon="BuildingOfficeIcon" color="green" />
      <StatsCard title="Risque eleve" :value="summary.risk.high" icon="ChartBarIcon" color="red" />
      <StatsCard title="Demandes a traiter" :value="pendingRequests" icon="ChatBubbleLeftRightIcon" color="yellow" />
    </div>

    <div v-if="isLoading" class="rounded-lg bg-white p-6 text-sm text-gray-500 shadow">
      Chargement du cockpit...
    </div>
    <div v-else-if="errorMessage" class="rounded-lg bg-white p-6 text-sm text-red-600 shadow">
      {{ errorMessage }}
    </div>

    <div v-else class="grid grid-cols-1 gap-6 xl:grid-cols-3">
      <section class="rounded-lg bg-white shadow xl:col-span-2">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
          <div>
            <h2 class="text-lg font-semibold text-gray-900">Priorites clients</h2>
            <p class="text-sm text-gray-500">Actions qui protegent la retention et l'activation terrain.</p>
          </div>
          <router-link class="text-sm font-medium text-indigo-600 hover:text-indigo-800" to="/companies">
            Voir portefeuille
          </router-link>
        </div>

        <div class="divide-y divide-gray-200">
          <article v-for="item in priorityCompanies" :key="item.company.id" class="p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <div class="flex flex-wrap items-center gap-3">
                  <h3 class="font-semibold text-gray-900">{{ item.company.name }}</h3>
                  <span :class="riskClass(item.risk_level)">{{ riskLabel(item.risk_level) }}</span>
                  <span class="text-sm font-semibold text-gray-900">{{ item.health_score }}/100</span>
                </div>
                <p class="mt-1 text-sm text-gray-500">
                  {{ item.plan.name || 'Sans plan' }} · {{ item.employees_active }} employes actifs · {{ item.attendance_logs_30d }} pointages 30j
                </p>
                <p class="mt-3 text-sm text-gray-700">
                  {{ item.next_action?.label || 'Aucune action prioritaire detectee.' }}
                </p>
              </div>
              <router-link class="btn-secondary justify-center" :to="`/companies/${item.company.id}`">
                Ouvrir
              </router-link>
            </div>
          </article>
          <div v-if="priorityCompanies.length === 0" class="p-6 text-sm text-gray-500">
            Aucun client prioritaire.
          </div>
        </div>
      </section>

      <section class="rounded-lg bg-white shadow">
        <div class="border-b border-gray-200 px-6 py-4">
          <h2 class="text-lg font-semibold text-gray-900">Demandes entrantes</h2>
          <p class="text-sm text-gray-500">Nouveaux comptes a qualifier.</p>
        </div>
        <div class="divide-y divide-gray-200">
          <article v-for="request in pendingCompanyRequests" :key="request.id" class="p-4">
            <div class="flex items-start justify-between gap-3">
              <div>
                <h3 class="font-medium text-gray-900">{{ request.company_name }}</h3>
                <p class="text-sm text-gray-500">{{ request.sector || 'Secteur non precise' }}</p>
              </div>
              <span class="rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-semibold text-yellow-800">
                A traiter
              </span>
            </div>
            <p class="mt-2 text-sm text-gray-600">{{ request.email || request.user?.email || 'Contact non renseigne' }}</p>
          </article>
          <div v-if="pendingCompanyRequests.length === 0" class="p-6 text-sm text-gray-500">
            Aucune demande en attente.
          </div>
        </div>
        <div class="border-t border-gray-200 px-6 py-4">
          <router-link class="text-sm font-medium text-indigo-600 hover:text-indigo-800" to="/support">
            Traiter les demandes
          </router-link>
        </div>
      </section>
    </div>

    <div v-if="!isLoading && !errorMessage" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <section class="rounded-lg bg-white p-6 shadow">
        <h2 class="text-lg font-semibold text-gray-900">Adoption</h2>
        <dl class="mt-4 space-y-4 text-sm">
          <div class="flex items-center justify-between">
            <dt class="text-gray-500">Pointages 30j</dt>
            <dd class="font-semibold text-gray-900">{{ adoption.attendance_logs }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-gray-500">Employes actifs</dt>
            <dd class="font-semibold text-gray-900">{{ adoption.active_employees }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-gray-500">Clients a risque</dt>
            <dd class="font-semibold text-gray-900">{{ summary.risk.high + summary.risk.medium }}</dd>
          </div>
        </dl>
      </section>

      <section class="rounded-lg bg-white p-6 shadow">
        <h2 class="text-lg font-semibold text-gray-900">Revenus</h2>
        <dl class="mt-4 space-y-4 text-sm">
          <div class="flex items-center justify-between">
            <dt class="text-gray-500">MRR</dt>
            <dd class="font-semibold text-gray-900">{{ formatCurrency(summary.mrr) }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-gray-500">ARPA</dt>
            <dd class="font-semibold text-gray-900">{{ formatCurrency(averageRevenuePerAccount) }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-gray-500">Plans actifs</dt>
            <dd class="font-semibold text-gray-900">{{ activePlansCount }}</dd>
          </div>
        </dl>
      </section>

      <section class="rounded-lg bg-white p-6 shadow">
        <h2 class="text-lg font-semibold text-gray-900">Raccourcis</h2>
        <div class="mt-4 grid grid-cols-1 gap-3 text-sm">
          <router-link class="btn-secondary justify-center" to="/companies">Portefeuille clients</router-link>
          <router-link class="btn-secondary justify-center" to="/subscriptions">Abonnements</router-link>
          <router-link class="btn-secondary justify-center" to="/support">Demandes clients</router-link>
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
const errorMessage = ref('')
const summary = ref({
  active_companies: 0,
  companies: 0,
  mrr: 0,
  risk: { high: 0, medium: 0, low: 0 },
})
const portfolioItems = ref([])
const pendingCompanyRequests = ref([])
const plans = ref([])
const pendingRequests = ref(0)

const priorityCompanies = computed(() => {
  const rank = { high: 0, medium: 1, low: 2 }
  return [...portfolioItems.value]
    .sort((a, b) => {
      const riskDiff = (rank[a.risk_level] ?? 3) - (rank[b.risk_level] ?? 3)
      return riskDiff !== 0 ? riskDiff : a.health_score - b.health_score
    })
    .slice(0, 5)
})
const activePlansCount = computed(() => plans.value.filter((plan) => plan.is_active).length)
const averageRevenuePerAccount = computed(() => {
  if (!summary.value.active_companies) return 0
  return Number(summary.value.mrr || 0) / summary.value.active_companies
})
const adoption = computed(() => portfolioItems.value.reduce((totals, item) => ({
  attendance_logs: totals.attendance_logs + Number(item.attendance_logs_30d || 0),
  active_employees: totals.active_employees + Number(item.employees_active || 0),
}), { attendance_logs: 0, active_employees: 0 }))

async function loadDashboard() {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const [portfolioResponse, plansResponse, requestsResponse] = await Promise.all([
      api.get('/platform/companies/health'),
      api.get('/platform/plans'),
      api.get('/platform/company-requests', { params: { status: 'pending' } }),
    ])

    summary.value = portfolioResponse.data?.data?.summary || summary.value
    portfolioItems.value = portfolioResponse.data?.data?.items || []
    plans.value = plansResponse.data?.data?.items || []
    pendingCompanyRequests.value = requestsResponse.data?.data || []
    pendingRequests.value = requestsResponse.data?.meta?.total || pendingCompanyRequests.value.length
  } catch (error) {
    console.error('Failed to load platform dashboard:', error)
    errorMessage.value = 'Impossible de charger le cockpit plateforme.'
  } finally {
    isLoading.value = false
  }
}

function formatCurrency(value) {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0))
}

function riskClass(risk) {
  const classes = {
    high: 'rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700',
    medium: 'rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-semibold text-yellow-800',
    low: 'rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700',
  }
  return classes[risk] || classes.medium
}

function riskLabel(risk) {
  const labels = { high: 'Risque eleve', medium: 'Risque moyen', low: 'Risque faible' }
  return labels[risk] || risk
}

onMounted(loadDashboard)
</script>
