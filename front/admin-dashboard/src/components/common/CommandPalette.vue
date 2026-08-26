<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-[110] flex items-start justify-center pt-[15vh] bg-slate-950/60 backdrop-blur-md"
      @click.self="close"
      @keydown.escape="close"
    >
      <div class="glass-card max-w-lg w-full mx-4 overflow-hidden animate-fade-in !rounded-3xl border-white/20 shadow-glass-lg">
        <div class="flex items-center gap-4 px-6 py-5 border-b border-slate-200/50 dark:border-slate-800/50">
          <MagnifyingGlassIcon class="h-6 w-6 text-brand-500 flex-shrink-0" />
          <input
            ref="searchInput"
            v-model="query"
            type="text"
            :placeholder="t('adminPalette.searchPlaceholder')"
            class="flex-1 bg-transparent text-lg font-bold text-slate-900 dark:text-white placeholder-slate-400 outline-none"
            @keydown.down.prevent="moveDown"
            @keydown.up.prevent="moveUp"
            @keydown.enter.prevent="selectCurrent"
          />
          <kbd class="hidden sm:inline-flex px-2 py-0.5 text-[10px] font-mono text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-700 rounded">
            ESC
          </kbd>
        </div>

        <div class="max-h-96 overflow-y-auto py-3 px-2" v-if="filteredItems.length">
          <div
            v-for="(item, index) in filteredItems"
            :key="item.id"
            class="flex items-center gap-4 px-4 py-3 cursor-pointer rounded-2xl transition-all duration-200"
            :class="index === activeIndex ? 'bg-brand-500 text-white shadow-lg shadow-brand-500/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100/50 dark:hover:bg-slate-800/50'"
            @click="selectItem(item)"
            @mouseenter="activeIndex = index"
          >
            <div
              class="h-10 w-10 rounded-xl flex items-center justify-center transition-colors"
              :class="index === activeIndex ? 'bg-white/20' : 'bg-slate-100 dark:bg-slate-800'"
            >
              <component :is="item.icon" class="h-5 w-5" />
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-bold truncate">{{ item.label }}</div>
              <div class="text-xs opacity-80 truncate font-medium" v-if="item.description">{{ item.description }}</div>
            </div>
            <span
              class="text-[10px] font-black px-2 py-1 rounded-lg uppercase tracking-wider"
              :class="index === activeIndex ? 'bg-white/20 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'"
              v-if="item.shortcut"
            >
              {{ item.shortcut }}
            </span>
          </div>
        </div>

        <div class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500" v-else-if="query">
          {{ noResultsLabel }}
        </div>

        <div class="px-6 py-4 border-t border-slate-200/50 dark:border-slate-800/50 flex items-center gap-6 text-[10px] font-bold uppercase tracking-widest text-slate-400">
          <span class="flex items-center gap-1.5"><kbd class="px-1.5 py-1 bg-slate-100 dark:bg-slate-800 rounded-md font-mono text-slate-500">↑↓</kbd> {{ t('adminPalette.navNavigate') }}</span>
          <span class="flex items-center gap-1.5"><kbd class="px-1.5 py-1 bg-slate-100 dark:bg-slate-800 rounded-md font-mono text-slate-500">↵</kbd> {{ t('adminPalette.navSelect') }}</span>
          <span class="flex items-center gap-1.5"><kbd class="px-1.5 py-1 bg-slate-100 dark:bg-slate-800 rounded-md font-mono text-slate-500">esc</kbd> {{ t('adminPalette.navClose') }}</span>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useThemeStore } from '@/stores/theme'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'
import {
  MagnifyingGlassIcon,
  HomeIcon,
  UsersIcon,
  BuildingOfficeIcon,
  CreditCardIcon,
  ChartBarIcon,
  CogIcon,
  TruckIcon,
  SunIcon,
  MoonIcon,
  ArrowTrendingUpIcon,
  ServerIcon,
  GlobeAltIcon,
  MegaphoneIcon,
  LifebuoyIcon,
  PresentationChartLineIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const themeStore = useThemeStore()
const localeStore = useLocaleStore()

const isOpen = ref(false)
const query = ref('')
const activeIndex = ref(0)
const searchInput = ref(null)

// #5508 — i18n : libellés de navigation localisés ×4 (fallback FR).
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const noResultsLabel = computed(() =>
  t('adminPalette.noResults').replace('{query}', query.value)
)

const itemDefs = [
  { id: 'dashboard', labelKey: 'itemDashboard', descKey: 'itemDashboardDesc', icon: HomeIcon, route: '/', shortcut: 'Alt+H' },
  { id: 'analytics', labelKey: 'itemAnalytics', descKey: 'itemAnalyticsDesc', icon: ChartBarIcon, route: '/analytics' },
  { id: 'users', labelKey: 'itemUsers', descKey: 'itemUsersDesc', icon: UsersIcon, route: '/users', shortcut: 'Alt+U' },
  { id: 'companies', labelKey: 'itemCompanies', descKey: 'itemCompaniesDesc', icon: BuildingOfficeIcon, route: '/companies', shortcut: 'Alt+C' },
  { id: 'subscriptions', labelKey: 'itemSubscriptions', descKey: 'itemSubscriptionsDesc', icon: CreditCardIcon, route: '/subscriptions', shortcut: 'Alt+S' },
  // Routes tenant sans endpoints super-admin (#3272) : vues retirées du
  // routeur — la palette ne propose que des destinations réellement ouvrables.
  { id: 'settings', labelKey: 'itemSettings', descKey: 'itemSettingsDesc', icon: CogIcon, route: '/settings' },
  { id: 'growth', labelKey: 'itemGrowth', descKey: 'itemGrowthDesc', icon: ArrowTrendingUpIcon, route: '/growth' },
  { id: 'edge', labelKey: 'itemEdge', descKey: 'itemEdgeDesc', icon: ServerIcon, route: '/edge' },
  { id: 'globe', labelKey: 'itemGlobe', descKey: 'itemGlobeDesc', icon: GlobeAltIcon, route: '/globe' },
  { id: 'fleet', labelKey: 'itemFleet', descKey: 'itemFleetDesc', icon: TruckIcon, route: '/fleet' },
  { id: 'marketing', labelKey: 'itemMarketing', descKey: 'itemMarketingDesc', icon: MegaphoneIcon, route: '/marketing/oauth' },
  { id: 'support', labelKey: 'itemSupport', descKey: 'itemSupportDesc', icon: LifebuoyIcon, route: '/support' },
  { id: 'crm', labelKey: 'itemCrm', descKey: 'itemCrmDesc', icon: PresentationChartLineIcon, route: '/crm/pipeline' },
  { id: 'toggle-dark', labelKey: 'itemToggleDark', descKey: 'itemToggleDarkDesc', icon: themeStore.isDark ? SunIcon : MoonIcon, action: () => themeStore.toggle(), shortcut: 'Ctrl+D' },
]

const items = computed(() =>
  itemDefs.map((item) => ({
    ...item,
    label: t(`adminPalette.${item.labelKey}`),
    description: t(`adminPalette.${item.descKey}`),
  }))
)

const filteredItems = computed(() => {
  if (!query.value) return items.value
  const q = query.value.toLowerCase()
  return items.value.filter(
    (item) =>
      item.label.toLowerCase().includes(q) ||
      (item.description && item.description.toLowerCase().includes(q))
  )
})

watch(query, () => { activeIndex.value = 0 })

function open() {
  isOpen.value = true
  query.value = ''
  activeIndex.value = 0
  nextTick(() => searchInput.value?.focus())
}

function close() {
  isOpen.value = false
}

function moveDown() {
  if (activeIndex.value < filteredItems.value.length - 1) activeIndex.value++
}

function moveUp() {
  if (activeIndex.value > 0) activeIndex.value--
}

function selectCurrent() {
  if (filteredItems.value[activeIndex.value]) {
    selectItem(filteredItems.value[activeIndex.value])
  }
}

function selectItem(item) {
  close()
  if (item.action) {
    item.action()
  } else if (item.route) {
    router.push(item.route)
  }
}

function onKeydown(e) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault()
    if (isOpen.value) {
      close()
    } else {
      open()
    }
  }
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
})
</script>
