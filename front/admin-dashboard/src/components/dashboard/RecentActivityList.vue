<template>
  <div class="flow-root">
    <ul role="list" class="-mb-8">
      <li
        v-for="(activity, activityIdx) in activities"
        :key="activity.id"
      >
        <div class="relative pb-8">
          <span
            v-if="activityIdx !== activities.length - 1"
            class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"
          ></span>
          <div class="relative flex space-x-3">
            <div>
              <span
                :class="[
                  'h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white',
                  getActivityColor(activity.type)
                ]"
              >
                <component
                  :is="getActivityIcon(activity.type)"
                  class="h-4 w-4 text-white"
                />
              </span>
            </div>
            <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
              <div>
                <p class="text-sm text-gray-500">
                  {{ activity.description }}
                  <a
                    v-if="activity.user"
                    href="#"
                    class="font-medium text-gray-900 hover:text-gray-700"
                  >
                    {{ activity.user.name }}
                  </a>
                  <a
                    v-if="activity.company"
                    href="#"
                    class="font-medium text-gray-900 hover:text-gray-700"
                  >
                    {{ activity.company.name }}
                  </a>
                </p>
                <div v-if="activity.metadata" class="mt-1">
                  <div
                    v-for="(value, key) in activity.metadata"
                    :key="key"
                    class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800 mr-2"
                  >
                    {{ key }}: {{ value }}
                  </div>
                </div>
              </div>
              <div class="whitespace-nowrap text-right text-sm text-gray-500">
                <time :datetime="activity.timestamp">
                  {{ formatTime(activity.timestamp) }}
                </time>
              </div>
            </div>
          </div>
        </div>
      </li>
    </ul>

    <!-- Empty state -->
    <div v-if="activities.length === 0" class="text-center py-12">
      <ClockIcon class="mx-auto h-12 w-12 text-gray-400" />
      <h3 class="mt-2 text-sm font-medium text-gray-900">Aucune activité récente</h3>
      <p class="mt-1 text-sm text-gray-500">
        Les activités récentes apparaîtront ici en temps réel.
      </p>
    </div>

    <!-- Load more -->
    <div v-if="activities.length > 0" class="mt-6 text-center">
      <button
        @click="loadMore"
        :disabled="isLoading"
        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
      >
        <span v-if="isLoading" class="flex items-center">
          <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Chargement...
        </span>
        <span v-else>Voir plus</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import {
  UserPlusIcon,
  BuildingOfficeIcon,
  CreditCardIcon,
  XCircleIcon,
  ChatBubbleLeftRightIcon,
  ExclamationTriangleIcon,
  ClockIcon,
  CogIcon
} from '@heroicons/vue/24/outline'
import { useDashboardStore } from '@/stores/dashboard'

const dashboardStore = useDashboardStore()
const isLoading = ref(false)

// Get activities from store
const activities = computed(() => dashboardStore.recentActivities)

onMounted(() => {
  // Activities are loaded by the dashboard store
})

// Methods
function getActivityIcon(type) {
  const iconMap = {
    user_registered: UserPlusIcon,
    company_created: BuildingOfficeIcon,
    subscription_created: CreditCardIcon,
    subscription_cancelled: XCircleIcon,
    support_ticket_created: ChatBubbleLeftRightIcon,
    system_alert: ExclamationTriangleIcon,
    system_maintenance: CogIcon
  }
  return iconMap[type] || ClockIcon
}

function getActivityColor(type) {
  const colorMap = {
    user_registered: 'bg-green-500',
    company_created: 'bg-blue-500',
    subscription_created: 'bg-purple-500',
    subscription_cancelled: 'bg-red-500',
    support_ticket_created: 'bg-yellow-500',
    system_alert: 'bg-red-600',
    system_maintenance: 'bg-gray-500'
  }
  return colorMap[type] || 'bg-gray-400'
}

function formatTime(timestamp) {
  const now = new Date()
  const time = new Date(timestamp)
  const diff = now - time

  if (diff < 60000) return 'À l\'instant'
  if (diff < 3600000) return `${Math.floor(diff / 60000)}m`
  if (diff < 86400000) return `${Math.floor(diff / 3600000)}h`
  if (diff < 604800000) return `${Math.floor(diff / 86400000)}j`
  return time.toLocaleDateString('fr-FR')
}

async function loadMore() {
  isLoading.value = true

  try {
    // Simulate loading more activities
    await new Promise(resolve => setTimeout(resolve, 1000))

    // In a real app, this would fetch more data from the API
    console.log('Loading more activities...')
  } catch (error) {
    console.error('Failed to load more activities:', error)
  } finally {
    isLoading.value = false
  }
}
</script>