<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between animate-fade-in">
      <div>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Cockpit plateforme</h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400">
          Synthèse commerciale, adoption terrain et demandes entrantes.
        </p>
      </div>
      <button class="btn-secondary" :disabled="isLoading" @click="loadDashboard">
        Actualiser
      </button>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-5 animate-slide-up">
      <StatsCard title="MRR portefeuille" :value="formatCurrency(revenue.mrr, revenue.currency)" icon="CurrencyEuroIcon" color="purple" />
      <StatsCard title="ARR estime" :value="formatCurrency(revenue.arr, revenue.currency)" icon="ChartBarIcon" color="blue" />
      <StatsCard title="Clients actifs" :value="companyMetrics.active" icon="BuildingOfficeIcon" color="green" />
      <StatsCard title="Impayes" :value="formatCurrency(revenue.overdue_total, revenue.currency)" icon="CreditCardIcon" color="red" />
      <StatsCard title="Demandes a traiter" :value="pendingRequests" icon="ChatBubbleLeftRightIcon" color="yellow" />
    </div>

    <div v-if="isLoading" class="rounded-lg bg-white p-6 text-sm text-gray-500 shadow">
      Chargement du cockpit...
    </div>
    <div v-else-if="errorMessage" class="rounded-lg bg-white p-6 text-sm text-red-600 shadow">
      {{ errorMessage }}
    </div>

    <section v-if="!isLoading && !errorMessage" class="card overflow-hidden animate-slide-up" style="animation-delay: 0.08s">
      <div class="border-b border-slate-200/50 px-6 py-5 dark:border-slate-800/50">
        <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
          <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-brand-600 dark:text-brand-400">Workflows plateforme</p>
            <h2 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">Exécuter les opérations critiques</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
              Création client, activation, suivi santé, abonnements, support et intégrations.
            </p>
          </div>
          <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
            {{ companyMetrics.active }} clients actifs
          </span>
        </div>
      </div>

      <div class="grid grid-cols-1 divide-y divide-slate-200/50 dark:divide-slate-800/50 md:grid-cols-2 md:divide-x md:divide-y-0 xl:grid-cols-3">
        <router-link
          v-for="workflow in workflowCards"
          :key="workflow.title"
          :to="workflow.to"
          class="group p-6 transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/60"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <h3 class="font-bold text-slate-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-300">
                {{ workflow.title }}
              </h3>
              <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ workflow.description }}</p>
            </div>
            <span :class="workflow.badgeClass">{{ workflow.badge }}</span>
          </div>
          <p class="mt-4 text-sm font-bold text-brand-600 dark:text-brand-400">{{ workflow.action }}</p>
        </router-link>
      </div>
    </section>

    <div v-if="!isLoading && !errorMessage" class="grid grid-cols-1 gap-6 xl:grid-cols-3 animate-slide-up" style="animation-delay: 0.1s">
      <section class="card xl:col-span-2">
        <div class="flex items-center justify-between border-b border-slate-200/50 dark:border-slate-800/50 px-6 py-5">
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Priorités clients</h2>
            <p class="text-sm text-slate-500">Actions qui protègent la rétention et l'activation terrain.</p>
          </div>
          <router-link class="text-sm font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300 transition-colors" to="/companies">
            Voir portefeuille
          </router-link>
        </div>

        <div class="divide-y divide-slate-200/50 dark:divide-slate-800/50">
          <article v-for="item in priorityCompanies" :key="item.company.id" class="p-6 hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <div class="flex flex-wrap items-center gap-3">
                  <h3 class="font-bold text-slate-900 dark:text-white">{{ item.company.name }}</h3>
                  <span :class="riskClass(item.risk_level)">{{ riskLabel(item.risk_level) }}</span>
                  <span class="text-sm font-semibold text-gray-900">{{ item.health_score }}/100</span>
                </div>
                <p class="mt-1 text-sm text-gray-500">
                  {{ item.plan.name || 'Sans plan' }} · {{ item.employees_active }} employés actifs · {{ item.attendance_logs_30d }} pointages 30j
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

      <section class="card">
        <div class="border-b border-slate-200/50 dark:border-slate-800/50 px-6 py-5">
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">Demandes entrantes</h2>
          <p class="text-sm text-slate-500">Nouveaux comptes à qualifier.</p>
        </div>
        <div class="divide-y divide-slate-200/50 dark:divide-slate-800/50">
          <article v-for="request in pendingCompanyRequests" :key="request.id" class="p-5 hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors">
            <div class="flex items-start justify-between gap-3">
              <div>
                <h3 class="font-bold text-slate-900 dark:text-white">{{ request.company_name }}</h3>
                <p class="text-sm text-slate-500">{{ request.sector || 'Secteur non précisé' }}</p>
              </div>
              <span class="rounded-full bg-yellow-100 dark:bg-yellow-900/30 px-2.5 py-1 text-xs font-bold text-yellow-800 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-800">
                A traiter
              </span>
            </div>
            <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">{{ request.email || request.user?.email || 'Contact non renseigné' }}</p>
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

    <div v-if="!isLoading && !errorMessage" class="grid grid-cols-1 gap-6 lg:grid-cols-3 animate-slide-up" style="animation-delay: 0.2s">
      <section class="card p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Adoption</h2>
        <dl class="mt-4 space-y-4 text-sm">
          <div class="flex items-center justify-between">
            <dt class="text-slate-500 dark:text-slate-400">Pointages 30j</dt>
            <dd class="font-bold text-slate-900 dark:text-white">{{ adoption.attendance_logs }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-slate-500 dark:text-slate-400">Employés actifs</dt>
            <dd class="font-bold text-slate-900 dark:text-white">{{ adoption.active_employees }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-slate-500 dark:text-slate-400">Clients à risque</dt>
            <dd class="font-bold text-slate-900 dark:text-white">{{ summary.risk.high + summary.risk.medium }}</dd>
          </div>
        </dl>
      </section>

      <section class="card p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Revenus</h2>
        <dl class="mt-4 space-y-4 text-sm">
          <div class="flex items-center justify-between">
            <dt class="text-slate-500 dark:text-slate-400">MRR</dt>
            <dd class="font-bold text-slate-900 dark:text-white">{{ formatCurrency(revenue.mrr, revenue.currency) }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-slate-500 dark:text-slate-400">Encaisse 30j</dt>
            <dd class="font-bold text-slate-900 dark:text-white">{{ formatCurrency(revenue.collected_30d, revenue.currency) }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-slate-500 dark:text-slate-400">Impayés</dt>
            <dd class="font-bold text-slate-900 dark:text-white">{{ formatCurrency(revenue.overdue_total, revenue.currency) }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-slate-500 dark:text-slate-400">ARPA</dt>
            <dd class="font-bold text-slate-900 dark:text-white">{{ formatCurrency(averageRevenuePerAccount, revenue.currency) }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-slate-500 dark:text-slate-400">Abonnements actifs</dt>
            <dd class="font-bold text-slate-900 dark:text-white">{{ subscriptionMetrics.active }}</dd>
          </div>
        </dl>
      </section>

      <section class="card p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Raccourcis</h2>
        <div class="mt-4 grid grid-cols-1 gap-3 text-sm">
          <router-link class="btn-secondary justify-center shadow-sm" to="/companies">Portefeuille clients</router-link>
          <router-link class="btn-secondary justify-center shadow-sm" to="/subscriptions">Abonnements</router-link>
          <router-link class="btn-secondary justify-center shadow-sm" to="/support">Demandes clients</router-link>
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
const workflowCards = computed(() => [
  {
    title: 'Créer ou activer un client',
    description: 'Qualifier une entreprise, ouvrir son tenant et vérifier son statut de lancement.',
    action: 'Ouvrir le portefeuille clients',
    to: '/companies',
    badge: `${companyMetrics.value.total} tenants`,
    badgeClass: 'rounded-full bg-brand-100 px-2.5 py-1 text-xs font-bold text-brand-700 dark:bg-brand-900/40 dark:text-brand-300',
  },
  {
    title: 'Traiter les demandes entrantes',
    description: 'Suivre les demandes d’essai, prioriser les leads et éviter les prospects bloqués.',
    action: 'Voir les demandes clients',
    to: '/support',
    badge: `${pendingRequests.value} à traiter`,
    badgeClass: 'rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  },
  {
    title: 'Surveiller les clients à risque',
    description: 'Identifier les comptes faibles en adoption, pointage ou santé opérationnelle.',
    action: 'Analyser les priorités',
    to: '/analytics',
    badge: `${summary.value.risk.high + summary.value.risk.medium} risques`,
    badgeClass: 'rounded-full bg-red-100 px-2.5 py-1 text-xs font-bold text-red-700 dark:bg-red-900/40 dark:text-red-300',
  },
  {
    title: 'Piloter abonnements et revenus',
    description: 'Contrôler MRR, impayés, plans actifs et trajectoire commerciale de la plateforme.',
    action: 'Ouvrir abonnements',
    to: '/subscriptions',
    badge: formatCurrency(revenue.value.mrr, revenue.value.currency),
    badgeClass: 'rounded-full bg-purple-100 px-2.5 py-1 text-xs font-bold text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
  },
  {
    title: 'Vérifier système et sécurité',
    description: 'Contrôler santé API, configuration, logs, sauvegardes et signaux d’incident.',
    action: 'Ouvrir système',
    to: '/system',
    badge: 'Ops',
    badgeClass: 'rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300',
  },
  {
    title: 'Préparer intégrations partenaires',
    description: 'Suivre webhooks, exports, rapports et surfaces API nécessaires aux intégrateurs.',
    action: 'Ouvrir webhooks',
    to: '/webhooks',
    badge: 'API',
    badgeClass: 'rounded-full bg-cyan-100 px-2.5 py-1 text-xs font-bold text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300',
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
