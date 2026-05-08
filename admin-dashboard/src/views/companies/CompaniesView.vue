<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Cockpit clients</h1>
        <p class="mt-1 text-sm text-gray-500">
          Adoption, risque, revenus recurrents et prochaine action par entreprise.
        </p>
      </div>
      <button class="btn-secondary" :disabled="isLoading" @click="fetchPortfolio">
        Actualiser
      </button>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
      <StatsCard title="Clients suivis" :value="summary.companies" icon="BuildingOfficeIcon" color="blue" />
      <StatsCard title="Clients actifs" :value="summary.active_companies" icon="UsersIcon" color="green" />
      <StatsCard title="MRR" :value="formattedMrr" icon="CurrencyEuroIcon" color="purple" />
      <StatsCard title="Risque eleve" :value="summary.risk.high" icon="ChartBarIcon" color="red" />
    </div>

    <div class="rounded-lg bg-white shadow">
      <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
        <div>
          <h2 class="text-lg font-semibold text-gray-900">Portefeuille</h2>
          <p class="text-sm text-gray-500">Clients classes par score de sante et priorite commerciale.</p>
        </div>
        <div class="flex gap-2 text-xs font-medium">
          <span class="rounded-full bg-red-100 px-2.5 py-1 text-red-700">High {{ summary.risk.high }}</span>
          <span class="rounded-full bg-yellow-100 px-2.5 py-1 text-yellow-800">Medium {{ summary.risk.medium }}</span>
          <span class="rounded-full bg-green-100 px-2.5 py-1 text-green-700">Low {{ summary.risk.low }}</span>
        </div>
      </div>

      <div v-if="isLoading" class="p-6 text-sm text-gray-500">Chargement du portefeuille...</div>
      <div v-else-if="errorMessage" class="p-6 text-sm text-red-600">{{ errorMessage }}</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Entreprise</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Plan</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Sante</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Pointage 30j</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Action</th>
              <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Detail</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-for="item in sortedItems" :key="item.company.id">
              <td class="whitespace-nowrap px-6 py-4">
                <div class="font-medium text-gray-900">{{ item.company.name }}</div>
                <div class="text-sm text-gray-500">{{ item.company.status }} · {{ item.company.country }}</div>
              </td>
              <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                <div>{{ item.plan.name || 'Sans plan' }}</div>
                <div class="text-xs text-gray-500">{{ formatCurrency(item.subscription.mrr, item.subscription.currency) }}/mois</div>
              </td>
              <td class="whitespace-nowrap px-6 py-4">
                <div class="flex items-center gap-2">
                  <span :class="riskClass(item.risk_level)">{{ item.risk_level }}</span>
                  <span class="text-sm font-semibold text-gray-900">{{ item.health_score }}/100</span>
                </div>
              </td>
              <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                <div>{{ item.attendance_logs_30d }} logs</div>
                <div class="text-xs text-gray-500">{{ item.employees_active }} employes actifs</div>
              </td>
              <td class="min-w-[260px] px-6 py-4 text-sm text-gray-700">
                <span v-if="item.next_action">{{ item.next_action.label }}</span>
                <span v-else class="text-gray-400">Aucune action prioritaire</span>
              </td>
              <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                <router-link class="font-medium text-indigo-600 hover:text-indigo-800" :to="`/companies/${item.company.id}`">
                  Ouvrir
                </router-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
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
  companies: 0,
  active_companies: 0,
  mrr: 0,
  risk: { high: 0, medium: 0, low: 0 },
})
const items = ref([])

const sortedItems = computed(() => {
  const rank = { high: 0, medium: 1, low: 2 }
  return [...items.value].sort((a, b) => {
    const riskDiff = (rank[a.risk_level] ?? 3) - (rank[b.risk_level] ?? 3)
    return riskDiff !== 0 ? riskDiff : a.health_score - b.health_score
  })
})

const formattedMrr = computed(() => formatCurrency(summary.value.mrr, 'EUR'))

async function fetchPortfolio() {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await api.get('/platform/companies/health')
    summary.value = response.data?.data?.summary || summary.value
    items.value = response.data?.data?.items || []
  } catch (error) {
    console.error('Failed to load company portfolio:', error)
    errorMessage.value = 'Impossible de charger le cockpit clients.'
  } finally {
    isLoading.value = false
  }
}

function formatCurrency(value, currency = 'EUR') {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: currency || 'EUR',
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

onMounted(fetchPortfolio)
</script>
