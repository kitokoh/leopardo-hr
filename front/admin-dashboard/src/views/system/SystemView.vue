<template>
  <div class="space-y-8 animate-fade-in">
    <!-- Header -->
    <div class="card overflow-hidden">
      <div class="bg-slate-900 px-8 py-10 relative overflow-hidden">
        <!-- Decoration -->
        <div class="absolute top-0 right-0 w-64 h-64 bg-brand-500/10 rounded-full blur-3xl -mr-32 -mt-32"></div>
        <div class="absolute bottom-0 left-0 w-48 h-48 bg-cyan-500/10 rounded-full blur-3xl -ml-24 -mb-24"></div>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between relative z-10">
          <div>
            <h1 class="text-4xl font-black tracking-tight text-white uppercase">Administration Système</h1>
            <p class="mt-2 text-slate-400 font-medium text-lg">
              Monitoring, configuration et automatisation de la plateforme Leopardo RH.
            </p>
          </div>

          <div class="mt-6 sm:mt-0 flex flex-wrap gap-3">
            <button
              @click="runHealthCheck"
              :disabled="isRunningHealthCheck"
              class="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-black uppercase tracking-widest text-white bg-white/10 dark:bg-slate-900/10 hover:bg-white/20 transition-all border border-white/10 disabled:opacity-50"
            >
              <HeartIcon class="h-4 w-4 mr-2" :class="{ 'animate-pulse text-red-400': isRunningHealthCheck }" />
              {{ isRunningHealthCheck ? 'Analyse...' : 'Health Check' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Queue / Jobs Observability (PA2-QA-006 — GET /platform/observability/queues) -->
    <QueueObservabilityCard
      :data="queueObservability"
      :loading="isLoadingObservability"
      @refresh="loadQueueObservability"
    />

    <!-- Notifications & Runbooks Observability (PA2-ADM-005 — GET /platform/observability/notifications) -->
    <NotificationObservabilityCard
      :data="notificationObservability"
      :loading="isLoadingNotificationObservability"
      @refresh="loadNotificationObservability"
    />

    <!-- System Status Overview -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 animate-slide-up">
      <SystemStatusCard
        title="Statut Global"
        :status="globalHealthStatus"
        :details="globalHealthDetails"
        :last-check="lastUpdated"
        icon="ServerIcon"
      />
      <SystemStatusCard
        title="Base de Données"
        :status="databaseStatus"
        :details="databaseDetails"
        :last-check="healthCheckTimestamp"
        icon="CircleStackIcon"
      />
      <SystemStatusCard
        title="Services API"
        status="unavailable"
        details="Aucun endpoint backend dédié pour le moment."
        :show-details="false"
        icon="CloudIcon"
      />
      <SystemStatusCard
        title="Infrastructure"
        status="unavailable"
        details="Aucun endpoint backend dédié pour le moment."
        :show-details="false"
        icon="WifiIcon"
      />
    </div>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 animate-slide-up" style="animation-delay: 0.1s">
      <!-- Performance Metrics -->
      <div class="lg:col-span-8 space-y-8">
        <section class="card">
          <div class="flex items-center justify-between border-b border-slate-200/50 px-6 py-5 dark:border-slate-800/50">
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">Métriques Temps Réel</h3>
          </div>
          <div class="flex flex-col items-center justify-center py-12 text-center">
            <InformationCircleIcon class="h-8 w-8 text-slate-300 dark:text-slate-600 mb-3" />
            <p class="text-sm font-bold text-slate-500 dark:text-slate-400">Non disponible</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Aucun endpoint backend ne fournit ces données pour le moment.</p>
          </div>
        </section>

        <!-- API Testing Tools -->
        <section class="card">
          <div class="flex items-center justify-between border-b border-slate-200/50 px-6 py-5 dark:border-slate-800/50">
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">Outils de Test API</h3>
          </div>
          <div class="flex flex-col items-center justify-center py-12 text-center">
            <InformationCircleIcon class="h-8 w-8 text-slate-300 dark:text-slate-600 mb-3" />
            <p class="text-sm font-bold text-slate-500 dark:text-slate-400">Non disponible</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Aucun endpoint backend ne fournit ces données pour le moment.</p>
          </div>
        </section>
      </div>

      <!-- Sidebar -->
      <div class="lg:col-span-4 space-y-8">
        <section class="card p-6">
          <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6 uppercase tracking-wider">Utilisation des Ressources</h3>
          <div class="flex flex-col items-center justify-center py-12 text-center">
            <InformationCircleIcon class="h-8 w-8 text-slate-300 dark:text-slate-600 mb-3" />
            <p class="text-sm font-bold text-slate-500 dark:text-slate-400">Non disponible</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Aucun endpoint backend ne fournit ces données pour le moment.</p>
          </div>
        </section>

        <!-- Security Monitoring -->
        <section class="card">
          <div class="border-b border-slate-200/50 px-6 py-4 dark:border-slate-800/50 flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white uppercase tracking-wider">Sécurité</h3>
          </div>
          <div class="p-4">
            <div class="flex flex-col items-center justify-center py-12 text-center">
              <InformationCircleIcon class="h-8 w-8 text-slate-300 dark:text-slate-600 mb-3" />
              <p class="text-sm font-bold text-slate-500 dark:text-slate-400">Non disponible</p>
              <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Aucun endpoint backend ne fournit ces données pour le moment.</p>
            </div>
          </div>
        </section>

        <!-- Backup Management -->
        <section class="card">
          <div class="border-b border-slate-200/50 px-6 py-4 dark:border-slate-800/50 flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white uppercase tracking-wider">Sauvegardes</h3>
          </div>
          <div class="p-4">
            <div class="flex flex-col items-center justify-center py-12 text-center">
              <InformationCircleIcon class="h-8 w-8 text-slate-300 dark:text-slate-600 mb-3" />
              <p class="text-sm font-bold text-slate-500 dark:text-slate-400">Non disponible</p>
              <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Aucun endpoint backend ne fournit ces données pour le moment.</p>
            </div>
          </div>
        </section>
      </div>
    </div>

    <!-- System Configuration -->
    <div class="card">
      <div class="flex items-center justify-between border-b border-slate-200/50 px-6 py-5 dark:border-slate-800/50">
        <h3 class="text-xl font-bold text-slate-900 dark:text-white uppercase tracking-tight">Configuration Plateforme</h3>
      </div>
      <div class="p-6">
        <div class="flex flex-col items-center justify-center py-12 text-center">
          <InformationCircleIcon class="h-8 w-8 text-slate-300 dark:text-slate-600 mb-3" />
          <p class="text-sm font-bold text-slate-500 dark:text-slate-400">Non disponible</p>
          <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Aucun endpoint backend ne fournit ces données pour le moment.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import {
  HeartIcon,
  InformationCircleIcon
} from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

// Components
import SystemStatusCard from '@/components/system/SystemStatusCard.vue'
import QueueObservabilityCard from '@/components/system/QueueObservabilityCard.vue'
import NotificationObservabilityCard from '@/components/system/NotificationObservabilityCard.vue'

const toast = useToast()

// Reactive state
const isRunningHealthCheck = ref(false)

// PA2-QA-006 — GET /platform/observability/queues
const queueObservability = ref(null)
const isLoadingObservability = ref(false)

// PA2-ADM-005 — GET /platform/observability/notifications
const notificationObservability = ref(null)
const isLoadingNotificationObservability = ref(false)

// Issue #2481 : toutes les refs du template DOIVENT être déclarées — le
// gate no-undef réactivé empêche désormais une ref manquante de passer en
// silencieux (ReferenceError au runtime, cf. #2297/#2316).

// Stats système (GET /admin/dashboard/stats) — seule la date de dernière
// collecte est exposée sur la carte « Statut Global ».
const stats = ref(null)
const lastUpdated = ref(null)

// Health check réel (GET /health/live + /health/ready) — #2186.
const healthCheck = ref(null)
const healthCheckTimestamp = ref(null)
const globalHealthStatus = ref('unavailable')
const databaseDetails = ref('En attente du prochain health check…')

// System status
const systemStatus = reactive({
  overall: 'unknown',
  overallDetails: "Statut inconnu tant que le health check n'a pas tourné.",
  database: 'unknown',
  databaseDetails: 'En attente du prochain health check…',
  api: 'unknown',
  apiDetails: "Statut inconnu tant que le health check n'a pas tourné.",
  websocket: 'unknown',
  websocketDetails: 'Non mesuré — aucun endpoint dédié.',
  maintenanceMode: false
})

const globalHealthDetails = computed(() => {
  const map = {
    healthy: 'Sonde agrégée DB + Redis opérationnelle.',
    warning: 'Sonde agrégée : dégradation détectée.',
    error: 'Sonde agrégée : base de données injoignable.'
  }
  return map[globalHealthStatus.value] || 'Non disponible — lancer le Health Check'
})

// Base de données : résultat réel du Health Check (GET /health/ready)
const databaseStatus = computed(() => {
  if (!healthCheck.value) return 'unavailable'
  return healthCheck.value.checks?.database?.ok ? 'healthy' : 'error'
})

onMounted(async () => {
  await Promise.all([
    loadSystemStats(),
    loadQueueObservability(),
    loadNotificationObservability()
  ])
})

// ── Méthodes ──

async function loadSystemStats() {
  try {
    const response = await api.get('/admin/dashboard/stats')
    stats.value = response.data
    lastUpdated.value = new Date()
  } catch (error) {
    // Endpoint indisponible : on ne casse pas le rendu, la carte reste en
    // état « last check : — » (honnête).
    console.error('Failed to load system stats:', error)
  }
}

// PA2-QA-006 — Redis/jobs observability: queue depth, failed jobs and last
// run of scheduled tasks, backed by GET /platform/observability/queues.
async function loadQueueObservability() {
  isLoadingObservability.value = true

  try {
    const response = await api.get('/platform/observability/queues')
    queueObservability.value = response.data?.data || null
  } catch (error) {
    console.error('Failed to load queue observability:', error)
    toast.error("Erreur lors du chargement de l'observabilité des jobs")
  } finally {
    isLoadingObservability.value = false
  }
}

// PA2-ADM-005 — Cross-tenant notification failure rate (24h) + curated
// runbook links, backed by GET /platform/observability/notifications.
async function loadNotificationObservability() {
  isLoadingNotificationObservability.value = true

  try {
    const response = await api.get('/platform/observability/notifications')
    notificationObservability.value = response.data?.data || null
  } catch (error) {
    console.error('Failed to load notification observability:', error)
    toast.error("Erreur lors du chargement de l'observabilité des notifications")
  } finally {
    isLoadingNotificationObservability.value = false
  }
}

async function runHealthCheck() {
  isRunningHealthCheck.value = true

  try {
    // Health check reel (endpoints publics /health/live + /health/ready)
    const [live, ready] = await Promise.all([
      api.get('/health/live').catch(() => null),
      api.get('/health/ready').catch(() => null)
    ])

    const liveOk = live !== null && live.status === 200
    const readyOk = ready !== null && ready.status === 200

    healthCheck.value = {
      status: liveOk && readyOk ? 'ok' : 'fail',
      checks: { database: { ok: readyOk } }
    }
    healthCheckTimestamp.value = new Date()
    globalHealthStatus.value = liveOk && readyOk ? 'healthy' : 'warning'

    systemStatus.overall = liveOk && readyOk ? 'healthy' : 'degraded'
    systemStatus.api = liveOk ? 'healthy' : 'unreachable'
    systemStatus.database = readyOk ? 'healthy' : 'degraded'
    systemStatus.websocket = 'unknown'
    systemStatus.overallDetails = liveOk && readyOk
      ? 'Liveness + readiness OK'
      : `Liveness ${liveOk ? 'OK' : 'KO'} / Readiness ${readyOk ? 'OK' : 'KO'}`

    if (liveOk && readyOk) {
      toast.success('Health check terminé — tous les services sont opérationnels')
    } else {
      toast.error('Health check : liveness/readiness en échec — voir détails ci-dessus')
    }
  } catch (error) {
    healthCheck.value = error.response?.data || {
      status: 'fail',
      checks: { database: { ok: false } }
    }
    healthCheckTimestamp.value = new Date()
    globalHealthStatus.value = 'error'
    console.error('Health check failed:', error)
    toast.error('Health check terminé — base de données injoignable')
  } finally {
    isRunningHealthCheck.value = false
  }
}
</script>
