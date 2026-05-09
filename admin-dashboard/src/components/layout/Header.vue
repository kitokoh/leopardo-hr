<template>
  <header class="glass sticky top-0 z-40 border-b border-zinc-200/60 transition-all duration-300">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex h-16 items-center justify-between gap-4">
        <!-- Mobile menu button -->
        <button
          @click="$emit('toggle-sidebar')"
          class="rounded-xl p-2 text-zinc-500 hover:bg-zinc-100 lg:hidden"
        >
          <Bars3Icon class="h-6 w-6" />
        </button>

        <!-- Global Search -->
        <div class="flex flex-1 items-center max-w-xl">
          <div class="w-full">
            <label for="search" class="sr-only">Rechercher</label>
            <div class="relative group">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                <MagnifyingGlassIcon class="h-4 w-4 text-zinc-400 group-focus-within:text-brand-500 transition-colors" />
              </div>
              <input
                id="search"
                v-model="searchQuery"
                name="search"
                class="block w-full rounded-xl border-zinc-200/60 bg-zinc-50/50 py-2 pl-10 pr-4 text-sm text-zinc-900 placeholder:text-zinc-400 focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all"
                placeholder="Rechercher partout (Comptes, Transactions, Logs...)"
                type="search"
                @keyup.enter="handleSearch"
              />
              <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                <kbd class="hidden sm:inline-flex h-5 items-center rounded border border-zinc-200 bg-white px-1.5 font-sans text-[10px] font-medium text-zinc-400">⌘K</kbd>
              </div>
            </div>
          </div>
        </div>

        <!-- Right side actions -->
        <div class="flex items-center gap-2 sm:gap-4">
          <!-- Live Telemetry (Desktop) -->
          <div class="hidden xl:flex items-center gap-6 px-4 py-1.5 rounded-xl bg-zinc-50 border border-zinc-100">
            <div class="flex items-center gap-2">
              <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-50">
                <UsersIcon class="h-3.5 w-3.5 text-emerald-600" />
              </div>
              <div class="flex flex-col">
                <span class="text-[10px] font-bold text-zinc-400 leading-none uppercase tracking-tighter">Utilisateurs</span>
                <span class="text-xs font-bold text-zinc-900">{{ dashboardStore.stats.totalUsers }}</span>
              </div>
            </div>
            <div class="h-6 w-px bg-zinc-200"></div>
            <div class="flex items-center gap-2">
              <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-brand-50">
                <CurrencyEuroIcon class="h-3.5 w-3.5 text-brand-600" />
              </div>
              <div class="flex flex-col">
                <span class="text-[10px] font-bold text-zinc-400 leading-none uppercase tracking-tighter">Revenu Mensuel</span>
                <span class="text-xs font-bold text-zinc-900">{{ dashboardStore.formattedRevenue }}</span>
              </div>
            </div>
          </div>

          <!-- Notification Bell -->
          <div class="relative">
            <button
              @click="showNotifications = !showNotifications"
              class="relative rounded-xl bg-white p-2 text-zinc-500 border border-zinc-200/60 hover:bg-zinc-50 hover:text-zinc-900 transition-all focus:ring-2 focus:ring-brand-500"
            >
              <BellIcon class="h-5 w-5" />
              <span
                v-if="realtimeStore.unreadNotifications > 0"
                class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white shadow-lg shadow-rose-200"
              >
                {{ realtimeStore.unreadNotifications > 9 ? '9+' : realtimeStore.unreadNotifications }}
              </span>
            </button>

            <!-- Notifications Dropdown -->
            <Transition
              enter-active-class="transition ease-out duration-200"
              enter-from-class="transform opacity-0 scale-95"
              enter-to-class="transform opacity-100 scale-100"
              leave-active-class="transition ease-in duration-75"
              leave-from-class="transform opacity-100 scale-100"
              leave-to-class="transform opacity-0 scale-95"
            >
              <div
                v-if="showNotifications"
                class="absolute right-0 z-50 mt-3 w-80 sm:w-96 origin-top-right rounded-2xl bg-white p-2 shadow-xl ring-1 ring-zinc-200"
                @click.stop
              >
                <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100">
                  <h3 class="text-sm font-bold text-zinc-900">Fil d'actualité</h3>
                  <button
                    @click="realtimeStore.markAllNotificationsAsRead()"
                    class="text-[11px] font-bold text-brand-600 hover:text-brand-700 uppercase tracking-wider"
                  >
                    Tout lire
                  </button>
                </div>

                <div class="max-h-[32rem] overflow-y-auto py-2">
                  <div
                    v-for="notification in realtimeStore.recentNotifications"
                    :key="notification.id"
                    :class="[
                      'group mx-2 rounded-xl px-4 py-3 hover:bg-zinc-50 cursor-pointer transition-colors',
                      !notification.read ? 'bg-brand-50/40' : ''
                    ]"
                    @click="markAsRead(notification.id)"
                  >
                    <div class="flex items-start gap-3">
                      <div class="mt-1">
                        <div :class="['h-2 w-2 rounded-full', getNotificationColor(notification.type)]"></div>
                      </div>
                      <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-zinc-900 line-clamp-1">{{ notification.title }}</p>
                        <p class="text-xs text-zinc-500 mt-0.5 line-clamp-2 leading-relaxed">{{ notification.message }}</p>
                        <div class="flex items-center gap-2 mt-2">
                          <span class="text-[10px] font-medium text-zinc-400">{{ formatTime(notification.timestamp) }}</span>
                          <span v-if="!notification.read" class="text-[10px] font-bold text-brand-600 uppercase">Nouveau</span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div v-if="realtimeStore.recentNotifications.length === 0" class="py-12 text-center">
                    <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-zinc-50 text-zinc-300 mb-3">
                      <BellIcon class="h-6 w-6" />
                    </div>
                    <p class="text-sm font-medium text-zinc-400">Aucune notification pour le moment</p>
                  </div>
                </div>
              </div>
            </Transition>
          </div>

          <!-- Refresh Control -->
          <button
            @click="refreshData"
            :disabled="isRefreshing"
            class="rounded-xl p-2 text-zinc-400 hover:bg-zinc-50 hover:text-brand-600 transition-all border border-transparent hover:border-zinc-200/60"
            title="Rafraîchir les données"
          >
            <ArrowPathIcon
              :class="[
                'h-5 w-5',
                isRefreshing ? 'animate-spin text-brand-600' : ''
              ]"
            />
          </button>
        </div>
      </div>
    </div>

    <!-- Click outside overlay -->
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
  CurrencyEuroIcon,
  ArrowPathIcon
} from '@heroicons/vue/24/outline'
import { useDashboardStore } from '@/stores/dashboard'
import { useRealtimeStore } from '@/stores/realtime'

defineEmits(['toggle-sidebar'])

const dashboardStore = useDashboardStore()
const realtimeStore = useRealtimeStore()

const searchQuery = ref('')
const showNotifications = ref(false)
const isRefreshing = ref(false)

let refreshInterval = null

onMounted(() => {
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

function handleSearch() {
  if (searchQuery.value.trim()) {
    console.log('Global search for:', searchQuery.value)
  }
}

function markAsRead(notificationId) {
  realtimeStore.markNotificationAsRead(notificationId)
}

function getNotificationColor(type) {
  const colors = {
    user_registered: 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]',
    subscription_created: 'bg-brand-500 shadow-[0_0_8px_rgba(139,92,246,0.5)]',
    subscription_cancelled: 'bg-rose-500',
    support_ticket: 'bg-amber-500',
    system_alert: 'bg-rose-600 animate-pulse'
  }
  return colors[type] || 'bg-zinc-400'
}

function formatTime(timestamp) {
  const now = new Date()
  const time = new Date(timestamp)
  const diff = now - time

  if (diff < 60000) return 'À l\'instant'
  if (diff < 3600000) return `Il y a ${Math.floor(diff / 60000)}m`
  if (diff < 86400000) return `Il y a ${Math.floor(diff / 3600000)}h`
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
