<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('travel.network.title', 'Routes & trajets') }}
      </h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        {{ t('travel.network.subtitle', 'Lignes, étapes, programmation, tarifs et publication.') }}
      </p>
    </div>

    <TravelGate :mode="gateMode" :message="loadError" @retry="init" />

    <template v-if="!gateMode">
      <div class="flex flex-wrap gap-2">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="rounded-md px-4 py-2 text-sm font-medium transition-all"
          :class="activeTab === tab.key
            ? 'bg-brand-500 text-white shadow-lg shadow-brand-500/25'
            : 'glass-card text-slate-600 ring-1 ring-slate-200 dark:text-slate-400 dark:ring-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800'"
          @click="switchTab(tab.key)"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- ─────────── ROUTES ─────────── -->
      <template v-if="activeTab === 'routes'">
        <div class="flex items-center justify-between gap-3">
          <input
            v-model="routeQuery"
            type="search"
            class="w-full max-w-sm rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            :placeholder="t('travel.common.search', 'Rechercher…')"
          />
          <button class="btn-primary inline-flex items-center gap-1.5" @click="openRouteCreate">
            <PlusIcon class="h-4 w-4" />
            {{ t('travel.common.create', 'Créer') }}
          </button>
        </div>

        <DataTable
          :columns="routeColumns"
          :rows="filteredRoutes"
          :loading="loading.routes"
          :error="errors.routes"
          :search-keys="['code']"
          :empty-message="t('travel.common.noData', 'Aucune donnée')"
        >
          <template #cell-status="{ value }">
            <StatusBadge :status="value" :map="statusMap" />
          </template>
          <template #row-actions="{ row }">
            <div class="flex justify-end gap-2">
              <button class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400" @click="openRouteStops(row)">
                {{ t('travel.network.stops', 'Étapes') }}
              </button>
              <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="openRouteEdit(row)">
                {{ t('travel.common.edit', 'Modifier') }}
              </button>
              <button class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="askDeleteRoute(row)">
                {{ t('travel.common.delete', 'Supprimer') }}
              </button>
            </div>
          </template>
        </DataTable>

        <!-- Panel étapes d'une route -->
        <div v-if="selectedRoute" class="rounded-2xl glass-card p-5">
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">
              {{ t('travel.network.routeStopsTitle', 'Étapes de la route').replace(':code', selectedRoute.code || '') }} — {{ selectedRoute.code }}
            </h2>
            <button class="btn-secondary" @click="selectedRoute = null">
              {{ t('travel.common.close', 'Fermer') }}
            </button>
          </div>
          <div class="mt-4 flex justify-end">
            <button class="btn-primary inline-flex items-center gap-1.5" @click="openStopCreate">
              <PlusIcon class="h-4 w-4" />
              {{ t('travel.network.addStop', 'Ajouter une étape') }}
            </button>
          </div>
          <DataTable
            class="mt-2"
            :columns="stopColumns"
            :rows="selectedRoute.stops || []"
            :loading="loading.stops"
            :error="errors.stops"
            :empty-message="t('travel.common.noData', 'Aucune donnée')"
          >
            <template #cell-is_stopover="{ value }">
              <span class="text-sm">{{ value ? t('travel.common.yes', 'Oui') : t('travel.common.no', 'Non') }}</span>
            </template>
            <template #row-actions="{ row }">
              <div class="flex justify-end gap-2">
                <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="openStopEdit(row)">
                  {{ t('travel.common.edit', 'Modifier') }}
                </button>
                <button class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="askDeleteStop(row)">
                  {{ t('travel.common.delete', 'Supprimer') }}
                </button>
              </div>
            </template>
          </DataTable>
        </div>
      </template>

      <!-- ─────────── TRAJETS ─────────── -->
      <template v-else>
        <div class="flex items-center justify-between gap-3">
          <div class="flex w-full max-w-md gap-2">
            <input
              v-model="tripQuery"
              type="search"
              class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
              :placeholder="t('travel.common.search', 'Rechercher…')"
            />
            <select
              v-model="tripStatusFilter"
              class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
              :aria-label="t('travel.common.status', 'Statut')"
            >
              <option value="">{{ t('travel.common.all', 'Tous') }}</option>
              <option v-for="s in tripStatusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </div>
          <button class="btn-primary inline-flex items-center gap-1.5" @click="openTripCreate">
            <PlusIcon class="h-4 w-4" />
            {{ t('travel.common.create', 'Créer') }}
          </button>
        </div>

        <DataTable
          :columns="tripColumns"
          :rows="filteredTrips"
          :loading="loading.trips"
          :error="errors.trips"
          :search-keys="['code']"
          :empty-message="t('travel.common.noData', 'Aucune donnée')"
        >
          <template #cell-status="{ value }">
            <StatusBadge :status="value" :map="tripStatusMap" />
          </template>
          <template #row-actions="{ row }">
            <div class="flex justify-end gap-2">
              <button class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400" @click="openTripPrices(row)">
                {{ t('travel.network.prices', 'Tarifs') }}
              </button>
              <button
                v-if="['draft', 'scheduled'].includes(row.status)"
                class="text-sm font-medium text-emerald-600 hover:text-emerald-800 dark:text-emerald-400"
                @click="publishTrip(row)"
              >
                {{ t('travel.network.publish', 'Publier') }}
              </button>
              <button
                v-if="row.status !== 'cancelled'"
                class="text-sm font-medium text-amber-600 hover:text-amber-800 dark:text-amber-400"
                @click="openTripCancel(row)"
              >
                {{ t('travel.network.cancelTrip', 'Annuler le trajet') }}
              </button>
              <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="openTripEdit(row)">
                {{ t('travel.common.edit', 'Modifier') }}
              </button>
              <button class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="askDeleteTrip(row)">
                {{ t('travel.common.delete', 'Supprimer') }}
              </button>
            </div>
          </template>
        </DataTable>

        <!-- Panel tarifs d'un trajet -->
        <div v-if="selectedTrip" class="rounded-2xl glass-card p-5">
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">
              {{ t('travel.network.tripPricesTitle', 'Tarifs du trajet') }} — {{ selectedTrip.code }}
            </h2>
            <button class="btn-secondary" @click="selectedTrip = null">
              {{ t('travel.common.close', 'Fermer') }}
            </button>
          </div>
          <div class="mt-4 flex justify-end">
            <button class="btn-primary inline-flex items-center gap-1.5" @click="openPriceCreate">
              <PlusIcon class="h-4 w-4" />
              {{ t('travel.network.addPrice', 'Ajouter un tarif') }}
            </button>
          </div>
          <DataTable
            class="mt-2"
            :columns="priceColumns"
            :rows="selectedTrip.prices || []"
            :loading="loading.prices"
            :error="errors.prices"
            :empty-message="t('travel.common.noData', 'Aucune donnée')"
          >
            <template #cell-adult_price_minor="{ value, row }">
              {{ formatMinor(value, row.currency) }}
            </template>
            <template #cell-child_price_minor="{ value, row }">
              {{ value ? formatMinor(value, row.currency) : '—' }}
            </template>
            <template #row-actions="{ row }">
              <div class="flex justify-end gap-2">
                <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="openPriceEdit(row)">
                  {{ t('travel.common.edit', 'Modifier') }}
                </button>
                <button class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="askDeletePrice(row)">
                  {{ t('travel.common.delete', 'Supprimer') }}
                </button>
              </div>
            </template>
          </DataTable>
        </div>
      </template>

      <!-- Modales -->
      <TravelFormModal
        :open="routeModalOpen"
        :title="editingRoute ? t('travel.common.edit', 'Modifier') : t('travel.common.create', 'Créer')"
        :fields="routeFields"
        :values="editingRoute || {}"
        :busy="saving"
        :error="formError"
        @save="saveRoute"
        @cancel="routeModalOpen = false"
      />

      <TravelFormModal
        :open="stopModalOpen"
        :title="editingStop ? t('travel.common.edit', 'Modifier') : t('travel.network.addStop', 'Ajouter une étape')"
        :fields="stopFields"
        :values="editingStop || {}"
        :busy="saving"
        :error="formError"
        @save="saveStop"
        @cancel="stopModalOpen = false"
      />

      <TravelFormModal
        :open="tripModalOpen"
        :title="editingTrip ? t('travel.common.edit', 'Modifier') : t('travel.common.create', 'Créer')"
        :fields="tripFields"
        :values="editingTrip || {}"
        :busy="saving"
        :error="formError"
        @save="saveTrip"
        @cancel="tripModalOpen = false"
      />

      <TravelFormModal
        :open="priceModalOpen"
        :title="editingPrice ? t('travel.common.edit', 'Modifier') : t('travel.network.addPrice', 'Ajouter un tarif')"
        :fields="priceFields"
        :values="editingPrice || {}"
        :busy="saving"
        :error="formError"
        @save="savePrice"
        @cancel="priceModalOpen = false"
      />

      <TravelFormModal
        :open="cancelTripModalOpen"
        :title="t('travel.network.cancelTrip', 'Annuler le trajet')"
        :fields="cancelTripFields"
        :values="{}"
        :busy="saving"
        :error="formError"
        @save="confirmCancelTrip"
        @cancel="cancelTripModalOpen = false"
      />

      <ConfirmDialog
        :open="deleteOpen"
        :title="t('travel.common.confirmDeleteTitle', 'Supprimer cet élément ?')"
        :message="deleteMessage"
        :confirm-label="t('travel.common.delete', 'Supprimer')"
        @confirm="confirmDelete"
        @cancel="deleteOpen = false"
      />
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { useTravelStore } from '@/stores/travel'
import TravelGate from '@/components/travel/TravelGate.vue'
import TravelFormModal from '@/components/travel/TravelFormModal.vue'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import { PlusIcon } from '@heroicons/vue/24/outline'
import {
  listTravel,
  createTravel,
  updateTravel,
  deleteTravel,
  travelAction,
  createTravelSub,
  listTravelSub,
  updateTravelSub,
  deleteTravelSub,
  travelList,
  formatMinor
} from '@/services/travel'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const travelStore = useTravelStore()

const activeTab = ref('routes')
const routeQuery = ref('')
const tripQuery = ref('')
const tripStatusFilter = ref('')
const loading = reactive({ routes: false, trips: false, stops: false, prices: false })
const errors = reactive({})
const lists = reactive({ routes: [], trips: [], cities: [], carriers: [], vehicles: [], classes: [] })
const loadError = ref('')

const selectedRoute = ref(null)
const selectedTrip = ref(null)

const routeModalOpen = ref(false)
const editingRoute = ref(null)
const stopModalOpen = ref(false)
const editingStop = ref(null)
const tripModalOpen = ref(false)
const editingTrip = ref(null)
const priceModalOpen = ref(false)
const editingPrice = ref(null)
const cancelTripModalOpen = ref(false)
const cancelTripTarget = ref(null)
const saving = ref(false)
const formError = ref('')

const deleteOpen = ref(false)
const deleteAction = ref(null)
const deleteMessage = ref('')

const gateMode = computed(() => {
  if (!travelStore.isReady) return ''
  if (travelStore.noTenantContext) return 'tenant'
  if (!travelStore.flagActive) return 'feature'
  return ''
})

const tabs = computed(() => [
  { key: 'routes', label: t('travel.network.routes', 'Routes & étapes') },
  { key: 'trips', label: t('travel.network.trips', 'Trajets & tarifs') }
])

const statusMap = {
  active: { label: t('travel.common.active', 'Actif'), color: 'green' },
  disabled: { label: t('travel.common.disabled', 'Inactif'), color: 'gray' }
}

const tripStatusMap = {
  draft: { label: t('travel.tripStatus.draft', 'Brouillon'), color: 'gray' },
  scheduled: { label: t('travel.tripStatus.scheduled', 'Planifié'), color: 'blue' },
  published: { label: t('travel.tripStatus.published', 'Publié'), color: 'green' },
  cancelled: { label: t('travel.tripStatus.cancelled', 'Annulé'), color: 'red' }
}

const tripStatusOptions = computed(() =>
  Object.keys(tripStatusMap).map((value) => ({ value, label: tripStatusMap[value].label }))
)

const cityOptions = computed(() =>
  lists.cities.map((city) => ({ value: city.id, label: `${city.name}${city.country_iso2 ? ` (${city.country_iso2})` : ''}` }))
)
const routeOptions = computed(() => lists.routes.map((route) => ({
  value: route.id,
  label: `${route.code} — ${cityName(route.origin_city_id)} → ${cityName(route.destination_city_id)}`
})))
const carrierOptions = computed(() =>
  lists.carriers.filter((c) => c.status === 'active').map((c) => ({ value: c.id, label: c.name }))
)
const vehicleOptions = computed(() =>
  lists.vehicles.filter((v) => v.status === 'active').map((v) => ({ value: v.id, label: `${v.code}${v.registration_number ? ` (${v.registration_number})` : ''}` }))
)
const classOptions = computed(() =>
  lists.classes.filter((c) => c.status === 'active').map((c) => ({ value: c.id, label: c.label }))
)

const statusFieldOptions = computed(() => [
  { value: 'active', label: t('travel.common.active', 'Actif') },
  { value: 'disabled', label: t('travel.common.disabled', 'Inactif') }
])

const meansOptions = computed(() => [
  { value: 'bus', label: t('travel.carrierTypes.bus', 'Bus') },
  { value: 'train', label: t('travel.carrierTypes.train', 'Train') },
  { value: 'plane', label: t('travel.carrierTypes.plane', 'Avion') },
  { value: 'boat', label: t('travel.carrierTypes.boat', 'Bateau') }
])

function cityName(cityId) {
  const city = lists.cities.find((c) => c.id === cityId)
  return city ? city.name : (cityId ?? '—')
}

const routeColumns = computed(() => [
  { key: 'code', label: 'Code', sortable: true },
  { key: 'origin_city_id', label: t('travel.network.origin', 'Origine'), sortable: true },
  { key: 'destination_city_id', label: t('travel.network.destination', 'Destination'), sortable: true },
  { key: 'distance_km', label: t('travel.network.distance', 'Distance (km)'), sortable: true },
  { key: 'duration_min', label: t('travel.network.duration', 'Durée (min)'), sortable: true },
  { key: 'status', label: t('travel.common.status', 'Statut'), sortable: true }
])

const stopColumns = computed(() => [
  { key: 'rank', label: t('travel.network.rank', 'Rang'), sortable: true },
  { key: 'city_id', label: t('travel.common.city', 'Ville'), sortable: true },
  { key: 'is_stopover', label: t('travel.network.stopover', 'Escale') },
  { key: 'min_duration_min', label: t('travel.network.minDuration', 'Durée min (min)') }
])

const tripColumns = computed(() => [
  { key: 'code', label: 'Code', sortable: true },
  { key: 'route_id', label: t('travel.network.route', 'Route'), sortable: true },
  { key: 'departure_date', label: t('travel.network.departure', 'Départ'), sortable: true },
  { key: 'arrival_date', label: t('travel.network.arrival', 'Arrivée'), sortable: true },
  { key: 'means_of_transport', label: t('travel.network.means', 'Moyen'), sortable: true },
  { key: 'total_seats', label: t('travel.vehicles.seats', 'Places'), sortable: true },
  { key: 'status', label: t('travel.common.status', 'Statut'), sortable: true }
])

const priceColumns = computed(() => [
  { key: 'class_id', label: t('travel.network.class', 'Classe'), sortable: true },
  { key: 'adult_price_minor', label: t('travel.network.adultPrice', 'Tarif adulte') },
  { key: 'child_price_minor', label: t('travel.network.childPrice', 'Tarif enfant') },
  { key: 'currency', label: t('travel.network.currency', 'Devise'), sortable: true }
])

const routeFields = computed(() => [
  { key: 'code', label: 'travel.network.routeCode', type: 'text', required: true, max: 40 },
  { key: 'origin_city_id', label: 'travel.network.origin', type: 'select', required: true, options: cityOptions },
  { key: 'destination_city_id', label: 'travel.network.destination', type: 'select', required: true, options: cityOptions },
  { key: 'distance_km', label: 'travel.network.distance', type: 'number', min: 0 },
  { key: 'duration_min', label: 'travel.network.duration', type: 'number', min: 1 },
  { key: 'status', label: 'travel.common.status', type: 'select', options: statusFieldOptions }
])

const stopFields = computed(() => [
  { key: 'city_id', label: 'travel.common.city', type: 'select', required: true, options: cityOptions },
  { key: 'rank', label: 'travel.network.rank', type: 'number', min: 1 },
  { key: 'is_stopover', label: 'travel.network.stopover', type: 'checkbox' },
  { key: 'min_duration_min', label: 'travel.network.minDuration', type: 'number', min: 1 }
])

const tripFields = computed(() => [
  { key: 'code', label: 'travel.network.tripCode', type: 'text', required: true, max: 40 },
  { key: 'route_id', label: 'travel.network.route', type: 'select', required: true, options: routeOptions },
  { key: 'carrier_id', label: 'travel.common.carrier', type: 'select', options: carrierOptions },
  { key: 'vehicle_id', label: 'travel.network.vehicle', type: 'select', options: vehicleOptions },
  { key: 'departure_date', label: 'travel.network.departureDate', type: 'text', required: true },
  { key: 'departure_time', label: 'travel.network.departureTime', type: 'text', required: true },
  { key: 'arrival_date', label: 'travel.network.arrivalDate', type: 'text', required: true },
  { key: 'arrival_time', label: 'travel.network.arrivalTime', type: 'text', required: true },
  { key: 'means_of_transport', label: 'travel.network.means', type: 'select', options: meansOptions },
  { key: 'total_seats', label: 'travel.vehicles.seats', type: 'number', required: true, min: 1, max: 200 },
  { key: 'status', label: 'travel.common.status', type: 'select', options: () => Object.keys(tripStatusMap).map((value) => ({ value, label: tripStatusMap[value].label })) }
])

const priceFields = computed(() => [
  { key: 'class_id', label: 'travel.network.class', type: 'select', required: true, options: classOptions },
  { key: 'adult_price_minor', label: 'travel.network.adultPrice', type: 'number', required: true, min: 1 },
  { key: 'child_price_minor', label: 'travel.network.childPrice', type: 'number', min: 1 },
  { key: 'currency', label: 'travel.network.currency', type: 'text', required: true, max: 3 }
])

const cancelTripFields = computed(() => [
  { key: 'reason', label: 'travel.common.reason', type: 'textarea', required: true, max: 500, rows: 3 }
])

const filteredRoutes = computed(() => {
  const q = routeQuery.value.trim().toLowerCase()
  const rows = lists.routes.map((route) => ({
    ...route,
    origin_city_id: cityName(route.origin_city_id),
    destination_city_id: cityName(route.destination_city_id)
  }))
  if (!q) return rows
  return rows.filter((r) => r.code.toLowerCase().includes(q) || r.origin_city_id.toLowerCase().includes(q))
})

const filteredTrips = computed(() => {
  const q = tripQuery.value.trim().toLowerCase()
  return (lists.trips || []).filter((trip) => {
    if (tripStatusFilter.value && trip.status !== tripStatusFilter.value) return false
    if (!q) return true
    return trip.code.toLowerCase().includes(q)
  })
})

async function load(key, params = {}) {
  loading[key] = true
  errors[key] = ''
  try {
    const response = await listTravel(key, { per_page: 1000, ...params })
    lists[key] = travelList(response)
  } catch (error) {
    errors[key] = error?.response?.data?.message || error?.message || t('travel.common.loadErrorBody', 'Une erreur est survenue.')
  } finally {
    loading[key] = false
  }
}

function switchTab(key) {
  activeTab.value = key
  if (key === 'trips' && lists.trips.length === 0) load('trips')
}

async function init() {
  await travelStore.checkFlag(true)
  if (gateMode.value) return
  loadError.value = ''
  await Promise.all([
    load('routes'),
    load('cities', { per_page: 1000 }),
    load('carriers'),
    load('vehicles'),
    load('classes')
  ])
}

function apiError(error) {
  const data = error?.response?.data
  const errorsBag = data?.errors
  if (errorsBag && typeof errorsBag === 'object') {
    const firstKey = Object.keys(errorsBag)[0]
    return Array.isArray(errorsBag[firstKey]) ? errorsBag[firstKey][0] : String(errorsBag[firstKey])
  }
  return data?.message || error?.message || t('travel.common.loadErrorBody', 'Une erreur est survenue.')
}

/* ─── Routes ─── */
function openRouteCreate() {
  editingRoute.value = null
  formError.value = ''
  routeModalOpen.value = true
}
function openRouteEdit(route) {
  editingRoute.value = route
  formError.value = ''
  routeModalOpen.value = true
}
async function saveRoute(values) {
  saving.value = true
  formError.value = ''
  try {
    if (editingRoute.value) {
      await updateTravel('routes', editingRoute.value.id, values)
    } else {
      await createTravel('routes', values)
    }
    routeModalOpen.value = false
    editingRoute.value = null
    await load('routes')
  } catch (error) {
    formError.value = apiError(error)
  } finally {
    saving.value = false
  }
}
function askDeleteRoute(route) {
  deleteAction.value = () => deleteTravel('routes', route.id).then(() => load('routes'))
  deleteMessage.value = t('travel.common.confirmDeleteBody', 'Cette action est irréversible. Voulez-vous vraiment supprimer « {name} » ?').replace('{name}', route.code)
  deleteOpen.value = true
}

/* ─── Étapes ─── */
async function openRouteStops(route) {
  selectedRoute.value = { ...route }
  await loadStops(route.id)
}
async function loadStops(routeId) {
  loading.stops = true
  errors.stops = ''
  try {
    const response = await listTravelSub('routes', routeId, 'stops', { per_page: 200 })
    if (selectedRoute.value) {
      selectedRoute.value.stops = travelList(response).sort((a, b) => a.rank - b.rank)
    }
  } catch (error) {
    errors.stops = apiError(error)
  } finally {
    loading.stops = false
  }
}
function openStopCreate() {
  editingStop.value = null
  formError.value = ''
  stopModalOpen.value = true
}
function openStopEdit(stop) {
  editingStop.value = stop
  formError.value = ''
  stopModalOpen.value = true
}
async function saveStop(values) {
  if (!selectedRoute.value) return
  saving.value = true
  formError.value = ''
  try {
    if (editingStop.value) {
      await updateTravelSub('routes', selectedRoute.value.id, 'stops', editingStop.value.id, values)
    } else {
      await createTravelSub('routes', selectedRoute.value.id, 'stops', values)
    }
    stopModalOpen.value = false
    editingStop.value = null
    await loadStops(selectedRoute.value.id)
  } catch (error) {
    formError.value = apiError(error)
  } finally {
    saving.value = false
  }
}
function askDeleteStop(stop) {
  deleteAction.value = () =>
    deleteTravelSub('routes', selectedRoute.value.id, 'stops', stop.id).then(() => loadStops(selectedRoute.value.id))
  deleteMessage.value = t('travel.common.confirmDeleteBody', 'Cette action est irréversible. Voulez-vous vraiment supprimer « {name} » ?').replace('{name}', `#${stop.rank}`)
  deleteOpen.value = true
}

/* ─── Trajets ─── */
function openTripCreate() {
  editingTrip.value = null
  formError.value = ''
  tripModalOpen.value = true
}
function openTripEdit(trip) {
  editingTrip.value = trip
  formError.value = ''
  tripModalOpen.value = true
}
async function saveTrip(values) {
  saving.value = true
  formError.value = ''
  try {
    if (editingTrip.value) {
      await updateTravel('trips', editingTrip.value.id, values)
    } else {
      await createTravel('trips', values)
    }
    tripModalOpen.value = false
    editingTrip.value = null
    await load('trips')
  } catch (error) {
    formError.value = apiError(error)
  } finally {
    saving.value = false
  }
}
function askDeleteTrip(trip) {
  deleteAction.value = () => deleteTravel('trips', trip.id).then(() => load('trips'))
  deleteMessage.value = t('travel.common.confirmDeleteBody', 'Cette action est irréversible. Voulez-vous vraiment supprimer « {name} » ?').replace('{name}', trip.code)
  deleteOpen.value = true
}
async function publishTrip(trip) {
  saving.value = true
  formError.value = ''
  try {
    await travelAction('trips', trip.id, 'publish')
    await load('trips')
  } catch (error) {
    errors.trips = apiError(error)
  } finally {
    saving.value = false
  }
}
function openTripCancel(trip) {
  cancelTripTarget.value = trip
  formError.value = ''
  cancelTripModalOpen.value = true
}
async function confirmCancelTrip(values) {
  if (!cancelTripTarget.value) return
  saving.value = true
  formError.value = ''
  try {
    await travelAction('trips', cancelTripTarget.value.id, 'cancel', { reason: values.reason })
    cancelTripModalOpen.value = false
    cancelTripTarget.value = null
    await load('trips')
  } catch (error) {
    formError.value = apiError(error)
  } finally {
    saving.value = false
  }
}

/* ─── Tarifs ─── */
async function openTripPrices(trip) {
  selectedTrip.value = { ...trip }
  await loadPrices(trip.id)
}
async function loadPrices(tripId) {
  loading.prices = true
  errors.prices = ''
  try {
    const response = await listTravelSub('trips', tripId, 'prices', { per_page: 100 })
    if (selectedTrip.value) {
      selectedTrip.value.prices = travelList(response).map((price) => ({
        ...price,
        class_id: classLabel(price.class_id)
      }))
    }
  } catch (error) {
    errors.prices = apiError(error)
  } finally {
    loading.prices = false
  }
}
function classLabel(classId) {
  const cls = lists.classes.find((c) => c.id === classId)
  return cls ? cls.label : (classId ?? '—')
}
function openPriceCreate() {
  editingPrice.value = null
  formError.value = ''
  priceModalOpen.value = true
}
function openPriceEdit(price) {
  editingPrice.value = price
  formError.value = ''
  priceModalOpen.value = true
}
async function savePrice(values) {
  if (!selectedTrip.value) return
  saving.value = true
  formError.value = ''
  try {
    if (editingPrice.value) {
      await updateTravelSub('trips', selectedTrip.value.id, 'prices', editingPrice.value.id, values)
    } else {
      await createTravelSub('trips', selectedTrip.value.id, 'prices', values)
    }
    priceModalOpen.value = false
    editingPrice.value = null
    await loadPrices(selectedTrip.value.id)
  } catch (error) {
    formError.value = apiError(error)
  } finally {
    saving.value = false
  }
}
function askDeletePrice(price) {
  deleteAction.value = () =>
    deleteTravelSub('trips', selectedTrip.value.id, 'prices', price.id).then(() => loadPrices(selectedTrip.value.id))
  deleteMessage.value = t('travel.common.confirmDeleteBody', 'Cette action est irréversible. Voulez-vous vraiment supprimer « {name} » ?').replace('{name}', price.class_id)
  deleteOpen.value = true
}

async function confirmDelete() {
  if (!deleteAction.value) return
  try {
    await deleteAction.value()
  } catch (error) {
    errors[activeTab.value] = apiError(error)
  } finally {
    deleteOpen.value = false
    deleteAction.value = null
  }
}

onMounted(init)
</script>
