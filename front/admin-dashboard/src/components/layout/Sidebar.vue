<template>
  <!-- Mobile sidebar overlay -->
  <transition name="fade">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-40 md:hidden"
      @click="$emit('close')"
    >
      <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    </div>
  </transition>

  <!-- Sidebar -->
  <div
    :class="[
      'fixed inset-y-0 left-0 z-50 flex w-64 flex-col overflow-hidden transform bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border-r border-slate-200/50 dark:border-slate-800/50 shadow-premium transition-all duration-300 ease-in-out md:translate-x-0',
      isOpen ? 'translate-x-0' : '-translate-x-full'
    ]"
  >
    <!-- Logo -->
    <div class="flex h-20 shrink-0 items-center justify-center border-b border-slate-200/50 dark:border-slate-800/50 px-6">
      <div class="flex items-center">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-cyan-600 shadow-lg shadow-brand-500/20">
          <span class="text-sm font-bold text-white">LRH</span>
        </div>
        <span class="ml-3 text-xl font-bold tracking-tight text-slate-900 dark:text-white">Leopardo</span>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="mt-6 flex-1 overflow-y-auto px-4 pb-24" role="navigation" :aria-label="t('navigation.mainMenu', 'Menu principal')">
      <div class="space-y-1">
        <template v-for="item in navigation" :key="item.name">
          <!-- #7327 — titre de section : les modules d'entreprise cliente ne
               sont plus des entrées de premier niveau. Section repliable,
               ouverte par défaut. -->
          <button
            v-if="item.type === 'section'"
            type="button"
            class="group flex w-full items-center justify-between rounded-xl px-3 pt-5 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors"
            :aria-expanded="isGroupOpen(item.group) ? 'true' : 'false'"
            :data-nav-section="item.group"
            @click="toggleGroup(item.group)"
          >
            <span>{{ item.title }}</span>
            <ChevronDownIcon
              :class="[
                'h-4 w-4 flex-shrink-0 transition-transform duration-200',
                isGroupOpen(item.group) ? 'rotate-180' : ''
              ]"
            />
          </button>

          <router-link
            v-else
            v-show="!item.group || isGroupOpen(item.group)"
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
        </template>
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
        <router-link to="/settings" class="ml-3 flex-1 overflow-hidden min-w-0 hover:opacity-80 transition-opacity" title="Mon compte">
          <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ authStore.userName }}</p>
          <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ authStore.userRole }}</p>
        </router-link>
        <router-link
          to="/settings"
          class="ml-2 p-2 rounded-xl text-slate-400 hover:text-brand-500 hover:bg-brand-50 dark:hover:bg-brand-900/20 transition-all duration-200"
          title="Mon compte"
        >
          <CogIcon class="h-5 w-5" />
        </router-link>
        <router-link
          to="/logout"
          class="ml-2 p-2 rounded-xl text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-all duration-200"
          title="Déconnexion"
        >
          <ArrowRightOnRectangleIcon class="h-5 w-5" />
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import {
  HomeIcon,
  ChartBarIcon,
  GlobeAltIcon,
  UsersIcon,
  BuildingOfficeIcon,
  ChevronDownIcon,
  CreditCardIcon,
  ChatBubbleLeftRightIcon,
  CogIcon,
  ArrowRightOnRectangleIcon,
  AcademicCapIcon,
  TruckIcon,
  SparklesIcon,
  BoltIcon,
  LinkIcon,
  ArrowDownTrayIcon,
  FunnelIcon,
  LifebuoyIcon,
  ServerIcon,
  ArrowTrendingUpIcon,
  MegaphoneIcon
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'
import { useRealtimeStore } from '@/stores/realtime'
import { useTravelStore } from '@/stores/travel'

defineProps({
  isOpen: {
    type: Boolean,
    default: false
  }
})

defineEmits(['close'])

const localeStore = useLocaleStore()
/** Traduction avec fallback sur le libellé anglais de la clé */
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const authStore = useAuthStore()
const dashboardStore = useDashboardStore()
const realtimeStore = useRealtimeStore()
const travelStore = useTravelStore()
const route = useRoute()

/**
 * #7327 — Les écrans des modules d'une entreprise cliente (formations, flotte,
 * stations-service, agence de voyage) n'étaient que des entrées de PREMIER
 * niveau dans ce menu, alors qu'ils ne s'adressent pas à la plateforme mais au
 * périmètre d'une entreprise cliente. Ils sont désormais regroupés sous une
 * section « Modules des entreprises clientes », rattachée à l'entrée
 * « Entreprises ».
 *
 * La section est repliable mais OUVERTE par défaut : les entrées restent
 * atteignables en un clic (et visibles dans l'arbre d'accessibilité, ce dont
 * dépendent les specs e2e travel-navigation / sidebar-unique-entries).
 */
const CLIENT_MODULES_GROUP = 'clientModules'
const OPEN_GROUPS_KEY = 'admin.nav.openGroups'

function readOpenGroups() {
  try {
    const raw = window.localStorage.getItem(OPEN_GROUPS_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw)
    return parsed && typeof parsed === 'object' ? parsed : null
  } catch {
    return null
  }
}

// Défaut = ouvert : une section repliée par défaut masquerait des écrans
// existants (régression d'accessibilité et de discoverabilité).
const openGroups = ref({ [CLIENT_MODULES_GROUP]: true, ...(readOpenGroups() || {}) })

function isGroupOpen(group) {
  if (!group) return true
  return openGroups.value[group] !== false
}

function toggleGroup(group) {
  if (!group) return
  openGroups.value = { ...openGroups.value, [group]: !isGroupOpen(group) }
  try {
    window.localStorage.setItem(OPEN_GROUPS_KEY, JSON.stringify(openGroups.value))
  } catch {
    /* stockage indisponible : l'état reste en mémoire */
  }
}

/**
 * TRAVEL-601 (#6078) — l'entrée « Agence de voyage » n'est proposée que si le
 * flag `travelagency` est ACTIF pour le contexte courant (sondé via le contrat
 * réel GET /travel/ping : 200 = actif, 403 FEATURE_NOT_ENABLED = absent,
 * 401 = hors contexte tenant). Tant que la sonde n'a pas répondu (isReady
 * false) l'entrée reste masquée — elle apparaît uniquement sur 200, et les
 * écrans /travel gèrent eux-mêmes les états explicites (TravelGate).
 */
let lastProbedUserEmail = null
watch(
  () => authStore.user,
  (user) => {
    if (!user) {
      travelStore.reset()
      lastProbedUserEmail = null
      return
    }
    // Reprobe quand l'identité change (tenant/flag différents par utilisateur).
    const email = user.email ?? user.id ?? ''
    if (email !== lastProbedUserEmail) {
      lastProbedUserEmail = email
      travelStore.checkFlag(true)
    }
  },
  { immediate: true }
)

/**
 * Comptabilité — RBAC backend (api.manager:comptable,principal) : le menu
 * Paramétrage comptable n'est proposé qu'aux managers comptable/principal.
 * Le backend reste la source de vérité (403 sinon) ; ce filtre évite juste
 * d'afficher une entrée inutile aux autres rôles.
 */
const canAccessAccounting = computed(() => {
  const user = authStore.user
  if (!user) return false
  const managerRole = user.manager_role
  return user.role === 'manager' && (managerRole === 'comptable' || managerRole === 'principal')
})

// Navigation items
const navigation = computed(() => [
  {
    name: 'dashboard',
    title: t('navigation.dashboard', 'Tableau de bord'),
    path: '/',
    icon: HomeIcon
  },
  {
    name: 'analytics',
    title: t('navigation.analytics', 'Analytics'),
    path: '/analytics',
    icon: ChartBarIcon
  },
  {
    name: 'globe',
    title: t('navigation.globe', 'Globe Temps Réel'),
    path: '/globe',
    icon: GlobeAltIcon
  },
  {
    name: 'users',
    title: t('navigation.users', 'Utilisateurs'),
    path: '/users',
    icon: UsersIcon
  },
  {
    name: 'companies',
    title: t('navigation.companies', 'Entreprises'),
    path: '/companies',
    icon: BuildingOfficeIcon
  },
  // #7327 — section des modules d'entreprise cliente : ces écrans ne
  // s'adressent pas à la plateforme mais au périmètre d'un client.
  {
    type: 'section',
    name: 'section-client-modules',
    group: CLIENT_MODULES_GROUP,
    title: t('navigation.clientModules', 'Modules des entreprises clientes')
  },
  {
    name: 'training',
    title: t('navigation.training', 'Formations'),
    path: '/training',
    icon: AcademicCapIcon,
    group: CLIENT_MODULES_GROUP
  },
  {
    name: 'fleet',
    title: t('navigation.fleet', 'Flotte véhicules'),
    path: '/fleet',
    icon: TruckIcon,
    group: CLIENT_MODULES_GROUP
  },
  // TRAVEL-601 (#6078) : entrée conditionnée par le flag travelagency réel
  // (GET /travel/ping → 200). Masquée tant que la sonde n'a pas répondu, si
  // le flag est inactif (403) ou hors contexte tenant (401).
  ...(travelStore.isReady && travelStore.flagActive
    ? [
        {
          name: 'travel',
          title: t('navigation.travelAgency', 'Agence de voyage'),
          path: '/travel',
          icon: GlobeAltIcon,
          group: CLIENT_MODULES_GROUP
        }
      ]
    : []),
  {
    name: 'fuelStation',
    title: t('navigation.fuelStation', 'Stations-service'),
    path: '/fuel-station',
    icon: BoltIcon,
    group: CLIENT_MODULES_GROUP
  },
  {
    name: 'subscriptions',
    title: t('navigation.subscriptions', 'Abonnements'),
    path: '/subscriptions',
    icon: CreditCardIcon
  },
  {
    name: 'chat',
    title: t('navigation.chat', 'Chat IA'),
    path: '/chat',
    icon: SparklesIcon
  },
  {
    name: 'showcase',
    title: t('navigation.showcase', 'Site vitrine'),
    path: '/showcase',
    icon: GlobeAltIcon
  },
  {
    name: 'webhooks',
    title: t('navigation.webhooks', 'Webhooks'),
    path: '/webhooks',
    icon: LinkIcon
  },
  {
    name: 'marketing-oauth',
    title: t('marketing.oauth.nav_title'),
    path: '/marketing/oauth',
    icon: MegaphoneIcon
  },
  {
    name: 'exports',
    title: t('navigation.exports', 'Exports & Rapports'),
    path: '/exports',
    icon: ArrowDownTrayIcon
  },
  ...(canAccessAccounting.value
    ? [
        {
          name: 'accounting-activation',
          title: t('navigation.accountingActivation', 'Comptabilité — Démarrer'),
          path: '/accounting/activation',
          icon: SparklesIcon
        },
        {
          name: 'accounting-dashboard',
          title: t('navigation.accountingDashboard', 'Comptabilité — Tableau de bord'),
          path: '/accounting/dashboard',
          icon: ChartBarIcon
        },
        {
          name: 'accounting-settings',
          title: t('navigation.accountingSettings', 'Comptabilité — Paramétrage'),
          path: '/accounting/settings',
          icon: CogIcon
        }
      ]
    : []),
  {
    name: 'support',
    title: t('navigation.support', 'Support'),
    path: '/support',
    icon: ChatBubbleLeftRightIcon,
    badge: dashboardStore.stats.supportTickets
  },
  {
    name: 'support-tickets',
    title: t('navigation.supportTickets', 'Centre support client'),
    path: '/support-tickets',
    icon: LifebuoyIcon
  },
  {
    name: 'crm-pipeline',
    title: t('navigation.crm', 'Pipeline CRM'),
    path: '/crm/pipeline',
    icon: FunnelIcon
  },
  {
    name: 'growth',
    title: t('navigation.growth', 'Administration Growth'),
    path: '/growth',
    icon: ArrowTrendingUpIcon
  },
  {
    name: 'edge',
    title: t('navigation.edge', 'Edge Nodes'),
    path: '/edge',
    icon: ServerIcon
  },
  {
    name: 'system',
    title: t('navigation.system', 'Système'),
    path: '/system',
    icon: CogIcon
  },
])

/**
 * #7327 — la section contenant l'écran courant est toujours dépliée : sinon le
 * repli mémorisé masquerait du menu la page qu'on est en train de consulter.
 */
watch(
  () => route.name,
  (name) => {
    if (!name) return
    const active = navigation.value.find((item) => item.name === name)
    if (active?.group && !isGroupOpen(active.group)) {
      openGroups.value = { ...openGroups.value, [active.group]: true }
    }
  },
  { immediate: true }
)

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
</script>