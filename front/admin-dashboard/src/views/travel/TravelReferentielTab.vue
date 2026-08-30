<template>
  <div class="space-y-6">
    <div class="flex flex-wrap gap-2" role="tablist" :aria-label="$t('travel.referentiel.tabsLabel', 'Sous-sections référentiel')">
      <button
        v-for="sub in subTabs"
        :key="sub.key"
        type="button"
        role="tab"
        :aria-selected="activeSub === sub.key"
        :class="[
          'rounded-md px-3.5 py-1.5 text-sm font-medium transition-colors',
          activeSub === sub.key
            ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
        ]"
        @click="activeSub = sub.key"
      >
        {{ sub.label }}
      </button>
    </div>

    <!-- Pays & villes : référentiel géographique en lecture (seeds) -->
    <div v-if="activeSub === 'geo'" class="space-y-6">
      <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <DataTable
          :columns="countryColumns"
          :rows="countries"
          :loading="loadingGeo"
          :error="errors.geo"
          :search-keys="['name', 'iso2', 'iso3']"
          :search-placeholder="$t('travel.search.country', 'Rechercher un pays…')"
          default-sort="name"
          :caption="$t('travel.referentiel.countries', 'Pays')"
        />
        <DataTable
          :columns="cityColumns"
          :rows="cities"
          :loading="loadingGeo"
          :error="errors.geo"
          :search-keys="['name', 'country_iso2', 'region']"
          :search-placeholder="$t('travel.search.city', 'Rechercher une ville…')"
          default-sort="name"
          :caption="$t('travel.referentiel.cities', 'Villes')"
        />
      </div>
      <p class="text-xs text-slate-400">
        {{ $t('travel.referentiel.geoNote', 'Référentiel géographique alimenté par seed (lecture seule).') }}
      </p>
    </div>

    <TravelCrudSection
      v-else-if="activeSub === 'stations'"
      :config="stationConfig"
      :lookups="{ cities: cityOptions }"
      @saved="loadGeo"
    />
    <TravelCrudSection
      v-else-if="activeSub === 'offices'"
      :config="officeConfig"
      :lookups="{ cities: cityOptions }"
      @saved="loadGeo"
    />
    <TravelCrudSection
      v-else-if="activeSub === 'carriers'"
      :config="carrierConfig"
      @saved="loadLookups"
    />
    <TravelCrudSection
      v-else-if="activeSub === 'classes'"
      :config="classConfig"
    />
    <TravelCrudSection
      v-else-if="activeSub === 'vehicles'"
      :config="vehicleConfig"
      :lookups="{ carriers: carrierOptions }"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import TravelCrudSection from '@/components/travel/TravelCrudSection.vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const activeSub = ref('geo')

const subTabs = computed(() => [
  { key: 'geo', label: t('travel.referentiel.tabGeo', 'Pays & Villes') },
  { key: 'stations', label: t('travel.referentiel.tabStations', 'Stations') },
  { key: 'offices', label: t('travel.referentiel.tabOffices', 'Bureaux') },
  { key: 'carriers', label: t('travel.referentiel.tabCarriers', 'Compagnies') },
  { key: 'classes', label: t('travel.referentiel.tabClasses', 'Classes') },
  { key: 'vehicles', label: t('travel.referentiel.tabVehicles', 'Véhicules') }
])

/* ── pays & villes (lecture) ────────────────────────────────── */
const countries = ref([])
const cities = ref([])
const loadingGeo = ref(false)
const errors = ref({ geo: '' })

const countryColumns = computed(() => [
  { key: 'iso2', label: t('travel.field.iso2', 'ISO2'), sortable: true },
  { key: 'iso3', label: t('travel.field.iso3', 'ISO3'), sortable: true },
  { key: 'name', label: t('travel.field.name', 'Nom'), sortable: true },
  { key: 'phone_code', label: t('travel.field.phoneCode', 'Indicatif'), sortable: true }
])

const cityColumns = computed(() => [
  { key: 'name', label: t('travel.field.name', 'Nom'), sortable: true },
  { key: 'country_iso2', label: t('travel.field.countryIso2', 'Pays'), sortable: true },
  { key: 'region', label: t('travel.field.region', 'Région'), sortable: true },
  { key: 'status', label: t('travel.field.status', 'Statut'), sortable: true }
])

async function loadGeo() {
  loadingGeo.value = true
  errors.value.geo = ''
  try {
    const [cRes, cityRes] = await Promise.all([
      api.get('/travel/countries', { params: { per_page: 100 }, _skipAuthRedirect: true }),
      api.get('/travel/cities', { params: { per_page: 100 }, _skipAuthRedirect: true })
    ])
    countries.value = cRes.data?.data || []
    cities.value = cityRes.data?.data || []
  } catch (err) {
    errors.value.geo = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    loadingGeo.value = false
  }
}

/* ── lookups ────────────────────────────────────────────────── */
const carriers = ref([])

const cityOptions = computed(() =>
  cities.value.map((c) => ({ value: c.id, label: `${c.name}${c.country_iso2 ? ` (${c.country_iso2})` : ''}` }))
)
const carrierOptions = computed(() =>
  carriers.value.map((c) => ({ value: c.id, label: `${c.name}${c.code ? ` — ${c.code}` : ''}` }))
)

async function loadLookups() {
  try {
    const res = await api.get('/travel/carriers', { params: { per_page: 100 }, _skipAuthRedirect: true })
    carriers.value = res.data?.data || []
  } catch {
    carriers.value = []
  }
}

/* ── statuts ────────────────────────────────────────────────── */
const statusMap = {
  active: { labelKey: 'travel.status.active', color: 'green' },
  inactive: { labelKey: 'travel.status.inactive', color: 'yellow' },
  archived: { labelKey: 'travel.status.archived', color: 'gray' }
}

const statusField = { key: 'status', label: 'travel.field.status', labelFallback: 'Statut', type: 'select', options: [
  { value: 'active', label: t('travel.status.active', 'Actif') },
  { value: 'inactive', label: t('travel.status.inactive', 'Inactif') },
  { value: 'archived', label: t('travel.status.archived', 'Archivé') }
] }

/* ── stations ───────────────────────────────────────────────── */
const stationConfig = computed(() => ({
  resource: 'stations',
  titleKey: 'travel.referentiel.stations',
  titleFallback: 'Stations / terminaux',
  searchPlaceholderKey: 'travel.search.station',
  searchKeys: ['code', 'name', 'city_id', 'timezone'],
  defaultSort: 'name',
  statusField: 'status',
  statusMap,
  columns: [
    { key: 'code', label: 'travel.field.code', labelFallback: 'Code', sortable: true },
    { key: 'name', label: 'travel.field.name', labelFallback: 'Nom', sortable: true },
    { key: 'city_id', label: 'travel.field.city', labelFallback: 'Ville', sortable: true },
    { key: 'timezone', label: 'travel.field.timezone', labelFallback: 'Fuseau', sortable: true },
    { key: 'is_terminal', label: 'travel.field.isTerminal', labelFallback: 'Terminal', sortable: true },
    { key: 'status', label: 'travel.field.status', labelFallback: 'Statut', sortable: true }
  ],
  fields: [
    { key: 'code', label: 'travel.field.code', labelFallback: 'Code', type: 'text', required: true, max: 40 },
    { key: 'name', label: 'travel.field.name', labelFallback: 'Nom', type: 'text', required: true, max: 120 },
    { key: 'city_id', label: 'travel.field.city', labelFallback: 'Ville', type: 'select', source: 'cities', required: true },
    { key: 'address', label: 'travel.field.address', labelFallback: 'Adresse', type: 'text' },
    { key: 'contact_phone', label: 'travel.field.contactPhone', labelFallback: 'Téléphone', type: 'text' },
    { key: 'timezone', label: 'travel.field.timezone', labelFallback: 'Fuseau', type: 'text' },
    { key: 'is_terminal', label: 'travel.field.isTerminal', labelFallback: 'Terminal (grande gare)', type: 'checkbox' },
    statusField
  ],
  defaults: { timezone: 'UTC', is_terminal: false, status: 'active' }
}))

/* ── bureaux ────────────────────────────────────────────────── */
const officeConfig = computed(() => ({
  resource: 'offices',
  titleKey: 'travel.referentiel.offices',
  titleFallback: 'Bureaux de vente',
  searchPlaceholderKey: 'travel.search.office',
  searchKeys: ['name', 'city_id'],
  defaultSort: 'name',
  statusField: 'status',
  statusMap,
  columns: [
    { key: 'name', label: 'travel.field.name', labelFallback: 'Nom', sortable: true },
    { key: 'city_id', label: 'travel.field.city', labelFallback: 'Ville', sortable: true },
    { key: 'contact_phone', label: 'travel.field.contactPhone', labelFallback: 'Téléphone', sortable: true },
    { key: 'status', label: 'travel.field.status', labelFallback: 'Statut', sortable: true }
  ],
  fields: [
    { key: 'name', label: 'travel.field.name', labelFallback: 'Nom', type: 'text', required: true, max: 120 },
    { key: 'city_id', label: 'travel.field.city', labelFallback: 'Ville', type: 'select', source: 'cities', required: true },
    { key: 'address', label: 'travel.field.address', labelFallback: 'Adresse', type: 'text' },
    { key: 'contact_phone', label: 'travel.field.contactPhone', labelFallback: 'Téléphone', type: 'text' },
    statusField
  ],
  defaults: { status: 'active' }
}))

/* ── compagnies ─────────────────────────────────────────────── */
const carrierConfig = computed(() => ({
  resource: 'carriers',
  titleKey: 'travel.referentiel.carriers',
  titleFallback: 'Compagnies de transport',
  searchPlaceholderKey: 'travel.search.carrier',
  searchKeys: ['code', 'name', 'type'],
  defaultSort: 'name',
  statusField: 'status',
  statusMap,
  columns: [
    { key: 'code', label: 'travel.field.code', labelFallback: 'Code', sortable: true },
    { key: 'name', label: 'travel.field.name', labelFallback: 'Nom', sortable: true },
    { key: 'type', label: 'travel.field.carrierType', labelFallback: 'Type', sortable: true },
    { key: 'contact_phone', label: 'travel.field.contactPhone', labelFallback: 'Téléphone', sortable: true },
    { key: 'status', label: 'travel.field.status', labelFallback: 'Statut', sortable: true }
  ],
  fields: [
    { key: 'code', label: 'travel.field.code', labelFallback: 'Code', type: 'text', required: true, max: 40 },
    { key: 'name', label: 'travel.field.name', labelFallback: 'Nom', type: 'text', required: true, max: 120 },
    {
      key: 'type', label: 'travel.field.carrierType', labelFallback: 'Type', type: 'select', required: true,
      options: [
        { value: 'bus', label: t('travel.carrierType.bus', 'Bus / autocar') },
        { value: 'train', label: t('travel.carrierType.train', 'Train') },
        { value: 'boat', label: t('travel.carrierType.boat', 'Bateau') },
        { value: 'plane', label: t('travel.carrierType.plane', 'Avion') }
      ]
    },
    { key: 'contact_phone', label: 'travel.field.contactPhone', labelFallback: 'Téléphone', type: 'text' },
    statusField
  ],
  defaults: { type: 'bus', status: 'active' }
}))

/* ── classes ────────────────────────────────────────────────── */
const classConfig = computed(() => ({
  resource: 'classes',
  titleKey: 'travel.referentiel.classes',
  titleFallback: 'Classes de service',
  searchPlaceholderKey: 'travel.search.class',
  searchKeys: ['code', 'label'],
  defaultSort: 'priority',
  statusField: 'status',
  statusMap,
  columns: [
    { key: 'code', label: 'travel.field.code', labelFallback: 'Code', sortable: true },
    { key: 'label', label: 'travel.field.label', labelFallback: 'Libellé', sortable: true },
    { key: 'priority', label: 'travel.field.priority', labelFallback: 'Priorité', sortable: true },
    { key: 'status', label: 'travel.field.status', labelFallback: 'Statut', sortable: true }
  ],
  fields: [
    { key: 'code', label: 'travel.field.code', labelFallback: 'Code', type: 'text', required: true, max: 40 },
    { key: 'label', label: 'travel.field.label', labelFallback: 'Libellé', type: 'text', required: true, max: 120 },
    { key: 'priority', label: 'travel.field.priority', labelFallback: 'Priorité', type: 'number', min: 0 },
    { key: 'color', label: 'travel.field.color', labelFallback: 'Couleur', type: 'color' },
    statusField
  ],
  defaults: { priority: 0, status: 'active' }
}))

/* ── véhicules ──────────────────────────────────────────────── */
const vehicleConfig = computed(() => ({
  resource: 'vehicles',
  titleKey: 'travel.referentiel.vehicles',
  titleFallback: 'Flotte de véhicules',
  searchPlaceholderKey: 'travel.search.vehicle',
  searchKeys: ['code', 'registration_number', 'carrier_id'],
  defaultSort: 'code',
  statusField: 'status',
  statusMap,
  columns: [
    { key: 'code', label: 'travel.field.code', labelFallback: 'Code', sortable: true },
    { key: 'registration_number', label: 'travel.field.registration', labelFallback: 'Immatriculation', sortable: true },
    { key: 'seat_capacity', label: 'travel.field.seatCapacity', labelFallback: 'Places', sortable: true },
    { key: 'carrier_id', label: 'travel.field.carrier', labelFallback: 'Compagnie', sortable: true },
    { key: 'status', label: 'travel.field.status', labelFallback: 'Statut', sortable: true }
  ],
  fields: [
    { key: 'code', label: 'travel.field.code', labelFallback: 'Code', type: 'text', required: true, max: 40 },
    { key: 'registration_number', label: 'travel.field.registration', labelFallback: 'Immatriculation', type: 'text' },
    { key: 'seat_capacity', label: 'travel.field.seatCapacity', labelFallback: 'Capacité (places)', type: 'number', required: true, min: 1 },
    { key: 'carrier_id', label: 'travel.field.carrier', labelFallback: 'Compagnie', type: 'select', source: 'carriers' },
    { key: 'notes', label: 'travel.field.notes', labelFallback: 'Notes', type: 'textarea' },
    statusField
  ],
  defaults: { seat_capacity: 45, status: 'active' }
}))

onMounted(() => {
  loadGeo()
  loadLookups()
})
</script>
