<template>
  <section class="space-y-4">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">
          {{ $t('travel.sites.title', 'Sites touristiques') }}
        </h2>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('travel.sites.subtitle', 'Lieux d\u2019intérêt référencés par ville (vitrine publique).') }}
        </p>
      </div>
      <button class="btn-primary" type="button" @click="openCreate">
        <PlusIcon class="mr-2 h-4 w-4" />
        {{ $t('travel.action.create', 'Créer') }}
      </button>
    </div>

    <div v-if="globalError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
      {{ globalError }}
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <select
        class="form-input w-auto"
        :aria-label="$t('travel.sites.filterCity', 'Filtrer par ville')"
        @change="filterCity = $event.target.value"
      >
        <option value="">{{ $t('travel.sites.allCities', 'Toutes les villes') }}</option>
        <option v-for="opt in cityOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
      <span class="text-xs text-slate-400">
        {{ $t('travel.sites.count', '{count} site(s)', { count: filteredRows.length }) }}
      </span>
    </div>

    <DataTable
      :columns="columns"
      :rows="filteredRows"
      :loading="loading"
      :error="listError"
      :search-keys="['name', 'cityName']"
      :search-placeholder="$t('travel.search.site', 'Rechercher un site…')"
      default-sort="name"
      :caption="$t('travel.sites.title', 'Sites touristiques')"
    >
      <template #cell-status="{ value }">
        <StatusBadge :status="value" :map="statusMap" />
      </template>
      <template #row-actions="{ row }">
        <div class="flex items-center justify-end gap-1">
          <button
            class="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
            type="button"
            :aria-label="$t('travel.action.edit', 'Modifier')"
            :title="$t('travel.action.edit', 'Modifier')"
            @click="openEdit(row)"
          >
            <PencilSquareIcon class="h-4 w-4" />
          </button>
          <button
            class="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600"
            type="button"
            :aria-label="$t('travel.action.delete', 'Supprimer')"
            :title="$t('travel.action.delete', 'Supprimer')"
            @click="askDelete(row)"
          >
            <TrashIcon class="h-4 w-4" />
          </button>
        </div>
      </template>
    </DataTable>

    <!-- Création / édition -->
    <TravelModal
      :open="formOpen"
      :title="editing ? $t('travel.action.editTitle', 'Modifier') : $t('travel.action.createTitle', 'Créer')"
      wide
      @close="closeForm"
    >
      <form class="grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="save">
        <FormField id="travel-site-name" :label="$t('travel.field.name', 'Nom')" :error="formErrors.name" required>
          <input v-model="form.name" type="text" class="form-input" required :maxlength="160" />
        </FormField>
        <FormField id="travel-site-city" :label="$t('travel.field.city', 'Ville')" :error="formErrors.city_id" required>
          <select v-model="form.city_id" class="form-input" required>
            <option value="">{{ $t('travel.form.selectPlaceholder', '— Sélectionner —') }}</option>
            <option v-for="opt in cityOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </FormField>
        <FormField
          id="travel-site-description"
          :label="$t('travel.field.description', 'Description')"
          class="sm:col-span-2"
          :error="formErrors.description"
        >
          <textarea v-model="form.description" class="form-input" rows="3" :maxlength="2000"></textarea>
        </FormField>
        <FormField id="travel-site-lat" :label="$t('travel.sites.latitude', 'Latitude')" :error="formErrors.latitude">
          <input v-model="form.latitude" type="number" step="0.000001" class="form-input" />
        </FormField>
        <FormField id="travel-site-lng" :label="$t('travel.sites.longitude', 'Longitude')" :error="formErrors.longitude">
          <input v-model="form.longitude" type="number" step="0.000001" class="form-input" />
        </FormField>
        <FormField id="travel-site-image" :label="$t('travel.field.imageAsset', 'Image (asset id)')" :error="formErrors.image_asset_id">
          <input v-model="form.image_asset_id" type="number" min="1" step="1" class="form-input" />
        </FormField>
        <FormField id="travel-site-status" :label="$t('travel.field.status', 'Statut')" :error="formErrors.status">
          <select v-model="form.status" class="form-input">
            <option value="active">{{ $t('travel.status.active', 'Actif') }}</option>
            <option value="disabled">{{ $t('travel.status.inactive', 'Inactif') }}</option>
          </select>
        </FormField>
        <div class="col-span-full flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="closeForm">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button type="submit" class="btn-primary" :disabled="saving">
            {{ saving ? $t('common.busy', 'En cours…') : $t('travel.action.save', 'Enregistrer') }}
          </button>
        </div>
      </form>
    </TravelModal>

    <ConfirmDialog
      :open="deleteOpen"
      :title="$t('travel.action.deleteTitle', 'Confirmer la suppression')"
      :message="deleteMessage"
      :busy="deleting"
      @confirm="confirmDelete"
      @cancel="closeDelete"
    />
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { PlusIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import FormField from '@/components/common/FormField.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import TravelModal from '@/components/travel/TravelModal.vue'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const toast = useToast()
const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const rows = ref([])
const cities = ref([])
const loading = ref(false)
const listError = ref('')
const globalError = ref('')
const filterCity = ref('')

const formOpen = ref(false)
const editing = ref(false)
const saving = ref(false)
const form = ref({})
const formErrors = ref({})

const deleteOpen = ref(false)
const deleting = ref(false)
const rowToDelete = ref(null)

const statusMap = computed(() => ({
  active: { label: t('travel.status.active', 'Actif'), color: 'green' },
  disabled: { label: t('travel.status.inactive', 'Inactif'), color: 'gray' },
}))

const cityOptions = computed(() =>
  cities.value.map((c) => ({ value: c.id, label: `${c.name}${c.country_iso2 ? ` (${c.country_iso2})` : ''}` }))
)

const columns = computed(() => [
  { key: 'name', label: t('travel.field.name', 'Nom'), sortable: true },
  { key: 'cityName', label: t('travel.field.city', 'Ville'), sortable: true },
  { key: 'status', label: t('travel.field.status', 'Statut'), sortable: true },
])

const cityById = computed(() => Object.fromEntries(cityOptions.value.map((o) => [o.value, o.label])))

const decoratedRows = computed(() =>
  rows.value.map((r) => ({ ...r, cityName: cityById.value[r.city_id] || String(r.city_id || '') }))
)

const filteredRows = computed(() => {
  if (!filterCity.value) return decoratedRows.value
  return decoratedRows.value.filter((r) => String(r.city_id) === String(filterCity.value))
})

const deleteMessage = computed(() =>
  t('travel.confirm.deleteMessage', 'Supprimer définitivement cet élément ?')
)

async function load() {
  loading.value = true
  listError.value = ''
  try {
    const res = await api.get('/travel/tourist-sites', { params: { per_page: 100 }, _skipAuthRedirect: true })
    rows.value = res.data?.data || []
  } catch (err) {
    listError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    loading.value = false
  }
}

async function loadCities() {
  try {
    const res = await api.get('/travel/cities', { params: { per_page: 500 }, _skipAuthRedirect: true })
    cities.value = res.data?.data || []
  } catch {
    /* silencieux : le filtre/select restera vide, l'erreur remonte via la liste */
  }
}

function openCreate() {
  editing.value = false
  form.value = { name: '', description: '', city_id: '', latitude: '', longitude: '', image_asset_id: '', status: 'active' }
  formErrors.value = {}
  globalError.value = ''
  formOpen.value = true
}

function openEdit(row) {
  editing.value = true
  form.value = {
    name: row.name || '',
    description: row.description || '',
    city_id: row.city_id ?? '',
    latitude: row.latitude ?? '',
    longitude: row.longitude ?? '',
    image_asset_id: row.image_asset_id ?? '',
    status: row.status || 'active',
  }
  formErrors.value = {}
  globalError.value = ''
  formOpen.value = true
}

function closeForm() {
  formOpen.value = false
}

async function save() {
  saving.value = true
  globalError.value = ''
  formErrors.value = {}
  try {
    const payload = { ...form.value }
    if (payload.latitude === '') payload.latitude = null
    if (payload.longitude === '') payload.longitude = null
    if (payload.image_asset_id === '') payload.image_asset_id = null
    if (editing.value) {
      await api.put(`/travel/tourist-sites/${form.value.id}`, payload, { _skipAuthRedirect: true })
    } else {
      await api.post('/travel/tourist-sites', payload, { _skipAuthRedirect: true })
    }
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    formOpen.value = false
    await load()
  } catch (err) {
    const data = err.response?.data || {}
    if (data.errors) {
      formErrors.value = Object.fromEntries(Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]))
    }
    globalError.value = data.message || data.localized_message || t('travel.error.saveFailed', "Échec de l'enregistrement.")
  } finally {
    saving.value = false
  }
}

function askDelete(row) {
  rowToDelete.value = row
  deleteOpen.value = true
}

function closeDelete() {
  deleteOpen.value = false
}

async function confirmDelete() {
  deleting.value = true
  try {
    await api.delete(`/travel/tourist-sites/${rowToDelete.value.id}`, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.deleted', 'Supprimé.'))
    deleteOpen.value = false
    await load()
  } catch (err) {
    toast.error(err.response?.data?.message || t('travel.error.deleteFailed', 'Échec de la suppression.'))
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  load()
  loadCities()
})
</script>
