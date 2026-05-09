<template>
  <!-- Mobile sidebar overlay -->
  <Transition
    enter-active-class="transition-opacity ease-linear duration-300"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition-opacity ease-linear duration-300"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="isOpen"
      class="fixed inset-0 z-40 lg:hidden"
      @click="$emit('close')"
    >
      <div class="fixed inset-0 bg-zinc-900/40 backdrop-blur-sm"></div>
    </div>
  </Transition>

  <!-- Sidebar -->
  <div
    :class="[
      'fixed inset-y-0 left-0 z-50 w-72 transform bg-white border-r border-zinc-200 transition-transform duration-300 ease-in-out lg:static lg:translate-x-0',
      isOpen ? 'translate-x-0' : '-translate-x-full'
    ]"
  >
    <!-- Logo -->
    <div class="flex h-20 items-center px-6">
      <div class="flex items-center gap-3">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl brand-gradient shadow-lg shadow-brand-200">
          <span class="text-lg font-bold text-white tracking-tighter">L</span>
        </div>
        <div class="flex flex-col">
          <span class="text-sm font-bold text-zinc-900 leading-none">Leopardo RH</span>
          <span class="text-[10px] font-medium text-brand-600 uppercase tracking-widest mt-1">Console Admin</span>
        </div>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="mt-4 px-4 h-[calc(100%-160px)] overflow-y-auto">
      <div class="space-y-1">
        <router-link
          v-for="item in navigation"
          :key="item.name"
          :to="item.path"
          :class="[
            'sidebar-nav-item group',
            $route.name === item.name ? 'active' : ''
          ]"
          @click="$emit('close')"
        >
          <component
            :is="item.icon"
            :class="[
              'mr-3 h-5 w-5 flex-shrink-0 transition-colors',
              $route.name === item.name
                ? 'text-brand-600'
                : 'text-zinc-400 group-hover:text-zinc-600'
            ]"
          />
          <span class="font-medium">{{ item.title }}</span>

          <!-- Badge for notifications -->
          <span
            v-if="item.badge && item.badge > 0"
            class="ml-auto inline-flex items-center rounded-full bg-brand-100 px-2 py-0.5 text-[10px] font-bold text-brand-700"
          >
            {{ item.badge > 99 ? '99+' : item.badge }}
          </span>
        </router-link>
      </div>

      <!-- System Status Section -->
      <div class="mt-10 mb-4 px-2">
        <h3 class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-400">
          Surveillance Système
        </h3>
        <div class="mt-4 space-y-4 rounded-2xl bg-zinc-50 p-4 border border-zinc-100">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-zinc-500">Infrastructure</span>
            <div class="flex items-center gap-1.5">
              <div
                :class="[
                  'h-1.5 w-1.5 rounded-full animate-pulse',
                  healthStatus.color === 'green' ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]' :
                  healthStatus.color === 'yellow' ? 'bg-amber-500' : 'bg-rose-500'
                ]"
              ></div>
              <span class="text-[10px] font-bold text-zinc-700 uppercase">{{ healthStatus.label }}</span>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-zinc-500">Live Users</span>
            <span class="text-xs font-bold text-zinc-900">{{ onlineUsersCount }}</span>
          </div>

          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-zinc-500">Alertes Actives</span>
            <span
              :class="[
                'text-xs font-bold',
                criticalAlertsCount > 0 ? 'text-rose-600' : 'text-zinc-400'
              ]"
            >
              {{ criticalAlertsCount }}
            </span>
          </div>
        </div>
      </div>
    </nav>

    <!-- User info -->
    <div class="absolute bottom-0 w-full border-t border-zinc-100 bg-zinc-50/50 p-4 backdrop-blur-sm">
      <div class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm border border-zinc-200/50">
        <div class="relative flex-shrink-0">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-100 text-brand-700 font-bold shadow-inner">
            {{ userInitials }}
          </div>
          <div class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white bg-emerald-500"></div>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-bold text-zinc-900 truncate">{{ authStore.userName }}</p>
          <p class="text-[10px] font-medium text-zinc-500 uppercase tracking-wider truncate">{{ authStore.userRole }}</p>
        </div>
        <button
          @click="handleLogout"
          class="flex-shrink-0 rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-rose-600 transition-colors"
          title="Déconnexion"
        >
          <ArrowRightOnRectangleIcon class="h-5 w-5" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import {
  HomeIcon,
  ChartBarIcon,
  GlobeAltIcon,
  UsersIcon,
  BuildingOfficeIcon,
  CreditCardIcon,
  ChatBubbleLeftRightIcon,
  CogIcon,
  DocumentTextIcon,
  ArrowRightOnRectangleIcon
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'
import { useRealtimeStore } from '@/stores/realtime'

defineProps({
  isOpen: {
    type: Boolean,
    default: false
  }
})

defineEmits(['close'])

const router = useRouter()
const authStore = useAuthStore()
const dashboardStore = useDashboardStore()
const realtimeStore = useRealtimeStore()

// Navigation items
const navigation = computed(() => [
  {
    name: 'dashboard',
    title: 'Tableau de bord',
    path: '/',
    icon: HomeIcon
  },
  {
    name: 'analytics',
    title: 'Analytics IA',
    path: '/analytics',
    icon: ChartBarIcon
  },
  {
    name: 'globe',
    title: 'Live Activity',
    path: '/globe',
    icon: GlobeAltIcon
  },
  {
    name: 'users',
    title: 'Utilisateurs',
    path: '/users',
    icon: UsersIcon
  },
  {
    name: 'companies',
    title: 'Portefeuille Clients',
    path: '/companies',
    icon: BuildingOfficeIcon
  },
  {
    name: 'subscriptions',
    title: 'Abonnements',
    path: '/subscriptions',
    icon: CreditCardIcon
  },
  {
    name: 'support',
    title: 'Centre Support',
    path: '/support',
    icon: ChatBubbleLeftRightIcon,
    badge: dashboardStore.stats.supportTickets
  },
  {
    name: 'system',
    title: 'Paramètres Système',
    path: '/system',
    icon: CogIcon
  },
  {
    name: 'logs',
    title: 'Audit & Logs',
    path: '/logs',
    icon: DocumentTextIcon
  }
])

// Computed properties
const userInitials = computed(() => {
  const name = authStore.userName || 'Admin'
  return name
    .split(' ')
    .map(n => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
})

const healthStatus = computed(() => dashboardStore.healthStatus || { color: 'green', label: 'Opérationnel' })
const onlineUsersCount = computed(() => realtimeStore.onlineUsers?.length || 0)
const criticalAlertsCount = computed(() => dashboardStore.criticalAlerts?.length || 0)

// Methods
async function handleLogout() {
  try {
    await authStore.logout()
    router.push('/login')
  } catch (error) {
    console.error('Logout failed:', error)
  }
}
</script>
