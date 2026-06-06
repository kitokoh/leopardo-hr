<template>
  <div class="space-y-8 animate-fade-in">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">Cockpit clients</h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium">
          Adoption, risque, revenus récurrents et prochaine action par entreprise.
        </p>
      </div>
      <button class="btn-secondary py-2.5" :disabled="isLoading" @click="fetchPortfolio">
        Actualiser
      </button>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4 animate-slide-up">
      <StatsCard title="Clients suivis" :value="summary.companies" icon="BuildingOfficeIcon" color="blue" />
      <StatsCard title="Clients actifs" :value="summary.active_companies" icon="UsersIcon" color="green" />
      <StatsCard title="MRR" :value="formattedMrr" icon="CurrencyEuroIcon" color="purple" />
      <StatsCard title="Risque eleve" :value="summary.risk.high" icon="ChartBarIcon" color="red" />
    </div>

    <div class="card animate-slide-up" style="animation-delay: 0.1s">
      <div class="flex items-center justify-between border-b border-slate-200/50 dark:border-slate-800/50 px-6 py-5">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">Portefeuille</h2>
          <p class="text-sm text-slate-500 dark:text-slate-400">Clients classés par score de santé et priorité commerciale.</p>
        </div>
        <div class="flex gap-3">
          <span class="rounded-full bg-red-100 dark:bg-red-900/30 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800">High {{ summary.risk.high }}</span>
          <span class="rounded-full bg-yellow-100 dark:bg-yellow-900/30 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-yellow-800 dark:text-yellow-400 border border-yellow-200 dark:border-yellow-800">Medium {{ summary.risk.medium }}</span>
          <span class="rounded-full bg-green-100 dark:bg-green-900/30 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-green-700 dark:text-green-400 border border-green-200 dark:border-green-800">Low {{ summary.risk.low }}</span>
        </div>
      </div>

      <div v-if="isLoading" class="p-12 text-center text-sm text-slate-500 dark:text-slate-400">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-600 mx-auto mb-4"></div>
        Chargement du portefeuille...
      </div>
      <div v-else-if="errorMessage" class="p-12 text-center text-sm text-red-600 font-bold bg-red-50 dark:bg-red-900/20 m-6 rounded-2xl">{{ errorMessage }}</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200/50 dark:divide-slate-800/50">
          <thead class="bg-slate-50/50 dark:bg-slate-800/50">
            <tr>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Entreprise</th>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Plan</th>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Santé</th>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Pointage 30j</th>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Action</th>
              <th class="px-6 py-4 text-right text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Détail</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200/50 dark:divide-slate-800/50 bg-white/40 dark:bg-slate-900/40 backdrop-blur-sm">
            <tr v-for="item in sortedItems" :key="item.company.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/80 transition-colors">
              <td class="whitespace-nowrap px-6 py-5">
                <div class="font-bold text-slate-900 dark:text-white">{{ item.company.name }}</div>
                <div class="text-xs font-medium text-slate-500 dark:text-slate-400 mt-1 uppercase tracking-wider">{{ item.company.status }} · {{ item.company.country }}</div>
              </td>
              <td class="whitespace-nowrap px-6 py-5 text-sm">
                <div class="font-semibold text-slate-700 dark:text-slate-300">{{ item.plan.name || 'Sans plan' }}</div>
                <div class="text-xs font-bold text-brand-600 dark:text-brand-400 mt-1">{{ formatCurrency(item.subscription.mrr, item.subscription.currency) }}/mois</div>
              </td>
              <td class="whitespace-nowrap px-6 py-5">
                <div class="flex items-center gap-3">
                  <span :class="riskClass(item.risk_level)">{{ item.risk_level }}</span>
                  <span class="text-sm font-black text-slate-900 dark:text-white">{{ item.health_score }}/100</span>
                </div>
              </td>
              <td class="whitespace-nowrap px-6 py-5 text-sm">
                <div class="font-semibold text-slate-700 dark:text-slate-300">{{ item.attendance_logs_30d }} logs</div>
                <div class="text-xs font-medium text-slate-500 dark:text-slate-400 mt-1">{{ item.employees_active }} employés actifs</div>
              </td>
              <td class="min-w-[260px] px-6 py-5 text-sm">
                <span v-if="item.next_action" class="font-medium text-slate-700 dark:text-slate-300">{{ item.next_action.label }}</span>
                <span v-else class="text-slate-400 italic">Aucune action prioritaire</span>
              </td>
              <td class="whitespace-nowrap px-6 py-5 text-right">
                <router-link class="inline-flex items-center px-3 py-1.5 rounded-lg bg-brand-50 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 text-xs font-bold hover:bg-brand-100 dark:hover:bg-brand-900/50 transition-all duration-200" :to="`/companies/${item.company.id}`">
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
