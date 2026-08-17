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
            <h1 class="text-4xl font-black tracking-tight text-white uppercase">{{ $t('systemPage.title') }}</h1>
            <p class="mt-2 text-slate-400 font-medium text-lg">
              {{ $t('systemPage.subtitle') }}
            </p>
          </div>

          <div class="mt-6 sm:mt-0 flex flex-wrap gap-3">
            <button
              @click="runHealthCheck"
              :disabled="isRunningHealthCheck"
              class="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-black uppercase tracking-widest text-white bg-white/10 dark:bg-slate-900/10 hover:bg-white/20 transition-all border border-white/10 disabled:opacity-50"
            >
              <HeartIcon class="h-4 w-4 mr-2" :class="{ 'animate-pulse text-red-400': isRunningHealthCheck }" />
              {{ isRunningHealthCheck ? $t('systemPage.healthCheckRunning') : $t('systemPage.healthCheck') }}
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
        :title="$t('systemPage.globalStatus')"
        :status="globalHealthStatus"
        :details="globalHealthDetails"
        :last-check="lastUpdated"
        icon="ServerIcon"
      />
      <SystemStatusCard
        :title="$t('systemPage.database')"
        :status="databaseStatus"
        :details="databaseDetails"
        :last-check="healthCheckTimestamp"
        icon="CircleStackIcon"
      />
      <div>
        <SystemStatusCard
          :title="$t('systemPage.apiServices')"
          :status="apiStatus"
          :details="apiDetails"
          :last-check="apiCheckTimestamp"
          icon="CloudIcon"
        />
        <div v-if="apiProbeError" class="mt-2 rounded-md border border-amber-200 bg-amber-50 p-2 text-center text-xs text-amber-800" role="alert">
          {{ $t('systemPage.healthLiveUnreachable') }}
          <button class="ml-1 font-semibold text-indigo-600 hover:text-indigo-800" @click="retryApiLiveness">
            {{ $t('systemPage.retry') }}
          </button>
        </div>
      </div>
      <SystemStatusCard
        :title="$t('systemPage.infrastructure')"
        :status="infraStatus"
        :details="infraDetails"
        :last-check="infraCheckTimestamp"
        icon="WifiIcon"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { HeartIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'

// Components
import SystemStatusCard from '@/components/system/SystemStatusCard.vue'
import QueueObservabilityCard from '@/components/system/QueueObservabilityCard.vue'
import NotificationObservabilityCard from '@/components/system/NotificationObservabilityCard.vue'

const toast = useToast()
const localeStore = useLocaleStore()

function t(key, fallback = '') {
  return translate(localeStore.current, key, fallback)
}

// Substitue les placeholders `:clef` d'une chaîne localisée (parité
// contrôlée par shared/i18n/validators/validate.js).
function interpolate(template, values) {
  return Object.entries(values).reduce(
    (acc, [key, value]) => acc.replace(`:${key}`, () => String(value)),
    template
  )
}

// Reactive state
const isRunningHealthCheck = ref(false)

// PA2-QA-006 — GET /platform/observability/queues
const queueObservability = ref(null)
const isLoadingObservability = ref(false)

// PA2-ADM-005 — GET /platform/observability/notifications
const notificationObservability = ref(null)
const isLoadingNotificationObservability = ref(false)

// GET /admin/dashboard/stats — agrégats plateforme (dont systemHealth)
const stats = ref(null)
const lastUpdated = ref(null)

// GET /health/ready — sonde DB réelle, déclenchée par le bouton Health Check
const healthCheck = ref(null)
const healthCheckTimestamp = ref(null)

// Issue #2789 — GET /health/live : disponibilité des Services API
const apiLive = ref(null)
// #4333 : échec de sonde visible (carte Services API) avec retry explicite.
const apiProbeError = ref(false)
const apiCheckTimestamp = ref(null)

// Issue #2789 / #4328 — GET /platform/metrics/overview : agrégats plateforme
// (Infrastructure). L'endpoint réel est /platform/metrics/overview
// (api/routes/api.php:252) — /admin/metrics/overview n'existe pas (404).
const platformMetrics = ref(null)
const infraCheckTimestamp = ref(null)

// Statut global dérivé de stats.systemHealth (good | warning | error)
const globalHealthStatus = computed(() => {
  const map = {
    good: 'healthy',
    warning: 'warning',
    error: 'error'
  }
  return map[stats.value?.systemHealth] || 'unavailable'
})

const globalHealthDetails = computed(() => {
  const map = {
    healthy: t('systemPage.globalHealthy'),
    warning: t('systemPage.globalWarning'),
    error: t('systemPage.globalError')
  }
  return map[globalHealthStatus.value] || t('systemPage.globalUnavailable')
})

// Base de données : résultat réel du Health Check (GET /health/ready)
const databaseStatus = computed(() => {
  if (!healthCheck.value) return 'unavailable'
  return healthCheck.value.checks?.database?.ok ? 'healthy' : 'error'
})

const databaseDetails = computed(() => {
  const db = healthCheck.value?.checks?.database
  if (!db) return t('systemPage.dbUnavailable')
  return db.ok
    ? interpolate(t('systemPage.dbLatency'), { ms: db.latency_ms })
    : interpolate(t('systemPage.dbError'), { error: db.error || t('systemPage.dbUnreachable') })
})

onMounted(async () => {
  await Promise.all([
    loadSystemStats(),
    loadQueueObservability(),
    loadNotificationObservability(),
    loadApiLiveness(),
    loadPlatformMetrics()
  ])
})

// Méthodes
async function loadSystemStats() {
  try {
    const response = await api.get('/admin/dashboard/stats')
    stats.value = response.data
    lastUpdated.value = new Date()
  } catch (error) {
    console.error('Failed to load system stats:', error)
    // #4333 : l'intercepteur global toast déjà sur erreur HTTP — ne pas doubler.
    if (!error.response) toast.error(t('systemPage.statsLoadError'))
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
    if (!error.response) toast.error(t('systemPage.queueObsLoadError'))
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
    if (!error.response) toast.error(t('systemPage.notifObsLoadError'))
  } finally {
    isLoadingNotificationObservability.value = false
  }
}

// Issue #2789 — GET /health/live (sonde liveness API publique)
const apiStatus = computed(() => (apiLive.value?.status === 'ok' || apiLive.value?.status === 'pass') ? 'healthy' : 'unavailable')
const apiDetails = computed(() => {
  if (!apiLive.value) return t('systemPage.apiUnavailable')
  const db = apiLive.value?.checks?.database
  return apiLive.value.status === 'ok'
    ? (db?.latency_ms != null
        ? interpolate(t('systemPage.apiOperationalDb'), { ms: db.latency_ms })
        : t('systemPage.apiOperational'))
    : interpolate(t('systemPage.apiError'), { error: apiLive.value.error || t('systemPage.serviceUnreachable') })
})

// Issue #4328 — GET /platform/metrics/overview (agrégats plateforme)
const infraStatus = computed(() => (platformMetrics.value ? 'healthy' : 'unavailable'))
const infraDetails = computed(() => {
  if (!platformMetrics.value) return t('systemPage.infraUnavailable')
  const companies = platformMetrics.value.companies
  const system = platformMetrics.value.system || {}
  return interpolate(t('systemPage.infraDetails'), {
    active: companies?.active ?? '?',
    php: system.php_version ?? '?',
    queue: system.queue_driver ?? '?'
  })
})

async function loadApiLiveness() {
  apiProbeError.value = false
  try {
    const response = await api.get('/health/live')
    apiLive.value = response.data
    apiCheckTimestamp.value = new Date()
  } catch (error) {
    apiLive.value = null
    apiProbeError.value = true
    console.error('Failed to load API liveness:', error)
  }
}

function retryApiLiveness() {
  loadApiLiveness()
}

async function loadPlatformMetrics() {
  try {
    const response = await api.get('/platform/metrics/overview')
    platformMetrics.value = response.data?.data || null
    infraCheckTimestamp.value = new Date()
  } catch (error) {
    console.error('Failed to load platform metrics:', error)
    if (!error.response) toast.error(t('systemPage.metricsLoadError'))
  }
}

async function runHealthCheck() {
  isRunningHealthCheck.value = true

  try {
    const response = await api.get('/health/ready')
    healthCheck.value = response.data
    healthCheckTimestamp.value = new Date()

    if (healthCheck.value?.checks?.database?.ok) {
      toast.success(t('systemPage.healthOk'))
    } else {
      toast.error(t('systemPage.healthError'))
    }
  } catch (error) {
    healthCheck.value = error.response?.data || {
      status: 'fail',
      checks: { database: { ok: false } }
    }
    healthCheckTimestamp.value = new Date()
    console.error('Health check failed:', error)
    toast.error(t('systemPage.healthUnreachable'))
  } finally {
    isRunningHealthCheck.value = false
  }
}
</script>
