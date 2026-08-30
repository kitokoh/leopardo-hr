<template>
  <div class="space-y-6">
    <!-- FUEL-012 (#5806) : interface manager multi-stations — KPIs tenant -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
      <StatsCard :title="t('fuel.statStations', 'Stations')" :value="stats.stations" icon="BoltIcon" color="blue" />
      <StatsCard :title="t('fuel.statOpenIncidents', 'Incidents ouverts')" :value="stats.openIncidents" icon="ExclamationTriangleIcon" color="red" />
      <StatsCard :title="t('fuel.statPendingReconciliations', 'Rapprochements à revoir')" :value="stats.pendingReconciliations" icon="ClipboardDocumentListIcon" color="yellow" />
      <StatsCard :title="t('fuel.statVariance', 'Écarts à expliquer')" :value="stats.varianceMinor" icon="ArrowDownTrayIcon" color="green" />
    </div>

    <div v-if="globalError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
      {{ globalError }}
    </div>

    <div class="flex gap-2">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        :class="[
          'rounded-md px-4 py-2 text-sm font-medium',
          activeTab === tab.key ? 'bg-indigo-600 text-white' : 'glass-card text-gray-700 ring-1 ring-gray-300 glass-bg-hover'
        ]"
        @click="selectTab(tab)"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Stations -->
    <DataTable
      v-if="activeTab === 'stations'"
      :columns="stationColumns"
      :rows="stations"
      :loading="loading"
      :error="errors.stations"
      :search-keys="['code', 'name', 'timezone']"
      :search-placeholder="t('fuel.searchStation', 'Rechercher une station...')"
      default-sort="name"
      :caption="t('fuel.stationsCaption', 'Stations de la flotte')"
    >
      <template #cell-status="{ value }">
        <StatusBadge :status="value" :map="stationStatusMap" />
      </template>
    </DataTable>

    <!-- Incidents -->
    <DataTable
      v-else-if="activeTab === 'incidents'"
      :columns="incidentColumns"
      :rows="incidents"
      :loading="loading"
      :error="errors.incidents"
      :search-keys="['title', 'description', 'equipment_type']"
      :search-placeholder="t('fuel.searchIncident', 'Rechercher un incident...')"
      default-sort="created_at"
      default-sort-dir="desc"
      :caption="t('fuel.incidentsCaption', 'Incidents signalés')"
    >
      <template #cell-priority="{ value }">
        <StatusBadge :status="value" :map="priorityMap" />
      </template>
      <template #cell-status="{ value }">
        <StatusBadge :status="value" :map="incidentStatusMap" />
      </template>
    </DataTable>

    <!-- Rapprochements (écarts explicables) -->
    <DataTable
      v-else
      :columns="reconciliationColumns"
      :rows="reconciliations"
      :loading="loading"
      :error="errors.reconciliations"
      :search-keys="['station_id', 'status']"
      :search-placeholder="t('fuel.searchReconciliation', 'Rechercher un rapport...')"
      default-sort="report_date"
      default-sort-dir="desc"
      :caption="t('fuel.reconciliationsCaption', 'Rapports de rapprochement')"
    >
      <template #cell-status="{ value }">
        <StatusBadge :status="value" :map="reconciliationStatusMap" />
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const loading = ref(false)
const globalError = ref('')
const errors = ref({ stations: '', incidents: '', reconciliations: '' })
const stations = ref([])
const incidents = ref([])
const reconciliations = ref([])
const activeTab = ref('stations')

const stats = ref({ stations: 0, openIncidents: 0, pendingReconciliations: 0, varianceMinor: 0 })

const tabs = [
  { key: 'stations', label: t('fuel.tabsStations', 'Stations') },
  { key: 'incidents', label: t('fuel.tabsIncidents', 'Incidents') },
  { key: 'reconciliations', label: t('fuel.tabsReconciliations', 'Rapprochements') },
]

const stationColumns = [
  { key: 'code', label: t('fuel.colCode', 'Code'), sortable: true },
  { key: 'name', label: t('fuel.colName', 'Nom'), sortable: true },
  { key: 'timezone', label: t('fuel.colTimezone', 'Fuseau'), sortable: true },
  { key: 'status', label: t('fuel.colStatus', 'Statut'), sortable: true },
  { key: 'created_at', label: t('fuel.colDate', 'Créé le'), sortable: true },
]

const incidentColumns = [
  { key: 'title', label: t('fuel.colTitle', 'Titre'), sortable: true },
  { key: 'station_id', label: t('fuel.colStation', 'Station'), sortable: true },
  { key: 'equipment_type', label: t('fuel.colEquipmentType', 'Équipement'), sortable: true },
  { key: 'priority', label: t('fuel.colPriority', 'Priorité'), sortable: true },
  { key: 'status', label: t('fuel.colStatus', 'Statut'), sortable: true },
  { key: 'created_at', label: t('fuel.colDate', 'Créé le'), sortable: true },
]

const reconciliationColumns = [
  { key: 'station_id', label: t('fuel.colStation', 'Station'), sortable: true },
  { key: 'report_date', label: t('fuel.colReportDate', 'Jour'), sortable: true },
  { key: 'expected_stock_minor', label: t('fuel.colExpected', 'Attendu'), sortable: true },
  { key: 'closing_stock_minor', label: t('fuel.colClosing', 'Clôture'), sortable: true },
  { key: 'variance_minor', label: t('fuel.colVariance', 'Écart'), sortable: true },
  { key: 'status', label: t('fuel.colStatus', 'Statut'), sortable: true },
]

const stationStatusMap = {
  active: { label: t('fuel.stationActive', 'Active'), color: 'green' },
  inactive: { label: t('fuel.stationInactive', 'Inactive'), color: 'yellow' },
  archived: { label: t('fuel.stationArchived', 'Archivée'), color: 'gray' },
}

const incidentStatusMap = {
  reported: { label: t('fuel.incidentReported', 'Signalé'), color: 'blue' },
  assigned: { label: t('fuel.incidentAssigned', 'Assigné'), color: 'yellow' },
  in_progress: { label: t('fuel.incidentInProgress', 'En cours'), color: 'yellow' },
  resolved: { label: t('fuel.incidentResolved', 'Résolu'), color: 'green' },
  closed: { label: t('fuel.incidentClosed', 'Clos'), color: 'gray' },
}

const priorityMap = {
  low: { label: t('fuel.priorityLow', 'Faible'), color: 'blue' },
  medium: { label: t('fuel.priorityMedium', 'Moyen'), color: 'yellow' },
  high: { label: t('fuel.priorityHigh', 'Élevé'), color: 'red' },
  critical: { label: t('fuel.priorityCritical', 'Critique'), color: 'red' },
}

const reconciliationStatusMap = {
  pending_review: { label: t('fuel.reconcilPending', 'À revoir'), color: 'yellow' },
  reviewed: { label: t('fuel.reconcilReviewed', 'Revu'), color: 'blue' },
  approved: { label: t('fuel.reconcilApproved', 'Approuvé'), color: 'green' },
}

function selectTab(tab) {
  activeTab.value = tab.key
}

async function fetchData() {
  loading.value = true
  globalError.value = ''
  errors.value = { stations: '', incidents: '', reconciliations: '' }
  try {
    // Routes tenant (auth:sanctum + api.manager) : 401 attendu en super-admin
    // — _skipAuthRedirect (#4170) évite la déconnexion de session.
    const sRes = await api.get('/fuel-station/stations?per_page=100', { _skipAuthRedirect: true })
    stations.value = sRes.data.data || []
  } catch {
    errors.value.stations = t('fuel.errStations', 'Impossible de charger les stations.')
  }
  try {
    const iRes = await api.get('/fuel-station/incidents?per_page=100', { _skipAuthRedirect: true })
    incidents.value = iRes.data.data || []
  } catch {
    errors.value.incidents = t('fuel.errIncidents', 'Impossible de charger les incidents.')
  }
  try {
    const rRes = await api.get('/fuel-station/reconciliations?status=pending_review&per_page=100', { _skipAuthRedirect: true })
    reconciliations.value = rRes.data.data || []
  } catch {
    errors.value.reconciliations = t('fuel.errReconciliations', 'Impossible de charger les rapprochements.')
  }
  stats.value = {
    stations: stations.value.length,
    openIncidents: incidents.value.filter(i => !['resolved', 'closed'].includes(i.status)).length,
    pendingReconciliations: reconciliations.value.filter(r => r.status === 'pending_review').length,
    varianceMinor: reconciliations.value
      .filter(r => r.status === 'pending_review')
      .reduce((acc, r) => acc + (Number(r.variance_minor) || 0), 0),
  }
  loading.value = false
}

onMounted(fetchData)
</script>
