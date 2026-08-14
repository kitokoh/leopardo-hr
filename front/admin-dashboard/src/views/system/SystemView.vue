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
import { ref, computed, onMounted } from 'vue'
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

// System status
const systemStatus = reactive({
  overall: 'healthy',
  overallDetails: 'Tous les services fonctionnent normalement',
  database: 'healthy',
  databaseDetails: 'Connexions: 45/100 • Latence: 12ms',
  api: 'healthy',
  apiDetails: 'Temps de réponse moyen: 89ms',
  websocket: 'healthy',
  websocketDetails: '1,247 connexions actives',
  maintenanceMode: false
})

const globalHealthDetails = computed(() => {
  const map = {
    healthy: 'Sonde agrégée DB + Redis opérationnelle.',
    warning: 'Sonde agrégée : dégradation détectée.',
    error: 'Sonde agrégée : base de données injoignable.'
  }
  return map[globalHealthStatus.value] || 'Non disponible — GET /admin/dashboard/stats'
})

// Base de données : résultat réel du Health Check (GET /health/ready)
const databaseStatus = computed(() => {
  if (!healthCheck.value) return 'unavailable'
  return healthCheck.value.checks?.database?.ok ? 'healthy' : 'error'
})

// Automated tasks
const automatedTasks = ref([])
const backups = ref([])
const securityAlerts = ref([])
const apiTests = ref([])

// Security status
const securityStatus = reactive({
  level: 'high',
  label: 'SÉCURISÉ',
  score: 95
})

onMounted(async () => {
  await Promise.all([
    loadSystemStats(),
    loadQueueObservability(),
    loadNotificationObservability()
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
    toast.error('Erreur lors du chargement des stats système')
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
    toast.error('Erreur lors du chargement de l\'observabilité des jobs')
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
    toast.error('Erreur lors du chargement de l\'observabilité des notifications')
  } finally {
    isLoadingNotificationObservability.value = false
  }
}

async function loadAutomatedTasks() {
  // Pas d'API de taches planifiees — liste vide honnete (vague QA 2026-08-14)
  automatedTasks.value = []
}

async function loadBackups() {
  // Pas d'API de sauvegardes — liste vide honnete (vague QA 2026-08-14)
  backups.value = []
}

async function loadSecurityAlerts() {
  // Pas d'API dediee — liste vide honnete (vague QA 2026-08-14)
  securityAlerts.value = []
}

async function loadApiTests() {
  // Pas d'API de tests — liste vide honnete (vague QA 2026-08-14)
  apiTests.value = []
}

async function loadSystemConfig() {
  // Pas d'API de configuration systeme — etat vide honnete (vague QA 2026-08-14)
  systemConfig.value = null
}

async function loadLoadBalancerNodes() {
  // Pas d'API de load balancer — liste vide honnete (vague QA 2026-08-14)
  loadBalancerNodes.value = []
}

function startMetricsRefresh() {
  // Update metrics every 5 seconds
  metricsInterval = setInterval(updatePerformanceMetrics, 5000)
}

function updatePerformanceMetrics() {
  const now = new Date()

  // Generate realistic metrics
  const cpu = Math.random() * 30 + 40 // 40-70%
  const memory = Math.random() * 20 + 60 // 60-80%
  const network = Math.random() * 40 + 10 // 10-50%

  // Update performance metrics
  performanceMetrics.value.cpu.push(cpu)
  performanceMetrics.value.memory.push(memory)
  performanceMetrics.value.network.push(network)
  performanceMetrics.value.timestamps.push(now)

  // Keep only last 20 points
  if (performanceMetrics.value.cpu.length > 20) {
    performanceMetrics.value.cpu.shift()
    performanceMetrics.value.memory.shift()
    performanceMetrics.value.network.shift()
    performanceMetrics.value.timestamps.shift()
  }

  // Update resource usage
  resourceUsage.cpu = Math.round(cpu)
  resourceUsage.memory = Math.round(memory)
  resourceUsage.network = Math.round(network)
  resourceUsage.disk = Math.round(Math.random() * 20 + 30) // 30-50%
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
    console.error('Health check failed:', error)
    toast.error('Health check terminé — base de données injoignable')
  } finally {
    isRunningHealthCheck.value = false
  }
}

async function toggleMaintenanceMode() {
  // Pas d'endpoint backend de maintenance — etat honnete (vague QA 2026-08-14)
  toast.warning('Action non disponible : aucun endpoint backend de maintenance n\'est exposé')
}

function refreshMetrics() {
  updatePerformanceMetrics()
  toast.success('Métriques actualisées')
}

// Task management
function toggleTask(taskId) {
  const task = automatedTasks.value.find(t => t.id === taskId)
  if (task) {
    task.enabled = !task.enabled
    toast.success(`Tâche ${task.enabled ? 'activée' : 'désactivée'}`)
  }
}

function editTask(task) {
  toast.info(`Édition de la tâche: ${task.name}`)
}

function deleteTask(taskId) {
  automatedTasks.value = automatedTasks.value.filter(t => t.id !== taskId)
  toast.success('Tâche supprimée')
}

function handleTaskCreated(task) {
  automatedTasks.value.push(task)
  showCreateTaskModal.value = false
  toast.success('Tâche créée avec succès')
}

// Backup management
async function createBackup() {
  isCreatingBackup.value = true

  try {
    await new Promise(resolve => setTimeout(resolve, 5000))

    const newBackup = {
      id: Date.now(),
      name: `backup-${new Date().toISOString().split('T')[0]}-${new Date().toTimeString().split(' ')[0].replace(/:/g, '-')}`,
      type: 'manual',
      size: '2.1 GB',
      createdAt: new Date(),
      status: 'completed'
    }

    backups.value.unshift(newBackup)
    toast.success('Sauvegarde créée avec succès')
  } catch (error) {
    console.error('Backup creation failed:', error)
    toast.error('Erreur lors de la création de la sauvegarde')
  } finally {
    isCreatingBackup.value = false
  }
}

function restoreBackup(backup) {
  toast.warning(`Restauration de la sauvegarde: ${backup.name}`)
}

function deleteBackup(backupId) {
  backups.value = backups.value.filter(b => b.id !== backupId)
  toast.success('Sauvegarde supprimée')
}

function downloadBackup(backup) {
  toast.info(`Téléchargement de: ${backup.name}`)
}

// Security
function investigateAlert(alert) {
  toast.info(`Investigation de l'alerte: ${alert.message}`)
}

function dismissSecurityAlert(alertId) {
  securityAlerts.value = securityAlerts.value.filter(a => a.id !== alertId)
  toast.success('Alerte fermée')
}

// Configuration
function updateConfig(section, config) {
  systemConfig.value[section] = { ...systemConfig.value[section], ...config }
  toast.success('Configuration mise à jour')
}

function resetConfig(section) {
  toast.warning(`Configuration ${section} réinitialisée`)
}

function exportConfig() {
  const configBlob = new Blob([JSON.stringify(systemConfig.value, null, 2)], { type: 'application/json' })
  const url = URL.createObjectURL(configBlob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'system-config.json'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)

  toast.success('Configuration exportée')
}

function handleConfigImported(config) {
  systemConfig.value = config
  showImportModal.value = false
  toast.success('Configuration importée')
}

// API Testing
function runApiTest(test) {
  toast.info(`Exécution du test: ${test.name}`)
}

function editApiTest(test) {
  toast.info(`Édition du test: ${test.name}`)
}

function deleteApiTest(testId) {
  apiTests.value = apiTests.value.filter(t => t.id !== testId)
  toast.success('Test supprimé')
}

function handleApiTestCreated(test) {
  apiTests.value.push(test)
  showApiTesterModal.value = false
  toast.success('Test API créé')
}

// Scaling
function updateScalingConfig(config) {
  Object.assign(scalingConfig, config)
  toast.success('Configuration d\'auto-scaling mise à jour')
}

function manualScale(action) {
  if (action === 'up') {
    scalingMetrics.currentInstances++
    toast.success('Instance ajoutée manuellement')
  } else {
    scalingMetrics.currentInstances--
    toast.success('Instance supprimée manuellement')
  }
}

// Load Balancer
function toggleLoadBalancerNode(nodeId) {
  const node = loadBalancerNodes.value.find(n => n.id === nodeId)
  if (node) {
    node.status = node.status === 'healthy' ? 'unhealthy' : 'healthy'
    toast.success(`NÅ“ud ${node.name} ${node.status === 'healthy' ? 'activé' : 'désactivé'}`)
  }
}

function drainNode(nodeId) {
  const node = loadBalancerNodes.value.find(n => n.id === nodeId)
  if (node) {
    node.status = 'draining'
    toast.info(`Drainage du nÅ“ud ${node.name} en cours`)
  }
}
</script>
