import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { io } from 'socket.io-client'
import { useDashboardStore } from './dashboard'
import { useToast } from 'vue-toastification'

export const useRealtimeStore = defineStore('realtime', () => {
  // State
  const socket = ref(null)
  const isConnected = ref(false)
  const notifications = ref([])
  const onlineUsers = ref([])
  const globePoints = ref([])

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
      return
    }

    socket.value = io(import.meta.env.VITE_WEBSOCKET_URL || 'ws://localhost:6001', {
      auth: {
        token
      },
      transports: ['websocket']
    })

    // Événements de connexion
    socket.value.on('connect', () => {
      console.log('WebSocket connecté')
      isConnected.value = true
      toast.success('Connexion temps réel établie')
    })

    socket.value.on('disconnect', () => {
      console.log('WebSocket déconnecté')
      isConnected.value = false
      toast.warning('Connexion temps réel perdue')
    })

    socket.value.on('connect_error', (error) => {
      console.error('Erreur de connexion WebSocket:', error)
      isConnected.value = false
    })

    // Événements métier
    setupEventListeners()
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
    if (socket.value) {
      socket.value.disconnect()
      socket.value = null
      isConnected.value = false
    }
  }

  function addNotification(notification) {
    const newNotification = {
      id: Date.now() + Math.random(),
      read: false,
      ...notification
    }

    notifications.value.unshift(newNotification)

    // Garder seulement les 100 dernières notifications
    if (notifications.value.length > 100) {
      notifications.value = notifications.value.slice(0, 100)
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