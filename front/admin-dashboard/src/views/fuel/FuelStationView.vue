<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
      <StatsCard :title="t('fuelStation.statStations', 'Stations')" :value="stats.stations" icon="BuildingOfficeIcon" color="blue" />
      <StatsCard :title="t('fuelStation.statActiveSessions', 'Caisses ouvertes')" :value="stats.open_sessions" icon="BanknotesIcon" color="green" />
      <StatsCard :title="t('fuelStation.statOpenIncidents', 'Incidents ouverts')" :value="stats.open_incidents" icon="ExclamationTriangleIcon" color="red" />
      <StatsCard :title="t('fuelStation.statReconciliations', 'Rapprochements')" :value="stats.reconciliations" icon="ClipboardDocumentCheckIcon" color="yellow" />
    </div>

    <div v-if="error" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
      {{ error }}
    </div>

    <div class="flex gap-2">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        :class="[
          'rounded-md px-4 py-2 text-sm font-medium',
          activeTab === tab.key ? 'bg-indigo-600 text-white' : 'glass-card text-gray-700 ring-1 ring-gray-300 glass-bg-hover'
        ]"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <DataTable
      v-if="activeTab === 'stations'"
      :columns="stationColumns"
      :rows="stations"
      :loading="loading"
      :error="error"
      :search-keys="['code', 'name', 'address']"
      :search-placeholder="t('fuelStation.searchStation', 'Rechercher une station...')"
      default-sort="code"
    >
      <template #cell-status="{ value }">
        <StatusBadge :status="value" :map="stationStatusMap" />
      </template>
    </DataTable>

    <DataTable
      v-else-if="activeTab === 'stocks'"
      :columns="stockColumns"
      :rows="stocks"
      :loading="loading"
      :error="error"
      :search-keys="['code', 'product_type']"
      :search-placeholder="t('fuelStation.searchStock', 'Rechercher une cuve...')"
      default-sort="code"
    >
      <template #cell-fill_ratio="{ value }">
        <span class="text-sm text-gray-700">{{ (value * 100).toFixed(1) }}%</span>
      </template>
    </DataTable>

    <DataTable
      v-else-if="activeTab === 'incidents'"
      :columns="incidentColumns"
      :rows="incidents"
      :loading="loading"
      :error="error"
      :search-keys="['category', 'severity', 'description_redacted']"
      :search-placeholder="t('fuelStation.searchIncident', 'Rechercher un incident...')"
      default-sort="reported_at"
      default-sort-dir="desc"
    >
      <template #cell-severity="{ value }">
        <StatusBadge :status="value" :map="severityMap" />
      </template>
      <template #cell-status="{ value }">
        <StatusBadge :status="value" :map="incidentStatusMap" />
      </template>
    </DataTable>

    <DataTable
      v-else-if="activeTab === 'reconciliations'"
      :columns="reconciliationColumns"
      :rows="reconciliations"
      :loading="loading"
      :error="error"
      :search-keys="['run_date', 'status']"
      :search-placeholder="t('fuelStation.searchReconciliation', 'Rechercher un rapprochement...')"
      default-sort="run_date"
      default-sort-dir="desc"
    >
      <template #cell-status="{ value }">
        <StatusBadge :status="value" :map="reconciliationStatusMap" />
      </template>
    </DataTable>

    <div v-else class="rounded-lg border border-gray-200 bg-white p-10 text-center text-sm text-gray-500">
      {{ t('fuelStation.tabHint', 'Sélectionnez un onglet pour explorer les données FuelStation.') }}
    </div>
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
const error = ref('')
const activeTab = ref('stations')
const stations = ref([])
const stocks = ref([])
const incidents = ref([])
const reconciliations = ref([])

const stats = ref({ stations: 0, open_sessions: 0, open_incidents: 0, reconciliations: 0 })

const tabs = [
  { key: 'stations', label: t('fuelStation.tabStations', 'Stations') },
  { key: 'stocks', label: t('fuelStation.tabStocks', 'Cuves & stock') },
  { key: 'incidents', label: t('fuelStation.tabIncidents', 'Incidents') },
  { key: 'reconciliations', label: t('fuelStation.tabReconciliations', 'Rapprochements') },
]

const stationColumns = [
  { key: 'code', label: t('fuelStation.colCode', 'Code'), sortable: true },
  { key: 'name', label: t('fuelStation.colName', 'Nom'), sortable: true },
  { key: 'address', label: t('fuelStation.colAddress', 'Adresse') },
  { key: 'status', label: t('fuelStation.colStatus', 'Statut'), sortable: true },
]

const stockColumns = [
  { key: 'code', label: t('fuelStation.colCode', 'Code'), sortable: true },
  { key: 'product_type', label: t('fuelStation.colProduct', 'Produit'), sortable: true },
  { key: 'current_level_minor', label: t('fuelStation.colLevel', 'Niveau (u.)'), sortable: true },
  { key: 'capacity_minor', label: t('fuelStation.colCapacity', 'Capacité (u.)'), sortable: true },
  { key: 'fill_ratio', label: t('fuelStation.colFill', 'Remplissage'), sortable: true },
]

const incidentColumns = [
  { key: 'id', label: '#', sortable: true },
  { key: 'category', label: t('fuelStation.colCategory', 'Catégorie'), sortable: true },
  { key: 'severity', label: t('fuelStation.colSeverity', 'Sévérité'), sortable: true },
  { key: 'description_redacted', label: t('fuelStation.colDescription', 'Description') },
  { key: 'status', label: t('fuelStation.colStatus', 'Statut'), sortable: true },
  { key: 'reported_at', label: t('fuelStation.colReportedAt', 'Signalé le'), sortable: true },
]

const reconciliationColumns = [
  { key: 'id', label: '#', sortable: true },
  { key: 'run_date', label: t('fuelStation.colRunDate', 'Date'), sortable: true },
  { key: 'status', label: t('fuelStation.colStatus', 'Statut'), sortable: true },
  { key: 'created_at', label: t('fuelStation.colCreatedAt', 'Créé le'), sortable: true },
]

const stationStatusMap = {
  active: { label: t('fuelStation.statusActive', 'Active'), color: 'green' },
  inactive: { label: t('fuelStation.statusInactive', 'Inactive'), color: 'gray' },
  archived: { label: t('fuelStation.statusArchived', 'Archivée'), color: 'red' },
}

const severityMap = {
  low: { label: t('fuelStation.severityLow', 'Faible'), color: 'blue' },
  medium: { label: t('fuelStation.severityMedium', 'Moyen'), color: 'yellow' },
  high: { label: t('fuelStation.severityHigh', 'Élevé'), color: 'red' },
  critical: { label: t('fuelStation.severityCritical', 'Critique'), color: 'red' },
}

const incidentStatusMap = {
  reported: { label: t('fuelStation.incidentReported', 'Signalé'), color: 'red' },
  assigned: { label: t('fuelStation.incidentAssigned', 'Affecté'), color: 'yellow' },
  resolved: { label: t('fuelStation.incidentResolved', 'Résolu'), color: 'green' },
  closed: { label: t('fuelStation.incidentClosed', 'Clôturé'), color: 'gray' },
}

const reconciliationStatusMap = {
  pending: { label: t('fuelStation.reconPending', 'En attente'), color: 'gray' },
  running: { label: t('fuelStation.reconRunning', 'En cours'), color: 'yellow' },
  completed: { label: t('fuelStation.reconCompleted', 'Terminé'), color: 'green' },
  failed: { label: t('fuelStation.reconFailed', 'Échoué'), color: 'red' },
}

async function fetchData() {
  loading.value = true
  error.value = ''
  try {
    const stationsRes = await api.get('/fuel-station/stations', { _skipAuthRedirect: true })
    stations.value = stationsRes.data.data || stationsRes.data || []
  } catch {
    error.value = 'Impossible de charger les données FuelStation.'
  }
  try {
    const stocksRes = await api.get('/fuel-station/stocks', { _skipAuthRedirect: true })
    stocks.value = stocksRes.data.data || stocksRes.data || []
  } catch {
    // best-effort
  }
  try {
    const incidentsRes = await api.get('/fuel-station/incidents', { _skipAuthRedirect: true })
    incidents.value = incidentsRes.data.data || incidentsRes.data || []
  } catch {
    // best-effort
  }
  try {
    const reconRes = await api.get('/fuel-station/reconciliations', { _skipAuthRedirect: true })
    const reconData = reconRes.data.data || reconRes.data || []
    reconciliations.value = Array.isArray(reconData) ? reconData : []
  } catch {
    // best-effort
  }
  stats.value = {
    stations: stations.value.length,
    open_sessions: 0,
    open_incidents: incidents.value.filter(i => i.status === 'reported' || i.status === 'assigned').length,
    reconciliations: reconciliations.value.length,
  }
  loading.value = false
}

onMounted(fetchData)
</script>
