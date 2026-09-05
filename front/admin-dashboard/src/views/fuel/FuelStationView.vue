<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">{{ t('fuel.title', 'Stations-service') }}</h1>
        <p class="text-sm text-gray-500">{{ t('fuel.subtitle', 'Pilotage multi-stations : équipements, shifts, incidents, alertes et rapports.') }}</p>
      </div>
      <button
        v-if="activeTab === 'stations'"
        type="button"
        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        @click="openCreateStation"
      >
        {{ t('fuel.addStation', 'Nouvelle station') }}
      </button>
    </div>

    <div v-if="loadError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
      {{ loadError }}
    </div>

    <div class="flex flex-wrap gap-2">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        :class="[
          'rounded-md px-4 py-2 text-sm font-medium',
          activeTab === tab.key ? 'bg-indigo-600 text-white' : 'glass-card text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50'
        ]"
        @click="switchTab(tab.key)"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Vue d'ensemble -->
    <div v-if="activeTab === 'overview'" class="space-y-6">
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
        <StatsCard :title="t('fuel.statStations', 'Stations')" :value="stats.stations" icon="MapPinIcon" color="blue" />
        <StatsCard :title="t('fuel.statSalesToday', 'Ventes du jour')" :value="stats.sales_today" icon="BanknotesIcon" color="green" />
        <StatsCard :title="t('fuel.statAlertsOpen', 'Alertes ouvertes')" :value="stats.alerts_open" icon="BellAlertIcon" color="yellow" />
        <StatsCard :title="t('fuel.statOutboxFailed', 'Outbox en échec')" :value="stats.outbox_failed" icon="ExclamationTriangleIcon" color="red" />
      </div>

      <div class="rounded-lg border border-gray-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-gray-900">{{ t('fuel.overviewReports', 'Rapports du jour') }}</h2>
        <p class="mt-1 text-sm text-gray-500">{{ t('fuel.overviewReportsHint', 'Read models calculés à la demande : volumes par pompe, ventes, stock.') }}</p>
        <div class="mt-4 flex flex-wrap gap-3">
          <button
            v-for="report in reportTypes"
            :key="report.key"
            type="button"
            class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200"
            :disabled="reportLoading"
            @click="openReport(report.key)"
          >
            {{ report.label }}
          </button>
        </div>
        <pre v-if="reportPayload" class="mt-4 max-h-96 overflow-auto rounded-md bg-gray-900 p-4 text-xs text-green-400">{{ reportPayload }}</pre>
      </div>
    </div>

    <!-- Stations -->
    <div v-else-if="activeTab === 'stations'">
      <DataTable
        :columns="stationColumns"
        :rows="stations"
        :loading="loading"
        :error="error"
        :search-keys="['name', 'code']"
        :search-placeholder="t('fuel.searchStation', 'Rechercher une station...')"
        default-sort="name"
      >
        <template #cell-status="{ value }">
          <StatusBadge :status="value" :map="stationStatusMap" />
        </template>
        <template #cell-sites_count="{ value }">
          <span>{{ value ?? 0 }}</span>
        </template>
        <template #row-actions="{ row }">
          <button type="button" class="text-sm font-medium text-indigo-600 hover:text-indigo-800" @click="selectStation(row)">
            {{ t('fuel.viewEquipment', 'Équipements') }}
          </button>
        </template>
      </DataTable>

      <Pagination
        v-if="stationsMeta.total > stationsMeta.per_page"
        :current-page="stationsMeta.current_page"
        :total-pages="stationsMeta.last_page"
        :total-items="stationsMeta.total"
        :per-page="stationsMeta.per_page"
        @page-change="loadStations"
      />
    </div>

    <!-- Équipements -->
    <div v-else-if="activeTab === 'equipment'" class="space-y-6">
      <div class="flex flex-wrap items-center gap-3">
        <label class="text-sm font-medium text-gray-700">{{ t('fuel.selectStation', 'Station') }}</label>
        <select
          class="rounded-md border border-gray-300 px-3 py-2 text-sm"
          :value="selectedStationId"
          @change="onStationSelect($event)"
        >
          <option value="">{{ t('fuel.selectStationHint', 'Choisir une station...') }}</option>
          <option v-for="s in stations" :key="s.id" :value="s.id">{{ s.name }} ({{ s.code }})</option>
        </select>
      </div>

      <div v-if="equipmentError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ equipmentError }}
      </div>

      <div v-if="selectedStationId" class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <DataTable
          :caption="t('fuel.pumps', 'Pompes')"
          :columns="pumpColumns"
          :rows="pumps"
          :loading="equipmentLoading"
          :error="equipmentError"
        />
        <DataTable
          :caption="t('fuel.tanks', 'Cuves')"
          :columns="tankColumns"
          :rows="tanks"
          :loading="equipmentLoading"
          :error="equipmentError"
        />
        <DataTable
          :caption="t('fuel.meters', 'Compteurs')"
          :columns="meterColumns"
          :rows="meters"
          :loading="equipmentLoading"
          :error="equipmentError"
        />
      </div>
    </div>

    <!-- Shifts -->
    <div v-else-if="activeTab === 'shifts'">
      <DataTable
        :columns="shiftColumns"
        :rows="shifts"
        :loading="loading"
        :error="error"
        :search-keys="['name']"
        :search-placeholder="t('fuel.searchShift', 'Rechercher un shift...')"
        default-sort="name"
      >
        <template #cell-status="{ value }">
          <StatusBadge :status="value" :map="activeStatusMap" />
        </template>
      </DataTable>
    </div>

    <!-- Incidents -->
    <div v-else-if="activeTab === 'incidents'">
      <DataTable
        :columns="incidentColumns"
        :rows="incidents"
        :loading="loading"
        :error="error"
        :search-keys="['title']"
        :search-placeholder="t('fuel.searchIncident', 'Rechercher un incident...')"
        default-sort="created_at"
        default-sort-dir="desc"
      >
        <template #cell-severity="{ value }">
          <StatusBadge :status="value" :map="severityMap" />
        </template>
        <template #cell-status="{ value }">
          <StatusBadge :status="value" :map="incidentStatusMap" />
        </template>
        <template #row-actions="{ row }">
          <button
            v-if="row.status === 'reported' || row.status === 'assigned' || row.status === 'in_progress'"
            type="button"
            class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
            @click="advanceIncident(row)"
          >
            {{ t('fuel.advanceIncident', 'Faire avancer') }}
          </button>
        </template>
      </DataTable>
    </div>

    <!-- Alertes -->
    <div v-else-if="activeTab === 'alerts'">
      <DataTable
        :columns="alertColumns"
        :rows="alerts"
        :loading="loading"
        :error="error"
        :search-keys="['alert_key', 'event_type']"
        :search-placeholder="t('fuel.searchAlert', 'Rechercher une alerte...')"
        default-sort="created_at"
        default-sort-dir="desc"
      >
        <template #cell-severity="{ value }">
          <StatusBadge :status="value" :map="severityMap" />
        </template>
        <template #cell-status="{ value }">
          <StatusBadge :status="value" :map="alertStatusMap" />
        </template>
        <template #row-actions="{ row }">
          <button
            v-if="row.status === 'open'"
            type="button"
            class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
            @click="ackAlert(row)"
          >
            {{ t('fuel.ackAlert', 'Accuser') }}
          </button>
          <button
            v-if="row.status === 'acknowledged'"
            type="button"
            class="text-sm font-medium text-gray-600 hover:text-gray-800"
            @click="resolveAlert(row)"
          >
            {{ t('fuel.resolveAlert', 'Résoudre') }}
          </button>
        </template>
      </DataTable>
    </div>

    <!-- Rapports -->
    <div v-else-if="activeTab === 'reports'" class="space-y-6">
      <DataTable
        :columns="exportColumns"
        :rows="exports"
        :loading="exportLoading"
        :error="error"
        default-sort="created_at"
        default-sort-dir="desc"
      >
        <template #cell-status="{ value }">
          <StatusBadge :status="value" :map="exportStatusMap" />
        </template>
        <template #row-actions="{ row }">
          <a
            v-if="row.status === 'generated'"
            :href="`/api/v1/fuel-station/reports/exports/${row.id}/download`"
            class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
          >
            {{ t('fuel.downloadExport', 'Télécharger') }}
          </a>
        </template>
      </DataTable>
      <button
        type="button"
        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        :disabled="exportLoading"
        @click="triggerExport"
      >
        {{ t('fuel.newExport', 'Lancer un export CSV') }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import Pagination from '@/components/common/Pagination.vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const loading = ref(false)
const exportLoading = ref(false)
const reportLoading = ref(false)
const equipmentLoading = ref(false)
const loadError = ref('')
const error = ref('')
const equipmentError = ref('')

const activeTab = ref('overview')
const tabs = [
  { key: 'overview', label: t('fuel.tabsOverview', 'Vue d\'ensemble') },
  { key: 'stations', label: t('fuel.tabsStations', 'Stations') },
  { key: 'equipment', label: t('fuel.tabsEquipment', 'Équipements') },
  { key: 'shifts', label: t('fuel.tabsShifts', 'Shifts') },
  { key: 'incidents', label: t('fuel.tabsIncidents', 'Incidents') },
  { key: 'alerts', label: t('fuel.tabsAlerts', 'Alertes') },
  { key: 'reports', label: t('fuel.tabsReports', 'Rapports') },
]

const stats = ref({ stations: 0, sales_today: 0, alerts_open: 0, outbox_failed: 0 })
const stations = ref([])
const stationsMeta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const selectedStationId = ref('')
const pumps = ref([])
const tanks = ref([])
const meters = ref([])
const shifts = ref([])
const incidents = ref([])
const alerts = ref([])
const exports = ref([])
const reportPayload = ref('')

const reportTypes = [
  { key: 'daily_volumes', label: t('fuel.reportVolumes', 'Volumes par pompe') },
  { key: 'sales_summary', label: t('fuel.reportSales', 'Ventes') },
  { key: 'stock_status', label: t('fuel.reportStock', 'Stock') },
  { key: 'variance_summary', label: t('fuel.reportVariances', 'Écarts') },
]

const stationColumns = [
  { key: 'code', label: t('fuel.colCode', 'Code'), sortable: true },
  { key: 'name', label: t('fuel.colName', 'Nom'), sortable: true },
  { key: 'status', label: t('fuel.colStatus', 'Statut'), sortable: true },
  { key: 'sites_count', label: t('fuel.colSites', 'Sites') },
  { key: 'timezone', label: t('fuel.colTimezone', 'Fuseau') },
]

const pumpColumns = [
  { key: 'code', label: t('fuel.colCode', 'Code') },
  { key: 'product_types', label: t('fuel.colProducts', 'Produits') },
  { key: 'status', label: t('fuel.colStatus', 'Statut') },
]

const tankColumns = [
  { key: 'code', label: t('fuel.colCode', 'Code') },
  { key: 'product_type', label: t('fuel.colProduct', 'Produit') },
  { key: 'capacity_minor', label: t('fuel.colCapacity', 'Capacité') },
  { key: 'current_level_minor', label: t('fuel.colLevel', 'Niveau') },
]

const meterColumns = [
  { key: 'meter_code', label: t('fuel.colMeterCode', 'Compteur') },
  { key: 'pump_id', label: t('fuel.colPump', 'Pompe') },
  { key: 'product_code', label: t('fuel.colProduct', 'Produit') },
  { key: 'status', label: t('fuel.colStatus', 'Statut') },
]

const shiftColumns = [
  { key: 'name', label: t('fuel.colName', 'Nom'), sortable: true },
  { key: 'start_time', label: t('fuel.colStart', 'Début') },
  { key: 'end_time', label: t('fuel.colEnd', 'Fin') },
  { key: 'status', label: t('fuel.colStatus', 'Statut') },
]

const incidentColumns = [
  { key: 'id', label: '#', sortable: true },
  { key: 'title', label: t('fuel.colTitle', 'Titre'), sortable: true },
  { key: 'severity', label: t('fuel.colSeverity', 'Sévérité') },
  { key: 'status', label: t('fuel.colStatus', 'Statut') },
  { key: 'created_at', label: t('fuel.colDate', 'Date'), sortable: true },
]

const alertColumns = [
  { key: 'id', label: '#', sortable: true },
  { key: 'event_type', label: t('fuel.colEventType', 'Type'), sortable: true },
  { key: 'severity', label: t('fuel.colSeverity', 'Sévérité') },
  { key: 'status', label: t('fuel.colStatus', 'Statut') },
  { key: 'created_at', label: t('fuel.colDate', 'Date'), sortable: true },
]

const exportColumns = [
  { key: 'id', label: '#', sortable: true },
  { key: 'report_type', label: t('fuel.colReportType', 'Rapport'), sortable: true },
  { key: 'status', label: t('fuel.colStatus', 'Statut') },
  { key: 'created_at', label: t('fuel.colDate', 'Date'), sortable: true },
]

const stationStatusMap = {
  active: { label: t('fuel.statusActive', 'Active'), color: 'green' },
  inactive: { label: t('fuel.statusInactive', 'Inactive'), color: 'gray' },
  archived: { label: t('fuel.statusArchived', 'Archivée'), color: 'red' },
}

const activeStatusMap = {
  active: { label: t('fuel.statusActive', 'Active'), color: 'green' },
  inactive: { label: t('fuel.statusInactive', 'Inactive'), color: 'gray' },
}

const severityMap = {
  low: { label: t('fuel.severityLow', 'Faible'), color: 'blue' },
  medium: { label: t('fuel.severityMedium', 'Moyen'), color: 'yellow' },
  high: { label: t('fuel.severityHigh', 'Élevé'), color: 'red' },
  critical: { label: t('fuel.severityCritical', 'Critique'), color: 'red' },
}

const incidentStatusMap = {
  reported: { label: t('fuel.incidentReported', 'Signalé'), color: 'yellow' },
  assigned: { label: t('fuel.incidentAssigned', 'Assigné'), color: 'blue' },
  in_progress: { label: t('fuel.incidentInProgress', 'En cours'), color: 'indigo' },
  resolved: { label: t('fuel.incidentResolved', 'Résolu'), color: 'green' },
  closed: { label: t('fuel.incidentClosed', 'Clos'), color: 'gray' },
}

const alertStatusMap = {
  open: { label: t('fuel.alertOpen', 'Ouverte'), color: 'yellow' },
  acknowledged: { label: t('fuel.alertAck', 'Accusée'), color: 'blue' },
  resolved: { label: t('fuel.alertResolved', 'Résolue'), color: 'green' },
}

const exportStatusMap = {
  pending: { label: t('fuel.exportPending', 'En attente'), color: 'yellow' },
  generating: { label: t('fuel.exportGenerating', 'Génération'), color: 'blue' },
  generated: { label: t('fuel.exportGenerated', 'Généré'), color: 'green' },
  failed: { label: t('fuel.exportFailed', 'Échec'), color: 'red' },
}

async function loadStations(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/fuel-station/stations', { params: { per_page: 50, page } })
    stations.value = res.data?.data ?? []
    stationsMeta.value = res.data?.meta ?? stationsMeta.value
  } catch (e) {
    error.value = e?.response?.data?.message || t('fuel.errorLoad', 'Impossible de charger les stations.')
  } finally {
    loading.value = false
  }
}

async function loadOverview() {
  try {
    const res = await api.get('/fuel-station/health/metrics')
    stats.value = {
      stations: stations.value.length,
      sales_today: 0,
      alerts_open: res.data?.data?.alerts_open ?? 0,
      outbox_failed: res.data?.data?.outbox_failed ?? 0,
    }
  } catch (e) {
    loadError.value = e?.response?.data?.message || t('fuel.errorMetrics', 'Métriques indisponibles.')
  }
}

async function loadEquipment() {
  if (!selectedStationId.value) return
  equipmentLoading.value = true
  equipmentError.value = ''
  try {
    const stationId = selectedStationId.value
    const [pRes, tRes, mRes] = await Promise.all([
      api.get(`/fuel-station/stations/${stationId}/pumps`, { params: { per_page: 100 } }),
      api.get(`/fuel-station/stations/${stationId}/tanks`, { params: { per_page: 100 } }),
      api.get(`/fuel-station/stations/${stationId}/meters`, { params: { per_page: 100 } }),
    ])
    pumps.value = pRes.data?.data ?? []
    tanks.value = tRes.data?.data ?? []
    meters.value = mRes.data?.data ?? []
  } catch (e) {
    equipmentError.value = e?.response?.data?.message || t('fuel.errorEquipment', 'Impossible de charger les équipements.')
  } finally {
    equipmentLoading.value = false
  }
}

async function loadShifts() {
  try {
    const res = await api.get('/fuel-station/shifts', { params: { per_page: 100 } })
    shifts.value = res.data?.data ?? []
  } catch (e) {
    error.value = e?.response?.data?.message || t('fuel.errorLoad', 'Impossible de charger les shifts.')
  }
}

async function loadIncidents() {
  try {
    const res = await api.get('/fuel-station/incidents', { params: { per_page: 50 } })
    incidents.value = res.data?.data ?? []
  } catch (e) {
    error.value = e?.response?.data?.message || t('fuel.errorLoad', 'Impossible de charger les incidents.')
  }
}

async function loadAlerts() {
  try {
    const res = await api.get('/fuel-station/alerts', { params: { per_page: 50 } })
    alerts.value = res.data?.data ?? []
  } catch (e) {
    error.value = e?.response?.data?.message || t('fuel.errorLoad', 'Impossible de charger les alertes.')
  }
}

async function loadExports() {
  exportLoading.value = true
  try {
    const res = await api.get('/fuel-station/reports/exports', { params: { per_page: 20 } })
    exports.value = res.data?.data ?? []
  } catch (e) {
    error.value = e?.response?.data?.message || t('fuel.errorLoad', 'Impossible de charger les exports.')
  } finally {
    exportLoading.value = false
  }
}

function switchTab(key) {
  activeTab.value = key
  if (key === 'overview') loadOverview()
  if (key === 'stations') loadStations()
  if (key === 'equipment') loadStations()
  if (key === 'shifts') loadShifts()
  if (key === 'incidents') loadIncidents()
  if (key === 'alerts') loadAlerts()
  if (key === 'reports') loadExports()
}

function selectStation(row) {
  selectedStationId.value = String(row.id)
  activeTab.value = 'equipment'
  loadEquipment()
}

function onStationSelect(event) {
  selectedStationId.value = event.target.value
  loadEquipment()
}

function openCreateStation() {
  const name = window.prompt(t('fuel.promptStationName', 'Nom de la station :'))
  if (!name) return
  const code = window.prompt(t('fuel.promptStationCode', 'Code de la station (ex. ST-01) :'))
  if (!code) return
  api
    .post('/fuel-station/stations', {
      code,
      name,
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
      status: 'active',
    })
    .then(() => loadStations())
    .catch((e) => {
      error.value = e?.response?.data?.message || t('fuel.errorCreate', 'Impossible de créer la station.')
    })
}

async function openReport(type) {
  const station = stations.value[0]
  if (!station) {
    loadError.value = t('fuel.errorNoStation', 'Créez d\'abord une station.')
    return
  }
  reportLoading.value = true
  try {
    const res = await api.get(`/fuel-station/reports/${type}`, { params: { station_id: station.id } })
    reportPayload.value = JSON.stringify(res.data?.data ?? {}, null, 2)
  } catch (e) {
    reportPayload.value = e?.response?.data?.message || t('fuel.errorReport', 'Rapport indisponible.')
  } finally {
    reportLoading.value = false
  }
}

async function triggerExport() {
  const station = stations.value[0]
  if (!station) {
    loadError.value = t('fuel.errorNoStation', 'Créez d\'abord une station.')
    return
  }
  exportLoading.value = true
  try {
    await api.post('/fuel-station/reports/exports', { report_type: 'daily_volumes', station_id: station.id })
    await loadExports()
  } catch (e) {
    error.value = e?.response?.data?.message || t('fuel.errorExport', 'Impossible de lancer l\'export.')
  } finally {
    exportLoading.value = false
  }
}

async function advanceIncident(row) {
  const next = { reported: 'assigned', assigned: 'in_progress', in_progress: 'resolved' }[row.status]
  const notes = next === 'resolved' ? window.prompt(t('fuel.promptResolution', 'Notes de résolution :')) : null
  if (next === 'resolved' && !notes) return
  try {
    await api.post(`/fuel-station/incidents/${row.id}/transition`, { status: next, resolution_notes: notes })
    await loadIncidents()
  } catch (e) {
    error.value = e?.response?.data?.message || t('fuel.errorTransition', 'Transition refusée.')
  }
}

async function ackAlert(row) {
  try {
    await api.post(`/fuel-station/alerts/${row.id}/acknowledge`)
    await loadAlerts()
  } catch (e) {
    error.value = e?.response?.data?.message || t('fuel.errorAlert', 'Action refusée.')
  }
}

async function resolveAlert(row) {
  try {
    await api.post(`/fuel-station/alerts/${row.id}/resolve`)
    await loadAlerts()
  } catch (e) {
    error.value = e?.response?.data?.message || t('fuel.errorAlert', 'Action refusée.')
  }
}

onMounted(() => {
  loadOverview()
})
</script>
