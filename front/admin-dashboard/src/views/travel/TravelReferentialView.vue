<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('travel.referential.title', 'Référentiel') }}
      </h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        {{ t('travel.referential.subtitle', 'Pays, villes, gares, bureaux, compagnies, classes et véhicules.') }}
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

      <div class="flex items-center justify-between gap-3">
        <input
          v-model="searchQuery"
          type="search"
          class="w-full max-w-sm rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
          :placeholder="t('travel.common.search', 'Rechercher…')"
          :aria-label="t('travel.common.search', 'Rechercher')"
        />
        <button
          v-if="entityConfig.canCreate"
          class="btn-primary inline-flex items-center gap-1.5"
          @click="openCreate"
        >
          <PlusIcon class="h-4 w-4" />
          {{ t('travel.common.create', 'Créer') }}
        </button>
      </div>

      <DataTable
        :columns="tableColumns"
        :rows="filteredRows"
        :loading="isLoading(activeTab)"
        :error="listError(activeTab)"
        :search-keys="entityConfig.searchKeys"
        :search-placeholder="t('travel.common.search', 'Rechercher…')"
        :empty-message="t('travel.common.noData', 'Aucune donnée')"
        :key-field="entityConfig.keyField || 'id'"
      >
        <template #cell-status="{ value }">
          <StatusBadge :status="value" :map="statusMap" />
        </template>
        <template #cell-is_terminal="{ value }">
          <span class="text-sm">{{ value ? t('travel.common.yes', 'Oui') : t('travel.common.no', 'Non') }}</span>
        </template>
        <template #row-actions="{ row }">
          <div v-if="entityConfig.canEdit || entityConfig.canDelete" class="flex justify-end gap-2">
            <button
              v-if="entityConfig.canEdit"
              class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
              @click="openEdit(row)"
            >
              {{ t('travel.common.edit', 'Modifier') }}
            </button>
            <button
              v-if="entityConfig.canDelete"
              class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400"
              @click="askDelete(row)"
            >
              {{ t('travel.common.delete', 'Supprimer') }}
            </button>
          </div>
        </template>
      </DataTable>

      <TravelFormModal
        :open="modalOpen"
        :title="editing ? t('travel.common.edit', 'Modifier') : t('travel.common.create', 'Créer')"
        :fields="formFields"
        :values="editing || {}"
        :busy="saving"
        :error="formError"
        @save="save"
        @cancel="closeModal"
      />

      <ConfirmDialog
        :open="deleteOpen"
        :title="t('travel.common.confirmDeleteTitle', 'Supprimer cet élément ?')"
        :message="deleteTarget ? t('travel.common.confirmDeleteBody', 'Cette action est irréversible. Voulez-vous vraiment supprimer « {name} » ?').replace('{name}', String(deleteTarget[entityConfig.labelField || 'name'] || '')) : ''"
        :confirm-label="t('travel.common.delete', 'Supprimer')"
        @confirm="confirmDelete"
        @cancel="closeDelete"
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
import { listTravel, createTravel, updateTravel, deleteTravel, travelList } from '@/services/travel'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const travelStore = useTravelStore()

const activeTab = ref('countries')
const searchQuery = ref('')
const loading = reactive({ countries: false, cities: false, stations: false, offices: false, carriers: false, classes: false, vehicles: false })
const errors = reactive({})
const lists = reactive({ countries: [], cities: [], stations: [], offices: [], carriers: [], classes: [], vehicles: [] })
const loadError = ref('')

const modalOpen = ref(false)
const editing = ref(null)
const saving = ref(false)
const formError = ref('')
const deleteOpen = ref(false)
const deleteTarget = ref(null)

const gateMode = computed(() => {
  if (!travelStore.isReady) return ''
  if (travelStore.noTenantContext) return 'tenant'
  if (!travelStore.flagActive) return 'feature'
  return ''
})

const statusMap = {
  active: { label: t('travel.common.active', 'Actif'), color: 'green' },
  disabled: { label: t('travel.common.disabled', 'Inactif'), color: 'gray' }
}

const carrierTypes = computed(() => [
  { value: 'bus', label: t('travel.carrierTypes.bus', 'Bus') },
  { value: 'train', label: t('travel.carrierTypes.train', 'Train') },
  { value: 'plane', label: t('travel.carrierTypes.plane', 'Avion') },
  { value: 'boat', label: t('travel.carrierTypes.boat', 'Bateau') }
])

const statusOptions = computed(() => [
  { value: 'active', label: t('travel.common.active', 'Actif') },
  { value: 'disabled', label: t('travel.common.disabled', 'Inactif') }
])

const cityOptions = computed(() =>
  lists.cities.map((city) => ({ value: city.id, label: `${city.name}${city.country_iso2 ? ` (${city.country_iso2})` : ''}` }))
)
const carrierOptions = computed(() =>
  lists.carriers.filter((c) => c.status === 'active').map((c) => ({ value: c.id, label: c.name }))
)

const tabs = computed(() => [
  { key: 'countries', label: t('travel.referential.countries', 'Pays') },
  { key: 'cities', label: t('travel.referential.cities', 'Villes') },
  { key: 'stations', label: t('travel.referential.stations', 'Gares & terminaux') },
  { key: 'offices', label: t('travel.referential.offices', 'Bureaux de vente') },
  { key: 'carriers', label: t('travel.referential.carriers', 'Compagnies') },
  { key: 'classes', label: t('travel.referential.classes', 'Classes de service') },
  { key: 'vehicles', label: t('travel.referential.vehicles', 'Véhicules') }
])

function cityName(cityId) {
  const city = lists.cities.find((c) => c.id === cityId)
  return city ? city.name : (cityId ?? '—')
}
function carrierName(carrierId) {
  const carrier = lists.carriers.find((c) => c.id === carrierId)
  return carrier ? carrier.name : (carrierId ?? '—')
}

const entityConfigs = {
  countries: {
    canCreate: false,
    canEdit: false,
    canDelete: false,
    searchKeys: ['name', 'iso2', 'iso3'],
    columns: () => [
      { key: 'iso2', label: 'ISO2', sortable: true },
      { key: 'iso3', label: 'ISO3', sortable: true },
      { key: 'name', label: t('travel.countries.name', 'Nom'), sortable: true },
      { key: 'phone_code', label: t('travel.countries.phoneCode', 'Indicatif'), sortable: true },
      { key: 'status', label: t('travel.common.status', 'Statut'), sortable: true }
    ]
  },
  cities: {
    canCreate: false,
    canEdit: false,
    canDelete: false,
    searchKeys: ['name', 'region', 'country_iso2'],
    columns: () => [
      { key: 'name', label: t('travel.cities.name', 'Ville'), sortable: true },
      { key: 'country_iso2', label: t('travel.cities.country', 'Pays'), sortable: true },
      { key: 'region', label: t('travel.cities.region', 'Région'), sortable: true },
      { key: 'status', label: t('travel.common.status', 'Statut'), sortable: true }
    ]
  },
  stations: {
    resource: 'stations',
    labelField: 'name',
    canCreate: true,
    canEdit: true,
    canDelete: true,
    searchKeys: ['code', 'name', 'address', 'contact_phone'],
    columns: () => [
      { key: 'code', label: 'Code', sortable: true },
      { key: 'name', label: t('travel.stations.name', 'Nom'), sortable: true },
      { key: 'city_label', label: t('travel.common.city', 'Ville'), sortable: false },
      { key: 'contact_phone', label: t('travel.stations.phone', 'Téléphone') },
      { key: 'is_terminal', label: t('travel.stations.isTerminal', 'Terminal') },
      { key: 'status', label: t('travel.common.status', 'Statut'), sortable: true }
    ],
    fields: () => [
      { key: 'code', label: 'travel.stations.code', type: 'text', required: true, max: 40 },
      { key: 'name', label: 'travel.stations.name', type: 'text', required: true, max: 120 },
      { key: 'city_id', label: 'travel.common.city', type: 'select', required: true, options: cityOptions },
      { key: 'address', label: 'travel.stations.address', type: 'text', max: 255 },
      { key: 'contact_phone', label: 'travel.stations.phone', type: 'text', max: 40 },
      { key: 'timezone', label: 'travel.stations.timezone', type: 'text', max: 64 },
      { key: 'is_terminal', label: 'travel.stations.isTerminal', type: 'checkbox' },
      { key: 'status', label: 'travel.common.status', type: 'select', options: statusOptions }
    ]
  },
  offices: {
    resource: 'offices',
    labelField: 'name',
    canCreate: true,
    canEdit: true,
    canDelete: true,
    searchKeys: ['name', 'address', 'contact_phone'],
    columns: () => [
      { key: 'name', label: t('travel.offices.name', 'Nom'), sortable: true },
      { key: 'city_label', label: t('travel.common.city', 'Ville') },
      { key: 'address', label: t('travel.offices.address', 'Adresse') },
      { key: 'contact_phone', label: t('travel.offices.phone', 'Téléphone') },
      { key: 'status', label: t('travel.common.status', 'Statut'), sortable: true }
    ],
    fields: () => [
      { key: 'name', label: 'travel.offices.name', type: 'text', required: true, max: 120 },
      { key: 'city_id', label: 'travel.common.city', type: 'select', required: true, options: cityOptions },
      { key: 'address', label: 'travel.offices.address', type: 'text', max: 255 },
      { key: 'contact_phone', label: 'travel.offices.phone', type: 'text', max: 40 },
      { key: 'status', label: 'travel.common.status', type: 'select', options: statusOptions }
    ]
  },
  carriers: {
    resource: 'carriers',
    labelField: 'name',
    canCreate: true,
    canEdit: true,
    canDelete: true,
    searchKeys: ['code', 'name', 'contact_phone'],
    columns: () => [
      { key: 'code', label: 'Code', sortable: true },
      { key: 'name', label: t('travel.carriers.name', 'Nom'), sortable: true },
      { key: 'type', label: t('travel.carriers.type', 'Type'), sortable: true },
      { key: 'contact_phone', label: t('travel.carriers.phone', 'Téléphone') },
      { key: 'status', label: t('travel.common.status', 'Statut'), sortable: true }
    ],
    fields: () => [
      { key: 'code', label: 'travel.carriers.code', type: 'text', required: true, max: 40 },
      { key: 'name', label: 'travel.carriers.name', type: 'text', required: true, max: 120 },
      { key: 'type', label: 'travel.carriers.type', type: 'select', options: carrierTypes },
      { key: 'contact_phone', label: 'travel.carriers.phone', type: 'text', max: 40 },
      { key: 'status', label: 'travel.common.status', type: 'select', options: statusOptions }
    ]
  },
  classes: {
    resource: 'classes',
    labelField: 'label',
    canCreate: true,
    canEdit: true,
    canDelete: true,
    searchKeys: ['code', 'label'],
    columns: () => [
      { key: 'code', label: 'Code', sortable: true },
      { key: 'label', label: t('travel.classes.label', 'Libellé'), sortable: true },
      { key: 'color', label: t('travel.classes.color', 'Couleur'), sortable: true },
      { key: 'priority', label: t('travel.classes.priority', 'Priorité'), sortable: true },
      { key: 'status', label: t('travel.common.status', 'Statut'), sortable: true }
    ],
    fields: () => [
      { key: 'code', label: 'travel.classes.code', type: 'text', required: true, max: 40 },
      { key: 'label', label: 'travel.classes.label', type: 'text', required: true, max: 120 },
      { key: 'color', label: 'travel.classes.color', type: 'text', max: 7 },
      { key: 'priority', label: 'travel.classes.priority', type: 'number', min: 0 },
      { key: 'status', label: 'travel.common.status', type: 'select', options: statusOptions }
    ]
  },
  vehicles: {
    resource: 'vehicles',
    labelField: 'code',
    canCreate: true,
    canEdit: true,
    canDelete: true,
    searchKeys: ['code', 'registration_number'],
    columns: () => [
      { key: 'code', label: 'Code', sortable: true },
      { key: 'registration_number', label: t('travel.vehicles.registration', 'Immatriculation'), sortable: true },
      { key: 'seat_capacity', label: t('travel.vehicles.seats', 'Places'), sortable: true },
      { key: 'carrier_label', label: t('travel.common.carrier', 'Compagnie') },
      { key: 'status', label: t('travel.common.status', 'Statut'), sortable: true }
    ],
    fields: () => [
      { key: 'code', label: 'travel.vehicles.code', type: 'text', required: true, max: 40 },
      { key: 'registration_number', label: 'travel.vehicles.registration', type: 'text', max: 40 },
      { key: 'seat_capacity', label: 'travel.vehicles.seats', type: 'number', required: true, min: 1, max: 200 },
      { key: 'carrier_id', label: 'travel.common.carrier', type: 'select', options: carrierOptions },
      { key: 'status', label: 'travel.common.status', type: 'select', options: statusOptions },
      { key: 'notes', label: 'travel.vehicles.notes', type: 'textarea', max: 2000 }
    ]
  }
}

const entityConfig = computed(() => entityConfigs[activeTab.value])

/** Colonnes et champs de l'entité active — computeds de premier niveau :
 *  Vue ne dépaquette pas les refs imbriquées dans un objet (les
 *  `columns`/`fields` stockés dans entityConfigs sont des fonctions). */
const tableColumns = computed(() => entityConfig.value.columns())
const formFields = computed(() => {
  const fields = entityConfig.value.fields
  return fields ? fields() : []
})

const filteredRows = computed(() => {
  const rows = lists[activeTab.value] || []
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return rows
  return rows.filter((row) =>
    (entityConfig.value.searchKeys || []).some((key) =>
      String(row[key] ?? '').toLowerCase().includes(q)
    )
  )
})

async function loadTab(key) {
  const config = entityConfigs[key]
  loading[key] = true
  errors[key] = ''
  try {
    const response = await listTravel(config.resource || key, { per_page: 1000 })
    lists[key] = travelList(response)
  } catch (error) {
    errors[key] = error?.response?.data?.message || error?.message || t('travel.common.loadErrorBody', 'Une erreur est survenue.')
  } finally {
    loading[key] = false
  }
}

/** Champs d'affichage dénormalisés (noms de ville / compagnie) pour les
 *  colonnes `city_id` / `carrier_id` (DataTable ne résout pas les relations). */
function decorate() {
  for (const key of Object.keys(entityConfigs)) {
    lists[key] = (lists[key] || []).map((row) => ({
      ...row,
      city_label: row.city_id ? cityName(row.city_id) : '—',
      carrier_label: row.carrier_id ? carrierName(row.carrier_id) : '—'
    }))
  }
}

function switchTab(key) {
  activeTab.value = key
}

function isLoading(key) {
  return !!loading[key]
}

function listError(key) {
  return errors[key] || ''
}

function closeDelete() {
  deleteOpen.value = false
  deleteTarget.value = null
}

async function init() {
  await travelStore.checkFlag(true)
  if (gateMode.value) return
  loadError.value = ''
  await Promise.all(Object.keys(entityConfigs).map(loadTab))
  decorate()
}


function openCreate() {
  editing.value = null
  formError.value = ''
  modalOpen.value = true
}

function openEdit(row) {
  editing.value = row
  formError.value = ''
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  editing.value = null
  formError.value = ''
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

async function save(values) {
  const config = entityConfig.value
  saving.value = true
  formError.value = ''
  try {
    const payload = { ...values }
    if (editing.value) {
      await updateTravel(config.resource, editing.value.id, payload)
    } else {
      await createTravel(config.resource, payload)
    }
    closeModal()
    await loadTab(activeTab.value)
    decorate()
  } catch (error) {
    formError.value = apiError(error)
  } finally {
    saving.value = false
  }
}

function askDelete(row) {
  deleteTarget.value = row
  deleteOpen.value = true
}

async function confirmDelete() {
  const config = entityConfig.value
  if (!deleteTarget.value) return
  try {
    await deleteTravel(config.resource, deleteTarget.value.id)
    deleteOpen.value = false
    deleteTarget.value = null
    await loadTab(activeTab.value)
    decorate()
  } catch (error) {
    deleteOpen.value = false
    errors[activeTab.value] = apiError(error)
    deleteTarget.value = null
  }
}

onMounted(init)
</script>
