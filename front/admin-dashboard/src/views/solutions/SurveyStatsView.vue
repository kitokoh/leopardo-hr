<template>
  <div class="space-y-6">
    <!-- #6694 : pilotage des surveys de solutions (wizard vitrine) -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-lg font-semibold text-gray-900">
          {{ t('survey.title', 'Surveys de solutions') }}
        </h1>
        <p class="text-sm text-gray-500">{{ t('survey.subtitle', 'Conversion du wizard « Je suis restaurateur » et des solutions sectorielles') }}</p>
      </div>
      <select
        v-model="days"
        class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
        @change="fetchStats"
      >
        <option :value="7">{{ t('survey.last7', '7 derniers jours') }}</option>
        <option :value="30">{{ t('survey.last30', '30 derniers jours') }}</option>
        <option :value="90">{{ t('survey.last90', '90 derniers jours') }}</option>
      </select>
    </div>

    <div v-if="error" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
      {{ error }}
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
      <StatsCard :title="t('survey.totalResponses', 'Réponses au survey')" :value="stats.total_responses ?? 0" icon="DocumentTextIcon" color="blue" />
      <StatsCard
        v-for="(solution, index) in stats.per_solution ?? []"
        :key="solution.code"
        :title="solutionTitle(solution.code)"
        :value="solution.responses"
        :icon="index === 0 ? 'ChartBarIcon' : 'DocumentTextIcon'"
        :color="index === 0 ? 'green' : 'gray'"
      />
      <StatsCard :title="t('survey.converted', 'Inscriptions avec solution')" :value="stats.conversion?.companies_with_solution ?? 0" icon="UserPlusIcon" color="indigo" />
      <StatsCard :title="t('survey.conversionRate', 'Taux de conversion')" :value="`${stats.conversion?.rate ?? 0}%`" icon="TrendingUpIcon" color="green" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <div class="rounded-lg glass-card shadow">
        <div class="border-b border-gray-200 px-4 py-3">
          <h2 class="text-sm font-semibold text-gray-900">{{ t('survey.topPackages', 'Packs les plus suggérés') }}</h2>
        </div>
        <DataTable
          :columns="packageColumns"
          :rows="stats.top_packages ?? []"
          :loading="loading"
          :error="null"
          :search-keys="[]"
          :caption="t('survey.topPackagesCaption', 'Suggestions du moteur de règles')"
        >
          <template #cell-key="{ value }">
            {{ packageLabel(value) }}
          </template>
        </DataTable>
      </div>

      <div class="rounded-lg glass-card shadow">
        <div class="border-b border-gray-200 px-4 py-3">
          <h2 class="text-sm font-semibold text-gray-900">{{ t('survey.bySolution', 'Réponses par solution') }}</h2>
        </div>
        <DataTable
          :columns="solutionColumns"
          :rows="stats.per_solution ?? []"
          :loading="loading"
          :error="null"
          :search-keys="['code']"
          :caption="t('survey.bySolutionCaption', 'Volume de réponses par solution')"
        >
          <template #cell-code="{ value }">
            {{ solutionTitle(value) }}
          </template>
        </DataTable>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import api from '@/services/api'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'
import StatsCard from '@/components/ui/StatsCard.vue'
import DataTable from '@/components/ui/DataTable.vue'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const loading = ref(false)
const error = ref('')
const days = ref(30)
const stats = ref({})

const packageColumns = [
  { key: 'key', label: t('survey.colPackage', 'Pack') },
  { key: 'suggestions', label: t('survey.colSuggestions', 'Suggestions'), sortable: true },
]

const solutionColumns = [
  { key: 'code', label: t('survey.colSolution', 'Solution') },
  { key: 'responses', label: t('survey.colResponses', 'Réponses'), sortable: true },
]

function solutionTitle(code) {
  const labels = {
    restaurant: t('survey.solutionRestaurant', 'Restaurant'),
    fuel_station: t('survey.solutionFuelStation', 'Station-service'),
  }
  return labels[code] || code
}

function packageLabel(key) {
  const labels = {
    mobile_employee: t('survey.pkgMobileEmployee', 'App mobile employé'),
    mobile_manager: t('survey.pkgMobileManager', 'App mobile manager'),
    attendance_mobile: t('survey.pkgAttendanceMobile', 'Pointage mobile'),
    kiosk: t('survey.pkgKiosk', 'Kiosque de pointage'),
    edge: t('survey.pkgEdge', 'Nœud Edge local'),
    planning: t('survey.pkgPlanning', 'Planning d\'équipe'),
    payroll: t('survey.pkgPayroll', 'Paie'),
    accounting: t('survey.pkgAccounting', 'Comptabilité'),
    delivery: t('survey.pkgDelivery', 'Livraison'),
    reservations: t('survey.pkgReservations', 'Réservations'),
    inventory: t('survey.pkgInventory', 'Stock'),
    loyalty: t('survey.pkgLoyalty', 'Fidélité'),
    pos: t('survey.pkgPos', 'Caisse (POS)'),
  }
  return labels[key] || key
}

async function fetchStats() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get(`/admin/solutions/surveys?days=${days.value}`)
    stats.value = res.data.data || res.data || {}
  } catch (err) {
    error.value = t('survey.loadError', 'Impossible de charger les statistiques des surveys.')
    console.warn('Failed to load solution survey stats', err)
  } finally {
    loading.value = false
  }
}

onMounted(fetchStats)
</script>
