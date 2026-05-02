<template>
  <header class="bg-white shadow-sm border-b border-gray-200">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex h-16 items-center justify-between">
        <!-- Mobile menu button -->
        <button
          @click="$emit('toggle-sidebar')"
          class="rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-500 lg:hidden"
        >
          <Bars3Icon class="h-6 w-6" />
        </button>

        <!-- Search -->
        <div class="flex flex-1 items-center justify-center px-2 lg:ml-6 lg:justify-start">
          <div class="w-full max-w-lg lg:max-w-xs">
            <label for="search" class="sr-only">Rechercher</label>
            <div class="relative">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" />
              </div>
              <input
                id="search"
                v-model="searchQuery"
                name="search"
                class="block w-full rounded-md border-0 bg-gray-50 py-1.5 pl-10 pr-3 text-gray-900 placeholder:text-gray-400 focus:bg-white focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
                placeholder="Rechercher..."
                type="search"
                @keyup.enter="handleSearch"
              />
            </div>
          </div>
        </div>

        <!-- Right side -->
        <div class="flex items-center space-x-4">
          <!-- Real-time connection status -->
          <div class="flex items-center">
            <div 
              :class="[
                'h-2 w-2 rounded-full mr-2',
                realtimeStore.isConnected ? 'bg-green-400' : 'bg-red-400'
              ]"
            ></div>
            <span class="text-xs text-gray-500 hidden sm:block">
              {{ realtimeStore.isConnected ? 'Connecté' : 'Déconnecté' }}
            </span>
          </div>

          <!-- Quick stats -->
          <div class="hidden md:flex items-center space-x-6 text-sm text-gray-500">
            <div class="flex items-center">
              <UsersIcon class="h-4 w-4 mr-1" />
              <span>{{ dashboardStore.stats.totalUsers }}</span>
            </div>
            <div class="flex items-center">
              <BuildingOfficeIcon class="h-4 w-4 mr-1" />
              <span>{{ dashboardStore.stats.totalCompanies }}</span>
            </div>
            <div class="flex items-center">
              <CurrencyEuroIcon class="h-4 w-4 mr-1" />
              <span>{{ dashboardStore.formattedRevenue }}</span>
            </div>
          </div>

          <!-- Notifications -->
          <div class="relative">
            <button
              @click="showNotifications = !showNotifications"
              class="relative rounded-full bg-white p-1 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
              <BellIcon class="h-6 w-6" />
              <span
                v-if="realtimeStore.unreadNotifications > 0"
                class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs font-medium text-white"
              >
                {{ realtimeStore.unreadNotifications > 9 ? '9+' : realtimeStore.unreadNotifications }}
              </span>
            </button>

            <!-- Notifications dropdown -->
            <div
              v-if="showNotifications"
              class="absolute right-0 z-10 mt-2 w-80 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
              @click.stop
            >
              <div class="px-4 py-2 border-b border-gray-200">
                <div class="flex items-center justify-between">
                  <h3 class="text-sm font-medium text-gray-900">Notifications</h3>
                  <button
                    @click="realtimeStore.markAllNotificationsAsRead()"
                    class="text-xs text-indigo-600 hover:text-indigo-500"
                  >
                    Tout marquer comme lu
                  </button>
                </div>
              </div>
              
              <div class="max-h-96 overflow-y-auto">
                <div
                  v-for="notification in realtimeStore.recentNotifications"
                  :key="notification.id"
                  :class="[
                    'px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100',
                    !notification.read ? 'bg-blue-50' : ''
                  ]"
                  @click="markAsRead(notification.id)"
                >
                  <div class="flex items-start">
                    <div class="flex-shrink-0">
                      <div 
                        :class="[
                          'h-2 w-2 rounded-full mt-2',
                          getNotificationColor(notification.type)
                        ]"
                      ></div>
                    </div>
                    <div class="ml-3 flex-1">
                      <p class="text-sm font-medium text-gray-900">
                        {{ notification.title }}
                      </p>
                      <p class="text-sm text-gray-500">
                        {{ notification.message }}
                      </p>
                      <p class="text-xs text-gray-400 mt-1">
                        {{ formatTime(notification.timestamp) }}
                      </p>
                    </div>
                  </div>
                </div>
                
                <div v-if="realtimeStore.recentNotifications.length === 0" class="px-4 py-6 text-center">
                  <p class="text-sm text-gray-500">Aucune notification</p>
                </div>
              </div>
            </div>
          </div>

          <!-- System alerts indicator -->
          <button
            v-if="dashboardStore.criticalAlerts.length > 0"
            @click="showAlerts = !showAlerts"
            class="relative rounded-full bg-red-100 p-2 text-red-600 hover:bg-red-200"
          >
            <ExclamationTriangleIcon class="h-5 w-5" />
            <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-xs font-medium text-white">
              {{ dashboardStore.criticalAlerts.length }}
            </span>
          </button>

          <!-- Refresh button -->
          <button
            @click="refreshData"
            :disabled="isRefreshing"
            class="rounded-md p-2 text-gray-400 hover:text-gray-500 disabled:opacity-50"
          >
            <ArrowPathIcon 
              :class="[
                'h-5 w-5',
                isRefreshing ? 'animate-spin' : ''
              ]" 
            />
          </button>
        </div>
      </div>
    </div>

    <!-- Click outside to close notifications -->
    <div
      v-if="showNotifications"
      class="fixed inset-0 z-0"
      @click="showNotifications = false"
    ></div>
  </header>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import {
  Bars3Icon,
  MagnifyingGlassIcon,
  BellIcon,
  UsersIcon,
  BuildingOfficeIcon,
  CurrencyEuroIcon,
  ExclamationTriangleIcon,
  ArrowPathIcon
} from '@heroicons/vue/24/outline'
import { useDashboardStore } from '@/stores/dashboard'
import { useRealtimeStore } from '@/stores/realtime'

defineEmits(['toggle-sidebar'])

const dashboardStore = useDashboardStore()
const realtimeStore = useRealtimeStore()

const searchQuery = ref('')
const showNotifications = ref(false)
const showAlerts = ref(false)
const isRefreshing = ref(false)

// Auto-refresh interval
let refreshInterval = null

onMounted(() => {
  // Auto-refresh every 30 seconds
  refreshInterval = setInterval(() => {
    if (!isRefreshing.value) {
      refreshData()
    }
  }, 30000)
})

onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval)
  }
})

// Methods
function handleSearch() {
  if (searchQuery.value.trim()) {
    // Implement search functionality
    console.log('Searching for:', searchQuery.value)
  }
}

function markAsRead(notificationId) {
  realtimeStore.markNotificationAsRead(notificationId)
}

function getNotificationColor(type) {
  const colors = {
    user_registered: 'bg-green-400',
    subscription_created: 'bg-blue-400',
    subscription_cancelled: 'bg-red-400',
    support_ticket: 'bg-yellow-400',
    system_alert: 'bg-red-500'
  }
  return colors[type] || 'bg-gray-400'
}

function formatTime(timestamp) {
  const now = new Date()
  const time = new Date(timestamp)
  const diff = now - time
  
  if (diff < 60000) return 'À l\'instant'
  if (diff < 3600000) return `${Math.floor(diff / 60000)}m`
  if (diff < 86400000) return `${Math.floor(diff / 3600000)}h`
  return time.toLocaleDateString('fr-FR')
}

async function refreshData() {
  isRefreshing.value = true
  try {
    await dashboardStore.refreshStats()
  } catch (error) {
    console.error('Failed to refresh data:', error)
  } finally {
    isRefreshing.value = false
  }
}
</script>