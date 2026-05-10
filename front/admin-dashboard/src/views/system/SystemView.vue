<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="bg-white shadow rounded-lg p-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Administration Système</h1>
          <p class="mt-1 text-sm text-gray-500">
            Monitoring, configuration et automatisation de la plateforme
          </p>
        </div>

        <div class="mt-4 sm:mt-0 flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
          <button
            @click="runHealthCheck"
            :disabled="isRunningHealthCheck"
            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
          >
            <HeartIcon class="h-4 w-4 mr-2" />
            {{ isRunningHealthCheck ? 'Vérification...' : 'Health Check' }}
          </button>

          <button
            @click="toggleMaintenanceMode"
            :class="[
              'inline-flex items-center px-3 py-2 border text-sm font-medium rounded-md',
              systemStatus.maintenanceMode
                ? 'border-red-300 text-red-700 bg-red-50 hover:bg-red-100'
                : 'border-yellow-300 text-yellow-700 bg-yellow-50 hover:bg-yellow-100'
            ]"
          >
            <WrenchScrewdriverIcon class="h-4 w-4 mr-2" />
            {{ systemStatus.maintenanceMode ? 'Désactiver maintenance' : 'Mode maintenance' }}
          </button>
        </div>
      </div>
    </div>

    <!-- System Status Overview -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
      <SystemStatusCard
        title="Statut Global"
        :status="systemStatus.overall"
        :details="systemStatus.overallDetails"
        icon="ServerIcon"
      />
      <SystemStatusCard
        title="Base de Données"
        :status="systemStatus.database"
        :details="systemStatus.databaseDetails"
        icon="CircleStackIcon"
      />
      <SystemStatusCard
        title="Services API"
        :status="systemStatus.api"
        :details="systemStatus.apiDetails"
        icon="CloudIcon"
      />
      <SystemStatusCard
        title="WebSocket"
        :status="systemStatus.websocket"
        :details="systemStatus.websocketDetails"
        icon="WifiIcon"
      />
    </div>

    <!-- Performance Metrics -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- Real-time Metrics -->
      <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">Métriques Temps Réel</h3>
          <button
            @click="refreshMetrics"
            class="p-2 text-gray-400 hover:text-gray-500"
          >
            <ArrowPathIcon class="h-4 w-4" />
          </button>
        </div>
        <RealTimeMetricsChart :data="performanceMetrics" />
      </div>

      <!-- Resource Usage -->
      <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Utilisation des Ressources</h3>
        <ResourceUsageWidget :data="resourceUsage" />
      </div>
    </div>

    <!-- Automation & Workflows -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <!-- Automated Tasks -->
      <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">Tâches Automatisées</h3>
          <button
            @click="showCreateTaskModal = true"
            class="text-sm text-indigo-600 hover:text-indigo-500 font-medium"
          >
            + Nouvelle tâche
          </button>
        </div>
        <AutomatedTasksList
          :tasks="automatedTasks"
          @toggle="toggleTask"
          @edit="editTask"
          @delete="deleteTask"
        />
      </div>

      <!-- Backup Management -->
      <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">Sauvegardes</h3>
          <button
            @click="createBackup"
            :disabled="isCreatingBackup"
            class="text-sm text-indigo-600 hover:text-indigo-500 font-medium disabled:opacity-50"
          >
            {{ isCreatingBackup ? 'Création...' : '+ Nouvelle sauvegarde' }}
          </button>
        </div>
        <BackupManagement
          :backups="backups"
          @restore="restoreBackup"
          @delete="deleteBackup"
          @download="downloadBackup"
        />
      </div>

      <!-- Security Monitoring -->
      <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">Sécurité</h3>
          <span
            :class="[
              'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
              securityStatus.level === 'high' ? 'bg-green-100 text-green-800' :
              securityStatus.level === 'medium' ? 'bg-yellow-100 text-yellow-800' :
              'bg-red-100 text-red-800'
            ]"
          >
            {{ securityStatus.label }}
          </span>
        </div>
        <SecurityMonitoring
          :alerts="securityAlerts"
          :status="securityStatus"
          @investigate="investigateAlert"
          @dismiss="dismissSecurityAlert"
        />
      </div>
    </div>

    <!-- System Configuration -->
    <div class="bg-white shadow rounded-lg p-6">
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-medium text-gray-900">Configuration Système</h3>
        <div class="flex items-center space-x-3">
          <button
            @click="exportConfig"
            class="text-sm text-gray-600 hover:text-gray-500"
          >
            Exporter config
          </button>
          <button
            @click="showImportModal = true"
            class="text-sm text-indigo-600 hover:text-indigo-500"
          >
            Importer config
          </button>
        </div>
      </div>
      <SystemConfiguration
        :config="systemConfig"
        @update="updateConfig"
        @reset="resetConfig"
      />
    </div>

    <!-- API Testing Tools -->
    <div class="bg-white shadow rounded-lg p-6">
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-medium text-gray-900">Outils de Test API</h3>
        <button
          @click="showApiTesterModal = true"
          class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
        >
          <BeakerIcon class="h-4 w-4 mr-2" />
          Nouveau test
        </button>
      </div>
      <ApiTestingTools
        :tests="apiTests"
        @run="runApiTest"
        @edit="editApiTest"
        @delete="deleteApiTest"
      />
    </div>

    <!-- Scaling & Load Balancing -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- Auto Scaling -->
      <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Auto-Scaling</h3>
        <AutoScalingManager
          :config="scalingConfig"
          :metrics="scalingMetrics"
          @update-config="updateScalingConfig"
          @manual-scale="manualScale"
        />
      </div>

      <!-- Load Balancer -->
      <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Load Balancer</h3>
        <LoadBalancerStatus
          :nodes="loadBalancerNodes"
          :traffic="trafficMetrics"
          @toggle-node="toggleLoadBalancerNode"
          @drain-node="drainNode"
        />
      </div>
    </div>

    <!-- Modals -->
    <CreateTaskModal
      v-if="showCreateTaskModal"
      @close="showCreateTaskModal = false"
      @created="handleTaskCreated"
    />

    <ImportConfigModal
      v-if="showImportModal"
      @close="showImportModal = false"
      @imported="handleConfigImported"
    />

    <ApiTesterModal
      v-if="showApiTesterModal"
      @close="showApiTesterModal = false"
      @created="handleApiTestCreated"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import {
  HeartIcon,
  WrenchScrewdriverIcon,
  ArrowPathIcon,
  ServerIcon,
  CircleStackIcon,
  CloudIcon,
  WifiIcon,
  BeakerIcon
} from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'

// Components
import SystemStatusCard from '@/components/system/SystemStatusCard.vue'
import RealTimeMetricsChart from '@/components/system/RealTimeMetricsChart.vue'
import ResourceUsageWidget from '@/components/system/ResourceUsageWidget.vue'
import AutomatedTasksList from '@/components/system/AutomatedTasksList.vue'
import BackupManagement from '@/components/system/BackupManagement.vue'
import SecurityMonitoring from '@/components/system/SecurityMonitoring.vue'
import SystemConfiguration from '@/components/system/SystemConfiguration.vue'
import ApiTestingTools from '@/components/system/ApiTestingTools.vue'
import AutoScalingManager from '@/components/system/AutoScalingManager.vue'
import LoadBalancerStatus from '@/components/system/LoadBalancerStatus.vue'
import CreateTaskModal from '@/components/system/CreateTaskModal.vue'
import ImportConfigModal from '@/components/system/ImportConfigModal.vue'
import ApiTesterModal from '@/components/system/ApiTesterModal.vue'

const toast = useToast()

// Reactive state
const isRunningHealthCheck = ref(false)
const isCreatingBackup = ref(false)
const showCreateTaskModal = ref(false)
const showImportModal = ref(false)
const showApiTesterModal = ref(false)

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

// Performance metrics
const performanceMetrics = ref({
  cpu: [],
  memory: [],
  network: [],
  timestamps: []
})

// Resource usage
const resourceUsage = reactive({
  cpu: 45,
  memory: 67,
  disk: 34,
  network: 23
})

// Automated tasks
const automatedTasks = ref([])
const backups = ref([])
const securityAlerts = ref([])
const apiTests = ref([])

// Security status
const securityStatus = reactive({
  level: 'high',
  label: 'Sécurisé',
  score: 95
})

// System configuration
const systemConfig = ref({})

// Scaling configuration
const scalingConfig = reactive({
  enabled: true,
  minInstances: 2,
  maxInstances: 10,
  targetCpuPercent: 70,
  scaleUpCooldown: 300,
  scaleDownCooldown: 600
})

const scalingMetrics = reactive({
  currentInstances: 3,
  averageCpu: 45,
  requestsPerSecond: 150
})

// Load balancer
const loadBalancerNodes = ref([])
const trafficMetrics = reactive({
  totalRequests: 15420,
  averageResponseTime: 89,
  errorRate: 0.02
})

// Auto-refresh interval
let metricsInterval = null

onMounted(async () => {
  await loadSystemData()
  startMetricsRefresh()
})

onUnmounted(() => {
  if (metricsInterval) {
    clearInterval(metricsInterval)
  }
})

// Methods
async function loadSystemData() {
  try {
    await Promise.all([
      loadAutomatedTasks(),
      loadBackups(),
      loadSecurityAlerts(),
      loadApiTests(),
      loadSystemConfig(),
      loadLoadBalancerNodes(),
      updatePerformanceMetrics()
    ])
  } catch (error) {
    console.error('Failed to load system data:', error)
    toast.error('Erreur lors du chargement des données système')
  }
}

async function loadAutomatedTasks() {
  // Mock automated tasks
  automatedTasks.value = [
    {
      id: 1,
      name: 'Sauvegarde quotidienne',
      description: 'Sauvegarde automatique de la base de données',
      schedule: '0 2 * * *',
      enabled: true,
      lastRun: new Date(Date.now() - 3600000),
      nextRun: new Date(Date.now() + 82800000),
      status: 'success'
    },
    {
      id: 2,
      name: 'Nettoyage des logs',
      description: 'Suppression des logs de plus de 30 jours',
      schedule: '0 3 * * 0',
      enabled: true,
      lastRun: new Date(Date.now() - 86400000 * 2),
      nextRun: new Date(Date.now() + 86400000 * 5),
      status: 'success'
    },
    {
      id: 3,
      name: 'Mise à jour des certificats',
      description: 'Renouvellement automatique des certificats SSL',
      schedule: '0 4 1 * *',
      enabled: true,
      lastRun: new Date(Date.now() - 86400000 * 15),
      nextRun: new Date(Date.now() + 86400000 * 15),
      status: 'pending'
    }
  ]
}

async function loadBackups() {
  // Mock backups
  backups.value = [
    {
      id: 1,
      name: 'backup-2026-05-02-02-00',
      type: 'full',
      size: '2.4 GB',
      createdAt: new Date(Date.now() - 3600000),
      status: 'completed'
    },
    {
      id: 2,
      name: 'backup-2026-05-01-02-00',
      type: 'full',
      size: '2.3 GB',
      createdAt: new Date(Date.now() - 86400000),
      status: 'completed'
    },
    {
      id: 3,
      name: 'backup-2026-04-30-02-00',
      type: 'incremental',
      size: '450 MB',
      createdAt: new Date(Date.now() - 86400000 * 2),
      status: 'completed'
    }
  ]
}

async function loadSecurityAlerts() {
  // Mock security alerts
  securityAlerts.value = [
    {
      id: 1,
      type: 'suspicious_login',
      severity: 'medium',
      message: 'Tentative de connexion depuis une IP inhabituelle',
      details: 'IP: 192.168.1.100 • Utilisateur: admin@example.com',
      timestamp: new Date(Date.now() - 1800000),
      status: 'open'
    },
    {
      id: 2,
      type: 'rate_limit_exceeded',
      severity: 'low',
      message: 'Limite de taux dépassée pour l\'API',
      details: 'Endpoint: /api/users • IP: 10.0.0.50',
      timestamp: new Date(Date.now() - 3600000),
      status: 'investigating'
    }
  ]
}

async function loadApiTests() {
  // Mock API tests
  apiTests.value = [
    {
      id: 1,
      name: 'Test authentification',
      method: 'POST',
      endpoint: '/api/auth/login',
      lastRun: new Date(Date.now() - 1800000),
      status: 'passed',
      responseTime: 145
    },
    {
      id: 2,
      name: 'Test liste utilisateurs',
      method: 'GET',
      endpoint: '/api/users',
      lastRun: new Date(Date.now() - 900000),
      status: 'passed',
      responseTime: 89
    },
    {
      id: 3,
      name: 'Test création entreprise',
      method: 'POST',
      endpoint: '/api/companies',
      lastRun: new Date(Date.now() - 2700000),
      status: 'failed',
      responseTime: 0,
      error: 'Timeout after 5000ms'
    }
  ]
}

async function loadSystemConfig() {
  // Mock system configuration
  systemConfig.value = {
    general: {
      siteName: 'Leopardo RH',
      timezone: 'Europe/Paris',
      language: 'fr',
      maintenanceMode: false
    },
    security: {
      sessionTimeout: 3600,
      maxLoginAttempts: 5,
      passwordMinLength: 8,
      twoFactorRequired: false
    },
    performance: {
      cacheEnabled: true,
      cacheTtl: 300,
      compressionEnabled: true,
      rateLimitEnabled: true
    },
    notifications: {
      emailEnabled: true,
      smsEnabled: false,
      pushEnabled: true,
      webhookEnabled: true
    }
  }
}

async function loadLoadBalancerNodes() {
  // Mock load balancer nodes
  loadBalancerNodes.value = [
    {
      id: 1,
      name: 'api-node-1',
      ip: '10.0.1.10',
      status: 'healthy',
      connections: 145,
      cpu: 45,
      memory: 67,
      responseTime: 89
    },
    {
      id: 2,
      name: 'api-node-2',
      ip: '10.0.1.11',
      status: 'healthy',
      connections: 132,
      cpu: 52,
      memory: 71,
      responseTime: 92
    },
    {
      id: 3,
      name: 'api-node-3',
      ip: '10.0.1.12',
      status: 'draining',
      connections: 23,
      cpu: 15,
      memory: 34,
      responseTime: 78
    }
  ]
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
    // Simulate health check
    await new Promise(resolve => setTimeout(resolve, 3000))

    // Update system status
    systemStatus.overall = 'healthy'
    systemStatus.database = 'healthy'
    systemStatus.api = 'healthy'
    systemStatus.websocket = 'healthy'

    toast.success('Health check terminé • Tous les services sont opérationnels')
  } catch (error) {
    console.error('Health check failed:', error)
    toast.error('Erreur lors du health check')
  } finally {
    isRunningHealthCheck.value = false
  }
}

async function toggleMaintenanceMode() {
  try {
    systemStatus.maintenanceMode = !systemStatus.maintenanceMode

    if (systemStatus.maintenanceMode) {
      toast.warning('Mode maintenance activé')
    } else {
      toast.success('Mode maintenance désactivé')
    }
  } catch (error) {
    console.error('Failed to toggle maintenance mode:', error)
    toast.error('Erreur lors du changement de mode')
  }
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
    toast.success(`Nœud ${node.name} ${node.status === 'healthy' ? 'activé' : 'désactivé'}`)
  }
}

function drainNode(nodeId) {
  const node = loadBalancerNodes.value.find(n => n.id === nodeId)
  if (node) {
    node.status = 'draining'
    toast.info(`Drainage du nœud ${node.name} en cours`)
  }
}
</script>