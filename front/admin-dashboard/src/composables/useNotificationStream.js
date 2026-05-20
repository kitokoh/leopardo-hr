import { ref, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'

export function useNotificationStream() {
  const notifications = ref([])
  const unreadCount = ref(0)
  const isConnected = ref(false)
  let eventSource = null
  let reconnectTimer = null

  function connect() {
    const authStore = useAuthStore()
    const token = authStore.token
    if (!token) return

    const apiUrl = import.meta.env.VITE_API_URL || ''
    const url = `${apiUrl}/api/v1/notifications/stream`

    // EventSource doesn't support custom headers natively,
    // so we pass the token as a query parameter
    eventSource = new EventSource(`${url}?token=${encodeURIComponent(token)}`)

    eventSource.addEventListener('notification', (event) => {
      try {
        const data = JSON.parse(event.data)
        if (data.notifications) {
          notifications.value = [...data.notifications, ...notifications.value].slice(0, 50)
        }
        if (typeof data.unread_count === 'number') {
          unreadCount.value = data.unread_count
        }
      } catch {
        // ignore malformed events
      }
    })

    eventSource.addEventListener('timeout', () => {
      disconnect()
      scheduleReconnect()
    })

    eventSource.addEventListener('error', () => {
      disconnect()
      scheduleReconnect()
    })

    eventSource.onopen = () => {
      isConnected.value = true
    }
  }

  function disconnect() {
    if (eventSource) {
      eventSource.close()
      eventSource = null
    }
    isConnected.value = false
  }

  function scheduleReconnect() {
    if (reconnectTimer) clearTimeout(reconnectTimer)
    reconnectTimer = setTimeout(() => {
      connect()
    }, 10000)
  }

  onMounted(() => {
    connect()
  })

  onUnmounted(() => {
    disconnect()
    if (reconnectTimer) clearTimeout(reconnectTimer)
  })

  return { notifications, unreadCount, isConnected, connect, disconnect }
}
