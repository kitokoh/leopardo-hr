<template>
  <div class="space-y-8 animate-fade-in">
    <!-- Header -->
    <div class="card p-8 relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-64 h-64 bg-brand-500/10 rounded-full blur-3xl"></div>

      <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div>
          <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white mb-2">Analytics Avancées</h1>
          <p class="text-slate-500 dark:text-slate-400 font-medium">
            Indicateurs réels de la plateforme — utilisateurs, entreprises, revenus et santé système.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-4">
          <button
            @click="loadAnalytics"
            :disabled="isLoading"
            class="btn-secondary py-2.5 disabled:opacity-50"
          >
            <ArrowPathIcon class="h-5 w-5 mr-2" :class="{ 'animate-spin': isLoading }" />
            Actualiser
          </button>

          <button
            @click="exportReport"
            class="btn-secondary py-2.5"
          >
            <DocumentArrowDownIcon class="h-5 w-5 mr-2" />
            Exporter
          </button>
        </div>
      </div>
    </div>

    <!-- Key Metrics Overview -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
      <MetricCard
        title="Taux de Croissance"
        :value="analytics.growthRate"
        suffix="%"
        :trend="analytics.growthTrend"
        icon="TrendingUpIcon"
        color="green"
      />
      <MetricCard
        title="Taux de Churn"
        :value="analytics.churnRate"
        suffix="%"
        :trend="analytics.churnTrend"
        icon="TrendingDownIcon"
        color="red"
      />
      <MetricCard
        title="LTV Moyen"
        :value="analytics.avgLTV"
        prefix="€"
        :trend="analytics.ltvTrend"
        icon="CurrencyEuroIcon"
        color="purple"
      />
      <MetricCard
        title="CAC"
        :value="analytics.cac"
        prefix="€"
        :trend="analytics.cacTrend"
        icon="UserPlusIcon"
        color="blue"
      />
    </div>

    <!-- Advanced Charts -->
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-2 animate-slide-up" style="animation-delay: 0.1s">
      <!-- Cohort Analysis -->
      <div class="card p-8">
        <div class="flex items-center justify-between mb-8">
          <div>
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">Analyse de Cohortes</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Rétention des utilisateurs par mois</p>
          </div>
          <InformationCircleIcon class="h-6 w-6 text-slate-400 cursor-help" />
        </div>
        <CohortChart :data="analytics.cohortData" />
      </div>

      <!-- Funnel Analysis -->
      <div class="card p-8">
        <div class="flex items-center justify-between mb-8">
          <div>
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">Entonnoir de Conversion</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Performance du cycle de vie client</p>
          </div>
          <select class="rounded-xl border-slate-200/50 dark:border-slate-700/50 bg-slate-50 dark:bg-slate-800 text-xs font-bold focus:ring-brand-500">
            <option>Inscription → Activation</option>
            <option>Activation → Abonnement</option>
            <option>Essai → Payant</option>
          </select>
        </div>
        <FunnelChart :data="analytics.funnelData" />
      </div>
    </div>

    <!-- Predictive Analytics -->
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3 animate-slide-up" style="animation-delay: 0.2s">
      <!-- Churn Prediction -->
      <div class="card p-8 border-t-4 border-t-red-500">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-xl font-bold text-slate-900 dark:text-white">Prédiction de Churn</h3>
          <span class="rounded-full bg-red-100 dark:bg-red-900/30 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-red-800 dark:text-red-300">
            {{ analytics.churnPrediction.riskUsers }} à risque
          </span>
        </div>
        <ChurnPredictionWidget :data="analytics.churnPrediction" />
      </div>

      <!-- Revenue Forecast -->
      <div class="card p-8 border-t-4 border-t-brand-500">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-xl font-bold text-slate-900 dark:text-white">Prévision Revenus</h3>
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400">3 PROCHAINS MOIS</span>
        </div>
        <RevenueForecastWidget :data="analytics.revenueForecast" />
      </div>

      <!-- Feature Adoption -->
      <div class="card p-8 border-t-4 border-t-cyan-500">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-xl font-bold text-slate-900 dark:text-white">Adoption Fonctionnalités</h3>
        </div>
        <FeatureAdoptionWidget :data="analytics.featureAdoption" />
      </div>
    </div>

    <!-- Segmentation Analysis -->
    <div class="card p-8 animate-slide-up" style="animation-delay: 0.3s">
      <div class="flex items-center justify-between mb-8">
        <div>
          <h3 class="text-xl font-bold text-slate-900 dark:text-white">Segmentation Utilisateurs</h3>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Répartition stratégique de la base</p>
        </div>
        <div class="flex items-center space-x-3">
          <select
            v-model="selectedSegmentation"
            class="rounded-xl border-slate-200/50 dark:border-slate-700/50 bg-slate-50 dark:bg-slate-800 text-xs font-bold focus:ring-brand-500"
          >
            <option value="behavior">Comportement</option>
            <option value="value">Valeur</option>
            <option value="engagement">Engagement</option>
            <option value="geography">Géographie</option>
          </select>
          <button
            @click="refreshSegmentation"
            class="p-2 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          >
            <ArrowPathIcon class="h-4 w-4" />
          </button>
        </div>
      </div>
      <UserSegmentationChart :data="analytics.segmentationData" :type="selectedSegmentation" />
    </div>

    <!-- Performance Benchmarks -->
    <div class="card p-8 animate-slide-up" style="animation-delay: 0.4s">
      <div class="flex items-center justify-between mb-8">
        <div>
          <h3 class="text-xl font-bold text-slate-900 dark:text-white">Benchmarks Sectoriels</h3>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Comparaison avec la moyenne du secteur</p>
        </div>
      </div>
      <BenchmarkChart :data="analytics.benchmarkData" />
    </div>

    <!-- Insights & Recommendations -->
    <div class="card p-8 animate-slide-up" style="animation-delay: 0.5s">
      <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-6">Insights & Recommandations</h3>
      <div class="space-y-4">
        <InsightCard
          v-for="insight in analytics.insights"
          :key="insight.id"
          :insight="insight"
          @action="handleInsightAction"
        />
      </div>

      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <MetricCard
          title="Nouveaux Utilisateurs (Aujourd'hui)"
          :value="stats.newUsersToday"
          icon="UserPlusIcon"
          color="indigo"
        />
        <MetricCard
          title="Nouvelles Entreprises (Aujourd'hui)"
          :value="stats.newCompaniesToday"
          icon="ChartBarIcon"
          color="yellow"
        />
        <MetricCard
          title="Tickets Support Ouverts"
          :value="stats.supportTickets"
          icon="ChartBarIcon"
          color="red"
        />
        <MetricCard
          title="Santé Système"
          :value="healthLabel[stats.systemHealth] || 'Inconnu'"
          :color="healthColor[stats.systemHealth] || 'blue'"
          icon="TrendingUpIcon"
        />
      </div>

      <!-- Activité récente + alertes (endpoints réels) -->
      <div class="grid grid-cols-1 gap-8 lg:grid-cols-2 animate-slide-up" style="animation-delay: 0.1s">
        <!-- Recent activity (GET /admin/dashboard/activities) -->
        <section class="card">
          <div class="flex items-center justify-between border-b border-slate-200/50 px-6 py-5 dark:border-slate-800/50">
            <div>
              <h3 class="text-xl font-bold text-slate-900 dark:text-white">Activité Récente</h3>
              <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Dernières actions plateforme</p>
            </div>
          </div>
          <div class="max-h-96 overflow-y-auto divide-y divide-slate-200/50 dark:divide-slate-800/50">
            <div
              v-for="activity in activities"
              :key="activity.id"
              class="flex items-start justify-between gap-4 px-6 py-4"
            >
              <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ activity.message }}</p>
                <p class="mt-0.5 text-xs text-slate-400">{{ formatTime(activity.created_at) }}</p>
              </div>
              <span class="flex-shrink-0 rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-widest text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                {{ activity.type }}
              </span>
            </div>
            <div v-if="activities.length === 0" class="p-8 text-center">
              <p class="text-sm font-medium text-slate-400">Aucune activité récente.</p>
            </div>
          </div>
        </section>

        <!-- System alerts (GET /admin/dashboard/alerts) -->
        <section class="card">
          <div class="flex items-center justify-between border-b border-slate-200/50 px-6 py-5 dark:border-slate-800/50">
            <div>
              <h3 class="text-xl font-bold text-slate-900 dark:text-white">Alertes Système</h3>
              <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Alertes actives détectées par la plateforme</p>
            </div>
          </div>
          <div class="max-h-96 overflow-y-auto divide-y divide-slate-200/50 dark:divide-slate-800/50">
            <div
              v-for="alert in alerts"
              :key="alert.id"
              class="flex items-start justify-between gap-4 px-6 py-4"
            >
              <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ alert.message }}</p>
                <div class="mt-1 flex items-center gap-2">
                  <span
                    :class="[
                      'rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-widest',
                      alert.level === 'critical'
                        ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                        : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                    ]"
                  >
                    {{ alertLevelLabel[alert.level] || alert.level }}
                  </span>
                  <span class="text-xs text-slate-400">{{ formatTime(alert.created_at) }}</span>
                </div>
              </div>
              <button
                @click="dismissAlert(alert)"
                title="Ignorer l'alerte"
                class="flex-shrink-0 p-2 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300 transition-colors"
              >
                <XMarkIcon class="h-4 w-4" />
              </button>
            </div>
            <div v-if="alerts.length === 0" class="p-8 text-center">
              <p class="text-sm font-medium text-slate-400">Aucune alerte active.</p>
            </div>
          </div>
        </section>
      </div>

      <!-- Advanced Charts — aucun endpoint backend pour le moment : états « non disponible » explicites -->
      <div class="grid grid-cols-1 gap-8 lg:grid-cols-2 animate-slide-up" style="animation-delay: 0.2s">
        <!-- Cohort Analysis -->
        <div class="card p-8">
          <div class="flex items-center justify-between mb-8">
            <div>
              <h3 class="text-xl font-bold text-slate-900 dark:text-white">Analyse de Cohortes</h3>
              <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Rétention des utilisateurs par mois</p>
            </div>
            <InformationCircleIcon class="h-6 w-6 text-slate-400 cursor-help" />
          </div>
          <div class="flex flex-col items-center justify-center py-12 text-center">
            <InformationCircleIcon class="h-8 w-8 text-slate-300 dark:text-slate-600 mb-3" />
            <p class="text-sm font-bold text-slate-500 dark:text-slate-400">Non disponible</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Aucun endpoint backend ne fournit ces données pour le moment.</p>
          </div>
        </div>

        <!-- Funnel Analysis -->
        <div class="card p-8">
          <div class="flex items-center justify-between mb-8">
            <div>
              <h3 class="text-xl font-bold text-slate-900 dark:text-white">Entonnoir de Conversion</h3>
              <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Performance du cycle de vie client</p>
            </div>
            <InformationCircleIcon class="h-6 w-6 text-slate-400 cursor-help" />
          </div>
          <div class="flex flex-col items-center justify-center py-12 text-center">
            <InformationCircleIcon class="h-8 w-8 text-slate-300 dark:text-slate-600 mb-3" />
            <p class="text-sm font-bold text-slate-500 dark:text-slate-400">Non disponible</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Aucun endpoint backend ne fournit ces données pour le moment.</p>
          </div>
        </div>
      </div>

      <!-- Predictive Analytics -->
      <div class="grid grid-cols-1 gap-8 lg:grid-cols-3 animate-slide-up" style="animation-delay: 0.3s">
        <!-- Churn Prediction -->
        <div class="card p-8 border-t-4 border-t-red-500">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">Prédiction de Churn</h3>
          </div>
          <div class="flex flex-col items-center justify-center py-12 text-center">
            <InformationCircleIcon class="h-8 w-8 text-slate-300 dark:text-slate-600 mb-3" />
            <p class="text-sm font-bold text-slate-500 dark:text-slate-400">Non disponible</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Aucun endpoint backend ne fournit ces données pour le moment.</p>
          </div>
        </div>

        <!-- Revenue Forecast -->
        <div class="card p-8 border-t-4 border-t-brand-500">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">Prévision Revenus</h3>
          </div>
          <div class="flex flex-col items-center justify-center py-12 text-center">
            <InformationCircleIcon class="h-8 w-8 text-slate-300 dark:text-slate-600 mb-3" />
            <p class="text-sm font-bold text-slate-500 dark:text-slate-400">Non disponible</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Aucun endpoint backend ne fournit ces données pour le moment.</p>
          </div>
        </div>

        <!-- Feature Adoption -->
        <div class="card p-8 border-t-4 border-t-cyan-500">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">Adoption Fonctionnalités</h3>
          </div>
          <div class="flex flex-col items-center justify-center py-12 text-center">
            <InformationCircleIcon class="h-8 w-8 text-slate-300 dark:text-slate-600 mb-3" />
            <p class="text-sm font-bold text-slate-500 dark:text-slate-400">Non disponible</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Aucun endpoint backend ne fournit ces données pour le moment.</p>
          </div>
        </div>
      </div>

      <!-- Segmentation Analysis -->
      <div class="card p-8 animate-slide-up" style="animation-delay: 0.4s">
        <div class="flex items-center justify-between mb-8">
          <div>
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">Segmentation Utilisateurs</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Répartition stratégique de la base</p>
          </div>
        </div>
        <div class="flex flex-col items-center justify-center py-12 text-center">
          <InformationCircleIcon class="h-8 w-8 text-slate-300 dark:text-slate-600 mb-3" />
          <p class="text-sm font-bold text-slate-500 dark:text-slate-400">Non disponible</p>
          <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Aucun endpoint backend ne fournit ces données pour le moment.</p>
        </div>
      </div>

      <!-- Performance Benchmarks -->
      <div class="card p-8 animate-slide-up" style="animation-delay: 0.5s">
        <div class="flex items-center justify-between mb-8">
          <div>
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">Benchmarks Sectoriels</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Comparaison avec la moyenne du secteur</p>
          </div>
        </div>
        <div class="flex flex-col items-center justify-center py-12 text-center">
          <InformationCircleIcon class="h-8 w-8 text-slate-300 dark:text-slate-600 mb-3" />
          <p class="text-sm font-bold text-slate-500 dark:text-slate-400">Non disponible</p>
          <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Aucun endpoint backend ne fournit ces données pour le moment.</p>
        </div>
      </div>

      <!-- Insights & Recommendations -->
      <div class="card p-8 animate-slide-up" style="animation-delay: 0.6s">
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-6">Insights & Recommandations</h3>
        <div class="flex flex-col items-center justify-center py-12 text-center">
          <InformationCircleIcon class="h-8 w-8 text-slate-300 dark:text-slate-600 mb-3" />
          <p class="text-sm font-bold text-slate-500 dark:text-slate-400">Non disponible</p>
          <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Aucun endpoint backend ne fournit ces données pour le moment.</p>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import {
  DocumentArrowDownIcon,
  ArrowPathIcon,
  InformationCircleIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { useLocaleStore } from '@/stores/locale'
import { toIntlLocale } from '@/i18n/index.js'

// Components
import MetricCard from '@/components/analytics/MetricCard.vue'

const toast = useToast()
const localeStore = useLocaleStore()

// Reactive state
const isLoading = ref(false)
const errorMessage = ref('')
const lastUpdated = ref(null)

// Données réelles — GET /api/v1/admin/dashboard/stats
const stats = reactive({
  totalUsers: 0,
  totalCompanies: 0,
  activeSubscriptions: 0,
  monthlyRevenue: 0,
  newUsersToday: 0,
  newCompaniesToday: 0,
  supportTickets: 0,
  systemHealth: 'good'
})

// Données réelles — GET /api/v1/admin/dashboard/activities
const activities = ref([])

// Données réelles — GET /api/v1/admin/dashboard/alerts
const alerts = ref([])

const healthLabel = {
  good: 'Bon',
  warning: 'Attention',
  error: 'Erreur'
}

const healthColor = {
  good: 'green',
  warning: 'yellow',
  error: 'red'
}

const alertLevelLabel = {
  critical: 'Critique',
  warning: 'Avertissement',
  info: 'Info'
}

onMounted(async () => {
  await loadAnalytics()
})

// Méthodes
async function loadAnalytics() {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const [statsResponse, activitiesResponse, alertsResponse] = await Promise.all([
      api.get('/admin/dashboard/stats'),
      api.get('/admin/dashboard/activities'),
      api.get('/admin/dashboard/alerts')
    ])

    Object.assign(stats, statsResponse.data || {})
    activities.value = activitiesResponse.data?.data || []
    alerts.value = alertsResponse.data?.data || []
    lastUpdated.value = new Date()
  } catch (error) {
    console.error('Failed to load analytics:', error)
    errorMessage.value = 'Erreur lors du chargement des analytics. Réessayez plus tard.'
    toast.error('Erreur lors du chargement des analytics')
  } finally {
    isLoading.value = false
  }
}

async function loadCohortData() {
  // Simulate cohort analysis data
  await new Promise(resolve => setTimeout(resolve, 500))

  analytics.cohortData = [
    { month: 'Jan 2026', week0: 100, week1: 85, week2: 72, week3: 65, week4: 58 },
    { month: 'Fév 2026', week0: 100, week1: 88, week2: 75, week3: 68, week4: 62 },
    { month: 'Mar 2026', week0: 100, week1: 90, week2: 78, week3: 71, week4: 65 },
    { month: 'Avr 2026', week0: 100, week1: 87, week2: 74, week3: 67, week4: 60 }
  ]
}

async function loadFunnelData() {
  // Simulate funnel data
  await new Promise(resolve => setTimeout(resolve, 300))

  analytics.funnelData = [
    { stage: 'Visiteurs', value: 10000, conversion: 100 },
    { stage: 'Inscriptions', value: 2500, conversion: 25 },
    { stage: 'Activations', value: 1800, conversion: 72 },
    { stage: 'Essais', value: 900, conversion: 50 },
    { stage: 'Abonnements', value: 450, conversion: 50 }
  ]
}

async function loadSegmentationData() {
  // Simulate segmentation data
  await new Promise(resolve => setTimeout(resolve, 400))

  analytics.segmentationData = [
    { segment: 'Champions', users: 450, value: 'Très élevée', color: '#10B981' },
    { segment: 'Loyaux', users: 680, value: 'Élevée', color: '#3B82F6' },
    { segment: 'Potentiels', users: 320, value: 'Moyenne', color: '#F59E0B' },
    { segment: 'Nouveaux', users: 890, value: 'Faible', color: '#8B5CF6' },
    { segment: 'À risque', users: 120, value: 'Critique', color: '#EF4444' }
  ]
}

async function loadBenchmarkData() {
  // Simulate benchmark data
  await new Promise(resolve => setTimeout(resolve, 350))

  analytics.benchmarkData = [
    { metric: 'Taux de conversion', our: 12.5, industry: 8.2, status: 'above' },
    { metric: 'Churn mensuel', our: 3.2, industry: 5.1, status: 'above' },
    { metric: 'LTV/CAC ratio', our: 13.6, industry: 11.8, status: 'above' },
    { metric: 'Time to value', our: 7, industry: 12, status: 'above' },
    { metric: 'Support satisfaction', our: 4.2, industry: 3.8, status: 'above' }
  ]
}

async function loadInsights() {
  // Simulate AI-generated insights
  await new Promise(resolve => setTimeout(resolve, 600))

  analytics.insights = [
    {
      id: 1,
      type: 'opportunity',
      title: 'Opportunité d\'amélioration du onboarding',
      description: 'Les utilisateurs qui complètent le tutoriel ont 3x plus de chances de s\'abonner.',
      impact: 'high',
      action: 'Optimiser le parcours d\'onboarding',
      confidence: 0.89
    },
    {
      id: 2,
      type: 'warning',
      title: 'Baisse d\'engagement détectée',
      description: 'Les utilisateurs du segment "Entreprises 50-100" montrent une baisse d\'activité de 15%.',
      impact: 'medium',
      action: 'Lancer une campagne de réengagement',
      confidence: 0.76
    },
    {
      id: 3,
      type: 'success',
      title: 'Performance exceptionnelle',
      description: 'Le taux de conversion mobile a augmenté de 25% ce mois-ci.',
      impact: 'positive',
      action: 'Analyser les facteurs de succès',
      confidence: 0.95
    }
  ]
}

async function updateAnalytics() {
  await loadAnalytics()
  toast.success('Analytics mis à jour')
}

async function refreshSegmentation() {
  isLoading.value = true
  await loadSegmentationData()
  isLoading.value = false
  toast.success('Segmentation actualisée')
}

async function exportReport() {
  try {
    const reportData = {
      exported_at: new Date().toISOString(),
      stats: { ...stats },
      activities: activities.value,
      alerts: alerts.value
    }

    const blob = new Blob([JSON.stringify(reportData, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `analytics-report-${Date.now()}.json`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)

    toast.success('Rapport exporté avec succès')
  } catch (error) {
    console.error('Export failed:', error)
    toast.error('Erreur lors de l\'export')
  }
}

function formatTime(value) {
  if (!value) return '—'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'

  const now = new Date()
  const diff = now - date

  if (diff < 60000) return 'À l\'instant'
  if (diff < 3600000) return `${Math.floor(diff / 60000)}m`
  if (diff < 86400000) return `${Math.floor(diff / 3600000)}h`
  return date.toLocaleString(toIntlLocale(localeStore.current), {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>
