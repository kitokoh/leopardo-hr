<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
      <StatsCard :title="t('fleet.statVehicles', 'Vehicules')" :value="stats.total" icon="ChartBarIcon" color="blue" />
      <StatsCard :title="t('fleet.statInService', 'En service')" :value="stats.in_service" icon="ChartBarIcon" color="green" />
      <StatsCard :title="t('fleet.statMaintenanceDue', 'Maintenance due')" :value="stats.maintenance_due" icon="ChartBarIcon" color="yellow" />
      <StatsCard :title="t('fleet.statAlerts', 'Alertes')" :value="stats.alerts" icon="ChartBarIcon" color="red" />
    </div>

    <div v-if="alertsError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
      {{ alertsError }}
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

    <div v-if="activeTab === 'map'" class="rounded-lg shadow">
      <div class="border-b border-gray-200 px-6 py-4">
        <h2 class="text-lg font-semibold text-gray-900">Carte des vehicules</h2>
        <p class="text-sm text-gray-500">Position en temps reel des vehicules de la flotte.</p>
      </div>
      <div ref="mapContainer" class="h-[500px] w-full" />
    </div>

    <DataTable
      v-else-if="activeTab === 'list'"
      :columns="vehicleColumns"
      :rows="vehicles"
      :loading="loading"
      :error="error"
      :search-keys="['plate_number', 'brand', 'model', 'assigned_to']"
      :search-placeholder="t('fleet.searchVehicle', 'Rechercher un vehicule...')"
      default-sort="plate_number"
      exportable
      @export="exportVehicles"
    >
      <template #cell-status="{ value }">
        <StatusBadge :status="value" :map="vehicleStatusMap" />
      </template>
      <template #row-actions="{ row }">
        <div class="flex justify-end gap-2">
          <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800" @click="centerOnVehicle(row)">
            Localiser
          </button>
          <button class="text-sm font-medium text-gray-600 hover:text-gray-800" @click="viewVehicle(row.id)">
            Detail
          </button>
        </div>
      </template>
    </DataTable>

    <DataTable
      v-else
      :columns="alertColumns"
      :rows="alerts"
      :loading="loading"
      :error="alertsError"
      :search-keys="['vehicle_plate', 'type', 'message']"
      :search-placeholder="t('fleet.searchAlert', 'Rechercher une alerte...')"
      default-sort="created_at"
      default-sort-dir="desc"
    >
      <template #cell-severity="{ value }">
        <StatusBadge :status="value" :map="severityMap" />
      </template>
    </DataTable>

    <VehicleDetailModal
      v-if="selectedVehicleId"
      :vehicle-id="selectedVehicleId"
      @close="selectedVehicleId = null"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick, watch } from 'vue'
import api, { downloadApiFile } from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import VehicleDetailModal from '@/components/fleet/VehicleDetailModal.vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const loading = ref(false)
const error = ref('')
const alertsError = ref('')
const vehicles = ref([])
const alerts = ref([])
const activeTab = ref('map')
const selectedVehicleId = ref(null)
const mapContainer = ref(null)
let leafletMap = null

const stats = ref({ total: 0, in_service: 0, maintenance_due: 0, alerts: 0 })

const tabs = [
  { key: 'map', label: t('fleet.tabsMap', 'Carte') },
  { key: 'list', label: t('fleet.tabsList', 'Liste') },
  { key: 'alerts', label: t('fleet.tabsAlerts', 'Alertes') },
]

const vehicleColumns = [
  { key: 'plate_number', label: t('fleet.colPlate', 'Immatriculation'), sortable: true },
  { key: 'brand', label: t('fleet.colBrand', 'Marque'), sortable: true },
  { key: 'model', label: t('fleet.colModel', 'Modele'), sortable: true },
  { key: 'assigned_to', label: t('fleet.colAssignedTo', 'Assigne a'), sortable: true },
  { key: 'km_current', label: t('fleet.colKm', 'Km'), sortable: true },
  { key: 'status', label: t('fleet.colStatus', 'Statut'), sortable: true },
]

const alertColumns = [
  { key: 'vehicle_plate', label: t('fleet.colVehicle', 'Vehicule'), sortable: true },
  { key: 'type', label: t('fleet.colType', 'Type'), sortable: true },
  { key: 'severity', label: t('fleet.colSeverity', 'Severite'), sortable: true },
  { key: 'message', label: t('fleet.colMessage', 'Message') },
  { key: 'created_at', label: t('fleet.colDate', 'Date'), sortable: true },
]

const vehicleStatusMap = {
  active: { label: t('fleet.statusActive', 'En service'), color: 'green' },
  maintenance: { label: t('fleet.statusMaintenance', 'Maintenance'), color: 'yellow' },
  inactive: { label: t('fleet.statusInactive', 'Inactif'), color: 'gray' },
  decommissioned: { label: t('fleet.statusDecommissioned', 'Reforme'), color: 'red' },
}

const severityMap = {
  low: { label: t('fleet.severityLow', 'Faible'), color: 'blue' },
  medium: { label: t('fleet.severityMedium', 'Moyen'), color: 'yellow' },
  high: { label: t('fleet.severityHigh', 'Eleve'), color: 'red' },
  critical: { label: t('fleet.severityCritical', 'Critique'), color: 'red' },
}

async function initMap() {
  if (!mapContainer.value) return
  const L = await import('leaflet')
  leafletMap = L.map(mapContainer.value).setView([36.75, 3.06], 6)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
  }).addTo(leafletMap)
  plotVehicles(L)
}

function escapeHtml(value) {
  // #4334 : les champs véhicule (plate_number, brand, model, assigned_to) sont
  // contrôlés par le tenant — les injecter bruts dans le popup Leaflet (innerHTML)
  // permettrait un XSS côté cockpit super-admin.
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;')
}

function plotVehicles(L) {
  if (!leafletMap || !L) return
  vehicles.value.forEach(v => {
    if (v.latitude && v.longitude) {
      L.marker([v.latitude, v.longitude])
        .bindPopup(`<b>${escapeHtml(v.plate_number)}</b><br>${escapeHtml(v.brand)} ${escapeHtml(v.model)}<br>${escapeHtml(v.assigned_to || t('fleet.unassigned', 'Non assigne'))}`)
        .addTo(leafletMap)
    }
  })
}

function centerOnVehicle(vehicle) {
  if (vehicle.latitude && vehicle.longitude && leafletMap) {
    activeTab.value = 'map'
    nextTick(() => leafletMap.setView([vehicle.latitude, vehicle.longitude], 14))
  }
}

watch(activeTab, async (val) => {
  if (val === 'map' && !leafletMap) {
    await nextTick()
    initMap()
  }
})

async function fetchData() {
  loading.value = true
  error.value = ''
  alertsError.value = ''
  try {
    // Route tenant (auth:sanctum + tenant + api.manager) : le token super-admin
    // ne s'y authentifie pas → 401 attendu. _skipAuthRedirect (#4170) évite
    // que l'intercepteur global détruise la session admin ; l'état d'erreur
    // local ci-dessous fait foi.
    const vRes = await api.get('/vehicles', { _skipAuthRedirect: true })
    vehicles.value = vRes.data.data || vRes.data || []
  } catch {
    error.value = 'Impossible de charger les donnees de flotte.'
  }
  // Alertes : best-effort mais jamais silencieux — un échec est surfacé
  // (bannière) au lieu d'une liste vide sans signal (audit 360° T016/#3739).
  try {
    const aRes = await api.get('/admin/fleet/alerts')
    alerts.value = aRes.data.data || aRes.data || []
  } catch {
    alertsError.value = 'Impossible de charger les alertes de flotte.'
  }
  stats.value = {
    total: vehicles.value.length,
    in_service: vehicles.value.filter(v => v.status === 'active').length,
    maintenance_due: vehicles.value.filter(v => v.status === 'maintenance').length,
    alerts: alerts.value.filter(a => a.severity === 'high' || a.severity === 'critical').length,
  }
  loading.value = false
}

function viewVehicle(id) {
  selectedVehicleId.value = id
}
function exportVehicles() {
  // Route tenant (api.manager) : 401 attendu en super-admin (#4170) — le
  // flag évite la déconnexion ; l'utilisateur voit l'état d'erreur local.
  downloadApiFile('/export/vehicles?format=csv', 'vehicles.csv', { _skipAuthRedirect: true })
}

onMounted(async () => {
  await fetchData()
  await nextTick()
  if (activeTab.value === 'map') initMap()
})
</script>

