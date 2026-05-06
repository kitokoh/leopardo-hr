<template>
  <!-- Mobile sidebar overlay -->
  <div 
    v-if="isOpen" 
    class="fixed inset-0 z-40 lg:hidden"
    @click="$emit('close')"
  >
    <div class="fixed inset-0 bg-gray-600 bg-opacity-75"></div>
  </div>

  <!-- Sidebar -->
  <div 
    :class="[
      'fixed inset-y-0 left-0 z-50 w-64 transform bg-white shadow-lg transition-transform duration-300 ease-in-out lg:static lg:translate-x-0',
      isOpen ? 'translate-x-0' : '-translate-x-full'
    ]"
  >
    <!-- Logo -->
    <div class="flex h-16 items-center justify-center border-b border-gray-200 px-6">
      <div class="flex items-center">
        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600">
          <span class="text-sm font-bold text-white">LRH</span>
        </div>
        <span class="ml-3 text-lg font-semibold text-gray-900">Admin</span>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="mt-6 px-3">
      <div class="space-y-1">
        <router-link
          v-for="item in navigation"
          :key="item.name"
          :to="item.path"
          :class="[
            'group flex items-center rounded-md px-3 py-2 text-sm font-medium transition-colors',
            $route.name === item.name
              ? 'bg-indigo-50 text-indigo-700'
              : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900'
          ]"
          @click="$emit('close')"
        >
          <component
            :is="item.icon"
            :class="[
              'mr-3 h-5 w-5 flex-shrink-0',
              $route.name === item.name
                ? 'text-indigo-500'
                : 'text-gray-400 group-hover:text-gray-500'
            ]"
          />
          {{ item.title }}
          
          <!-- Badge for notifications -->
          <span
            v-if="item.badge && item.badge > 0"
            class="ml-auto inline-flex items-center rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-800"
          >
            {{ item.badge > 99 ? '99+' : item.badge }}
          </span>
        </router-link>
      </div>

      <!-- System Status -->
      <div class="mt-8 border-t border-gray-200 pt-6">
        <div class="px-3">
          <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500">
            Système
          </h3>
          <div class="mt-3 space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-sm text-gray-600">Statut</span>
              <div class="flex items-center">
                <div 
                  :class="[
                    'h-2 w-2 rounded-full mr-2',
                    healthStatus.color === 'green' ? 'bg-green-400' :
                    healthStatus.color === 'yellow' ? 'bg-yellow-400' : 'bg-red-400'
                  ]"
                ></div>
                <span class="text-xs text-gray-500">{{ healthStatus.label }}</span>
              </div>
            </div>
            
            <div class="flex items-center justify-between">
              <span class="text-sm text-gray-600">Utilisateurs en ligne</span>
              <span class="text-xs font-medium text-gray-900">{{ onlineUsersCount }}</span>
            </div>
            
            <div class="flex items-center justify-between">
              <span class="text-sm text-gray-600">Alertes</span>
              <span 
                :class="[
                  'text-xs font-medium',
                  criticalAlertsCount > 0 ? 'text-red-600' : 'text-gray-500'
                ]"
              >
                {{ criticalAlertsCount }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </nav>

    <!-- User info -->
    <div class="absolute bottom-0 w-full border-t border-gray-200 p-4">
      <div class="flex items-center">
        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-300">
          <span class="text-sm font-medium text-gray-700">
            {{ userInitials }}
          </span>
        </div>
        <div class="ml-3 flex-1">
          <p class="text-sm font-medium text-gray-900">{{ authStore.userName }}</p>
          <p class="text-xs text-gray-500">{{ authStore.userRole }}</p>
        </div>
        <button
          @click="handleLogout"
          class="ml-2 rounded-md p-1 text-gray-400 hover:text-gray-600"
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
    title: 'Analytics',
    path: '/analytics',
    icon: ChartBarIcon
  },
  {
    name: 'globe',
    title: 'Globe Temps Réel',
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
    title: 'Entreprises',
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
    title: 'Support',
    path: '/support',
    icon: ChatBubbleLeftRightIcon,
    badge: dashboardStore.stats.supportTickets
  },
  {
    name: 'system',
    title: 'Système',
    path: '/system',
    icon: CogIcon
  },
  {
    name: 'logs',
    title: 'Logs',
    path: '/logs',
    icon: DocumentTextIcon
  }
])

// Computed properties
const userInitials = computed(() => {
  const name = authStore.userName
  return name
    .split(' ')
    .map(n => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
})

const healthStatus = computed(() => dashboardStore.healthStatus)
const onlineUsersCount = computed(() => realtimeStore.onlineUsers.length)
const criticalAlertsCount = computed(() => dashboardStore.criticalAlerts.length)

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