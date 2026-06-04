<template>
  <!-- Mobile sidebar overlay -->
  <transition name="fade">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-40 lg:hidden"
      @click="$emit('close')"
    >
      <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    </div>
  </transition>

  <!-- Sidebar -->
  <div
    :class="[
      'fixed inset-y-0 left-0 z-50 w-64 transform bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border-r border-slate-200/50 dark:border-slate-800/50 shadow-premium transition-all duration-300 ease-in-out lg:static lg:translate-x-0',
      isOpen ? 'translate-x-0' : '-translate-x-full'
    ]"
  >
    <!-- Logo -->
    <div class="flex h-20 items-center justify-center border-b border-slate-200/50 dark:border-slate-800/50 px-6">
      <div class="flex items-center">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-cyan-600 shadow-lg shadow-brand-500/20">
          <span class="text-sm font-bold text-white">LRH</span>
        </div>
        <span class="ml-3 text-xl font-bold tracking-tight text-slate-900 dark:text-white">Leopardo</span>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="mt-6 px-4" role="navigation" aria-label="Menu principal">
      <div class="space-y-1">
        <router-link
          v-for="item in navigation"
          :key="item.name"
          :to="item.path"
          :class="[
            'group flex items-center rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200',
            $route.name === item.name
              ? 'bg-brand-500 text-white shadow-lg shadow-brand-500/25'
              : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
          ]"
          @click="$emit('close')"
        >
          <component
            :is="item.icon"
            :class="[
              'mr-3 h-5 w-5 flex-shrink-0 transition-colors',
              $route.name === item.name
                ? 'text-white'
                : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-300'
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
      <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6">
        <div class="px-3">
          <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
            Système
          </h3>
          <div class="mt-3 space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-sm text-gray-600 dark:text-gray-400">Statut</span>
              <div class="flex items-center">
                <div
                  :class="[
                    'h-2 w-2 rounded-full mr-2',
                    healthStatus.color === 'green' ? 'bg-green-400' :
                    healthStatus.color === 'yellow' ? 'bg-yellow-400' : 'bg-red-400'
                  ]"
                ></div>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ healthStatus.label }}</span>
              </div>
            </div>

            <div class="flex items-center justify-between">
              <span class="text-sm text-gray-600 dark:text-gray-400">Utilisateurs en ligne</span>
              <span class="text-xs font-medium text-gray-900 dark:text-gray-200">{{ onlineUsersCount }}</span>
            </div>

            <div class="flex items-center justify-between">
              <span class="text-sm text-gray-600 dark:text-gray-400">Alertes</span>
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
    <div class="absolute bottom-0 w-full border-t border-slate-200/50 dark:border-slate-800/50 p-4 bg-white/50 dark:bg-slate-900/50 backdrop-blur-sm">
      <div class="flex items-center">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-slate-200 to-slate-300 dark:from-slate-700 dark:to-slate-800 shadow-sm">
          <span class="text-sm font-bold text-slate-700 dark:text-slate-300">
            {{ userInitials }}
          </span>
        </div>
        <div class="ml-3 flex-1 overflow-hidden">
          <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ authStore.userName }}</p>
          <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ authStore.userRole }}</p>
        </div>
        <button
          @click="handleLogout"
          class="ml-2 p-2 rounded-xl text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all duration-200"
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
  ArrowRightOnRectangleIcon,
  CurrencyEuroIcon,
  CalendarDaysIcon,
  DocumentDuplicateIcon,
  UserPlusIcon,
  AcademicCapIcon,
  TruckIcon,
  SparklesIcon,
  LinkIcon,
  ArrowDownTrayIcon,
  ChartPieIcon,
  ShieldCheckIcon
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
    name: 'payroll',
    title: 'Paie',
    path: '/payroll',
    icon: CurrencyEuroIcon
  },
  {
    name: 'leaves',
    title: 'Congés & Absences',
    path: '/leaves',
    icon: CalendarDaysIcon
  },
  {
    name: 'contracts',
    title: 'Contrats',
    path: '/contracts',
    icon: DocumentDuplicateIcon
  },
  {
    name: 'recruitment',
    title: 'Recrutement',
    path: '/recruitment',
    icon: UserPlusIcon
  },
  {
    name: 'training',
    title: 'Formations',
    path: '/training',
    icon: AcademicCapIcon
  },
  {
    name: 'fleet',
    title: 'Flotte véhicules',
    path: '/fleet',
    icon: TruckIcon
  },
  {
    name: 'chat',
    title: 'Chat IA',
    path: '/chat',
    icon: SparklesIcon
  },
  {
    name: 'reports',
    title: 'Rapports RH',
    path: '/reports',
    icon: ChartPieIcon
  },
  {
    name: 'audit',
    title: 'Journal d\'audit',
    path: '/audit',
    icon: ShieldCheckIcon
  },
  {
    name: 'webhooks',
    title: 'Webhooks',
    path: '/webhooks',
    icon: LinkIcon
  },
  {
    name: 'exports',
    title: 'Exports & Rapports',
    path: '/exports',
    icon: ArrowDownTrayIcon
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