<template>
  <div class="space-y-8">
    <TravelCrudSection
      ref="routesSection"
      :config="routeConfig"
      :lookups="{ cities: cityOptions }"
      :column-display="{ origin_city_id: cityName, destination_city_id: cityName }"
    />

    <div class="border-t border-slate-200/50 pt-8 dark:border-slate-800/50">
      <TravelCrudSection
        ref="tripsSection"
        :config="tripConfig"
        :lookups="{ routes: routeOptions, carriers: carrierOptions, vehicles: vehicleOptions, classes: classOptions }"
        :column-display="{
          route_id: routeName,
          carrier_id: carrierName,
          vehicle_id: vehicleName
        }"
        @action="onTripAction"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import TravelCrudSection from '@/components/travel/TravelCrudSection.vue'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const toast = useToast()

const routesSection = ref(null)
const tripsSection = ref(null)

/* ── lookups ────────────────────────────────────────────────── */
const cities = ref([])
const routes = ref([])
const carriers = ref([])
const vehicles = ref([])
const classes = ref([])

const cityOptions = computed(() =>
  cities.value.map((c) => ({ value: c.id, label: `${c.name}${c.country_iso2 ? ` (${c.country_iso2})` : ''}` }))
)
const routeOptions = computed(() => routes.value.map((r) => ({ value: r.id, label: r.code })))
const carrierOptions = computed(() => carriers.value.map((c) => ({ value: c.id, label: c.name })))
const vehicleOptions = computed(() => vehicles.value.map((v) => ({ value: v.id, label: v.code })))
const classOptions = computed(() => classes.value.map((c) => ({ value: c.id, label: c.label })))

function cityName(_row, value) {
  const city = cities.value.find((c) => String(c.id) === String(value))
  return city ? `${city.name} (${city.country_iso2 || ''})` : value
}
function routeName(_row, value) {
  const route = routes.value.find((r) => String(r.id) === String(value))
  return route ? route.code : value
}
function carrierName(_row, value) {
  const carrier = carriers.value.find((c) => String(c.id) === String(value))
  return carrier ? carrier.name : value
}
function vehicleName(_row, value) {
  const vehicle = vehicles.value.find((v) => String(v.id) === String(value))
  return vehicle ? vehicle.code : value
}

async function loadLookups() {
  try {
    const [cRes, rRes, caRes, vRes, clRes] = await Promise.all([
      api.get('/travel/cities', { params: { per_page: 100 }, _skipAuthRedirect: true }),
      api.get('/travel/routes', { params: { per_page: 100 }, _skipAuthRedirect: true }),
      api.get('/travel/carriers', { params: { per_page: 100 }, _skipAuthRedirect: true }),
      api.get('/travel/vehicles', { params: { per_page: 100 }, _skipAuthRedirect: true }),
      api.get('/travel/classes', { params: { per_page: 100 }, _skipAuthRedirect: true })
    ])
    cities.value = cRes.data?.data || []
    routes.value = rRes.data?.data || []
    carriers.value = caRes.data?.data || []
    vehicles.value = vRes.data?.data || []
    classes.value = clRes.data?.data || []
  } catch {
    // Les lookups restent vides — les selects seront incomplets mais la liste principale s'affiche.
  }
}

/* ── statuts ────────────────────────────────────────────────── */
const routeStatusMap = {
  active: { labelKey: 'travel.status.active', color: 'green' },
  inactive: { labelKey: 'travel.status.inactive', color: 'yellow' },
  archived: { labelKey: 'travel.status.archived', color: 'gray' }
}

const tripStatusMap = {
  draft: { labelKey: 'travel.tripStatus.draft', color: 'gray' },
  published: { labelKey: 'travel.tripStatus.published', color: 'green' },
  cancelled: { labelKey: 'travel.tripStatus.cancelled', color: 'red' }
}

const tripStatusField = {
  key: 'status', label: 'travel.field.status', type: 'select',
  options: [
    { value: 'draft', label: t('travel.tripStatus.draft', 'Brouillon') },
    { value: 'published', label: t('travel.tripStatus.published', 'Publié') },
    { value: 'cancelled', label: t('travel.tripStatus.cancelled', 'Annulé') }
  ]
}

const meansOptions = [
  { value: 'bus', label: t('travel.means.bus', 'Bus / autocar') },
  { value: 'train', label: t('travel.means.train', 'Train') },
  { value: 'boat', label: t('travel.means.boat', 'Bateau') },
  { value: 'plane', label: t('travel.means.plane', 'Avion') }
]

/* ── routes ─────────────────────────────────────────────────── */
const routeConfig = computed(() => ({
  resource: 'routes',
  titleKey: 'travel.routes.title',
  subtitleKey: 'travel.routes.subtitle',
  searchPlaceholderKey: 'travel.search.route',
  searchKeys: ['code', 'origin_city_id', 'destination_city_id'],
  defaultSort: 'code',
  statusField: 'status',
  statusMap: routeStatusMap,
  columns: [
    { key: 'code', label: 'travel.field.code', sortable: true },
    { key: 'origin_city_id', label: 'travel.field.origin', sortable: true },
    { key: 'destination_city_id', label: 'travel.field.destination', sortable: true },
    { key: 'distance_km', label: 'travel.field.distanceKm', sortable: true },
    { key: 'duration_min', label: 'travel.field.durationMin', sortable: true },
    { key: 'status', label: 'travel.field.status', sortable: true }
  ],
  fields: [
    { key: 'code', label: 'travel.field.code', type: 'text', required: true, max: 40 },
    { key: 'origin_city_id', label: 'travel.field.origin', type: 'select', source: 'cities', required: true },
    { key: 'destination_city_id', label: 'travel.field.destination', type: 'select', source: 'cities', required: true },
    { key: 'distance_km', label: 'travel.field.distanceKm', type: 'number', min: 0 },
    { key: 'duration_min', label: 'travel.field.durationMin', type: 'number', min: 0 },
    { key: 'status', label: 'travel.field.status', type: 'select', options: [
      { value: 'active', label: t('travel.status.active', 'Actif') },
      { value: 'inactive', label: t('travel.status.inactive', 'Inactif') }
    ] }
  ],
  defaults: { status: 'active' },
  nested: {
    titleKey: 'travel.routes.stopsTitle',
    resource: 'routes/{id}/stops',
    columns: [
      { key: 'rank', label: 'travel.field.rank', type: 'number' },
      { key: 'city_id', label: 'travel.field.city' },
      { key: 'is_stopover', label: 'travel.field.isStopover' },
      { key: 'min_duration_min', label: 'travel.field.minDurationMin' }
    ],
    fields: [
      { key: 'city_id', label: 'travel.field.city', type: 'select', source: 'cities', required: true },
      { key: 'rank', label: 'travel.field.rank', type: 'number', required: true, min: 0 },
      { key: 'is_stopover', label: 'travel.field.isStopover', type: 'checkbox' },
      { key: 'min_duration_min', label: 'travel.field.minDurationMin', type: 'number', min: 0 }
    ],
    defaults: { is_stopover: true }
  }
}))

/* ── trajets ────────────────────────────────────────────────── */
const tripConfig = computed(() => ({
  resource: 'trips',
  titleKey: 'travel.trips.title',
  subtitleKey: 'travel.trips.subtitle',
  searchPlaceholderKey: 'travel.search.trip',
  searchKeys: ['code', 'route_id', 'departure_date'],
  defaultSort: 'departure_date',
  defaultSortDir: 'desc',
  statusField: 'status',
  statusMap: tripStatusMap,
  rowActions: [
    {
      key: 'publish',
      label: 'travel.action.publish',
      condition: (row) => row.status === 'draft'
    },
    {
      key: 'cancel',
      label: 'travel.action.cancelTrip',
      condition: (row) => row.status !== 'cancelled'
    }
  ],
  columns: [
    { key: 'code', label: 'travel.field.code', sortable: true },
    { key: 'route_id', label: 'travel.field.route', sortable: true },
    { key: 'departure_date', label: 'travel.field.departureDate', sortable: true },
    { key: 'departure_time', label: 'travel.field.departureTime', sortable: true },
    { key: 'means_of_transport', label: 'travel.field.means', sortable: true },
    { key: 'total_seats', label: 'travel.field.totalSeats', sortable: true },
    { key: 'status', label: 'travel.field.status', sortable: true }
  ],
  fields: [
    { key: 'code', label: 'travel.field.code', type: 'text', required: true, max: 40 },
    { key: 'route_id', label: 'travel.field.route', type: 'select', source: 'routes', required: true },
    { key: 'carrier_id', label: 'travel.field.carrier', type: 'select', source: 'carriers' },
    { key: 'vehicle_id', label: 'travel.field.vehicle', type: 'select', source: 'vehicles' },
    { key: 'departure_date', label: 'travel.field.departureDate', type: 'date', required: true },
    { key: 'departure_time', label: 'travel.field.departureTime', type: 'time', required: true },
    { key: 'arrival_date', label: 'travel.field.arrivalDate', type: 'date', required: true },
    { key: 'arrival_time', label: 'travel.field.arrivalTime', type: 'time', required: true },
    { key: 'means_of_transport', label: 'travel.field.means', type: 'select', required: true, options: meansOptions },
    { key: 'total_seats', label: 'travel.field.totalSeats', type: 'number', required: true, min: 1 },
    tripStatusField
  ],
  defaults: { means_of_transport: 'bus', status: 'draft', total_seats: 45 },
  nested: {
    titleKey: 'travel.trips.pricesTitle',
    resource: 'trips/{id}/prices',
    columns: [
      { key: 'class_id', label: 'travel.field.class' },
      { key: 'adult_price_minor', label: 'travel.field.adultPrice', type: 'money' },
      { key: 'child_price_minor', label: 'travel.field.childPrice', type: 'money' },
      { key: 'currency', label: 'travel.field.currency' }
    ],
    fields: [
      { key: 'class_id', label: 'travel.field.class', type: 'select', source: 'classes', required: true },
      { key: 'adult_price_minor', label: 'travel.field.adultPrice', type: 'money', required: true, min: 0 },
      { key: 'child_price_minor', label: 'travel.field.childPrice', type: 'money', min: 0 },
      { key: 'currency', label: 'travel.field.currency', type: 'text', required: true, max: 3 }
    ],
    defaults: { currency: 'XAF' }
  }
}))

/* ── actions publication / annulation ───────────────────────── */
async function onTripAction({ key, row }) {
  try {
    if (key === 'publish') {
      await api.post(`/travel/trips/${row.id}/publish`, {}, { _skipAuthRedirect: true })
      toast.success(t('travel.toast.published', 'Trajet publié.'))
    } else if (key === 'cancel') {
      const ok = window.confirm(t('travel.confirm.cancelTrip', 'Annuler ce trajet ? Les réservations seront traitées selon les règles métier.'))
      if (!ok) return
      await api.post(`/travel/trips/${row.id}/cancel`, {}, { _skipAuthRedirect: true })
      toast.success(t('travel.toast.cancelled', 'Trajet annulé.'))
    }
    tripsSection.value?.load()
  } catch (err) {
    toast.error(err.response?.data?.message || err.response?.data?.localized_message || t('travel.error.actionFailed', "L'action a échoué."))
  }
}

onMounted(loadLookups)
</script>
