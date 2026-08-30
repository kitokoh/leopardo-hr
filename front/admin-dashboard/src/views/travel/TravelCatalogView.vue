<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('travel.catalog.title', 'Locations & hôtels') }}
      </h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        {{ t('travel.catalog.subtitle', 'Véhicules en location, réservations et catalogue hôtelier.') }}
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

      <!-- ─── Véhicules en location ─── -->
      <template v-if="activeTab === 'rentals'">
        <div class="flex justify-end">
          <button class="btn-primary inline-flex items-center gap-1.5" @click="openRentalCreate">
            <PlusIcon class="h-4 w-4" />
            {{ t('travel.common.create', 'Créer') }}
          </button>
        </div>
        <DataTable
          :columns="rentalColumns"
          :rows="rentals"
          :loading="loading.rentals"
          :error="errors.rentals"
          :search-keys="['code', 'title']"
          :empty-message="t('travel.common.noData', 'Aucune donnée')"
        >
          <template #cell-price_per_day_minor="{ value, row }">
            {{ formatMinor(value, row.currency) }}
          </template>
          <template #cell-status="{ value }">
            <StatusBadge :status="value" :map="statusMap" />
          </template>
          <template #row-actions="{ row }">
            <div class="flex justify-end gap-2">
              <button class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400" @click="openRentalImages(row)">
                {{ t('travel.catalog.images', 'Images') }}
              </button>
              <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="openRentalEdit(row)">
                {{ t('travel.common.edit', 'Modifier') }}
              </button>
              <button class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="askDelete('rentals', row)">
                {{ t('travel.common.delete', 'Supprimer') }}
              </button>
            </div>
          </template>
        </DataTable>

        <div v-if="selectedRental" class="rounded-2xl glass-card p-5">
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">
              {{ t('travel.catalog.rentalImagesTitle', 'Images du véhicule') }} — {{ selectedRental.title }}
            </h2>
            <button class="btn-secondary" @click="selectedRental = null">
              {{ t('travel.common.close', 'Fermer') }}
            </button>
          </div>
          <div v-if="(selectedRental.images || []).length === 0" class="mt-3 text-sm text-slate-500">
            {{ t('travel.catalog.noImages', 'Aucune image') }}
          </div>
          <div v-else class="mt-3 flex flex-wrap gap-3">
            <div v-for="image in selectedRental.images" :key="image.id" class="relative">
              <img :src="image.image_url || ''" :alt="selectedRental.title" class="h-24 w-32 rounded-lg object-cover" />
              <button
                class="absolute -right-2 -top-2 rounded-full bg-red-500 p-1 text-white shadow"
                :aria-label="t('travel.common.delete', 'Supprimer')"
                @click="deleteImage(image)"
              >
                <XMarkIcon class="h-3 w-3" />
              </button>
            </div>
          </div>
        </div>
      </template>

      <!-- ─── Réservations location ─── -->
      <template v-else-if="activeTab === 'rental-bookings'">
        <DataTable
          :columns="rentalBookingColumns"
          :rows="rentalBookings"
          :loading="loading['rental-bookings']"
          :error="errors['rental-bookings']"
          :search-keys="['reference']"
          :empty-message="t('travel.common.noData', 'Aucune donnée')"
        >
          <template #cell-total_amount_minor="{ value, row }">
            {{ formatMinor(value, row.currency) }}
          </template>
          <template #cell-status="{ value }">
            <StatusBadge :status="value" :map="rentalBookingStatusMap" />
          </template>
          <template #row-actions="{ row }">
            <button
              v-if="['pending', 'confirmed'].includes(row.status)"
              class="text-sm font-medium text-amber-600 hover:text-amber-800 dark:text-amber-400"
              @click="openRentalCancel(row)"
            >
              {{ t('travel.bookings.cancel', 'Annuler') }}
            </button>
          </template>
        </DataTable>
      </template>

      <!-- ─── Hôtels ─── -->
      <template v-else>
        <div class="flex justify-end">
          <button class="btn-primary inline-flex items-center gap-1.5" @click="openHotelCreate">
            <PlusIcon class="h-4 w-4" />
            {{ t('travel.common.create', 'Créer') }}
          </button>
        </div>
        <DataTable
          :columns="hotelColumns"
          :rows="hotels"
          :loading="loading.hotels"
          :error="errors.hotels"
          :search-keys="['name', 'city_label']"
          :empty-message="t('travel.common.noData', 'Aucune donnée')"
        >
          <template #cell-classification="{ value }">
            <span>{{ '★'.repeat(value || 0) }}</span>
          </template>
          <template #cell-status="{ value }">
            <StatusBadge :status="value" :map="statusMap" />
          </template>
          <template #row-actions="{ row }">
            <div class="flex justify-end gap-2">
              <button class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400" @click="openHotelRooms(row)">
                {{ t('travel.catalog.rooms', 'Chambres') }}
              </button>
              <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="openHotelEdit(row)">
                {{ t('travel.common.edit', 'Modifier') }}
              </button>
              <button class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="askDelete('hotels', row)">
                {{ t('travel.common.delete', 'Supprimer') }}
              </button>
            </div>
          </template>
        </DataTable>

        <div v-if="selectedHotel" class="rounded-2xl glass-card p-5">
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">
              {{ t('travel.catalog.hotelRoomsTitle', 'Chambres de l\u2019hôtel') }} — {{ selectedHotel.name }}
            </h2>
            <button class="btn-secondary" @click="selectedHotel = null">
              {{ t('travel.common.close', 'Fermer') }}
            </button>
          </div>
          <div class="mt-4 flex justify-end">
            <button class="btn-primary inline-flex items-center gap-1.5" @click="openRoomCreate">
              <PlusIcon class="h-4 w-4" />
              {{ t('travel.catalog.addRoom', 'Ajouter une chambre') }}
            </button>
          </div>
          <DataTable
            class="mt-2"
            :columns="roomColumns"
            :rows="selectedHotel.rooms || []"
            :loading="loading.rooms"
            :error="errors.rooms"
            :empty-message="t('travel.common.noData', 'Aucune donnée')"
          >
            <template #cell-price_per_night_minor="{ value, row }">
              {{ formatMinor(value, row.currency) }}
            </template>
            <template #row-actions="{ row }">
              <div class="flex justify-end gap-2">
                <button class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400" @click="askDeleteRoom(row)">
                  {{ t('travel.common.delete', 'Supprimer') }}
                </button>
              </div>
            </template>
          </DataTable>
        </div>
      </template>

      <!-- Modales -->
      <TravelFormModal
        :open="rentalModalOpen"
        :title="editingRental ? t('travel.common.edit', 'Modifier') : t('travel.common.create', 'Créer')"
        :fields="rentalFields"
        :values="editingRental || {}"
        :busy="saving"
        :error="formError"
        @save="saveRental"
        @cancel="rentalModalOpen = false"
      />

      <TravelFormModal
        :open="hotelModalOpen"
        :title="editingHotel ? t('travel.common.edit', 'Modifier') : t('travel.common.create', 'Créer')"
        :fields="hotelFields"
        :values="editingHotel || {}"
        :busy="saving"
        :error="formError"
        @save="saveHotel"
        @cancel="hotelModalOpen = false"
      />

      <TravelFormModal
        :open="roomModalOpen"
        :title="t('travel.catalog.addRoom', 'Ajouter une chambre')"
        :fields="roomFields"
        :values="{}"
        :busy="saving"
        :error="formError"
        @save="saveRoom"
        @cancel="roomModalOpen = false"
      />

      <TravelFormModal
        :open="rentalCancelModalOpen"
        :title="t('travel.bookings.cancelTitle', 'Annuler la réservation')"
        :fields="reasonFields"
        :values="{}"
        :busy="saving"
        :error="formError"
        @save="confirmRentalCancel"
        @cancel="rentalCancelModalOpen = false"
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
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import {
  listTravel,
  createTravel,
  updateTravel,
  deleteTravel,
  travelAction,
  createTravelSub,
  deleteTravelSub,
  travelList,
  formatMinor
} from '@/services/travel'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const travelStore = useTravelStore()

const activeTab = ref('rentals')
const loading = reactive({ rentals: false, 'rental-bookings': false, hotels: false, rooms: false })
const errors = reactive({})
const lists = reactive({ rentals: [], 'rental-bookings': [], hotels: [], cities: [], carriers: [] })
const loadError = ref('')

const selectedRental = ref(null)
const selectedHotel = ref(null)

const rentalModalOpen = ref(false)
const editingRental = ref(null)
const hotelModalOpen = ref(false)
const editingHotel = ref(null)
const roomModalOpen = ref(false)
const rentalCancelModalOpen = ref(false)
const rentalCancelTarget = ref(null)
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
  { key: 'rentals', label: t('travel.catalog.tabRentals', 'Véhicules en location') },
  { key: 'rental-bookings', label: t('travel.catalog.tabRentalBookings', 'Réservations location') },
  { key: 'hotels', label: t('travel.catalog.tabHotels', 'Hôtels') }
])

const statusMap = {
  active: { label: t('travel.common.active', 'Actif'), color: 'green' },
  disabled: { label: t('travel.common.disabled', 'Inactif'), color: 'gray' }
}

const rentalBookingStatusMap = {
  pending: { label: t('travel.bookingStatus.pending', 'En attente'), color: 'yellow' },
  confirmed: { label: t('travel.bookingStatus.confirmed', 'Confirmée'), color: 'green' },
  cancelled: { label: t('travel.bookingStatus.cancelled', 'Annulée'), color: 'red' },
  completed: { label: t('travel.bookingStatus.completed', 'Terminée'), color: 'blue' }
}

const cityOptions = computed(() =>
  lists.cities.map((city) => ({ value: city.id, label: `${city.name}${city.country_iso2 ? ` (${city.country_iso2})` : ''}` }))
)
const carrierOptions = computed(() =>
  lists.carriers.filter((c) => c.status === 'active').map((c) => ({ value: c.id, label: c.name }))
)
const statusFieldOptions = computed(() => [
  { value: 'active', label: t('travel.common.active', 'Actif') },
  { value: 'disabled', label: t('travel.common.disabled', 'Inactif') }
])

function cityName(cityId) {
  const city = lists.cities.find((c) => c.id === cityId)
  return city ? city.name : (cityId ?? '—')
}

const rentalColumns = computed(() => [
  { key: 'code', label: 'Code', sortable: true },
  { key: 'title', label: t('travel.catalog.title', 'Intitulé'), sortable: true },
  { key: 'city_label', label: t('travel.common.city', 'Ville'), sortable: true },
  { key: 'price_per_day_minor', label: t('travel.catalog.priceDay', 'Prix / jour') },
  { key: 'status', label: t('travel.common.status', 'Statut'), sortable: true }
])

const rentalBookingColumns = computed(() => [
  { key: 'reference', label: t('travel.bookings.reference', 'Référence'), sortable: true },
  { key: 'start_date', label: t('travel.catalog.start', 'Début'), sortable: true },
  { key: 'end_date', label: t('travel.catalog.end', 'Fin'), sortable: true },
  { key: 'total_amount_minor', label: t('travel.bookings.total', 'Total') },
  { key: 'status', label: t('travel.common.status', 'Statut'), sortable: true }
])

const hotelColumns = computed(() => [
  { key: 'name', label: t('travel.catalog.hotelName', 'Nom'), sortable: true },
  { key: 'city_label', label: t('travel.common.city', 'Ville'), sortable: true },
  { key: 'classification', label: t('travel.catalog.classification', 'Classification'), sortable: true },
  { key: 'contact_phone', label: t('travel.offices.phone', 'Téléphone') },
  { key: 'status', label: t('travel.common.status', 'Statut'), sortable: true }
])

const roomColumns = computed(() => [
  { key: 'type_code', label: t('travel.catalog.roomType', 'Type'), sortable: true },
  { key: 'room_number', label: t('travel.catalog.roomNumber', 'N°'), sortable: true },
  { key: 'capacity', label: t('travel.catalog.capacity', 'Capacité'), sortable: true },
  { key: 'price_per_night_minor', label: t('travel.catalog.priceNight', 'Prix / nuit') }
])

const rentalFields = computed(() => [
  { key: 'code', label: 'travel.catalog.code', type: 'text', required: true, max: 40 },
  { key: 'title', label: 'travel.catalog.title', type: 'text', required: true, max: 160 },
  { key: 'city_id', label: 'travel.common.city', type: 'select', required: true, options: cityOptions },
  { key: 'price_per_day_minor', label: 'travel.catalog.priceDay', type: 'number', required: true, min: 1 },
  { key: 'currency', label: 'travel.network.currency', type: 'text', required: true, max: 3 },
  { key: 'available_from', label: 'travel.catalog.availableFrom', type: 'text' },
  { key: 'available_until', label: 'travel.catalog.availableUntil', type: 'text' },
  { key: 'owner_carrier_id', label: 'travel.catalog.owner', type: 'select', options: carrierOptions },
  { key: 'status', label: 'travel.common.status', type: 'select', options: statusFieldOptions },
  { key: 'notes', label: 'travel.vehicles.notes', type: 'textarea', max: 2000 }
])

const hotelFields = computed(() => [
  { key: 'name', label: 'travel.catalog.hotelName', type: 'text', required: true, max: 160 },
  { key: 'city_id', label: 'travel.common.city', type: 'select', required: true, options: cityOptions },
  { key: 'classification', label: 'travel.catalog.classification', type: 'number', required: true, min: 1, max: 5 },
  { key: 'address', label: 'travel.offices.address', type: 'text', max: 255 },
  { key: 'contact_phone', label: 'travel.offices.phone', type: 'text', max: 40 },
  { key: 'description_redacted', label: 'travel.catalog.description', type: 'textarea', max: 5000 },
  { key: 'status', label: 'travel.common.status', type: 'select', options: statusFieldOptions }
])

const roomFields = computed(() => [
  { key: 'type_code', label: 'travel.catalog.roomType', type: 'text', required: true, max: 40 },
  { key: 'room_number', label: 'travel.catalog.roomNumber', type: 'text', required: true, max: 20 },
  { key: 'capacity', label: 'travel.catalog.capacity', type: 'number', required: true, min: 1 },
  { key: 'price_per_night_minor', label: 'travel.catalog.priceNight', type: 'number', required: true, min: 1 },
  { key: 'currency', label: 'travel.network.currency', type: 'text', required: true, max: 3 },
  { key: 'status', label: 'travel.common.status', type: 'select', options: statusFieldOptions }
])

const reasonFields = computed(() => [
  { key: 'reason', label: 'travel.common.reason', type: 'textarea', required: true, max: 500, rows: 3 }
])

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
  if (key === 'rental-bookings' && lists['rental-bookings'].length === 0) {
    load('rental-bookings')
  }
}

async function init() {
  await travelStore.checkFlag(true)
  if (gateMode.value) return
  loadError.value = ''
  await Promise.all([load('rentals'), load('hotels'), load('cities', { per_page: 1000 }), load('carriers')])
  decorate()
}

function decorate() {
  lists.rentals = (lists.rentals || []).map((row) => ({ ...row, city_label: row.city_id ? cityName(row.city_id) : '—' }))
  lists.hotels = (lists.hotels || []).map((row) => ({ ...row, city_label: row.city_id ? cityName(row.city_id) : '—' }))
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

/* ─── Véhicules location ─── */
function openRentalCreate() {
  editingRental.value = null
  formError.value = ''
  rentalModalOpen.value = true
}
function openRentalEdit(rental) {
  editingRental.value = rental
  formError.value = ''
  rentalModalOpen.value = true
}
async function saveRental(values) {
  saving.value = true
  formError.value = ''
  try {
    if (editingRental.value) {
      await updateTravel('rental-vehicles', editingRental.value.id, values)
    } else {
      await createTravel('rental-vehicles', values)
    }
    rentalModalOpen.value = false
    editingRental.value = null
    await load('rentals')
    decorate()
  } catch (error) {
    formError.value = apiError(error)
  } finally {
    saving.value = false
  }
}
async function openRentalImages(rental) {
  selectedRental.value = { ...rental }
  try {
    const response = await listTravel(`rental-vehicles/${rental.id}/images`)
    selectedRental.value.images = travelList(response).map((image) => ({
      ...image,
      image_url: image.image_url || image.asset_url || ''
    }))
  } catch (error) {
    errors.rentals = apiError(error)
  }
}
async function deleteImage(image) {
  if (!selectedRental.value) return
  try {
    await deleteTravelSub('rental-vehicles', selectedRental.value.id, 'images', image.id)
    await openRentalImages(selectedRental.value)
  } catch (error) {
    errors.rentals = apiError(error)
  }
}

/* ─── Réservations location ─── */
function openRentalCancel(row) {
  rentalCancelTarget.value = row
  formError.value = ''
  rentalCancelModalOpen.value = true
}
async function confirmRentalCancel(values) {
  if (!rentalCancelTarget.value) return
  saving.value = true
  formError.value = ''
  try {
    await travelAction('rental-bookings', rentalCancelTarget.value.id, 'cancel', { reason: values.reason })
    rentalCancelModalOpen.value = false
    rentalCancelTarget.value = null
    await load('rental-bookings')
  } catch (error) {
    formError.value = apiError(error)
  } finally {
    saving.value = false
  }
}

/* ─── Hôtels ─── */
function openHotelCreate() {
  editingHotel.value = null
  formError.value = ''
  hotelModalOpen.value = true
}
function openHotelEdit(hotel) {
  editingHotel.value = hotel
  formError.value = ''
  hotelModalOpen.value = true
}
async function saveHotel(values) {
  saving.value = true
  formError.value = ''
  try {
    if (editingHotel.value) {
      await updateTravel('hotels', editingHotel.value.id, values)
    } else {
      await createTravel('hotels', values)
    }
    hotelModalOpen.value = false
    editingHotel.value = null
    await load('hotels')
    decorate()
  } catch (error) {
    formError.value = apiError(error)
  } finally {
    saving.value = false
  }
}
async function openHotelRooms(hotel) {
  selectedHotel.value = { ...hotel }
  await loadRooms(hotel.id)
}
async function loadRooms(hotelId) {
  loading.rooms = true
  errors.rooms = ''
  try {
    const response = await listTravel(`hotels/${hotelId}/rooms`, { per_page: 200 })
    if (selectedHotel.value) {
      selectedHotel.value.rooms = travelList(response)
    }
  } catch (error) {
    errors.rooms = apiError(error)
  } finally {
    loading.rooms = false
  }
}
function openRoomCreate() {
  formError.value = ''
  roomModalOpen.value = true
}
async function saveRoom(values) {
  if (!selectedHotel.value) return
  saving.value = true
  formError.value = ''
  try {
    await createTravelSub('hotels', selectedHotel.value.id, 'rooms', values)
    roomModalOpen.value = false
    await loadRooms(selectedHotel.value.id)
  } catch (error) {
    formError.value = apiError(error)
  } finally {
    saving.value = false
  }
}
function askDeleteRoom(room) {
  deleteAction.value = () =>
    deleteTravelSub('hotels', selectedHotel.value.id, 'rooms', room.id).then(() => loadRooms(selectedHotel.value.id))
  deleteMessage.value = t('travel.common.confirmDeleteBody', 'Cette action est irréversible. Voulez-vous vraiment supprimer « {name} » ?').replace('{name}', room.room_number)
  deleteOpen.value = true
}

/* ─── Suppression générique ─── */
function askDelete(collection, row) {
  const label = row.code || row.name || row.title || String(row.id)
  if (collection === 'rentals') {
    deleteAction.value = () => deleteTravel('rental-vehicles', row.id).then(async () => {
      await load('rentals')
      decorate()
    })
  } else {
    deleteAction.value = () => deleteTravel('hotels', row.id).then(async () => {
      await load('hotels')
      decorate()
    })
  }
  deleteMessage.value = t('travel.common.confirmDeleteBody', 'Cette action est irréversible. Voulez-vous vraiment supprimer « {name} » ?').replace('{name}', label)
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
