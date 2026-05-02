<template>
  <div class="space-y-6">
    <!-- Header with filters -->
    <div class="bg-white shadow rounded-lg p-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Analytics Avancées</h1>
          <p class="mt-1 text-sm text-gray-500">
            Analyse approfondie des performances et tendances
          </p>
        </div>
        
        <!-- Filters -->
        <div class="mt-4 sm:mt-0 flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
          <select 
            v-model="selectedPeriod"
            @change="updateAnalytics"
            class="rounded-md border-gray-300 text-sm"
          >
            <option value="7d">7 derniers jours</option>
            <option value="30d">30 derniers jours</option>
            <option value="90d">3 derniers mois</option>
            <option value="1y">Cette année</option>
          </select>
          
          <select 
            v-model="selectedMetric"
            @change="updateAnalytics"
            class="rounded-md border-gray-300 text-sm"
          >
            <option value="users">Utilisateurs</option>
            <option value="revenue">Revenus</option>
            <option value="engagement">Engagement</option>
            <option value="churn">Churn</option>
          </select>
          
          <button
            @click="exportReport"
            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
          >
            <DocumentArrowDownIcon class="h-4 w-4 mr-2" />
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
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- Cohort Analysis -->
      <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">Analyse de Cohortes</h3>
          <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-500">Rétention par mois</span>
            <InformationCircleIcon class="h-4 w-4 text-gray-400" />
          </div>
        </div>
        <CohortChart :data="analytics.cohortData" />
      </div>

      <!-- Funnel Analysis -->
      <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">Entonnoir de Conversion</h3>
          <select class="text-sm border-gray-300 rounded-md">
            <option>Inscription → Activation</option>
            <option>Activation → Abonnement</option>
            <option>Essai → Payant</option>
          </select>
        </div>
        <FunnelChart :data="analytics.funnelData" />
      </div>
    </div>

    <!-- Predictive Analytics -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <!-- Churn Prediction -->
      <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">Prédiction de Churn</h3>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
            {{ analytics.churnPrediction.riskUsers }} à risque
          </span>
        </div>
        <ChurnPredictionWidget :data="analytics.churnPrediction" />
      </div>

      <!-- Revenue Forecast -->
      <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">Prévision Revenus</h3>
          <span class="text-sm text-gray-500">3 prochains mois</span>
        </div>
        <RevenueForecastWidget :data="analytics.revenueForecast" />
      </div>

      <!-- Feature Adoption -->
      <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">Adoption Fonctionnalités</h3>
          <button class="text-sm text-indigo-600 hover:text-indigo-500">
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
  TrendingUpIcon,
  TrendingDownIcon,
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