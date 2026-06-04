<template>
  <div class="space-y-8 animate-fade-in">
    <!-- Header with filters -->
    <div class="card p-8 relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-64 h-64 bg-brand-500/10 rounded-full blur-3xl"></div>

      <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div>
          <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white mb-2">Analytics Avancées</h1>
          <p class="text-slate-500 dark:text-slate-400 font-medium">
            Analyse approfondie des performances et tendances de la plateforme
          </p>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-4">
          <div class="flex bg-slate-100 dark:bg-slate-800 p-1 rounded-2xl border border-slate-200/50 dark:border-slate-700/50">
            <button
              v-for="period in ['7d', '30d', '90d', '1y']"
              :key="period"
              @click="selectedPeriod = period; updateAnalytics()"
              :class="[
                'px-4 py-2 text-xs font-black uppercase tracking-widest rounded-xl transition-all duration-300',
                selectedPeriod === period
                  ? 'bg-white dark:bg-slate-700 text-brand-600 dark:text-brand-400 shadow-premium'
                  : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
              ]"
            >
              {{ period }}
            </button>
          </div>

          <select
            v-model="selectedMetric"
            @change="updateAnalytics"
            class="rounded-xl border-slate-200/50 dark:border-slate-700/50 bg-white/50 dark:bg-slate-800/50 text-sm font-bold focus:ring-brand-500 transition-all duration-200 px-4 py-2.5"
          >
            <option value="users">Utilisateurs</option>
            <option value="revenue">Revenus</option>
            <option value="engagement">Engagement</option>
            <option value="churn">Churn</option>
          </select>

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
          <button class="text-xs font-black uppercase tracking-widest text-brand-600 hover:text-brand-700 dark:text-brand-400 transition-colors">
            Voir détails
          </button>
        </div>
        <FeatureAdoptionWidget :data="analytics.featureAdoption" />
      </div>
    </div>

    <!-- Segmentation Analysis -->
    <div class="bg-white shadow rounded-lg p-6">
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-medium text-gray-900">Segmentation Utilisateurs</h3>
        <div class="flex items-center space-x-3">
          <select
            v-model="selectedSegmentation"
            class="text-sm border-gray-300 rounded-md"
          >
            <option value="behavior">Comportement</option>
            <option value="value">Valeur</option>
            <option value="engagement">Engagement</option>
            <option value="geography">Géographie</option>
          </select>
          <button
            @click="refreshSegmentation"
            class="p-2 text-gray-400 hover:text-gray-500"
          >
            <ArrowPathIcon class="h-4 w-4" />
          </button>
        </div>
      </div>
      <UserSegmentationChart :data="analytics.segmentationData" :type="selectedSegmentation" />
    </div>

    <!-- Performance Benchmarks -->
    <div class="bg-white shadow rounded-lg p-6">
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-medium text-gray-900">Benchmarks Sectoriels</h3>
        <span class="text-sm text-gray-500">Comparaison avec la moyenne du secteur</span>
      </div>
      <BenchmarkChart :data="analytics.benchmarkData" />
    </div>

    <!-- Insights & Recommendations -->
    <div class="bg-white shadow rounded-lg p-6">
      <h3 class="text-lg font-medium text-gray-900 mb-4">Insights & Recommandations</h3>
      <div class="space-y-4">
        <InsightCard
          v-for="insight in analytics.insights"
          :key="insight.id"
          :insight="insight"
          @action="handleInsightAction"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import {
  DocumentArrowDownIcon,
  InformationCircleIcon,
  ArrowPathIcon,
  ArrowTrendingUpIcon as TrendingUpIcon,
  ArrowTrendingDownIcon as TrendingDownIcon,
  CurrencyEuroIcon,
  UserPlusIcon
} from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'

// Components
import MetricCard from '@/components/analytics/MetricCard.vue'
import CohortChart from '@/components/analytics/CohortChart.vue'
import FunnelChart from '@/components/analytics/FunnelChart.vue'
import ChurnPredictionWidget from '@/components/analytics/ChurnPredictionWidget.vue'
import RevenueForecastWidget from '@/components/analytics/RevenueForecastWidget.vue'
import FeatureAdoptionWidget from '@/components/analytics/FeatureAdoptionWidget.vue'
import UserSegmentationChart from '@/components/analytics/UserSegmentationChart.vue'
import BenchmarkChart from '@/components/analytics/BenchmarkChart.vue'
import InsightCard from '@/components/analytics/InsightCard.vue'

const toast = useToast()

// Reactive state
const selectedPeriod = ref('30d')
const selectedMetric = ref('users')
const selectedSegmentation = ref('behavior')
const isLoading = ref(false)

// Analytics data
const analytics = reactive({
  growthRate: 12.5,
  growthTrend: 'up',
  churnRate: 3.2,
  churnTrend: 'down',
  avgLTV: 2450,
  ltvTrend: 'up',
  cac: 180,
  cacTrend: 'down',
  cohortData: [],
  funnelData: [],
  churnPrediction: {
    riskUsers: 23,
    probability: 0.15,
    factors: ['Low engagement', 'Support tickets', 'Feature usage decline']
  },
  revenueForecast: {
    nextMonth: 45000,
    confidence: 0.85,
    trend: 'positive'
  },
  featureAdoption: [],
  segmentationData: [],
  benchmarkData: [],
  insights: []
})

onMounted(async () => {
  await loadAnalytics()
})

// Methods
async function loadAnalytics() {
  isLoading.value = true

  try {
    // Simulate API calls for different analytics data
    await Promise.all([
      loadCohortData(),
      loadFunnelData(),
      loadSegmentationData(),
      loadBenchmarkData(),
      loadInsights()
    ])
  } catch (error) {
    console.error('Failed to load analytics:', error)
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
    // Simulate report generation
    toast.info('Génération du rapport en cours...')
    await new Promise(resolve => setTimeout(resolve, 2000))

    // Create and download mock report
    const reportData = {
      period: selectedPeriod.value,
      metric: selectedMetric.value,
      generated: new Date().toISOString(),
      data: analytics
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

function handleInsightAction(insight) {
  toast.info(`Action: ${insight.action}`)
  // Implement specific actions based on insight type
}
</script>
