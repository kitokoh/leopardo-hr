<template>
  <div class="bg-white overflow-hidden shadow rounded-lg">
    <div class="p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-medium text-gray-900">État du Système</h3>
        <div class="flex items-center">
          <div
            :class="[
              'h-3 w-3 rounded-full mr-2',
              healthStatus.color === 'green' ? 'bg-green-400' :
              healthStatus.color === 'yellow' ? 'bg-yellow-400' : 'bg-red-400'
            ]"
          ></div>
          <span :class="[
            'text-sm font-medium',
            healthStatus.color === 'green' ? 'text-green-600' :
            healthStatus.color === 'yellow' ? 'text-yellow-600' : 'text-red-600'
          ]">
            {{ healthStatus.label }}
          </span>
        </div>
      </div>

      <!-- System metrics -->
      <div class="space-y-4">
        <!-- API Response Time -->
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-600">Temps de réponse API</span>
          <div class="flex items-center">
            <div class="w-20 bg-gray-200 rounded-full h-2 mr-3">
              <div
                class="bg-green-400 h-2 rounded-full transition-all duration-300"
                :style="{ width: `${Math.min(apiResponseTime / 10, 100)}%` }"
              ></div>
            </div>
            <span class="text-sm font-medium text-gray-900">{{ apiResponseTime }}ms</span>
          </div>
        </div>

        <!-- Database Connections -->
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-600">Connexions DB</span>
          <div class="flex items-center">
            <div class="w-20 bg-gray-200 rounded-full h-2 mr-3">
              <div
                class="bg-blue-400 h-2 rounded-full transition-all duration-300"
                :style="{ width: `${(dbConnections / maxDbConnections) * 100}%` }"
              ></div>
            </div>
            <span class="text-sm font-medium text-gray-900">{{ dbConnections }}/{{ maxDbConnections }}</span>
          </div>
        </div>

        <!-- Memory Usage -->
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-600">Utilisation mémoire</span>
          <div class="flex items-center">
            <div class="w-20 bg-gray-200 rounded-full h-2 mr-3">
              <div
                :class="[
                  'h-2 rounded-full transition-all duration-300',
                  memoryUsage > 80 ? 'bg-red-400' :
                  memoryUsage > 60 ? 'bg-yellow-400' : 'bg-green-400'
                ]"
                :style="{ width: `${memoryUsage}%` }"
              ></div>
            </div>
            <span class="text-sm font-medium text-gray-900">{{ memoryUsage }}%</span>
          </div>
        </div>

        <!-- WebSocket Connections -->
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-600">WebSocket</span>
          <div class="flex items-center">
            <div
              :class="[
                'h-2 w-2 rounded-full mr-2',
                realtimeStore.isConnected ? 'bg-green-400' : 'bg-red-400'
              ]"
            ></div>
            <span class="text-sm font-medium text-gray-900">
              {{ realtimeStore.isConnected ? 'Connecté' : 'Déconnecté' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Critical Alerts -->
      <div v-if="criticalAlerts.length > 0" class="mt-6 pt-4 border-t border-gray-200">
        <h4 class="text-sm font-medium text-red-600 mb-2">Alertes Critiques</h4>
        <div class="space-y-2">
          <div
            v-for="alert in criticalAlerts.slice(0, 3)"
            :key="alert.id"
            class="flex items-start p-2 bg-red-50 rounded-md"
          >
            <ExclamationTriangleIcon class="h-4 w-4 text-red-400 mt-0.5 mr-2 flex-shrink-0" />
            <div class="flex-1">
              <p class="text-xs text-red-800">{{ alert.message }}</p>
              <p class="text-xs text-red-600 mt-1">{{ formatTime(alert.timestamp) }}</p>
            </div>
            <button
              @click="dismissAlert(alert.id)"
              class="ml-2 text-red-400 hover:text-red-600"
            >
              <XMarkIcon class="h-4 w-4" />
            </button>
          </div>
        </div>

        <div v-if="criticalAlerts.length > 3" class="mt-2">
          <router-link
            to="/system"
            class="text-xs text-red-600 hover:text-red-500 font-medium"
          >
            Voir toutes les alertes ({{ criticalAlerts.length }})
          </router-link>
        </div>
      </div>

      <!-- Last updated -->
      <div class="mt-6 pt-4 border-t border-gray-200">
        <div class="flex items-center justify-between text-xs text-gray-500">
          <span>Dernière mise à jour</span>
          <span>{{ formatTime(dashboardStore.lastUpdated) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { ExclamationTriangleIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { useDashboardStore } from '@/stores/dashboard'
import { useRealtimeStore } from '@/stores/realtime'

const dashboardStore = useDashboardStore()
const realtimeStore = useRealtimeStore()

// Mock system metrics (in real app, these would come from monitoring APIs)
const apiResponseTime = ref(45)
const dbConnections = ref(12)
const maxDbConnections = ref(100)
const memoryUsage = ref(67)

// Update metrics periodically
let metricsInterval = null

onMounted(() => {
  // Update metrics every 5 seconds
  metricsInterval = setInterval(updateMetrics, 5000)
})

onUnmounted(() => {
  if (metricsInterval) {
    clearInterval(metricsInterval)
  }
})

// Computed properties
const healthStatus = computed(() => dashboardStore.healthStatus)
const criticalAlerts = computed(() => dashboardStore.criticalAlerts)

// Methods
function updateMetrics() {
  // Simulate real-time metrics updates
  apiResponseTime.value = Math.floor(Math.random() * 100) + 20
  dbConnections.value = Math.floor(Math.random() * 20) + 5
  memoryUsage.value = Math.floor(Math.random() * 40) + 40
}

function dismissAlert(alertId) {
  dashboardStore.dismissAlert(alertId)
}

function formatTime(timestamp) {
  if (!timestamp) return 'Jamais'

  const now = new Date()
  const time = new Date(timestamp)
  const diff = now - time

  if (diff < 60000) return 'À l\'instant'
  if (diff < 3600000) return `${Math.floor(diff / 60000)}m`
  if (diff < 86400000) return `${Math.floor(diff / 3600000)}h`
  return time.toLocaleDateString('fr-FR')
}
</script>