<template>
  <div class="space-y-8 animate-fade-in">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">Abonnements</h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg text-pretty max-w-2xl">
          Packaging, MRR et contrats clients disponibles pour le cockpit super-admin.
        </p>
      </div>
      <button class="btn-secondary py-2.5 shadow-glass-sm" :disabled="isLoading" @click="loadSubscriptions">
        <ArrowPathIcon class="mr-2 h-4 w-4" :class="{ 'animate-spin': isLoading }" />
        Actualiser
      </button>
    </div>

    <!-- Revenue & Subscription KPIs -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4 animate-slide-up">
      <StatsCard title="MRR Portefeuille" :value="formattedMrr" icon="BanknotesIcon" color="purple" />
      <StatsCard title="Abonnements Actifs" :value="subscriptionMetrics.active" icon="CheckBadgeIcon" color="green" />
      <StatsCard title="Retards Paiement" :value="subscriptionMetrics.past_due" icon="ClockIcon" color="yellow" />
      <StatsCard title="Impayés Totaux" :value="formatCurrency(revenue.overdue_total, revenue.currency)" icon="ExclamationCircleIcon" color="red" />
    </div>

    <div class="grid grid-cols-1 gap-8 xl:grid-cols-3 animate-slide-up" style="animation-delay: 0.1s">
      <!-- Plans Catalog -->
      <section class="card xl:col-span-2">
        <div class="border-b border-slate-200/50 bg-slate-50/50 px-6 py-5 dark:border-slate-800/50 dark:bg-slate-800/30">
          <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <Squares2X2Icon class="h-5 w-5 text-brand-500" />
            Catalogue des Offres
          </h2>
          <p class="mt-1 text-sm font-medium text-slate-500">Source de vérité API pour les quotas et fonctionnalités.</p>
        </div>

        <div v-if="isLoading && plans.length === 0" class="flex h-64 items-center justify-center">
          <div class="h-10 w-10 animate-spin rounded-full border-4 border-brand-500 border-t-transparent"></div>
        </div>

        <div v-else class="grid grid-cols-1 gap-6 p-6 lg:grid-cols-2">
          <article
            v-for="plan in plans"
            :key="plan.id"
            class="group relative flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition-all hover:shadow-glass-lg dark:border-slate-800 dark:bg-slate-900/50 overflow-hidden"
          >
            <div class="flex items-start justify-between gap-3 relative z-10">
              <div>
                <h3 class="text-lg font-black text-slate-900 dark:text-white uppercase tracking-tight group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">{{ plan.name }}</h3>
                <p class="mt-1 text-sm font-bold text-slate-500">
                  {{ plan.max_employees || 'Illimité' }} employés · {{ plan.trial_days }}j essai
                </p>
              </div>
              <span :class="['rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-widest border', plan.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200']">
                {{ plan.is_active ? 'Public' : 'Archive' }}
              </span>
            </div>

            <div class="mt-6 flex items-baseline gap-2 relative z-10">
              <span class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">{{ formatCurrency(plan.price_monthly) }}</span>
              <span class="text-sm font-bold text-slate-500">/ mois</span>
            </div>
            <p class="mt-1 text-xs font-bold text-brand-600/70 uppercase tracking-widest">{{ formatCurrency(plan.price_yearly) }} facturé annuellement</p>

            <div class="mt-6 flex flex-wrap gap-2 relative z-10">
              <span
                v-for="feature in enabledFeatures(plan)"
                :key="feature"
                class="rounded-lg bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-slate-600 dark:bg-slate-800 dark:text-slate-400"
              >
                {{ feature }}
              </span>
            </div>

            <!-- Subtle background accent -->
            <div class="absolute -right-4 -bottom-4 h-24 w-24 rounded-full bg-brand-500/5 blur-2xl transition-all group-hover:bg-brand-500/10"></div>
          </article>
        </div>
      </section>

      <!-- Revenue Insights -->
      <section class="space-y-6">
        <div class="card">
          <div class="border-b border-slate-200/50 bg-slate-50/50 px-6 py-4 dark:border-slate-800/50 dark:bg-slate-800/30">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Santé Commerciale</h2>
          </div>
          <div class="p-6">
            <dl class="space-y-4">
              <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <dt class="text-xs font-black uppercase tracking-widest text-slate-500">ARR Estimé</dt>
                <dd class="text-sm font-black text-slate-900 dark:text-white">{{ formatCurrency(revenue.arr, revenue.currency) }}</dd>
              </div>
              <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <dt class="text-xs font-black uppercase tracking-widest text-slate-500">Encaisse 30j</dt>
                <dd class="text-sm font-black text-emerald-600">{{ formatCurrency(revenue.collected_30d, revenue.currency) }}</dd>
              </div>
              <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <dt class="text-xs font-black uppercase tracking-widest text-slate-500">Total Abonnés</dt>
                <dd class="text-sm font-black text-slate-900 dark:text-white">{{ subscriptionMetrics.total }}</dd>
              </div>
              <div class="flex items-center justify-between">
                <dt class="text-xs font-black uppercase tracking-widest text-slate-500">Périodes d'essai</dt>
                <dd class="text-sm font-black text-blue-600">{{ subscriptionMetrics.trial }}</dd>
              </div>
            </dl>
          </div>
        </div>

        <div class="card">
          <div class="border-b border-slate-200/50 bg-slate-50/50 px-6 py-4 dark:border-slate-800/50 dark:bg-slate-800/30">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Priorités Rétention</h2>
          </div>
          <div class="divide-y divide-slate-100 dark:divide-slate-800">
            <article v-for="item in priorityClients" :key="item.company.id" class="p-4 transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/50">
              <div class="flex items-center justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-black text-slate-900 dark:text-white truncate uppercase">{{ item.company.name }}</p>
                  <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                    {{ item.plan.name || 'Sans plan' }} · <span :class="item.risk_level === 'high' ? 'text-red-500' : 'text-amber-500'">{{ item.risk_level }} risk</span>
                  </p>
                </div>
                <router-link class="shrink-0 rounded-lg bg-brand-50 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-brand-600 hover:bg-brand-100 dark:bg-brand-900/30 dark:text-brand-400 transition-colors" :to="`/companies/${item.company.id}`">
                  Gérer
                </router-link>
              </div>
              <p v-if="item.next_action" class="mt-2 text-xs font-medium text-slate-600 dark:text-slate-400">
                <span class="text-brand-500 mr-1">→</span> {{ item.next_action.label }}
              </p>
            </article>
            <div v-if="priorityClients.length === 0 && !isLoading" class="p-8 text-center text-xs font-bold text-slate-400 uppercase tracking-widest">
              Aucun risque immédiat détecté
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  ArrowPathIcon,
  BanknotesIcon,
  CheckBadgeIcon,
  ClockIcon,
  ExclamationCircleIcon,
  Squares2X2Icon
} from '@heroicons/vue/24/outline'
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
    .map(([feature]) => formatFeatureLabel(feature))
}

function formatFeatureLabel(feature) {
  const labels = {
    rh: 'RH',
    finance: 'Finance',
    ai: 'Leo IA',
    cameras: 'Vidéo',
    tracking: 'Suivi',
    planning: 'Planning',
    training: 'Formations',
    cabinet: 'Documents'
  }
  return labels[feature] || feature.toUpperCase()
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
