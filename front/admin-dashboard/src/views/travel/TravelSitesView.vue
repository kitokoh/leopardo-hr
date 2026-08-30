<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('travel.sites.title', 'Sites touristiques') }}
      </h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        {{ t('travel.sites.subtitle', 'Annuaire des sites touristiques, consultable par ville.') }}
      </p>
    </div>

    <TravelGate :mode="gateMode" :message="loadError" @retry="init" />

    <template v-if="!gateMode">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
          <input
            v-model="search"
            type="search"
            class="w-full max-w-xs rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            :placeholder="t('travel.sites.searchPlaceholder', 'Rechercher un site…')"
            :aria-label="t('travel.common.search', 'Rechercher')"
          />
          <select
            v-model="cityFilter"
            class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            :aria-label="t('travel.sites.city', 'Ville')"
          >
            <option value="">{{ t('travel.sites.allCities', 'Toutes les villes') }}</option>
            <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name }}</option>
          </select>
        </div>
        <button class="btn-primary inline-flex items-center gap-1.5" @click="openCreate">
          {{ t('travel.common.create', 'Créer') }}
        </button>
      </div>

      <DataTable
        :columns="columns"
        :rows="filteredRows"
        :loading="loading"
        :error="listError"
        :search-keys="['name']"
        :search-placeholder="t('travel.common.search', 'Rechercher…')"
        :empty-message="t('travel.common.noData', 'Aucun site')"
        key-field="id"
      >
        <template #cell-status="{ value }">
          <StatusBadge :status="value" :map="statusMap" />
        </template>
        <template #row-actions="{ row }">
          <div class="flex justify-end gap-2">
            <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="openEdit(row)">
              {{ t('travel.common.edit', 'Modifier') }}
            </button>
            <button class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="askDelete(row)">
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
        @close="modalOpen = false"
      />
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/ui/DataTable.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import TravelFormModal from '@/components/travel/TravelFormModal.vue'
import TravelGate from '@/components/travel/TravelGate.vue'
import {
  createTravel, deleteTravel, listTravel, updateTravel, travelList, listTouristSites,
} from '@/services/travel'

const { t } = useI18n()

const gateMode = ref('')
const loadError = ref('')
const sites = ref([])
const cities = ref([])
const loading = ref(false)
const listError = ref('')
const search = ref('')
const cityFilter = ref('')

const statusMap = {
  draft: { label: t('travel.sites.statusDraft', 'Brouillon') },
  published: { label: t('travel.sites.statusPublished', 'Publié') },
  archived: { label: t('travel.sites.statusArchived', 'Archivé') },
}

const columns = [
  { key: 'name', label: t('travel.sites.name', 'Nom'), sortable: true },
  { key: 'city_id', label: t('travel.sites.city', 'Ville'), sortable: true },
  { key: 'status', label: t('travel.common.status', 'Statut'), sortable: true },
]

const filteredRows = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return sites.value
  return sites.value.filter((r) => `${r.name ?? ''}`.toLowerCase().includes(q))
})

async function loadSites() {
  loading.value = true
  listError.value = ''
  try {
    const params = cityFilter.value ? { city_id: cityFilter.value } : {}
    const res = await listTouristSites(params)
    sites.value = travelList(res)
    const citiesRes = await listTravel('cities', { per_page: 1000 }).catch(() => ({ data: [] }))
    cities.value = travelList(citiesRes)
  } catch (err) {
    listError.value = err?.response?.data?.message || String(err)
  } finally {
    loading.value = false
  }
}

const formFields = computed(() => [
  { key: 'name', label: t('travel.sites.name', 'Nom'), type: 'text', required: true, maxlength: 200 },
  { key: 'description_redacted', label: t('travel.sites.description', 'Description'), type: 'textarea' },
  {
    key: 'city_id', label: t('travel.sites.city', 'Ville'), type: 'select',
    options: cities.value.map((c) => ({ value: c.id, label: c.name })),
  },
  { key: 'latitude', label: t('travel.sites.latitude', 'Latitude'), type: 'number', min: -90, max: 90 },
  { key: 'longitude', label: t('travel.sites.longitude', 'Longitude'), type: 'number', min: -180, max: 180 },
  {
    key: 'status', label: t('travel.common.status', 'Statut'), type: 'select',
    options: [
      { value: 'published', label: t('travel.sites.statusPublished', 'Publié') },
      { value: 'draft', label: t('travel.sites.statusDraft', 'Brouillon') },
      { value: 'archived', label: t('travel.sites.statusArchived', 'Archivé') },
    ],
  },
])

const modalOpen = ref(false)
const editing = ref(null)
const saving = ref(false)
const formError = ref('')

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

async function save(values) {
  saving.value = true
  formError.value = ''
  try {
    if (editing.value) {
      await updateTravel('tourist-sites', editing.value.id, values)
    } else {
      await createTravel('tourist-sites', values)
    }
    modalOpen.value = false
    await loadSites()
  } catch (err) {
    formError.value = err?.response?.data?.message || String(err)
  } finally {
    saving.value = false
  }
}

async function askDelete(row) {
  if (!window.confirm(t('travel.common.confirmDelete', 'Supprimer ce site ?'))) return
  try {
    await deleteTravel('tourist-sites', row.id)
    await loadSites()
  } catch (err) {
    window.alert(err?.response?.data?.message || String(err))
  }
}

function init() {
  gateMode.value = ''
  loadError.value = ''
  loadSites()
}

onMounted(init)
</script>
