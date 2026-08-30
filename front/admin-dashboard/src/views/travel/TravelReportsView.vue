<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('travel.reports.title', 'Rapports') }}
      </h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        {{ t('travel.reports.subtitle', 'Ventes, occupation, recettes, annulations et exports CSV.') }}
      </p>
    </div>

    <TravelGate :mode="gateMode" :message="loadError" @retry="init" />

    <template v-if="!gateMode">
      <div class="flex flex-wrap items-end gap-2">
        <div class="space-y-1">
          <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">
            {{ t('travel.reports.from', 'Du') }}
          </label>
          <input
            v-model="fromDate"
            type="date"
            class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
          />
        </div>
        <div class="space-y-1">
          <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">
            {{ t('travel.reports.to', 'Au') }}
          </label>
          <input
            v-model="toDate"
            type="date"
            class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
          />
        </div>
        <div class="flex gap-2">
          <button class="btn-primary" :disabled="loadingKpis" @click="reload">
            {{ t('travel.reports.apply', 'Appliquer') }}
          </button>
          <button class="btn-secondary" :disabled="exporting" @click="exportCsv">
            {{ exporting ? '…' : t('travel.reports.export', 'Exporter CSV') }}
          </button>
        </div>
      </div>

      <!-- Cartes KPI -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatsCard :title="t('travel.reports.kpiSales', 'Ventes')" :value="formatMinor(kpis.sales_minor, currency)" icon="ChartBarIcon" color="blue" />
        <StatsCard :title="t('travel.reports.kpiBookings', 'Réservations')" :value="String(kpis.bookings_count ?? 0)" icon="ClipboardDocumentListIcon" color="green" />
        <StatsCard :title="t('travel.reports.kpiOccupancy', 'Occupation')" :value="`${Math.round((kpis.occupancy_rate ?? 0) * 100)} %`" icon="ChartBarIcon" color="yellow" />
        <StatsCard :title="t('travel.reports.kpiCancellations', 'Annulations')" :value="String(kpis.cancellations_count ?? 0)" icon="ChartBarIcon" color="red" />
      </div>

      <!-- Onglets rapports -->
      <div class="flex flex-wrap gap-2">
        <button
          v-for="tab in reportTabs"
          :key="tab.key"
          class="rounded-md px-4 py-2 text-sm font-medium transition-all"
          :class="activeReport === tab.key
            ? 'bg-brand-500 text-white shadow-lg shadow-brand-500/25'
            : 'glass-card text-slate-600 ring-1 ring-slate-200 dark:text-slate-400 dark:ring-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800'"
          @click="switchReport(tab.key)"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Ventes -->
      <template v-if="activeReport === 'sales'">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <StatsCard :title="t('travel.reports.salesCount', 'Ventes')" :value="String(salesSummary.booking_count ?? 0)" icon="ChartBarIcon" color="blue" />
          <StatsCard :title="t('travel.reports.salesPassengers', 'Passagers')" :value="String(salesSummary.passenger_count ?? 0)" icon="ChartBarIcon" color="green" />
          <StatsCard :title="t('travel.reports.salesAmount', 'Montant')" :value="formatMinor(salesSummary.total_amount_minor, currency)" icon="ChartBarIcon" color="yellow" />
        </div>
        <DataTable
          :columns="salesColumns"
          :rows="salesRows"
          :loading="loadingSales"
          :error="errorSales"
          :empty-message="t('travel.common.noData', 'Aucune donnée')"
          key-field="id"
        >
          <template #cell-total_amount_minor="{ value, row }">
            {{ formatMinor(value, row.currency) }}
          </template>
          <template #cell-status="{ value }">
            <StatusBadge :status="value" :map="bookingStatusMap" />
          </template>
        </DataTable>
      </template>

      <!-- Occupation -->
      <template v-else-if="activeReport === 'occupancy'">
        <DataTable
          :columns="occupancyColumns"
          :rows="occupancyRows"
          :loading="loadingOccupancy"
          :error="errorOccupancy"
          :empty-message="t('travel.common.noData', 'Aucune donnée')"
          key-field="id"
        >
          <template #cell-occupancy_rate="{ value }">
            <span class="font-medium">{{ Math.round((value ?? 0) * 100) }} %</span>
          </template>
        </DataTable>
      </template>

      <!-- Recettes -->
      <template v-else-if="activeReport === 'revenue'">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <StatsCard :title="t('travel.reports.revenueConfirmed', 'Confirmé')" :value="formatMinor(revenue.confirmed_minor, currency)" icon="ChartBarIcon" color="green" />
          <StatsCard :title="t('travel.reports.revenueRefunded', 'Remboursé')" :value="formatMinor(revenue.refunded_minor, currency)" icon="ChartBarIcon" color="red" />
          <StatsCard :title="t('travel.reports.revenueNet', 'Net')" :value="formatMinor(revenue.net_minor, currency)" icon="ChartBarIcon" color="blue" />
        </div>
        <DataTable
          v-if="(revenue.by_route || []).length > 0"
          :columns="revenueByRouteColumns"
          :rows="revenue.by_route || []"
          :loading="loadingRevenue"
          :error="errorRevenue"
          :empty-message="t('travel.common.noData', 'Aucune donnée')"
          key-field="route_id"
        >
          <template #cell-confirmed_minor="{ value }">
            {{ formatMinor(value, currency) }}
          </template>
          <template #cell-refunded_minor="{ value }">
            {{ formatMinor(value, currency) }}
          </template>
          <template #cell-net_minor="{ value }">
            <span class="font-medium">{{ formatMinor(value, currency) }}</span>
          </template>
        </DataTable>
      </template>

      <!-- Annulations -->
      <template v-else>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <StatsCard :title="t('travel.reports.cancellationsCount', 'Annulations')" :value="String(cancellations.cancelled_count ?? 0)" icon="ChartBarIcon" color="red" />
          <StatsCard :title="t('travel.reports.cancellationsRate', 'Taux')" :value="`${Math.round((cancellations.cancellation_rate ?? 0) * 100)} %`" icon="ChartBarIcon" color="yellow" />
          <StatsCard :title="t('travel.reports.cancellationsTotal', 'Réservations finales')" :value="String(cancellations.total_final_count ?? 0)" icon="ChartBarIcon" color="blue" />
        </div>
        <div v-if="(cancellations.by_reason || []).length > 0" class="rounded-2xl glass-card p-5">
          <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
            {{ t('travel.reports.byReason', 'Par motif') }}
          </h3>
          <DataTable
            class="mt-2"
            :columns="reasonColumns"
            :rows="cancellations.by_reason || []"
            :empty-message="t('travel.common.noData', 'Aucune donnée')"
            key-field="reason"
          />
        </div>
        <div v-if="(cancellations.by_source || []).length > 0" class="rounded-2xl glass-card p-5">
          <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
            {{ t('travel.reports.bySource', 'Par source') }}
          </h3>
          <DataTable
            class="mt-2"
            :columns="sourceColumns"
            :rows="cancellations.by_source || []"
            :empty-message="t('travel.common.noData', 'Aucune donnée')"
            key-field="source"
          />
        </div>
      </template>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { useTravelStore } from '@/stores/travel'
import TravelGate from '@/components/travel/TravelGate.vue'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import StatsCard from '@/components/dashboard/StatsCard.vue'
import api from '@/services/api'
import { travelList, travelItem, formatMinor } from '@/services/travel'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const travelStore = useTravelStore()

const fromDate = ref('')
const toDate = ref('')
const currency = ref('XOF')

const kpis = ref({})
const loadingKpis = ref(false)

const activeReport = ref('sales')
const salesRows = ref([])
const salesSummary = ref({})
const loadingSales = ref(false)
const errorSales = ref('')
const occupancyRows = ref([])
const loadingOccupancy = ref(false)
const errorOccupancy = ref('')
const revenue = ref({})
const loadingRevenue = ref(false)
const errorRevenue = ref('')
const cancellations = ref({})
const loadingCancellations = ref(false)
const errorCancellations = ref('')
const exporting = ref(false)
const loadError = ref('')

const gateMode = computed(() => {
  if (!travelStore.isReady) return ''
  if (travelStore.noTenantContext) return 'tenant'
  if (!travelStore.flagActive) return 'feature'
  return ''
})

const bookingStatusMap = {
  pending: { label: t('travel.bookingStatus.pending', 'En attente'), color: 'yellow' },
  confirmed: { label: t('travel.bookingStatus.confirmed', 'Confirmée'), color: 'green' },
  cancelled: { label: t('travel.bookingStatus.cancelled', 'Annulée'), color: 'red' },
  refunded: { label: t('travel.bookingStatus.refunded', 'Remboursée'), color: 'purple' },
  completed: { label: t('travel.bookingStatus.completed', 'Terminée'), color: 'blue' }
}

const reportTabs = computed(() => [
  { key: 'sales', label: t('travel.reports.tabSales', 'Ventes') },
  { key: 'occupancy', label: t('travel.reports.tabOccupancy', 'Occupation') },
  { key: 'revenue', label: t('travel.reports.tabRevenue', 'Recettes') },
  { key: 'cancellations', label: t('travel.reports.tabCancellations', 'Annulations') }
])

const salesColumns = computed(() => [
  { key: 'reference', label: t('travel.bookings.reference', 'Référence'), sortable: true },
  { key: 'passenger_count', label: t('travel.bookings.passengerCount', 'Passagers'), sortable: true },
  { key: 'total_amount_minor', label: t('travel.bookings.total', 'Total'), sortable: true },
  { key: 'status', label: t('travel.common.status', 'Statut'), sortable: true },
  { key: 'created_at', label: t('travel.common.createdAt', 'Créé le'), sortable: true }
])

const occupancyColumns = computed(() => [
  { key: 'code', label: 'Code', sortable: true },
  { key: 'departure_date', label: t('travel.network.departure', 'Départ'), sortable: true },
  { key: 'total_seats', label: t('travel.vehicles.seats', 'Places'), sortable: true },
  { key: 'sold_seats', label: t('travel.reports.sold', 'Vendus'), sortable: true },
  { key: 'reserved_seats', label: t('travel.reports.reserved', 'Réservés'), sortable: true },
  { key: 'free_seats', label: t('travel.reports.free', 'Libres'), sortable: true },
  { key: 'occupancy_rate', label: t('travel.reports.kpiOccupancy', 'Occupation'), sortable: true }
])

const revenueByRouteColumns = computed(() => [
  { key: 'route_id', label: t('travel.network.route', 'Route'), sortable: true },
  { key: 'confirmed_minor', label: t('travel.reports.revenueConfirmed', 'Confirmé') },
  { key: 'refunded_minor', label: t('travel.reports.revenueRefunded', 'Remboursé') },
  { key: 'net_minor', label: t('travel.reports.revenueNet', 'Net') }
])

const reasonColumns = computed(() => [
  { key: 'reason', label: t('travel.common.reason', 'Motif'), sortable: true },
  { key: 'count', label: t('travel.reports.count', 'Nombre'), sortable: true }
])

const sourceColumns = computed(() => [
  { key: 'source', label: t('travel.bookings.source', 'Source'), sortable: true },
  { key: 'count', label: t('travel.reports.count', 'Nombre'), sortable: true }
])

function queryParams(extra = {}) {
  const params = { ...extra }
  if (fromDate.value) params.from = fromDate.value
  if (toDate.value) params.to = toDate.value
  return params
}

function apiError(e) {
  return e?.response?.data?.message || e?.message || t('travel.common.loadErrorBody', 'Une erreur est survenue.')
}

async function loadKpis() {
  loadingKpis.value = true
  try {
    const response = await api.get('/travel/reports/dashboard', { params: queryParams() })
    const data = travelItem(response)
    kpis.value = data || {}
  } catch (e) {
    loadError.value = apiError(e)
  } finally {
    loadingKpis.value = false
  }
}

async function loadSales() {
  loadingSales.value = true
  errorSales.value = ''
  try {
    const response = await api.get('/travel/reports/sales', { params: queryParams({ per_page: 100 }) })
    salesRows.value = travelList(response)
    salesSummary.value = response?.data?.summary || {}
    if (salesRows.value[0]?.currency) currency.value = salesRows.value[0].currency
  } catch (e) {
    errorSales.value = apiError(e)
  } finally {
    loadingSales.value = false
  }
}

async function loadOccupancy() {
  loadingOccupancy.value = true
  errorOccupancy.value = ''
  try {
    const response = await api.get('/travel/reports/occupancy', { params: queryParams() })
    occupancyRows.value = travelList(response)
  } catch (e) {
    errorOccupancy.value = apiError(e)
  } finally {
    loadingOccupancy.value = false
  }
}

async function loadRevenue() {
  loadingRevenue.value = true
  errorRevenue.value = ''
  try {
    const response = await api.get('/travel/reports/revenue', { params: queryParams() })
    revenue.value = travelItem(response) || {}
  } catch (e) {
    errorRevenue.value = apiError(e)
  } finally {
    loadingRevenue.value = false
  }
}

async function loadCancellations() {
  loadingCancellations.value = true
  errorCancellations.value = ''
  try {
    const response = await api.get('/travel/reports/cancellations', { params: queryParams() })
    cancellations.value = travelItem(response) || {}
  } catch (e) {
    errorCancellations.value = apiError(e)
  } finally {
    loadingCancellations.value = false
  }
}

async function reload() {
  await Promise.all([loadKpis(), loadSales(), loadOccupancy(), loadRevenue(), loadCancellations()])
}

async function switchReport(key) {
  activeReport.value = key
}

async function exportCsv() {
  exporting.value = true
  loadError.value = ''
  try {
    const response = await api.get('/travel/reports/export', { params: queryParams({ type: 'sales' }) })
    const data = travelItem(response)
    if (data?.signed_url) {
      window.open(data.signed_url, '_blank', 'noopener,noreferrer')
    }
  } catch (e) {
    loadError.value = apiError(e)
  } finally {
    exporting.value = false
  }
}

async function init() {
  await travelStore.checkFlag(true)
  if (gateMode.value) return
  loadError.value = ''
  await reload()
}

onMounted(init)
</script>
