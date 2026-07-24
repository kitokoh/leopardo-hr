import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { io } from 'socket.io-client'
import { useDashboardStore } from './dashboard'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

// PA2-COMM-013 — Fallback polling robuste : quand le canal push (Socket.IO)
// est indisponible (proxy/firewall bloquant les websockets, serveur
// websocket down, etc.), on ne doit pas perdre les notifications. Si aucune
// connexion push n'est etablie apres ce delai, on bascule sur un polling
// REST regulier de l'inbox existante.
const PUSH_CONNECT_GRACE_MS = 8000
const POLL_INTERVAL_MS = 30000

export const useRealtimeStore = defineStore('realtime', () => {
  // State
  const socket = ref(null)
  const isConnected = ref(false)
  const isPolling = ref(false)
  const notifications = ref([])
  const onlineUsers = ref([])
  const globePoints = ref([])

  // Internal fallback-polling bookkeeping
  let pollTimer = null
  let pushGraceTimer = null
  let knownNotificationIds = new Set()
  let pollInFlight = false

  // Services
  const toast = useToast()
  const dashboardStore = useDashboardStore()

  // Getters
  const unreadNotifications = computed(() => {
    return notifications.value.filter(n => !n.read).length
  })

  const recentNotifications = computed(() => {
    return notifications.value.slice(0, 10)
  })

  // Actions
  function connect() {
    if (socket.value?.connected) {
      return
    }

    const token = localStorage.getItem('admin_token')
    if (!token) {
      console.warn('Pas de token pour la connexion WebSocket')
      // No token yet (not logged in): nothing to poll either, bail out.
      return
    }

    socket.value = io(import.meta.env.VITE_WEBSOCKET_URL || 'ws://localhost:6001', {
      auth: {
        token
      },
      transports: ['websocket']
    })

    // If the push channel hasn't connected within the grace period, assume
    // it is unavailable (blocked websocket, server down, ...) and fall back
    // to REST polling so the inbox keeps updating regardless.
    schedulePushGraceTimer()

    // Événements de connexion
    socket.value.on('connect', () => {
      console.log('WebSocket connecté')
      isConnected.value = true
      clearPushGraceTimer()
      stopPolling()
      toast.success('Connexion temps réel établie')
    })

    socket.value.on('disconnect', () => {
      console.log('WebSocket déconnecté')
      isConnected.value = false
      toast.warning('Connexion temps réel perdue')
      // Push dropped after being connected: switch to fallback polling
      // immediately instead of waiting silently for a reconnection.
      startPolling()
    })

    socket.value.on('connect_error', (error) => {
      console.error('Erreur de connexion WebSocket:', error)
      isConnected.value = false
      startPolling()
    })

    // Événements métier
    setupEventListeners()
  }

  function schedulePushGraceTimer() {
    clearPushGraceTimer()
    pushGraceTimer = setTimeout(() => {
      if (!isConnected.value) {
        startPolling()
      }
    }, PUSH_CONNECT_GRACE_MS)
  }

  function clearPushGraceTimer() {
    if (pushGraceTimer) {
      clearTimeout(pushGraceTimer)
      pushGraceTimer = null
    }
  }

  /**
   * Fallback polling (PA2-COMM-013): periodically fetches the REST inbox
   * endpoint so notifications keep arriving even when the push channel
   * (Socket.IO) never connects or drops. Runs alongside the socket and
   * stops itself automatically once push reports connected again.
   */
  function startPolling() {
    if (isPolling.value) {
      return
    }

    isPolling.value = true
    pollNotifications()
    pollTimer = setInterval(pollNotifications, POLL_INTERVAL_MS)
  }

  function stopPolling() {
    if (pollTimer) {
      clearInterval(pollTimer)
      pollTimer = null
    }
    isPolling.value = false
  }

  async function pollNotifications() {
    const token = localStorage.getItem('admin_token')
    if (!token || pollInFlight) {
      return
    }

    pollInFlight = true
    const isBaselinePoll = knownNotificationIds.size === 0 && notifications.value.length === 0
    try {
      const { data } = await api.get('/notifications', { params: { per_page: 20 } })
      const items = Array.isArray(data?.data) ? data.data : []

      // Present newest first, and only surface items we haven't already
      // recorded (from polling or from a previous push connection) to
      // avoid duplicate toasts/badges.
      for (const item of [...items].reverse()) {
        const id = item.id ?? `poll-${item.created_at}-${item.title}`
        if (knownNotificationIds.has(id)) {
          continue
        }
        knownNotificationIds.add(id)

        addNotification({
          id,
          type: item.type || 'info',
          title: item.title || 'Notification',
          message: item.body || item.message || '',
          data: item.data,
          timestamp: item.created_at ? new Date(item.created_at) : new Date(),
          read: Boolean(item.is_read),
          // The very first poll just seeds already-existing notifications;
          // toasting for all of them would spam the admin on every
          // fallback activation (e.g. page reload). Only genuinely new
          // notifications discovered on later polls should toast.
          silent: isBaselinePoll
        })
      }
    } catch (error) {
      console.error('Fallback polling notifications a échoué:', error)
    } finally {
      pollInFlight = false
    }
  }

  function setupEventListeners() {
    if (!socket.value) return

    // Nouvelles inscriptions
    socket.value.on('user.registered', (data) => {
      addNotification({
        type: 'user_registered',
        title: 'Nouvel utilisateur',
        message: `${data.user.name} s'est inscrit`,
        data: data.user,
        timestamp: new Date()
      })

      dashboardStore.addRealtimeActivity({
        type: 'user_registered',
        description: `Nouvel utilisateur: ${data.user.name}`,
        timestamp: new Date(),
        user: data.user
      })
    })

    // Nouveaux abonnements
    socket.value.on('subscription.created', (data) => {
      addNotification({
        type: 'subscription_created',
        title: 'Nouvel abonnement',
        message: `${data.company.name} a souscrit au plan ${data.plan.name}`,
        data: data,
        timestamp: new Date()
      })

      dashboardStore.addRealtimeActivity({
        type: 'subscription_created',
        description: `Nouvel abonnement: ${data.company.name} - ${data.plan.name}`,
        timestamp: new Date(),
        company: data.company
      })
    })

    // Annulations d'abonnement
    socket.value.on('subscription.cancelled', (data) => {
      addNotification({
        type: 'subscription_cancelled',
        title: 'Abonnement annulé',
        message: `${data.company.name} a annulé son abonnement`,
        data: data,
        timestamp: new Date(),
        priority: 'high'
      })
    })

    // Tickets de support
    socket.value.on('support.ticket.created', (data) => {
      addNotification({
        type: 'support_ticket',
        title: 'Nouveau ticket support',
        message: `Ticket #${data.ticket.id} - ${data.ticket.subject}`,
        data: data.ticket,
        timestamp: new Date()
      })
    })

    // Alertes système
    socket.value.on('system.alert', (data) => {
      addNotification({
        type: 'system_alert',
        title: 'Alerte système',
        message: data.message,
        data: data,
        timestamp: new Date(),
        priority: data.level
      })

      if (data.level === 'critical') {
        toast.error(`Alerte critique: ${data.message}`)
      }
    })

    // Points du globe en temps réel
    socket.value.on('globe.point.added', (data) => {
      addGlobePoint(data.point)
    })

    socket.value.on('globe.points.update', (data) => {
      globePoints.value = data.points
    })

    // Utilisateurs en ligne
    socket.value.on('users.online.update', (data) => {
      onlineUsers.value = data.users
    })
  }

  function disconnect() {
    clearPushGraceTimer()
    stopPolling()
    knownNotificationIds = new Set()
    if (socket.value) {
      socket.value.disconnect()
      socket.value = null
      isConnected.value = false
    }
  }

  function addNotification(notification) {
    const { silent, ...rest } = notification
    const newNotification = {
      id: Date.now() + Math.random(),
      read: false,
      ...rest
    }

    notifications.value.unshift(newNotification)

    // Garder seulement les 100 dernières notifications
    if (notifications.value.length > 100) {
      notifications.value = notifications.value.slice(0, 100)
    }

    if (silent) {
      return
    }

    // Toast pour les notifications importantes
    if (notification.priority === 'high' || notification.priority === 'critical') {
      toast.warning(notification.message)
    } else {
      toast.info(notification.message)
    }
  }

  function markNotificationAsRead(notificationId) {
    const notification = notifications.value.find(n => n.id === notificationId)
    if (notification) {
      notification.read = true
    }
  }

  function markAllNotificationsAsRead() {
    notifications.value.forEach(n => n.read = true)
  }

  function clearNotifications() {
    notifications.value = []
  }

  function addGlobePoint(point) {
    globePoints.value.unshift(point)

    // Garder seulement les 1000 derniers points
    if (globePoints.value.length > 1000) {
      globePoints.value = globePoints.value.slice(0, 1000)
    }
  }

  function emit(event, data) {
    if (socket.value?.connected) {
      socket.value.emit(event, data)
    }
  }

  return {
    // State
    socket,
    isConnected,
    isPolling,
    notifications,
    onlineUsers,
    globePoints,

    // Getters
    unreadNotifications,
    recentNotifications,

    // Actions
    connect,
    disconnect,
    addNotification,
    markNotificationAsRead,
    markAllNotificationsAsRead,
    clearNotifications,
    addGlobePoint,
    emit
  }
})