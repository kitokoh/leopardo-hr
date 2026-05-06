<template>
  <div class="space-y-6">
    <!-- Stats Overview -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
      <StatsCard
        title="Utilisateurs Total"
        :value="dashboardStore.stats.totalUsers"
        :change="dashboardStore.stats.newUsersToday"
        change-label="nouveaux aujourd'hui"
        icon="UsersIcon"
        color="blue"
      />
      <StatsCard
        title="Entreprises"
        :value="dashboardStore.stats.totalCompanies"
        :change="dashboardStore.stats.newCompaniesToday"
        change-label="nouvelles aujourd'hui"
        icon="BuildingOfficeIcon"
        color="green"
      />
      <StatsCard
        title="Abonnements Actifs"
        :value="dashboardStore.stats.activeSubscriptions"
        icon="CreditCardIcon"
        color="purple"
      />
      <StatsCard
        title="Revenus Mensuels"
        :value="dashboardStore.formattedRevenue"
        icon="CurrencyEuroIcon"
        color="yellow"
      />
    </div>

    <!-- System Health & Alerts -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <!-- System Health -->
      <div class="lg:col-span-1">
        <SystemHealthCard />
      </div>

      <!-- Quick Actions -->
      <div class="lg:col-span-2">
        <QuickActionsCard />
      </div>
    </div>

    <!-- Charts and Analytics -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- User Growth Chart -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">Croissance des Utilisateurs</h3>
          <select class="text-sm border-gray-300 rounded-md">
            <option>7 derniers jours</option>
            <option>30 derniers jours</option>
            <option>3 derniers mois</option>
          </select>
        </div>
        <UserGrowthChart />
      </div>

      <!-- Revenue Chart -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">Revenus</h3>
          <select class="text-sm border-gray-300 rounded-md">
            <option>Ce mois</option>
            <option>3 derniers mois</option>
            <option>Cette année</option>
          </select>
        </div>
        <RevenueChart />
      </div>
    </div>

    <!-- Recent Activity & Support -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- Recent Activity -->
      <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900">Activité Récente</h3>
            <span class="flex items-center text-sm text-gray-500">
              <div class="h-2 w-2 rounded-full bg-green-400 mr-2"></div>
              Temps réel
            </span>
          </div>
        </div>
        <RecentActivityList />
      </div>

      <!-- Support Tickets -->
      <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900">Tickets Support</h3>
            <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">
              {{ dashboardStore.stats.supportTickets }} en attente
            </span>
          </div>
        </div>
        <SupportTicketsList />
      </div>
    </div>

    <!-- Globe View (if enabled) -->
    <div v-if="showGlobe" class="bg-white rounded-lg shadow p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-medium text-gray-900">Activité Mondiale</h3>
        <button
          @click="showGlobe = false"
          class="text-gray-400 hover:text-gray-500"
        >
          <XMarkIcon class="h-5 w-5" />
        </button>
      </div>
      <div class="h-96">
        <MiniGlobe />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import { useDashboardStore } from '@/stores/dashboard'
import { useRealtimeStore } from '@/stores/realtime'

// Components
import StatsCard from '@/components/dashboard/StatsCard.vue'
import SystemHealthCard from '@/components/dashboard/SystemHealthCard.vue'
import QuickActionsCard from '@/components/dashboard/QuickActionsCard.vue'
import UserGrowthChart from '@/components/charts/UserGrowthChart.vue'
import RevenueChart from '@/components/charts/RevenueChart.vue'
import RecentActivityList from '@/components/dashboard/RecentActivityList.vue'
import SupportTicketsList from '@/components/dashboard/SupportTicketsList.vue'
import MiniGlobe from '@/components/globe/MiniGlobe.vue'

const dashboardStore = useDashboardStore()
const realtimeStore = useRealtimeStore()

const showGlobe = ref(true)

onMounted(async () => {
  // Load dashboard data if not already loaded
  if (!dashboardStore.lastUpdated) {
    try {
      await dashboardStore.fetchDashboardData()
    } catch (error) {
      console.error('Failed to load dashboard data:', error)
    }
  }
})
</script>