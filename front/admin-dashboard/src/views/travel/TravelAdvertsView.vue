<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('travel.adverts.title', 'Annonces payantes') }}
      </h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        {{ t('travel.adverts.subtitle', 'Types, positions, grille tarifaire et cycle de vie des annonces.') }}
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

      <!-- ── Référentiels (types / positions / tarifs) ─────────────────── -->
      <template v-if="catalogTab">
        <div class="flex items-center justify-between gap-3">
          <input
            v-model="searchQuery"
            type="search"
            class="w-full max-w-sm rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            :placeholder="t('travel.common.search', 'Rechercher…')"
            :aria-label="t('travel.common.search', 'Rechercher')"
          />
          <button class="btn-primary inline-flex items-center gap-1.5" @click="openCatalogCreate">
            {{ t('travel.common.create', 'Créer') }}
          </button>
        </div>

        <DataTable
          :columns="catalogColumns"
          :rows="filteredCatalogRows"
          :loading="catalogLoading"
          :error="catalogError"
          :search-keys="['code', 'name']"
          :search-placeholder="t('travel.common.search', 'Rechercher…')"
          :empty-message="t('travel.common.noData', 'Aucune donnée')"
          key-field="id"
        >
          <template #row-actions="{ row }">
            <div class="flex justify-end gap-2">
              <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="openCatalogEdit(row)">
                {{ t('travel.common.edit', 'Modifier') }}
              </button>
              <button class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="askCatalogDelete(row)">
                {{ t('travel.common.delete', 'Supprimer') }}
              </button>
            </div>
          </template>
        </DataTable>
      </template>

      <!-- ── Annonces (cycle de vie) ────────────────────────────────────── -->
      <template v-else>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex flex-wrap gap-2">
            <button
              v-for="status in advertStatuses"
              :key="status.value"
              class="rounded-full px-3 py-1 text-xs font-medium"
              :class="statusFilter === status.value
                ? 'bg-brand-500 text-white'
                : 'glass-card text-slate-600 ring-1 ring-slate-200 dark:text-slate-400 dark:ring-slate-700'"
              @click="setStatusFilter(status.value)"
            >
              {{ status.label }}
            </button>
          </div>
          <button class="btn-primary inline-flex items-center gap-1.5" @click="openAdvertCreate">
            {{ t('travel.adverts.submit', 'Soumettre une annonce') }}
          </button>
        </div>

        <DataTable
          :columns="advertColumns"
          :rows="adverts"
          :loading="advertsLoading"
          :error="advertsError"
          :search-keys="['title']"
          :search-placeholder="t('travel.common.search', 'Rechercher…')"
          :empty-message="t('travel.common.noData', 'Aucune annonce')"
          key-field="id"
        >
          <template #cell-status="{ value }">
            <StatusBadge :status="value" :map="advertStatusMap" />
          </template>
          <template #cell-total_minor="{ row }">
            <span class="text-sm font-medium">{{ formatMinor(row.total_minor, row.currency) }}</span>
          </template>
          <template #row-actions="{ row }">
            <div class="flex justify-end gap-2">
              <button
                v-if="row.status === 'draft' || row.status === 'pending_payment'"
                class="text-sm font-medium text-emerald-600 hover:text-emerald-800 dark:text-emerald-400"
                @click="payAdvertRow(row)"
              >
                {{ t('travel.adverts.pay', 'Payer') }}
              </button>
              <button
                v-if="row.status === 'paid'"
                class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                @click="openAdvertValidate(row)"
              >
                {{ t('travel.adverts.validate', 'Valider') }}
              </button>
              <button
                v-if="row.status === 'expired'"
                class="text-sm font-medium text-amber-600 hover:text-amber-800 dark:text-amber-400"
                @click="renewAdvertRow(row)"
              >
                {{ t('travel.adverts.renew', 'Renouveler') }}
              </button>
              <button
                v-if="row.status === 'draft' || row.status === 'rejected'"
                class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400"
                @click="askAdvertDelete(row)"
              >
                {{ t('travel.common.delete', 'Supprimer') }}
              </button>
            </div>
          </template>
        </DataTable>
      </template>

      <!-- Modales -->
      <TravelFormModal
        :open="catalogModalOpen"
        :title="catalogEditing ? t('travel.common.edit', 'Modifier') : t('travel.common.create', 'Créer')"
        :fields="catalogFormFields"
        :values="catalogEditing || {}"
        :busy="saving"
        :error="formError"
        @save="saveCatalog"
        @close="catalogModalOpen = false"
      />
      <TravelFormModal
        :open="advertModalOpen"
        :title="t('travel.adverts.submit', 'Soumettre une annonce')"
        :fields="advertFormFields"
        :values="{}"
        :busy="saving"
        :error="formError"
        @save="createAdvert"
        @close="advertModalOpen = false"
      />
      <TravelFormModal
        :open="validateModalOpen"
        :title="t('travel.adverts.validate', 'Valider l’annonce')"
        :fields="validateFormFields"
        :values="{ approved: true }"
        :busy="saving"
        :error="formError"
        @save="submitValidate"
        @close="validateModalOpen = false"
      />
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import TravelFormModal from '@/components/travel/TravelFormModal.vue'
import TravelGate from '@/components/travel/TravelGate.vue'
import {
  createTravel, deleteTravel, listTravel, payAdvert, renewAdvert, validateAdvert,
  listAdvertCatalog, createAdvertCatalog, updateAdvertCatalog, deleteAdvertCatalog,
  travelList,
} from '@/services/travel'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const gateMode = ref('')
const loadError = ref('')
const activeTab = ref('adverts')
const searchQuery = ref('')
const statusFilter = ref('all')

const tabs = [
  { key: 'adverts', label: t('travel.adverts.tabAdverts', 'Annonces') },
  { key: 'types', label: t('travel.adverts.tabTypes', 'Types') },
  { key: 'positions', label: t('travel.adverts.tabPositions', 'Positions') },
  { key: 'prices', label: t('travel.adverts.tabPrices', 'Tarifs') },
]

const catalogTab = computed(() => activeTab.value !== 'adverts')

const advertStatuses = [
  { value: 'all', label: t('travel.adverts.statusAll', 'Toutes') },
  { value: 'draft', label: t('travel.adverts.statusDraft', 'Brouillon') },
  { value: 'paid', label: t('travel.adverts.statusPaid', 'Payée') },
  { value: 'published', label: t('travel.adverts.statusPublished', 'Publiée') },
  { value: 'expired', label: t('travel.adverts.statusExpired', 'Expirée') },
  { value: 'rejected', label: t('travel.adverts.statusRejected', 'Rejetée') },
]

const advertStatusMap = Object.fromEntries(
  advertStatuses.filter((s) => s.value !== 'all').map((s) => [s.value, { label: s.label }]),
)

function setStatusFilter(value) {
  statusFilter.value = value
  loadAdverts()
}

// ── Catalogue (types / positions / tarifs) ────────────────────────────────

const catalogResource = computed(() => ({
  types: 'advert-types',
  positions: 'advert-positions',
  prices: 'advert-prices',
}[activeTab.value]))

const catalogRows = ref([])
const catalogLoading = ref(false)
const catalogError = ref('')

async function loadCatalog() {
  catalogLoading.value = true
  catalogError.value = ''
  try {
    const res = await listAdvertCatalog(catalogResource.value)
    catalogRows.value = travelList(res)
  } catch (err) {
    catalogError.value = err?.response?.data?.message || String(err)
  } finally {
    catalogLoading.value = false
  }
}

const catalogColumns = computed(() => {
  if (activeTab.value === 'prices') {
    return [
      { key: 'advert_type_id', label: t('travel.adverts.typeId', 'Type'), sortable: true },
      { key: 'advert_position_id', label: t('travel.adverts.positionId', 'Position'), sortable: true },
      { key: 'price_image_minor', label: t('travel.adverts.priceImage', 'Prix image'), sortable: true },
      { key: 'price_character_minor', label: t('travel.adverts.priceCharacter', 'Prix/caractère'), sortable: true },
      { key: 'currency', label: t('travel.adverts.currency', 'Devise'), sortable: true },
    ]
  }
  return [
    { key: 'code', label: t('travel.adverts.code', 'Code'), sortable: true },
    { key: 'name', label: t('travel.adverts.name', 'Nom'), sortable: true },
    { key: 'description', label: t('travel.adverts.description', 'Description') },
  ]
})

const filteredCatalogRows = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return catalogRows.value
  return catalogRows.value.filter((r) => `${r.code ?? ''} ${r.name ?? ''}`.toLowerCase().includes(q))
})

const catalogFormFields = computed(() => {
  if (activeTab.value === 'prices') {
    return [
      { key: 'advert_type_id', label: t('travel.adverts.typeId', 'Type'), type: 'number', required: true },
      { key: 'advert_position_id', label: t('travel.adverts.positionId', 'Position'), type: 'number', required: true },
      { key: 'price_image_minor', label: t('travel.adverts.priceImage', 'Prix image'), type: 'number', min: 0, required: true },
      { key: 'price_character_minor', label: t('travel.adverts.priceCharacter', 'Prix/caractère'), type: 'number', min: 0, required: true },
      { key: 'currency', label: t('travel.adverts.currency', 'Devise'), type: 'text', maxlength: 3 },
    ]
  }
  return [
    { key: 'code', label: t('travel.adverts.code', 'Code'), type: 'text', required: true, maxlength: 40 },
    { key: 'name', label: t('travel.adverts.name', 'Nom'), type: 'text', required: true, maxlength: 120 },
    { key: 'description', label: t('travel.adverts.description', 'Description'), type: 'textarea' },
  ]
})

const catalogModalOpen = ref(false)
const catalogEditing = ref(null)

function openCatalogCreate() {
  catalogEditing.value = null
  formError.value = ''
  catalogModalOpen.value = true
}

function openCatalogEdit(row) {
  catalogEditing.value = row
  formError.value = ''
  catalogModalOpen.value = true
}

async function saveCatalog(values) {
  saving.value = true
  formError.value = ''
  try {
    const resource = catalogResource.value
    if (catalogEditing.value) {
      await updateAdvertCatalog(resource, catalogEditing.value.id, values)
    } else {
      await createAdvertCatalog(resource, values)
    }
    catalogModalOpen.value = false
    await loadCatalog()
  } catch (err) {
    formError.value = err?.response?.data?.message || String(err)
  } finally {
    saving.value = false
  }
}

async function askCatalogDelete(row) {
  if (!window.confirm(t('travel.common.confirmDelete', 'Supprimer cet élément ?'))) return
  try {
    await deleteAdvertCatalog(catalogResource.value, row.id)
    await loadCatalog()
  } catch (err) {
    window.alert(err?.response?.data?.message || String(err))
  }
}

// ── Annonces (cycle de vie) ───────────────────────────────────────────────

const adverts = ref([])
const advertsLoading = ref(false)
const advertsError = ref('')
const advertTypes = ref([])
const advertPositions = ref([])

const advertColumns = [
  { key: 'title', label: 'Titre', sortable: true },
  { key: 'status', label: 'Statut', sortable: true },
  { key: 'total_minor', label: 'Total', sortable: true },
  { key: 'currency', label: 'Devise' },
  { key: 'expires_at', label: 'Expiration' },
]

async function loadAdverts() {
  advertsLoading.value = true
  advertsError.value = ''
  try {
    const params = statusFilter.value === 'all' ? {} : { status: statusFilter.value }
    const res = await listTravel('adverts', params)
    adverts.value = travelList(res)
    const [typesRes, positionsRes] = await Promise.all([
      listAdvertCatalog('advert-types').catch(() => ({ data: [] })),
      listAdvertCatalog('advert-positions').catch(() => ({ data: [] })),
    ])
    advertTypes.value = travelList(typesRes)
    advertPositions.value = travelList(positionsRes)
  } catch (err) {
    advertsError.value = err?.response?.data?.message || String(err)
  } finally {
    advertsLoading.value = false
  }
}

function switchTab(key) {
  activeTab.value = key
  searchQuery.value = ''
  if (catalogTab.value) loadCatalog()
}

function formatMinor(value, currency) {
  const n = Number(value ?? 0)
  return `${(n / 100).toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} ${currency ?? ''}`
}

const saving = ref(false)
const formError = ref('')
const advertModalOpen = ref(false)
const validateModalOpen = ref(false)
const activeAdvertId = ref(null)

function openAdvertCreate() {
  formError.value = ''
  advertModalOpen.value = true
}

async function createAdvert(values) {
  saving.value = true
  formError.value = ''
  try {
    await createTravel('adverts', values)
    advertModalOpen.value = false
    await loadAdverts()
  } catch (err) {
    formError.value = err?.response?.data?.message || String(err)
  } finally {
    saving.value = false
  }
}

async function payAdvertRow(row) {
  try {
    await payAdvert(row.id, { provider: 'cash' })
    await loadAdverts()
  } catch (err) {
    window.alert(err?.response?.data?.message || String(err))
  }
}

function openAdvertValidate(row) {
  activeAdvertId.value = row.id
  formError.value = ''
  validateModalOpen.value = true
}

async function submitValidate(values) {
  saving.value = true
  formError.value = ''
  try {
    await validateAdvert(activeAdvertId.value, values)
    validateModalOpen.value = false
    await loadAdverts()
  } catch (err) {
    formError.value = err?.response?.data?.message || String(err)
  } finally {
    saving.value = false
  }
}

async function renewAdvertRow(row) {
  try {
    await renewAdvert(row.id, { provider: 'cash' })
    await loadAdverts()
  } catch (err) {
    window.alert(err?.response?.data?.message || String(err))
  }
}

async function askAdvertDelete(row) {
  if (!window.confirm(t('travel.common.confirmDelete', 'Supprimer cette annonce ?'))) return
  try {
    await deleteTravel('adverts', row.id)
    await loadAdverts()
  } catch (err) {
    window.alert(err?.response?.data?.message || String(err))
  }
}

function init() {
  gateMode.value = ''
  loadError.value = ''
  loadAdverts()
}

onMounted(init)
</script>
