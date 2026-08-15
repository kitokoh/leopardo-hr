<template>
  <!-- Critical alerts overlay -->
  <div
    v-if="showCriticalAlert"
    class="fixed inset-x-0 top-0 z-50"
  >
    <div class="bg-red-600">
      <div class="mx-auto max-w-7xl py-3 px-3 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between">
          <div class="flex w-0 flex-1 items-center">
            <span class="flex rounded-lg bg-red-800 p-2">
              <ExclamationTriangleIcon class="h-6 w-6 text-white" />
            </span>
            <p class="ml-3 truncate font-medium text-white">
              <span class="md:hidden">Alerte critique système</span>
              <span class="hidden md:inline">
                {{ currentCriticalAlert?.message || 'Alerte critique système détectée' }}
              </span>
            </p>
          </div>
          <div class="order-3 mt-2 w-full flex-shrink-0 sm:order-2 sm:mt-0 sm:w-auto">
            <button
              @click="viewSystemAlerts"
              class="flex items-center justify-center rounded-md border border-transparent bg-white px-4 py-2 text-sm font-medium text-red-600 shadow-sm hover:bg-red-50"
            >
              Voir les détails
            </button>
          </div>
          <div class="order-2 flex-shrink-0 sm:order-3 sm:ml-3">
            <button
              @click="dismissCriticalAlert"
              class="-mr-1 flex rounded-md p-2 hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-white sm:-mr-2"
            >
              <XMarkIcon class="h-6 w-6 text-white" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- System maintenance banner -->
  <div
    v-if="isMaintenanceMode"
    class="fixed inset-x-0 top-0 z-40"
    :class="{ 'mt-16': showCriticalAlert }"
  >
    <div class="bg-yellow-400">
      <div class="mx-auto max-w-7xl py-2 px-3 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between">
          <div class="flex w-0 flex-1 items-center">
            <span class="flex rounded-lg bg-yellow-600 p-2">
              <WrenchScrewdriverIcon class="h-5 w-5 text-white" />
            </span>
            <p class="ml-3 font-medium text-yellow-900">
              <span class="md:hidden">Mode maintenance</span>
              <span class="hidden md:inline">
                Le système est en mode maintenance. Certaines fonctionnalités peuvent être indisponibles.
              </span>
            </p>
          </div>
          <div class="order-2 flex-shrink-0 sm:ml-3">
            <button
              @click="disableMaintenanceMode"
              class="flex items-center justify-center rounded-md border border-transparent bg-yellow-600 px-4 py-1 text-sm font-medium text-white shadow-sm hover:bg-yellow-700"
            >
              Désactiver
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Connection status banner -->
  <div
    v-if="!realtimeStore.isConnected"
    class="fixed inset-x-0 top-0 z-30"
    :class="{
      'mt-16': showCriticalAlert && !isMaintenanceMode,
      'mt-12': !showCriticalAlert && isMaintenanceMode,
      'mt-28': showCriticalAlert && isMaintenanceMode
    }"
  >
    <div class="bg-gray-600">
      <div class="mx-auto max-w-7xl py-2 px-3 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between">
          <div class="flex w-0 flex-1 items-center">
            <span class="flex rounded-lg bg-gray-800 p-2">
              <WifiIcon class="h-5 w-5 text-white" />
            </span>
            <p class="ml-3 font-medium text-white">
              <span class="md:hidden">{{ realtimeStore.isPolling ? 'Mode secours' : 'Connexion perdue' }}</span>
              <span class="hidden md:inline">
                <template v-if="realtimeStore.isPolling">
                  Connexion temps réel indisponible. Les notifications continuent d'arriver via actualisation périodique.
                </template>
                <template v-else>
                  Connexion temps réel perdue. Tentative de reconnexion...
                </template>
              </span>
            </p>
          </div>
          <div class="order-2 flex-shrink-0 sm:ml-3">
            <button
              @click="realtimeStore.connect()"
              class="flex items-center justify-center rounded-md border border-transparent bg-white px-4 py-1 text-sm font-medium text-gray-600 shadow-sm hover:bg-gray-50"
            >
              Reconnecter
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  ExclamationTriangleIcon,
  XMarkIcon,
  WrenchScrewdriverIcon,
  WifiIcon
} from '@heroicons/vue/24/outline'
import { useDashboardStore } from '@/stores/dashboard'
import { useRealtimeStore } from '@/stores/realtime'
import { useToast } from 'vue-toastification'

const router = useRouter()
const dashboardStore = useDashboardStore()
const realtimeStore = useRealtimeStore()
const toast = useToast()

const showCriticalAlert = ref(false)
const isMaintenanceMode = ref(false)
const currentCriticalAlert = ref(null)

// Auto-check for critical alerts
let alertCheckInterval = null
let stopNotificationWatch = null

onMounted(() => {
  checkForCriticalAlerts()

  // Check for alerts every 30 seconds
  alertCheckInterval = setInterval(checkForCriticalAlerts, 30000)

  // Issue #2716 — $subscribe(mutation.events) ne se déclenche jamais pour les
  // mutations directes Pinia (events === null) : on écoute l'état du store.
  stopNotificationWatch = watch(
    () => realtimeStore.notifications[0],
    (newNotification, oldNotification) => {
      if (!newNotification) return
      if (oldNotification && oldNotification.id === newNotification.id) return
      if (newNotification.priority === 'critical' && newNotification.type === 'system_alert') {
        showCriticalAlertBanner(newNotification)
      }
    }
  )
})

onUnmounted(() => {
  if (alertCheckInterval) {
    clearInterval(alertCheckInterval)
  }
  if (stopNotificationWatch) {
    stopNotificationWatch()
  }
})

// Computed
const criticalAlerts = computed(() => dashboardStore.criticalAlerts)

// Methods
function checkForCriticalAlerts() {
  const alerts = criticalAlerts.value
  if (alerts.length > 0 && !showCriticalAlert.value) {
    showCriticalAlertBanner(alerts[0])
  }
}

function showCriticalAlertBanner(alert) {
  currentCriticalAlert.value = alert
  showCriticalAlert.value = true

  // Auto-dismiss after 30 seconds if not critical
  if (alert.level !== 'critical') {
    setTimeout(() => {
      if (showCriticalAlert.value && currentCriticalAlert.value?.id === alert.id) {
        dismissCriticalAlert()
      }
    }, 30000)
  }
}

function dismissCriticalAlert() {
  showCriticalAlert.value = false
  currentCriticalAlert.value = null
}

function viewSystemAlerts() {
  router.push('/system')
  dismissCriticalAlert()
}

async function disableMaintenanceMode() {
  // Issue #2693 (QA 2026-08-15) — aucun endpoint admin de maintenance
  // n'existe : l'action était simulée (setTimeout + toast de succès).
  // Désormais, état honnête : on ne peut pas désactiver le mode depuis l'UI.
  toast.info('Mode maintenance : désactivation non disponible depuis la console (aucun endpoint admin).')
}

// Expose methods for external control
defineExpose({
  showCriticalAlertBanner,
  dismissCriticalAlert,
  setMaintenanceMode: (enabled) => {
    isMaintenanceMode.value = enabled
  }
})
</script>