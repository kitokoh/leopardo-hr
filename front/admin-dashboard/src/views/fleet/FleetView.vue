<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
      <StatsCard title="Vehicules" :value="stats.total" icon="ChartBarIcon" color="blue" />
      <StatsCard title="En service" :value="stats.in_service" icon="ChartBarIcon" color="green" />
      <StatsCard title="Maintenance due" :value="stats.maintenance_due" icon="ChartBarIcon" color="yellow" />
      <StatsCard title="Alertes" :value="stats.alerts" icon="ChartBarIcon" color="red" />
    </div>

    <div class="flex gap-2">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        :class="[
          'rounded-md px-4 py-2 text-sm font-medium',
          activeTab === tab.key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50'
        ]"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <div v-if="activeTab === 'map'" class="rounded-lg bg-white shadow">
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
      search-placeholder="Rechercher un vehicule..."
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
      :error="error"
      :search-keys="['vehicle_plate', 'type', 'message']"
      search-placeholder="Rechercher une alerte..."
      default-sort="created_at"
      default-sort-dir="desc"
    >
      <template #cell-severity="{ value }">
        <StatusBadge :status="value" :map="severityMap" />
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick, watch } from 'vue'
import api, { downloadApiFile } from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'

const loading = ref(false)
const error = ref('')
const vehicles = ref([])
const alerts = ref([])
const activeTab = ref('map')
const mapContainer = ref(null)
let leafletMap = null

const stats = ref({ total: 0, in_service: 0, maintenance_due: 0, alerts: 0 })

const tabs = [
  { key: 'map', label: 'Carte' },
  { key: 'list', label: 'Liste' },
  { key: 'alerts', label: 'Alertes' },
]

const vehicleColumns = [
  { key: 'plate_number', label: 'Immatriculation', sortable: true },
  { key: 'brand', label: 'Marque', sortable: true },
  { key: 'model', label: 'Modele', sortable: true },
  { key: 'assigned_to', label: 'Assigne a', sortable: true },
  { key: 'km_current', label: 'Km', sortable: true },
  { key: 'status', label: 'Statut', sortable: true },
]

const alertColumns = [
  { key: 'vehicle_plate', label: 'Vehicule', sortable: true },
  { key: 'type', label: 'Type', sortable: true },
  { key: 'severity', label: 'Severite', sortable: true },
  { key: 'message', label: 'Message' },
  { key: 'created_at', label: 'Date', sortable: true },
]

const vehicleStatusMap = {
  active: { label: 'En service', color: 'green' },
  maintenance: { label: 'Maintenance', color: 'yellow' },
  inactive: { label: 'Inactif', color: 'gray' },
  decommissioned: { label: 'Reforme', color: 'red' },
}

const severityMap = {
  low: { label: 'Faible', color: 'blue' },
  medium: { label: 'Moyen', color: 'yellow' },
  high: { label: 'Eleve', color: 'red' },
  critical: { label: 'Critique', color: 'red' },
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

function plotVehicles(L) {
  if (!leafletMap || !L) return
  vehicles.value.forEach(v => {
    if (v.latitude && v.longitude) {
      L.marker([v.latitude, v.longitude])
        .bindPopup(`<b>${v.plate_number}</b><br>${v.brand} ${v.model}<br>${v.assigned_to || 'Non assigne'}`)
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
  try {
    const [vRes, aRes] = await Promise.all([
      api.get('/v1/vehicles'),
      api.get('/v1/fleet/alerts').catch(() => ({ data: { data: [] } })),
    ])
    vehicles.value = vRes.data.data || vRes.data || []
    alerts.value = aRes.data.data || aRes.data || []
    stats.value = {
      total: vehicles.value.length,
      in_service: vehicles.value.filter(v => v.status === 'active').length,
      maintenance_due: vehicles.value.filter(v => v.status === 'maintenance').length,
      alerts: alerts.value.filter(a => a.severity === 'high' || a.severity === 'critical').length,
    }
  } catch {
    error.value = 'Impossible de charger les donnees de flotte.'
  } finally {
    loading.value = false
  }
}

function viewVehicle(id) { /* TODO: detail modal */ }
function exportVehicles() { downloadApiFile('/v1/export/vehicles?format=csv', 'vehicles.csv') }

onMounted(async () => {
  await fetchData()
  await nextTick()
  if (activeTab.value === 'map') initMap()
})
</script>
