<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 transition-colors duration-300">
    <!-- Skip to content (WCAG 2.4.1) -->
    <a
      href="#main-content"
      class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[100] focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-indigo-600 focus:shadow-lg"
    >Aller au contenu principal</a>

    <!-- Sidebar -->
    <Sidebar
      :is-open="sidebarOpen"
      @close="sidebarOpen = false"
      class="fixed inset-y-0 left-0 z-50 lg:static lg:inset-0"
    />

    <!-- Main content -->
    <div class="lg:pl-64">
      <!-- Header -->
      <Header
        @toggle-sidebar="sidebarOpen = !sidebarOpen"
        class="sticky top-0 z-40"
      />

      <!-- Page content -->
      <main id="main-content" class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <!-- Page header -->
          <div class="mb-6" v-if="$route.meta.title">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ $route.meta.title }}
            </h1>
            <nav class="mt-2" v-if="breadcrumbs.length > 1">
              <ol class="flex items-center space-x-2 text-sm text-gray-500">
                <li v-for="(crumb, index) in breadcrumbs" :key="crumb.name">
                  <div class="flex items-center">
                    <router-link
                      v-if="index < breadcrumbs.length - 1"
                      :to="crumb.path"
                      class="hover:text-gray-700"
                    >
                      {{ crumb.title }}
                    </router-link>
                    <span v-else class="font-medium text-gray-900">
                      {{ crumb.title }}
                    </span>
                    <ChevronRightIcon
                      v-if="index < breadcrumbs.length - 1"
                      class="ml-2 h-4 w-4 text-gray-400"
                    />
                  </div>
                </li>
              </ol>
            </nav>
          </div>

          <!-- Router view -->
          <router-view />
        </div>
      </main>
    </div>

    <!-- Notifications -->
    <NotificationPanel />

    <!-- System alerts overlay -->
    <SystemAlertsOverlay />

    <!-- Keyboard shortcuts modal -->
    <KeyboardShortcutsModal />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { ChevronRightIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useRealtimeStore } from '@/stores/realtime'
import { useDashboardStore } from '@/stores/dashboard'
import Sidebar from '@/components/layout/Sidebar.vue'
import Header from '@/components/layout/Header.vue'
import NotificationPanel from '@/components/notifications/NotificationPanel.vue'
import SystemAlertsOverlay from '@/components/alerts/SystemAlertsOverlay.vue'
import KeyboardShortcutsModal from '@/components/common/KeyboardShortcutsModal.vue'
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts'

const route = useRoute()
const authStore = useAuthStore()
const realtimeStore = useRealtimeStore()
const dashboardStore = useDashboardStore()

const sidebarOpen = ref(false)

// Initialize keyboard shortcuts
useKeyboardShortcuts()

// Breadcrumbs computation
const breadcrumbs = computed(() => {
  const crumbs = [{ name: 'dashboard', title: 'Tableau de bord', path: '/' }]

  if (route.meta.parent) {
    // Find parent route
    const parentRoute = routes.find(r => r.name === route.meta.parent)
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