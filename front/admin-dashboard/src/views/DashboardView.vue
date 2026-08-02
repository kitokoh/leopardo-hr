<template>
  <div class="space-y-8">
    <div v-if="isLoading" class="flex h-64 items-center justify-center">
      <div class="h-12 w-12 animate-spin rounded-full border-4 border-brand-500 border-t-transparent"></div>
    </div>

    <div v-if="errorMessage" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700 animate-fade-in">
      {{ errorMessage }}
    </div>

    <!-- Top Stats -->
    <div v-if="!isLoading && !errorMessage" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 animate-fade-in">
      <StatsCard
        title="Entreprises actives"
        :value="companyMetrics.active"
        icon="BuildingOfficeIcon"
        color="purple"
      />
      <StatsCard
        title="MRR Plateforme"
        :value="formattedMrr"
        icon="CurrencyEuroIcon"
        color="green"
      />
      <StatsCard
        title="Demandes en attente"
        :value="pendingRequests"
        icon="ChatBubbleLeftRightIcon"
        color="yellow"
      />
      <StatsCard
        title="Alertes critiques"
        :value="summary.risk.high"
        icon="UsersIcon"
        color="red"
      />
    </div>

    <!-- Workflows -->
    <div v-if="!isLoading && !errorMessage" class="mb-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <router-link
        v-for="(card, index) in workflowCards"
        :key="card.title"
        :to="card.to"
        class="group card p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-premium animate-slide-up"
        :style="{ animationDelay: `${index * 0.05}s` }"
      >
        <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-emerald-500/5 transition-colors group-hover:bg-emerald-500/10"></div>
        <div class="flex items-start justify-between">
          <div :class="card.badgeClass">{{ card.badge }}</div>
        </div>
        <h3 class="mt-4 text-lg font-black tracking-tight text-slate-900 dark:text-white uppercase">{{ card.title }}</h3>
        <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ card.description }}</p>
        <div class="mt-6 flex items-center text-xs font-black uppercase tracking-widest text-emerald-500 dark:text-emerald-400">
          {{ card.action }}
          <ArrowRightIcon class="ml-2 h-4 w-4 transition-transform group-hover:translate-x-1" />
        </div>
      </router-link>
    </div>

    <div v-if="!isLoading && !errorMessage" class="mb-12 grid grid-cols-1 gap-8 lg:grid-cols-3 animate-slide-up" style="animation-delay: 0.1s">
      <section class="card lg:col-span-2">
        <div class="flex items-center justify-between border-b border-slate-200/50 px-6 py-4 dark:border-slate-800/50 bg-surface-bright/20 dark:bg-surface-bright/20 backdrop-blur-md">
          <h2 class="text-lg font-black tracking-tight text-slate-950 dark:text-white uppercase">PrioritÃ©s Portefeuille</h2>
          <router-link class="text-xs font-black uppercase tracking-widest text-emerald-500 hover:text-brand-500" to="/analytics">
            Voir tout
          </router-link>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-white/5 dark:divide-white/5">
            <thead class="bg-slate-50/80 dark:bg-slate-900/80">
              <tr>
                <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Client</th>
                <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">SantÃ©</th>
                <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Risque</th>
                <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">MRR</th>
                <th class="px-6 py-3 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5 bg-transparent dark:divide-white/5 dark:bg-transparent">
              <tr v-for="item in priorityCompanies" :key="item.id" class="hover:bg-white/5 dark:hover:bg-white/5 transition-colors">
                <td class="whitespace-nowrap px-6 py-4">
                  <div class="flex items-center">
                    <div class="h-9 w-9 flex-shrink-0 rounded-xl bg-surface-variant/30 p-1.5 dark:bg-surface-variant/30 border border-slate-200/50 dark:border-slate-700/50">
                      <BuildingOfficeIcon class="h-full w-full text-slate-400" />
                    </div>
                    <div class="ml-4">
                      <div class="text-sm font-bold text-slate-900 dark:text-white">{{ item.name }}</div>
                      <div class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">{{ item.slug }}</div>
                    </div>
                  </div>
                </td>
                <td class="whitespace-nowrap px-6 py-4">
                  <div class="flex items-center">
                    <div class="mr-3 h-1.5 w-16 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                      <div
                        class="h-full rounded-full transition-all duration-1000"
                        :class="item.health_score >= 70 ? 'bg-emerald-500' : item.health_score >= 40 ? 'bg-amber-500' : 'bg-red-500'"
                        :style="{ width: `${item.health_score}%` }"
                      ></div>
                    </div>
                    <span class="text-[10px] font-black text-slate-600 dark:text-slate-400">{{ item.health_score }}%</span>
                  </div>
                </td>
                <td class="whitespace-nowrap px-6 py-4">
                  <span :class="riskClass(item.risk_level)">{{ riskLabel(item.risk_level) }}</span>
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-sm font-black text-slate-950 dark:text-white">
                  {{ formatCurrency(item.mrr_eur, 'EUR') }}
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                  <router-link :to="`/companies/${item.id}`" class="text-emerald-500 hover:text-emerald-600 dark:text-emerald-400 font-bold">
                    DÃ©tails
                  </router-link>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-if="priorityCompanies.length === 0" class="p-8 text-center">
            <p class="text-sm font-medium text-slate-400">Aucune entreprise prioritaire pour le moment.</p>
          </div>
        </div>
      </section>

      <section class="card">
        <div class="border-b border-slate-200/50 px-6 py-4 dark:border-slate-800/50 bg-surface-bright/20 dark:bg-surface-bright/20 backdrop-blur-md">
          <h2 class="text-lg font-black tracking-tight text-slate-950 dark:text-white uppercase">Inscriptions en attente</h2>
        </div>
        <div class="divide-y divide-white/5 dark:divide-white/5">
          <div v-for="request in pendingCompanyRequests" :key="request.id" class="p-6 transition-colors hover:bg-white/5 dark:hover:bg-white/5">
            <div class="flex items-start justify-between">
              <div class="space-y-1">
                <h3 class="text-sm font-bold text-slate-950 dark:text-white uppercase tracking-tight">{{ request.name }}</h3>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ request.manager_email }}</p>
                <div class="pt-1 flex items-center text-[10px] font-black uppercase tracking-widest text-slate-400">
                  <GlobeAltIcon class="mr-1.5 h-3 w-3" />
                  {{ request.city }}, {{ request.country }}
                </div>
              </div>
              <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">
                {{ formatDate(request.created_at) }}
              </div>
            </div>
          </div>
          <div v-if="pendingCompanyRequests.length === 0" class="p-8 text-center">
            <p class="text-sm font-medium text-slate-400">Aucune demande en attente.</p>
          </div>
        </div>
        <div class="border-t border-slate-200 dark:border-slate-800 px-6 py-4 bg-surface-bright/20 dark:bg-surface-bright/20 backdrop-blur-md">
          <router-link class="text-sm font-bold uppercase tracking-widest text-emerald-500 hover:text-emerald-600 dark:text-emerald-400 dark:hover:text-brand-300 transition-colors" to="/support">
            Traiter les demandes
          </router-link>
        </div>
      </section>
    </div>

    <div v-if="!isLoading && !errorMessage" class="grid grid-cols-1 gap-6 lg:grid-cols-3 animate-slide-up" style="animation-delay: 0.2s">
      <section class="card p-6">
        <h2 class="text-lg font-black tracking-tight text-slate-950 dark:text-white uppercase">Adoption</h2>
        <dl class="mt-4 space-y-4 text-sm">
          <div class="flex items-center justify-between">
            <dt class="text-xs font-black uppercase tracking-widest text-slate-400">Pointages 30j</dt>
            <dd class="font-black text-slate-900 dark:text-white">{{ adoption.attendance_logs }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-xs font-black uppercase tracking-widest text-slate-400">EmployÃ©s actifs</dt>
            <dd class="font-black text-slate-900 dark:text-white">{{ adoption.active_employees }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-xs font-black uppercase tracking-widest text-slate-400">Clients Ã  risque</dt>
            <dd class="font-black text-slate-900 dark:text-white">{{ summary.risk.high + summary.risk.medium }}</dd>
          </div>
        </dl>
      </section>

      <section class="card p-6">
        <h2 class="text-lg font-black tracking-tight text-slate-950 dark:text-white uppercase">Revenus</h2>
        <dl class="mt-4 space-y-4 text-sm">
          <div class="flex items-center justify-between">
            <dt class="text-xs font-black uppercase tracking-widest text-slate-400">MRR</dt>
            <dd class="font-black text-slate-900 dark:text-white">{{ formatCurrency(revenue.mrr, revenue.currency) }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-xs font-black uppercase tracking-widest text-slate-400">Encaisse 30j</dt>
            <dd class="font-black text-slate-900 dark:text-white">{{ formatCurrency(revenue.collected_30d, revenue.currency) }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-xs font-black uppercase tracking-widest text-slate-400">ImpayÃ©s</dt>
            <dd class="font-black text-slate-900 dark:text-white">{{ formatCurrency(revenue.overdue_total, revenue.currency) }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-xs font-black uppercase tracking-widest text-slate-400">ARPA</dt>
            <dd class="font-black text-slate-900 dark:text-white">{{ formatCurrency(averageRevenuePerAccount, revenue.currency) }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-xs font-black uppercase tracking-widest text-slate-400">Abonnements actifs</dt>
            <dd class="font-black text-slate-900 dark:text-white">{{ subscriptionMetrics.active }}</dd>
          </div>
        </dl>
      </section>

      <section class="card p-6">
        <h2 class="text-lg font-black tracking-tight text-slate-950 dark:text-white uppercase">Raccourcis</h2>
        <div class="mt-4 grid grid-cols-1 gap-3">
          <router-link class="btn-secondary justify-center shadow-sm text-xs font-black uppercase tracking-widest" to="/companies">Portefeuille clients</router-link>
          <router-link class="btn-secondary justify-center shadow-sm text-xs font-black uppercase tracking-widest" to="/subscriptions">Abonnements</router-link>
          <router-link class="btn-secondary justify-center shadow-sm text-xs font-black uppercase tracking-widest" to="/support">Demandes clients</router-link>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  ArrowRightIcon,
  BuildingOfficeIcon,
  GlobeAltIcon
} from '@heroicons/vue/24/outline'
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'
import { useLocaleStore } from '@/stores/locale'
import { toIntlLocale } from '@/i18n/index.js'

const localeStore = useLocaleStore()
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
const pendingRequests = ref(0)
const platformMetrics = ref({
  revenue: {
    currency: 'EUR',
    mrr: 0,
    arr: 0,
    collected_30d: 0,
    overdue_total: 0,
  },
  companies: {
    total: 0,
    active: 0,
    trial: 0,
    suspended: 0,
    expired: 0,
  },
  subscriptions: {
    total: 0,
    active: 0,
    trial: 0,
    past_due: 0,
    cancelled_30d: 0,
  },
})

const priorityCompanies = computed(() => {
  const rank = { high: 0, medium: 1, low: 2 }
  return [...portfolioItems.value]
    .sort((a, b) => {
      const riskDiff = (rank[a.risk_level] ?? 3) - (rank[b.risk_level] ?? 3)
      return riskDiff !== 0 ? riskDiff : a.health_score - b.health_score
    })
    .slice(0, 5)
})
const revenue = computed(() => platformMetrics.value.revenue || {})
const companyMetrics = computed(() => {
  const metrics = platformMetrics.value.companies || {}

  return {
    total: Number(metrics.total || summary.value.companies || 0),
    active: Number(metrics.active || summary.value.active_companies || 0),
  }
})
const subscriptionMetrics = computed(() => platformMetrics.value.subscriptions || {})
const averageRevenuePerAccount = computed(() => {
  if (!companyMetrics.value.active) return 0
  return Number(revenue.value.mrr || summary.value.mrr || 0) / companyMetrics.value.active
})
const adoption = computed(() => portfolioItems.value.reduce((totals, item) => ({
  attendance_logs: totals.attendance_logs + Number(item.attendance_logs_30d || 0),
  active_employees: totals.active_employees + Number(item.employees_active || 0),
}), { attendance_logs: 0, active_employees: 0 }))
const formattedMrr = computed(() => formatCurrency(revenue.value.mrr || summary.value.mrr, revenue.value.currency))

const workflowCards = computed(() => [
  {
    title: 'CrÃ©er ou activer un client',
    description: 'Qualifier une entreprise, ouvrir son tenant et vÃ©rifier son statut de lancement.',
    action: 'Ouvrir le portefeuille clients',
    to: '/companies',
    badge: `${companyMetrics.value.total} tenants`,
    badgeClass: 'rounded-lg bg-emerald-500/10 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-emerald-500 dark:bg-brand-900/40 dark:text-brand-300 border border-emerald-500/20 dark:border-brand-800',
  },
  {
    title: 'Traiter les demandes entrantes',
    description: 'Suivre les demandes dâ€™essai, prioriser les leads et Ã©viter les prospects bloquÃ©s.',
    action: 'Voir les demandes clients',
    to: '/support',
    badge: `${pendingRequests.value} Ã  traiter`,
    badgeClass: 'rounded-lg bg-amber-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
  },
  {
    title: 'Surveiller les clients Ã  risque',
    description: 'Identifier les comptes faibles en adoption, pointage ou santÃ© opÃ©rationnelle.',
    action: 'Analyser les prioritÃ©s',
    to: '/analytics',
    badge: `${summary.value.risk.high + summary.value.risk.medium} risques`,
    badgeClass: 'rounded-lg bg-red-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-red-700 dark:bg-red-900/40 dark:text-red-300 border border-red-200 dark:border-red-800',
  },
  {
    title: 'Piloter abonnements et revenus',
    description: 'ContrÃ´ler MRR, impayÃ©s, plans actifs et trajectoire commerciale de la plateforme.',
    action: 'Ouvrir abonnements',
    to: '/subscriptions',
    badge: formatCurrency(revenue.value.mrr, revenue.value.currency),
    badgeClass: 'rounded-lg bg-purple-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-purple-700 dark:bg-purple-900/40 dark:text-purple-300 border border-purple-200 dark:border-purple-800',
  },
  {
    title: 'VÃ©rifier systÃ¨me et sÃ©curitÃ©',
    description: 'ContrÃ´ler santÃ© API, configuration, logs, sauvegardes et signaux dâ€™incident.',
    action: 'Ouvrir systÃ¨me',
    to: '/system',
    badge: 'Ops',
    badgeClass: 'rounded-lg bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700',
  },
  {
    title: 'PrÃ©parer intÃ©grations partenaires',
    description: 'Suivre webhooks, exports, rapports et surfaces API nÃ©cessaires aux intÃ©grateurs.',
    action: 'Ouvrir webhooks',
    to: '/webhooks',
    badge: 'API',
    badgeClass: 'rounded-lg bg-cyan-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800',
  },
])

async function loadDashboard() {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const [portfolioResponse, metricsResponse, requestsResponse] = await Promise.all([
      api.get('/platform/companies/health'),
      api.get('/platform/metrics/overview'),
      api.get('/platform/company-requests', { params: { status: 'pending' } }),
    ])

    summary.value = portfolioResponse.data?.data?.summary || summary.value
    platformMetrics.value = metricsResponse.data?.data || platformMetrics.value
    portfolioItems.value = portfolioResponse.data?.data?.items || []
    pendingCompanyRequests.value = requestsResponse.data?.data || []
    pendingRequests.value = requestsResponse.data?.meta?.total || pendingCompanyRequests.value.length
  } catch (error) {
    console.error('Failed to load platform dashboard:', error)
    errorMessage.value = 'Impossible de charger le cockpit plateforme.'
  } finally {
    isLoading.value = false
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

function riskClass(risk) {
  const classes = {
    high: 'rounded-lg bg-red-100 dark:bg-red-900/30 px-2 py-0.5 text-[10px] font-black uppercase tracking-widest text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800',
    medium: 'rounded-lg bg-amber-100 dark:bg-amber-900/30 px-2 py-0.5 text-[10px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800',
    low: 'rounded-lg bg-emerald-100 dark:bg-emerald-900/30 px-2 py-0.5 text-[10px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800',
  }
  return classes[risk] || classes.medium
}

function riskLabel(risk) {
  const labels = { high: 'Risque eleve', medium: 'Risque moyen', low: 'Risque faible' }
  return labels[risk] || risk
}

function formatDate(value) {
  if (!value) return 'Non renseignÃ©'
  return new Intl.DateTimeFormat(toIntlLocale(localeStore.current), { dateStyle: 'medium' }).format(new Date(value))
}

onMounted(loadDashboard)
</script>

