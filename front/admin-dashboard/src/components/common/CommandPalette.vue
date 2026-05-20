<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-[110] flex items-start justify-center pt-[15vh] bg-black/50 backdrop-blur-sm"
      @click.self="close"
      @keydown.escape="close"
    >
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-lg w-full mx-4 overflow-hidden animate-fade-in">
        <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
          <MagnifyingGlassIcon class="h-5 w-5 text-gray-400 flex-shrink-0" />
          <input
            ref="searchInput"
            v-model="query"
            type="text"
            placeholder="Rechercher pages, actions..."
            class="flex-1 bg-transparent text-sm text-gray-900 dark:text-white placeholder-gray-400 outline-none"
            @keydown.down.prevent="moveDown"
            @keydown.up.prevent="moveUp"
            @keydown.enter.prevent="selectCurrent"
          />
          <kbd class="hidden sm:inline-flex px-2 py-0.5 text-[10px] font-mono text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-700 rounded">
            ESC
          </kbd>
        </div>

        <div class="max-h-72 overflow-y-auto py-2" v-if="filteredItems.length">
          <div
            v-for="(item, index) in filteredItems"
            :key="item.id"
            class="flex items-center gap-3 px-4 py-2.5 cursor-pointer transition-colors"
            :class="index === activeIndex ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50'"
            @click="selectItem(item)"
            @mouseenter="activeIndex = index"
          >
            <component :is="item.icon" class="h-4 w-4 flex-shrink-0 opacity-60" />
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium truncate">{{ item.label }}</div>
              <div class="text-xs opacity-60 truncate" v-if="item.description">{{ item.description }}</div>
            </div>
            <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400" v-if="item.shortcut">
              {{ item.shortcut }}
            </span>
          </div>
        </div>

        <div class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500" v-else-if="query">
          Aucun resultat pour "{{ query }}"
        </div>

        <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 flex items-center gap-4 text-[10px] text-gray-400 dark:text-gray-500">
          <span><kbd class="px-1 py-0.5 bg-gray-100 dark:bg-gray-700 rounded font-mono">↑↓</kbd> naviguer</span>
          <span><kbd class="px-1 py-0.5 bg-gray-100 dark:bg-gray-700 rounded font-mono">↵</kbd> selectionner</span>
          <span><kbd class="px-1 py-0.5 bg-gray-100 dark:bg-gray-700 rounded font-mono">esc</kbd> fermer</span>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useThemeStore } from '@/stores/theme'
import {
  MagnifyingGlassIcon,
  HomeIcon,
  UsersIcon,
  BuildingOfficeIcon,
  CreditCardIcon,
  ChartBarIcon,
  DocumentTextIcon,
  CogIcon,
  ArrowDownTrayIcon,
  CalendarDaysIcon,
  BriefcaseIcon,
  AcademicCapIcon,
  TruckIcon,
  ChatBubbleLeftRightIcon,
  SunIcon,
  MoonIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const themeStore = useThemeStore()

const isOpen = ref(false)
const query = ref('')
const activeIndex = ref(0)
const searchInput = ref(null)

const items = [
  { id: 'dashboard', label: 'Tableau de bord', description: 'Vue principale', icon: HomeIcon, route: '/', shortcut: 'Alt+H' },
  { id: 'analytics', label: 'Analytics', description: 'Statistiques et rapports', icon: ChartBarIcon, route: '/analytics' },
  { id: 'users', label: 'Utilisateurs', description: 'Gestion des utilisateurs', icon: UsersIcon, route: '/users', shortcut: 'Alt+U' },
  { id: 'companies', label: 'Entreprises', description: 'Gestion des entreprises', icon: BuildingOfficeIcon, route: '/companies', shortcut: 'Alt+C' },
  { id: 'subscriptions', label: 'Abonnements', description: 'Plans et facturation', icon: CreditCardIcon, route: '/subscriptions', shortcut: 'Alt+S' },
  { id: 'payroll', label: 'Paie', description: 'Cycles de paie et bulletins', icon: DocumentTextIcon, route: '/payroll' },
  { id: 'leaves', label: 'Conges', description: 'Gestion des conges et absences', icon: CalendarDaysIcon, route: '/leaves' },
  { id: 'recruitment', label: 'Recrutement', description: 'Pipeline de recrutement', icon: BriefcaseIcon, route: '/recruitment', shortcut: 'Alt+R' },
  { id: 'training', label: 'Formations', description: 'Catalogue de formations', icon: AcademicCapIcon, route: '/training' },
  { id: 'vehicles', label: 'Vehicules', description: 'Flotte et suivi GPS', icon: TruckIcon, route: '/vehicles' },
  { id: 'exports', label: 'Exports', description: 'Rapports et exports CSV/PDF', icon: ArrowDownTrayIcon, route: '/exports' },
  { id: 'webhooks', label: 'Webhooks', description: 'Configuration des webhooks', icon: CogIcon, route: '/webhooks' },
  { id: 'chat', label: 'Chat IA', description: 'Assistant RH intelligent', icon: ChatBubbleLeftRightIcon, route: '/chat' },
  { id: 'settings', label: 'Parametres', description: 'Configuration systeme', icon: CogIcon, route: '/system' },
  { id: 'toggle-dark', label: 'Basculer mode sombre', description: 'Changer le theme', icon: themeStore.isDark ? SunIcon : MoonIcon, action: () => themeStore.toggle(), shortcut: 'Ctrl+D' },
]

const filteredItems = computed(() => {
  if (!query.value) return items
  const q = query.value.toLowerCase()
  return items.filter(
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
