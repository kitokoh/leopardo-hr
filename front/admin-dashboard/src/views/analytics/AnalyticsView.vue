<template>
  <div class="space-y-8 animate-fade-in">
    <!-- Header -->
    <div class="card p-8 relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-64 h-64 bg-brand-500/10 rounded-full blur-3xl"></div>

      <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div>
          <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white mb-2">Analytics Plateforme</h1>
          <p class="text-slate-500 dark:text-slate-400 font-medium">
            Indicateurs reels du cockpit super-admin (issue #2185)
          </p>
        </div>

        <div class="flex items-center gap-4">
          <button
            @click="loadAll"
            :disabled="isLoading"
            class="btn-secondary py-2.5"
          >
            <ArrowPathIcon :class="['h-5 w-5 mr-2', isLoading ? 'animate-spin' : '']" />
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

    <!-- Key Metrics Overview (donnees reelles /admin/dashboard/stats) -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
      <MetricCard
        title="Utilisateurs"
        :value="String(stats.totalUsers ?? 0)"
        :trend="newUsersTrend"
        icon="UsersIcon"
        color="blue"
      />
      <MetricCard
        title="Entreprises"
        :value="String(stats.totalCompanies ?? 0)"
        :trend="newCompaniesTrend"
        icon="BuildingOfficeIcon"
        color="green"
      />
      <MetricCard
        title="Abonnements actifs"
        :value="String(stats.activeSubscriptions ?? 0)"
        :trend="monthlyRevenueLabel"
        icon="CreditCardIcon"
        color="purple"
      />
      <MetricCard
        title="Tickets support ouverts"
        :value="String(stats.supportTickets ?? 0)"
        :trend="systemHealthLabel"
        icon="LifebuoyIcon"
        color="amber"
      />
    </div>

    <!-- Activite recente (donnees reelles /admin/dashboard/activities) -->
    <div class="card p-8 animate-slide-up" style="animation-delay: 0.1s">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h3 class="text-xl font-bold text-slate-900 dark:text-white">Activité récente</h3>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">25 derniers événements plateforme</p>
        </div>
      </div>
      <div v-if="isLoading" class="py-12 text-center text-sm font-bold text-slate-400 uppercase tracking-widest">
        Chargement...
      </div>
      <div v-else-if="activities.length === 0" class="py-12 text-center">
        <InformationCircleIcon class="mx-auto h-10 w-10 text-slate-300" />
        <p class="mt-3 text-sm font-medium text-slate-500">Aucune activité récente</p>
      </div>
      <ul v-else class="divide-y divide-slate-200/50 dark:divide-slate-800/50">
        <li v-for="activity in activities" :key="activity.id" class="py-4 flex items-start justify-between gap-4">
          <div class="flex items-start space-x-3">
            <div class="mt-0.5 h-2.5 w-2.5 rounded-full bg-brand-500 flex-shrink-0"></div>
            <div>
              <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ activity.message }}</p>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ activity.type }}</p>
            </div>
          </div>
          <span class="text-xs font-medium text-slate-400 whitespace-nowrap">{{ formatDate(activity.created_at) }}</span>
        </li>
      </ul>
    </div>

    <!-- Alertes plateforme (donnees reelles /admin/dashboard/alerts) -->
    <div class="card p-8 animate-slide-up" style="animation-delay: 0.2s">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h3 class="text-xl font-bold text-slate-900 dark:text-white">Alertes plateforme</h3>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Problèmes d'infrastructure et risques abonnements</p>
        </div>
      </div>
      <div v-if="alerts.length === 0" class="py-8 text-center">
        <CheckCircleIcon class="mx-auto h-10 w-10 text-emerald-400" />
        <p class="mt-3 text-sm font-medium text-slate-500">Aucune alerte active</p>
      </div>
      <div v-else class="space-y-3">
        <div
          v-for="alert in alerts"
          :key="alert.id"
          :class="[
            'rounded-xl border p-4 flex items-start justify-between gap-4',
            alert.level === 'critical'
              ? 'border-red-200 bg-red-50 dark:border-red-900/40 dark:bg-red-950/20'
              : alert.level === 'warning'
                ? 'border-amber-200 bg-amber-50 dark:border-amber-900/40 dark:bg-amber-950/20'
                : 'border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900/40'
          ]"
        >
          <div class="flex items-start space-x-3">
            <ExclamationTriangleIcon :class="['h-5 w-5 mt-0.5', alert.level === 'critical' ? 'text-red-500' : alert.level === 'warning' ? 'text-amber-500' : 'text-slate-400']" />
            <div>
              <p class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ alert.title }}</p>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ alert.description }}</p>
            </div>
          </div>
          <button
            @click="dismissAlert(alert.id)"
            class="text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
          >
            Ignorer
          </button>
        </div>
      </div>
    </div>

    <!-- Sections non disponibles (etat honnete — pas de backend) -->
    <div class="card p-8 border-dashed border-2 border-slate-200 dark:border-slate-800">
      <h3 class="text-sm font-black uppercase tracking-widest text-slate-400 mb-2">Analyses avancées (cohortes, funnels, prédictions, segmentation, benchmarks)</h3>
      <p class="text-sm text-slate-500 dark:text-slate-400">
        Les cohortes, entonnoirs, prédictions de churn et benchmarks ne sont pas encore servis par un endpoint backend. Les données affichées ici sont limitées aux agrégats réels du cockpit (stats, activités, alertes). Vague QA 2026-08-14 — suppression des données fabriquées.
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import {
  DocumentArrowDownIcon,
  ArrowPathIcon,
  InformationCircleIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import api from '@/services/api.js'
import { useLocaleStore } from '@/stores/locale'

import MetricCard from '@/components/analytics/MetricCard.vue'

const toast = useToast()
const localeStore = useLocaleStore()

const isLoading = ref(false)
const stats = reactive({
  totalUsers: 0,
  totalCompanies: 0,
  activeSubscriptions: 0,
  monthlyRevenue: null,
  newUsersToday: 0,
  newCompaniesToday: 0,
  supportTickets: 0,
  systemHealth: null
})
const activities = ref([])
const alerts = ref([])

const monthlyRevenueLabel = ref('')
const newUsersTrend = computed(() => `+${stats.newUsersToday ?? 0} aujourd'hui`)
const newCompaniesTrend = computed(() => `+${stats.newCompaniesToday ?? 0} aujourd'hui`)
const systemHealthLabel = computed(() => (stats.systemHealth ? `Health: ${stats.systemHealth}` : ''))

onMounted(loadAll)

async function loadAll() {
  isLoading.value = true
  try {
    const [statsRes, activitiesRes, alertsRes] = await Promise.all([
      api.get('/admin/dashboard/stats'),
      api.get('/admin/dashboard/activities'),
      api.get('/admin/dashboard/alerts')
    ])
    Object.assign(stats, statsRes.data || {})
    activities.value = activitiesRes.data?.data || []
    alerts.value = alertsRes.data?.data || []
    if (stats.monthlyRevenue) {
      monthlyRevenueLabel.value = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(stats.monthlyRevenue)
    } else {
      monthlyRevenueLabel.value = ''
    }
  } catch (error) {
    console.error('Failed to load analytics:', error)
    toast.error("Erreur lors du chargement des analytics")
  } finally {
    isLoading.value = false
  }
}

async function dismissAlert(alertKey) {
  try {
    await api.post(`/admin/dashboard/alerts/${alertKey}/dismiss`)
    alerts.value = alerts.value.filter(a => a.id !== alertKey)
    toast.success('Alerte ignorée')
  } catch (error) {
    console.error('Dismiss alert failed:', error)
    toast.error("Erreur lors de l'ignorance de l'alerte")
  }
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

async function exportReport() {
  try {
    const csvContent = "data:text/csv;charset=utf-8," +
      "Type,Message,Date\n" +
      activities.value.map(a => `${a.type || ''},${String(a.message || '').replace(/,/g, ';')},${a.created_at || ''}`).join('\n')
    const link = document.createElement("a")
    link.setAttribute("href", encodeURI(csvContent))
    link.setAttribute("download", "analytics-activities.csv")
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    toast.success('Export terminé')
  } catch (error) {
    console.error('Export failed:', error)
    toast.error("Erreur lors de l'export")
  }
}
</script>
