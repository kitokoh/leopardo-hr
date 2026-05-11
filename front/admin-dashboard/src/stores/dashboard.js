import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useDashboardStore = defineStore('dashboard', () => {
  // State
  const stats = ref({
    totalUsers: 0,
    totalCompanies: 0,
    activeSubscriptions: 0,
    monthlyRevenue: 0,
    newUsersToday: 0,
    newCompaniesToday: 0,
    supportTickets: 0,
    systemHealth: 'good'
  })

  const recentActivities = ref([])
  const systemAlerts = ref([])
  const isLoading = ref(false)
  const lastUpdated = ref(null)

  // Getters
  const formattedRevenue = computed(() => {
    return new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'EUR'
    }).format(stats.value.monthlyRevenue)
  })

  const healthStatus = computed(() => {
    const status = stats.value.systemHealth
    const statusMap = {
      good: { label: 'Excellent', color: 'green', icon: 'CheckCircleIcon' },
      warning: { label: 'Attention', color: 'yellow', icon: 'ExclamationTriangleIcon' },
      error: { label: 'Problème', color: 'red', icon: 'XCircleIcon' }
    }
    return statusMap[status] || statusMap.good
  })

  const criticalAlerts = computed(() => {
    return systemAlerts.value.filter(alert => alert.level === 'critical')
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

      stats.value = statsResponse.data
      recentActivities.value = activitiesResponse.data
      systemAlerts.value = alertsResponse.data
      lastUpdated.value = new Date()

    } catch (error) {
      console.error('Erreur lors du chargement du dashboard:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  async function refreshStats() {
    try {
      const response = await api.get('/admin/dashboard/stats')
      stats.value = response.data
      lastUpdated.value = new Date()
    } catch (error) {
      console.error('Erreur lors du rafraîchissement des stats:', error)
    }
  }

  async function dismissAlert(alertId) {
    try {
      await api.post(`/admin/dashboard/alerts/${alertId}/dismiss`)
      systemAlerts.value = systemAlerts.value.filter(alert => alert.id !== alertId)
    } catch (error) {
      console.error('Erreur lors de la suppression de l\'alerte:', error)
    }
  }

  function addRealtimeActivity(activity) {
    recentActivities.value.unshift(activity)
    // Garder seulement les 50 dernières activités
    if (recentActivities.value.length > 50) {
      recentActivities.value = recentActivities.value.slice(0, 50)
    }
  }

  function updateStats(newStats) {
    stats.value = { ...stats.value, ...newStats }
    lastUpdated.value = new Date()
  }

  return {
    // State
    stats,
    recentActivities,
    systemAlerts,
    isLoading,
    lastUpdated,

    // Getters
    formattedRevenue,
    healthStatus,
    criticalAlerts,

    // Actions
    fetchDashboardData,
    refreshStats,
    dismissAlert,
    addRealtimeActivity,
    updateStats
  }
})