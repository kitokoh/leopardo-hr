<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950 transition-colors duration-500 overflow-x-hidden">
    <!-- Decorative background elements -->
    <div class="fixed inset-0 z-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] rounded-full bg-brand-500/10 blur-[120px]"></div>
      <div class="absolute top-[20%] -right-[5%] w-[30%] h-[30%] rounded-full bg-cyan-500/10 blur-[100px]"></div>
      <div class="absolute -bottom-[10%] left-[20%] w-[35%] h-[35%] rounded-full bg-emerald-500/10 blur-[120px]"></div>
    </div>

    <!-- Skip to content (WCAG 2.4.1) -->
    <a
      href="#main-content"
      class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[100] focus:rounded-xl focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-brand-600 focus:shadow-premium"
    >{{ $t('a11y.skip_to_content', 'Aller au contenu principal') }}</a>

    <!-- Sidebar -->
    <Sidebar
      :is-open="sidebarOpen"
      @close="sidebarOpen = false"
      class="fixed inset-y-0 left-0 z-50 lg:static lg:inset-0"
    />

    <!-- Main content -->
    <div class="relative z-10 lg:pl-64">
      <!-- Header -->
      <Header
        @toggle-sidebar="sidebarOpen = !sidebarOpen"
        class="sticky top-0 z-40 backdrop-blur-md bg-white/70 dark:bg-slate-900/70 border-b border-slate-200/50 dark:border-slate-800/50"
      />

      <!-- Page content -->
      <main id="main-content" class="py-8">
        <div v-if="dashboardStore.loadError" class="mx-auto mb-6 max-w-7xl px-4 sm:px-6 lg:px-8">
          <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
            {{ dashboardStore.loadError }} Les indicateurs affichés peuvent être incomplets. Utilisez Actualiser pour réessayer.
          </div>
        </div>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <!-- Page header -->
          <div class="mb-8 animate-fade-in" v-if="$route.meta.title">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
              {{ $t($route.meta.title, $route.meta.title) }}
            </h1>
            <nav class="mt-2" v-if="breadcrumbs.length > 1">
              <ol class="flex items-center space-x-2 text-sm text-slate-500">
                <li v-for="(crumb, index) in breadcrumbs" :key="crumb.name">
                  <div class="flex items-center">
                    <router-link
                      v-if="index < breadcrumbs.length - 1"
                      :to="crumb.path"
                      class="hover:text-brand-600 transition-colors"
                    >
                      {{ $t(crumb.title, crumb.title) }}
                    </router-link>
                    <span v-else class="font-medium text-slate-900 dark:text-slate-200">
                      {{ $t(crumb.title, crumb.title) }}
                    </span>
                    <ChevronRightIcon
                      v-if="index < breadcrumbs.length - 1"
                      class="ml-2 h-4 w-4 text-slate-400"
                    />
                  </div>
                </li>
              </ol>
            </nav>
          </div>

          <!-- Router view with transition -->
          <router-view v-slot="{ Component }">
            <transition name="fade" mode="out-in">
              <component :is="Component" />
            </transition>
          </router-view>
        </div>
      </main>
    </div>

    <!-- Notifications -->
    <NotificationPanel />

    <!-- System alerts overlay -->
    <SystemAlertsOverlay />

    <!-- Keyboard shortcuts modal -->
    <KeyboardShortcutsModal />

    <!-- Command palette (Ctrl+K) -->
    <CommandPalette />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
// #3935 : résolution du parent via router.getRoutes() (records aplatis)
// au lieu du tableau routes de premier niveau (qui ne contient que login/
// logout//not-found — les enfants comme company-detail en sont absents).
import { ChevronRightIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useRealtimeStore } from '@/stores/realtime'
import { useDashboardStore } from '@/stores/dashboard'
import Sidebar from '@/components/layout/Sidebar.vue'
import Header from '@/components/layout/Header.vue'
import NotificationPanel from '@/components/notifications/NotificationPanel.vue'
import SystemAlertsOverlay from '@/components/alerts/SystemAlertsOverlay.vue'
import KeyboardShortcutsModal from '@/components/common/KeyboardShortcutsModal.vue'
import CommandPalette from '@/components/common/CommandPalette.vue'
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const realtimeStore = useRealtimeStore()
const dashboardStore = useDashboardStore()

const sidebarOpen = ref(false)

// Issue #5632 : verrouiller le scroll du body quand la sidebar est ouverte
// sur mobile (< lg) pour éviter le double-scroll derrière l'overlay.
watch(sidebarOpen, (open) => {
  if (typeof document !== 'undefined') {
    document.body.style.overflow = open ? 'hidden' : ''
  }
})

// Close sidebar on route change (navigation on mobile).
watch(route, () => { sidebarOpen.value = false })

// Initialize keyboard shortcuts
useKeyboardShortcuts()

// Breadcrumbs computation
const breadcrumbs = computed(() => {
  const crumbs = [{ name: 'dashboard', title: 'Tableau de bord', path: '/' }]

  if (route.meta.parent) {
    // Find parent route in the FLATTENED route records (#3935) — children
    // like company-detail are not in the top-level routes array.
    const parentRoute = router.getRoutes().find(r => r.name === route.meta.parent)
    if (parentRoute) {
      crumbs.push({
        name: parentRoute.name,
        title: parentRoute.meta.title,
        path: parentRoute.path
      })
    }
  }

  if (route.name !== 'dashboard') {
    crumbs.push({
      name: route.name,
      title: route.meta.title,
      path: route.path
    })
  }

  return crumbs
})

// Initialize stores and connections
onMounted(async () => {
  // Check authentication
  await authStore.checkAuth()

  // Connect to real-time services
  realtimeStore.connect()

  // Load initial dashboard data
  try {
    await dashboardStore.fetchDashboardData()
  } catch (error) {
    console.error('Failed to load dashboard data:', error)
  }
})

onUnmounted(() => {
  // Cleanup connections
  realtimeStore.disconnect()
})
</script>