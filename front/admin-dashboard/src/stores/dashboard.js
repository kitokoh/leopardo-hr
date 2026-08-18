import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import { useLocaleStore } from '@/stores/locale'
import { toIntlLocale } from '@/i18n/index.js'

export const useDashboardStore = defineStore('dashboard', () => {
  const localeStore = useLocaleStore()

  // State
  const stats = ref({
    totalUsers: 0,
    totalCompanies: 0,
    activeSubscriptions: 0,
    monthlyRevenue: 0,
    newUsersToday: 0,
    newCompaniesToday: 0,
    supportTickets: 0,
    systemHealth: 'unknown'
  })

  const recentActivities = ref([])
  const systemAlerts = ref([])
  const isLoading = ref(false)
  const lastUpdated = ref(null)
  const loadError = ref(null)

  // Getters
  const formattedRevenue = computed(() => {
    return new Intl.NumberFormat(toIntlLocale(localeStore.current), {
      style: 'currency',
      currency: 'EUR'
    }).format(stats.value.monthlyRevenue)
  })

  const healthStatus = computed(() => {
    const status = stats.value.systemHealth
    const statusMap = {
      good: { label: 'Excellent', color: 'green', icon: 'CheckCircleIcon' },
      warning: { label: 'Attention', color: 'yellow', icon: 'ExclamationTriangleIcon' },
      error: { label: 'Problème', color: 'red', icon: 'XCircleIcon' },
      unknown: { label: 'Indisponible', color: 'gray', icon: 'QuestionMarkCircleIcon' }
    }
    return statusMap[status] || statusMap.unknown
  })

  const criticalAlerts = computed(() => {
    // Garde de forme : ne filtre que si systemAlerts est bien un tableau
    // (voir #2747 — l'enveloppe {data:[...]} aurait pu s'y glisser).
    return Array.isArray(systemAlerts.value)
      ? systemAlerts.value.filter(alert => alert.level === 'critical')
      : []
  })

  // Actions
  async function fetchDashboardData() {
    isLoading.value = true
    try {
      const [statsResponse, activitiesResponse, alertsResponse] = await Promise.all([
        api.get('/admin/dashboard/stats'),
        api.get('/admin/dashboard/activities'),
        api.get('/admin/dashboard/alerts')
      ])

      stats.value = { systemHealth: 'unknown', ...statsResponse.data }
      loadError.value = null
      // Issue #2747 — les endpoints /admin/dashboard/{activities,alerts} renvoient
      // une enveloppe Laravel {data: [...]} : déballer avant d'affecter, sinon
      // criticalAlerts() (filter) explose sur l'objet enveloppe → badge alertes /
      // SystemAlertsOverlay cassés sur le happy path connecté.
      recentActivities.value = activitiesResponse.data?.data ?? []
      systemAlerts.value = alertsResponse.data?.data ?? []
      lastUpdated.value = new Date()

    } catch (error) {
      loadError.value = 'Impossible de charger les métriques du dashboard.'
      stats.value = { ...stats.value, systemHealth: 'unknown' }
      console.error('Erreur lors du chargement du dashboard:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  async function refreshStats() {
    try {
      const response = await api.get('/admin/dashboard/stats')
      stats.value = { systemHealth: 'unknown', ...response.data }
      loadError.value = null
      lastUpdated.value = new Date()
    } catch (error) {
      loadError.value = 'Impossible de rafraîchir les métriques du dashboard.'
      stats.value = { ...stats.value, systemHealth: 'unknown' }
      console.error('Erreur lors du rafraîchissement des stats:', error)
    }
  }

  function addRealtimeActivity(activity) {
    if (!Array.isArray(recentActivities.value)) {
      recentActivities.value = []
    }
    recentActivities.value.unshift(activity)
    // Garder seulement les 50 dernières activités
    if (recentActivities.value.length > 50) {
      recentActivities.value = recentActivities.value.slice(0, 50)
    }
  }

  return {
    // State
    stats,
    recentActivities,
    systemAlerts,
    isLoading,
    lastUpdated,
    loadError,

    // Getters
    formattedRevenue,
    healthStatus,
    criticalAlerts,

    // Actions
    fetchDashboardData,
    refreshStats,
    addRealtimeActivity,
  }
})