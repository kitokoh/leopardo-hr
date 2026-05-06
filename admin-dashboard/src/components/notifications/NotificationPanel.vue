<template>
  <!-- Notification container -->
  <div
    aria-live="assertive"
    class="pointer-events-none fixed inset-0 flex items-end px-4 py-6 sm:items-start sm:p-6 z-50"
  >
    <div class="flex w-full flex-col items-center space-y-4 sm:items-end">
      <!-- Notification -->
      <transition
        v-for="notification in visibleNotifications"
        :key="notification.id"
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition ease-in duration-100"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div class="pointer-events-auto w-full max-w-sm overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5">
          <div class="p-4">
            <div class="flex items-start">
              <div class="flex-shrink-0">
                <component
                  :is="getNotificationIcon(notification.type)"
                  :class="[
                    'h-6 w-6',
                    getNotificationIconColor(notification.type)
                  ]"
                />
              </div>
              <div class="ml-3 w-0 flex-1 pt-0.5">
                <p class="text-sm font-medium text-gray-900">
                  {{ notification.title }}
                </p>
                <p class="mt-1 text-sm text-gray-500">
                  {{ notification.message }}
                </p>
                <div v-if="notification.action" class="mt-3 flex space-x-7">
                  <button
                    @click="handleNotificationAction(notification)"
                    class="rounded-md bg-white text-sm font-medium text-indigo-600 hover:text-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                  >
                    {{ notification.action.label }}
                  </button>
                </div>
              </div>
              <div class="ml-4 flex flex-shrink-0">
                <button
                  @click="dismissNotification(notification.id)"
                  class="inline-flex rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                  <XMarkIcon class="h-5 w-5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </transition>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import {
  CheckCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  XCircleIcon,
  UserIcon,
  BuildingOfficeIcon,
  CreditCardIcon,
  ChatBubbleLeftRightIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'
import { useRealtimeStore } from '@/stores/realtime'

const realtimeStore = useRealtimeStore()

// Local notifications for toast-style display
const localNotifications = ref([])

// Show only the last 3 notifications as toasts
const visibleNotifications = computed(() => {
  return localNotifications.value.slice(0, 3)
})

// Auto-dismiss timer
let dismissTimers = new Map()

onMounted(() => {
  // Listen for new real-time notifications
  const unsubscribe = realtimeStore.$subscribe((mutation, state) => {
    if (mutation.events?.some(event => event.key === 'notifications' && event.type === 'add')) {
      const newNotification = state.notifications[0]
      if (newNotification && !localNotifications.value.find(n => n.id === newNotification.id)) {
        showNotification(newNotification)
      }
    }
  })

  // Cleanup subscription
  onUnmounted(() => {
    unsubscribe()
    // Clear all timers
    dismissTimers.forEach(timer => clearTimeout(timer))
    dismissTimers.clear()
  })
})

// Methods
function showNotification(notification) {
  // Add to local notifications
  localNotifications.value.unshift({
    ...notification,
    timestamp: Date.now()
  })

  // Auto-dismiss after 5 seconds (unless it's critical)
  if (notification.priority !== 'critical') {
    const timer = setTimeout(() => {
      dismissNotification(notification.id)
    }, 5000)
    dismissTimers.set(notification.id, timer)
  }

  // Keep only last 10 notifications
  if (localNotifications.value.length > 10) {
    localNotifications.value = localNotifications.value.slice(0, 10)
  }
}

function dismissNotification(notificationId) {
  const index = localNotifications.value.findIndex(n => n.id === notificationId)
  if (index > -1) {
    localNotifications.value.splice(index, 1)
  }

  // Clear timer if exists
  const timer = dismissTimers.get(notificationId)
  if (timer) {
    clearTimeout(timer)
    dismissTimers.delete(notificationId)
  }
}

function handleNotificationAction(notification) {
  if (notification.action?.callback) {
    notification.action.callback()
  }
  dismissNotification(notification.id)
}

function getNotificationIcon(type) {
  const iconMap = {
    success: CheckCircleIcon,
    error: XCircleIcon,
    warning: ExclamationTriangleIcon,
    info: InformationCircleIcon,
    user_registered: UserIcon,
    subscription_created: CreditCardIcon,
    subscription_cancelled: XCircleIcon,
    support_ticket: ChatBubbleLeftRightIcon,
    system_alert: ExclamationTriangleIcon,
    company_created: BuildingOfficeIcon
  }
  return iconMap[type] || InformationCircleIcon
}

function getNotificationIconColor(type) {
  const colorMap = {
    success: 'text-green-400',
    error: 'text-red-400',
    warning: 'text-yellow-400',
    info: 'text-blue-400',
    user_registered: 'text-green-400',
    subscription_created: 'text-blue-400',
    subscription_cancelled: 'text-red-400',
    support_ticket: 'text-yellow-400',
    system_alert: 'text-red-400',
    company_created: 'text-green-400'
  }
  return colorMap[type] || 'text-gray-400'
}

// Expose method to manually show notifications
defineExpose({
  showNotification
})
</script>