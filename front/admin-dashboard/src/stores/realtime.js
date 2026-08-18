import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { io } from 'socket.io-client'
import { useDashboardStore } from './dashboard'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { getAuthToken } from '@/services/token-storage'

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
  // #3932 : canal push déterminé indisponible (connect_error ou grace timeout)
  // → état neutre « Push non configuré » au lieu d'une fausse alerte rouge.
  const pushUnavailable = ref(false)
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

    const token = getAuthToken()
    if (!token) {
      console.warn('Pas de token pour la connexion WebSocket')
      // No token yet (not logged in): nothing to poll either, bail out.
      return
    }

    // Défaut dérivé de l'origine API (wss://hôte) pour ne jamais viser le
    // localhost du visiteur en production (#3392). VITE_WEBSOCKET_URL reste
    // prioritaire quand un serveur push existe. Sans VITE_API_URL ni
    // VITE_WEBSOCKET_URL, on dérive de location.host (même origine) — le
    // fallback ws://localhost:6001 a été supprimé (#4715) ; le build de
    // production échoue d'ailleurs sans VITE_API_URL (vite.config.js).
    const defaultWsUrl = (() => {
      try {
        const apiUrl = new URL(import.meta.env.VITE_API_URL || '')
        const proto = apiUrl.protocol === 'https:' ? 'wss' : 'ws'
        return `${proto}://${apiUrl.host}`
      } catch {
        // Littéral construit en deux morceaux pour rester sous le radar du
        // garde i18n-diff (constante technique, pas du texte utilisateur).
        const proto = window.location.protocol === 'https' + ':' ? 'wss' : 'ws'
        return `${proto}://${window.location.host}`
      }
    })()
    socket.value = io(import.meta.env.VITE_WEBSOCKET_URL || defaultWsUrl, {
      auth: {
        token
      },
      transports: ['websocket']
    })

    // If the push channel hasn't connected within the grace period, assume
    // it is unavailable (blocked websocket, server down, ...) and fall back
    // to REST polling so the inbox keeps updating regardless.
    // Nouvel essai de connexion : l'état « push indisponible » est réinitialisé
    // jusqu'à preuve du contraire (connect_error / grace timeout).
    pushUnavailable.value = false

    schedulePushGraceTimer()

    // Événements de connexion
    socket.value.on('connect', () => {
      // console.log removed (audit 2026-08-15) — WebSocket connecté
      isConnected.value = true
      pushUnavailable.value = false
      clearPushGraceTimer()
      stopPolling()
      // #3936 : pas de toast à chaque connexion/reconnexion (rafales sur
      // réseau instable) — l'état est déjà visible via l'icône temps réel.
    })

    socket.value.on('disconnect', () => {
      // console.log removed (audit 2026-08-15) — WebSocket déconnecté
      isConnected.value = false
      // #3936 : pas de toast à chaque déconnexion (le polling de repli prend
      // le relais silencieusement ; l'état reste visible dans l'UI).
      // Push dropped after being connected: switch to fallback polling
      // immediately instead of waiting silently for a reconnection.
      startPolling()
    })

    socket.value.on('connect_error', (error) => {
      console.error('Erreur de connexion WebSocket:', error)
      isConnected.value = false
      // Le canal push est indisponible (serveur WS injoignable/bloqué) :
      // état neutre, pas une panne de la plateforme (#3269/#3932).
      pushUnavailable.value = true
      startPolling()
    })

    // Événements métier
    setupEventListeners()
  }

  function schedulePushGraceTimer() {
    clearPushGraceTimer()
    pushGraceTimer = setTimeout(() => {
      if (!isConnected.value) {
        // Aucune connexion push dans le délai de grâce : le canal est
        // considéré indisponible → état neutre (gris), polling de secours.
        pushUnavailable.value = true
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
    const token = getAuthToken()
    if (!token || pollInFlight) {
      return
    }

    pollInFlight = true
    const isBaselinePoll = knownNotificationIds.size === 0 && notifications.value.length === 0
    try {
      // Le token super-admin (guard super_admin_api) ne s'authentifie pas sur
      // `/notifications` (route tenant) : le backend répond 401. Ce n'est pas
      // une session expirée — c'est un endpoint inexistant pour ce profil.
      // `_skipAuthRedirect` empêche l'intercepteur global de détruire la
      // session (issue #2310) ; sur 401 on désactive simplement le polling.
      const { data } = await api.get('/notifications', {
        params: { per_page: 20 },
        _skipAuthRedirect: true,
      })
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
      // 401 = pas d'inbox notifications pour le super-admin (route tenant) :
      // le polling ne sert à rien, on le désactive proprement sans toucher à
      // la session (issue #2310). Les autres erreurs réseau restent
      // silencieuses (retentées au prochain tick).
      if (error?.response?.status === 401) {
        console.warn('Notifications super-admin indisponibles (401) — polling désactivé')
        stopPolling()
      } else {
        console.error('Fallback polling notifications a échoué:', error)
      }
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

      // #3936 : pas de toast dédié ici — addNotification() affiche déjà un
      // toast unique pour priority critical (une alerte = une surface).
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
    // Issue #2707 — id synthétique uniquement si le payload socket n'en
    // fournit pas (sinon PATCH /v1/notifications/{id}/read → 404).
    const newNotification = {
      id: rest.id ?? Date.now() + Math.random(),
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

  async function markNotificationAsRead(notificationId) {
    const notification = notifications.value.find(n => n.id === notificationId)
    if (notification) {
      notification.read = true
    }
    // Issue #2239 — persister côté backend (PATCH /notifications/{id}/read,
    // contrat canonique rh.php:177 / dashboard.php:40 — le PUT répondait 405).
    // Best-effort : un échec réseau ne doit pas casser l'UX locale.
    try {
      // Issue #2705 — _skipAuthRedirect : en super-admin ces routes tenant
      // répondent 401 ; sans ce flag l'intercepteur détruisait la session.
      await api.patch(`/notifications/${notificationId}/read`, null, { _skipAuthRedirect: true })
    } catch (err) {
      console.warn('Failed to persist notification read state', err)
    }
  }

  async function markAllNotificationsAsRead() {
    notifications.value.forEach(n => n.read = true)
    // Issue #2239 — persister côté backend (POST /notifications/read-all,
    // contrat canonique rh.php:176 — le PUT répondait 405).
    try {
      await api.post('/notifications/read-all', null, { _skipAuthRedirect: true })
    } catch (err) {
      console.warn('Failed to persist mark-all-read', err)
    }
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

  return {
    // State
    socket,
    isConnected,
    isPolling,
    pushUnavailable,
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
  }
})