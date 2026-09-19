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
    v-bind="$attrs"
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
          <!-- #7329 — titre de section : les modules d'entreprise cliente ne
               sont plus des entrées de premier niveau. Section repliable,
               ouverte par défaut. -->
          <button
            v-if="item.type === 'section'"
            type="button"
            class="group flex w-full items-center justify-between rounded-xl px-3 pt-5 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors"
            :aria-expanded="isGroupOpen(item.group)"
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

          <!-- #7554 — la sonde asynchrone du flag `travelagency` ne doit plus
               décaler le menu : la place de l'entrée est RÉSERVÉE pendant la
               sonde (squelette inerte, hors arbre d'accessibilité) au lieu
               d'une entrée injectée après coup. -->
          <div
            v-else-if="item.state === 'pending'"
            v-show="isGroupOpen(item.group)"
            class="mx-3 my-1 h-10 animate-pulse rounded-xl bg-slate-100/70 dark:bg-slate-800/40"
            aria-hidden="true"
            :data-nav-pending="item.name"
          ></div>

          <!-- #7725 — UN niveau de sous-menu (Comptabilité, Stations-service) :
               le parent est un déclencheur repliable (état persisté
               `admin.nav.openSubmenus`), les enfants sont les liens. -->
          <template v-else-if="item.type === 'submenu'">
            <button
              v-show="!item.group || isGroupOpen(item.group)"
              type="button"
              :class="[
                'group flex w-full items-center rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200',
                isSubmenuActive(item)
                  ? 'bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300'
                  : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
              ]"
              :aria-expanded="isSubmenuOpen(item.name)"
              :data-nav-submenu="item.name"
              @click="toggleSubmenu(item.name)"
            >
              <component
                :is="item.icon"
                class="mr-3 h-5 w-5 flex-shrink-0 text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-300"
              />
              {{ item.title }}
              <ChevronDownIcon
                :class="[
                  'ml-auto h-4 w-4 flex-shrink-0 transition-transform duration-200',
                  isSubmenuOpen(item.name) ? 'rotate-180' : ''
                ]"
              />
            </button>
            <router-link
              v-for="child in item.children"
              v-show="(!item.group || isGroupOpen(item.group)) && isSubmenuOpen(item.name)"
              :key="child.name"
              :to="child.path"
              :class="[
                'group ml-6 flex items-center rounded-xl border-l border-slate-200 dark:border-slate-700 px-3 py-2 text-sm font-medium transition-all duration-200',
                $route.name === child.name
                  ? 'bg-brand-500 text-white shadow-lg shadow-brand-500/25'
                  : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
              ]"
              @click="$emit('close')"
            >
              <component
                :is="child.icon"
                :class="[
                  'mr-3 h-4 w-4 flex-shrink-0 transition-colors',
                  $route.name === child.name
                    ? 'text-white'
                    : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-300'
                ]"
              />
              {{ child.title }}
            </router-link>
          </template>

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
            {{ t('navigation.systemHealth') }}
          </h3>
          <div class="mt-3 space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-sm text-gray-600 dark:text-gray-400">{{ t('navigation.healthStatus') }}</span>
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
              <span class="text-sm text-gray-600 dark:text-gray-400">{{ t('navigation.onlineUsers') }}</span>
              <span class="text-xs font-medium text-gray-900 dark:text-gray-200">{{ onlineUsersCount }}</span>
            </div>

            <div class="flex items-center justify-between">
              <span class="text-sm text-gray-600 dark:text-gray-400">{{ t('navigation.alerts') }}</span>
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

  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { ChevronDownIcon } from '@heroicons/vue/24/outline'
// #7557/#7554 — la navigation n'est plus décrite dans ce composant : elle vient
// de la source de vérité unique `src/navigation/navigation.js`.
import {
  NAV_GROUPS,
  NAV_ENTRIES,
  NAV_STATE_HIDDEN,
  LEGACY_GROUP_IDS,
  navEntryState,
} from '@/navigation/navigation.js'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'
import { useRealtimeStore } from '@/stores/realtime'
import { useTravelStore } from '@/stores/travel'

// #7305 — ce composant a une racine FRAGMENTAIRE (l'overlay mobile
// `<transition>` ET la sidebar sont deux nœuds frères). Vue ne peut donc pas
// hériter automatiquement d'un attribut passé par le parent
// (`[Vue warn]: Extraneous non-props attributes (class) were passed to
// component … renders fragment or text or teleport root nodes`) : l'attribut
// était silencieusement PERDU. On désactive l'héritage implicite et on le
// rebranche explicitement sur la racine « sidebar » ci-dessous.
defineOptions({
  inheritAttrs: false
})

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
 * #7329/#7554 — les écrans des modules d'une entreprise cliente (formations,
 * flotte, stations-service, agence de voyage) ne sont pas des entrées de la
 * plateforme : ils vivent dans la section « Modules des entreprises
 * clientes » (groupe `modules-clients` de `src/navigation/navigation.js`).
 *
 * Toutes les sections sont repliables et OUVERTES par défaut : une section
 * repliée par défaut masquerait des écrans existants (régression
 * d'accessibilité et de discoverabilité, cf. specs e2e sidebar-unique-entries
 * / travel-navigation qui exigent ces entrées visibles au chargement).
 */
const OPEN_GROUPS_KEY = 'admin.nav.openGroups'

function readOpenGroups() {
  try {
    const raw = window.localStorage.getItem(OPEN_GROUPS_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw)
    if (!parsed || typeof parsed !== 'object') return null
    // Le groupe des modules clients s'appelait `clientModules` avant #7554 :
    // on relit l'ancienne clé pour ne pas rouvrir une section que
    // l'utilisateur avait repliée.
    const normalized = {}
    for (const [key, value] of Object.entries(parsed)) {
      normalized[LEGACY_GROUP_IDS[key] || key] = value
    }
    return normalized
  } catch {
    return null
  }
}

// Défaut = ouvert (voir commentaire ci-dessus).
const openGroups = ref({ ...(readOpenGroups() || {}) })

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
 * #7725 — état replié/déplié des SOUS-MENUS (un niveau : Comptabilité,
 * Stations-service), persisté comme celui des groupes. Ouvert par défaut :
 * un sous-menu replié par défaut masquerait des écrans existants (même règle
 * que les sections, cf. specs e2e sidebar-unique-entries).
 */
const OPEN_SUBMENUS_KEY = 'admin.nav.openSubmenus'

function readOpenSubmenus() {
  try {
    const raw = window.localStorage.getItem(OPEN_SUBMENUS_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw)
    if (!parsed || typeof parsed !== 'object') return null
    return parsed
  } catch {
    return null
  }
}

const openSubmenus = ref({ ...(readOpenSubmenus() || {}) })

function isSubmenuOpen(name) {
  return openSubmenus.value[name] !== false
}

function toggleSubmenu(name) {
  openSubmenus.value = { ...openSubmenus.value, [name]: !isSubmenuOpen(name) }
  try {
    window.localStorage.setItem(OPEN_SUBMENUS_KEY, JSON.stringify(openSubmenus.value))
  } catch {
    /* stockage indisponible : l'état reste en mémoire */
  }
}

/** Le parent d'un sous-menu est surligné quand l'écran courant est un enfant. */
function isSubmenuActive(item) {
  return (item.children || []).some((child) => child.name === route.name)
}

/**
 * TRAVEL-601 (#6078) — l'entrée « Agence de voyage » n'est proposée que si le
 * flag `travelagency` est ACTIF pour le contexte courant (sondé via le contrat
 * réel GET /travel/ping : 200 = actif, 403 FEATURE_NOT_ENABLED = absent,
 * 401 = hors contexte tenant). Tant que la sonde n'a pas répondu (isReady
 * false) la place de l'entrée est RÉSERVÉE (état `pending`, squelette inerte)
 * — elle n'apparaît comme lien que sur 200 (#7554 : plus d'injection après
 * coup, donc plus de décalage du menu), et les écrans /travel gèrent eux-mêmes
 * les états explicites (TravelGate).
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
 * #7557/#7554 — la liste de navigation vit dans la source de vérité unique
 * `src/navigation/navigation.js` (consommée aussi par la palette de commandes
 * et les raccourcis clavier). Ce composant ne fait plus que :
 *   1. filtrer chaque entrée sur la permission plateforme du compte courant
 *      (`hasPermission`, contrat `/platform/auth/me` #7553) ;
 *   2. intercaler les titres de section repliables (groupes) ;
 *   3. résoudre les entrées dont la disponibilité est sondée (flag travel) en
 *      états `shown` / `pending` (place réservée) / `hidden`.
 *
 * Les écrans du périmètre comptable : leur condition historique
 * (`user.role === 'manager'`) ne pouvait plus jamais être vraie pour une
 * session plateforme — elle est retirée ici et les écrans seront rattachés à
 * une permission plateforme par #7554 (aucun changement de rendu : la
 * condition était morte).
 */
const navigation = computed(() => {
  const capabilities = {
    hasPermission: (permission) => authStore.hasPermission(permission),
    travelFlagReady: travelStore.isReady,
    travelFlagActive: travelStore.flagActive,
  }

  const items = []

  for (const group of NAV_GROUPS) {
    const entries = NAV_ENTRIES.filter((entry) => {
      if (entry.group !== group.id) return false
      return navEntryState(entry, capabilities) !== NAV_STATE_HIDDEN
    })

    if (entries.length === 0) continue

    items.push({
      type: 'section',
      name: `section-${group.id}`,
      group: group.id,
      title: t(group.titleKey)
    })

    for (const entry of entries) {
      // #7725 — entrée à sous-menu : le parent devient un déclencheur et ses
      // enfants visibles sont résolus ici (libellé court `menuTitleKey` dans
      // le menu, `titleKey` complet ailleurs — palette, recherche, titres).
      if (Array.isArray(entry.children) && entry.children.length > 0) {
        const children = entry.children
          .filter((child) => navEntryState(child, capabilities) !== NAV_STATE_HIDDEN)
          .map((child) => ({
            ...child,
            title: t(child.menuTitleKey || child.titleKey),
            state: navEntryState(child, capabilities),
          }))

        if (children.length === 0) continue

        items.push({
          ...entry,
          type: 'submenu',
          title: t(entry.titleKey),
          state: navEntryState(entry, capabilities),
          children,
          badge: 0,
        })
        continue
      }

      items.push({
        ...entry,
        title: t(entry.titleKey),
        state: navEntryState(entry, capabilities),
        badge: entry.badgeKey === 'supportTickets' ? dashboardStore.stats.supportTickets : 0
      })
    }
  }

  return items
})

/**
 * #7329 — la section contenant l'écran courant est toujours dépliée : sinon le
 * repli mémorisé masquerait du menu la page qu'on est en train de consulter.
 * #7725 — même règle pour un sous-menu dont un enfant est l'écran courant.
 */
watch(
  () => route.name,
  (name) => {
    if (!name) return
    const active = navigation.value.find(
      (item) => item.name === name || (item.children || []).some((child) => child.name === name),
    )
    if (!active) return
    if (active.group && !isGroupOpen(active.group)) {
      openGroups.value = { ...openGroups.value, [active.group]: true }
    }
    if (active.type === 'submenu' && active.name !== name && !isSubmenuOpen(active.name)) {
      openSubmenus.value = { ...openSubmenus.value, [active.name]: true }
    }
  },
  { immediate: true }
)

// Computed properties
const healthStatus = computed(() => dashboardStore.healthStatus)
const onlineUsersCount = computed(() => realtimeStore.onlineUsers.length)
const criticalAlertsCount = computed(() => dashboardStore.criticalAlerts.length)
</script>