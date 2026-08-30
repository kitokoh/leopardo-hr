<template>
  <div class="space-y-8">
    <TravelCrudSection
      ref="vehiclesSection"
      :config="vehicleConfig"
      :lookups="{ cities: cityOptions, carriers: carrierOptions }"
      :column-display="{ city_id: cityName, owner_carrier_id: carrierName }"
      @action="onVehicleAction"
    />

    <div class="border-t border-slate-200/50 pt-8 dark:border-slate-800/50">
      <TravelCrudSection
        ref="rentalBookingsSection"
        :config="rentalBookingConfig"
        :lookups="{ vehicles: vehicleOptions }"
        :column-display="{ vehicle_id: vehicleName }"
        @action="onRentalBookingAction"
      />
    </div>

    <div class="border-t border-slate-200/50 pt-8 dark:border-slate-800/50">
      <TravelCrudSection
        :config="hotelConfig"
        :lookups="{ cities: cityOptions }"
        :column-display="{ city_id: cityName }"
      />
    </div>

    <!-- Gestion des images d'un véhicule de location -->
    <TravelModal :open="imagesOpen" :title="imagesTitle" @close="closeimagesOpen">
      <div v-if="imagesError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert">
        {{ imagesError }}
      </div>
      <div class="space-y-2">
        <div
          v-for="img in images"
          :key="img.id"
          class="flex items-center justify-between gap-3 rounded-lg border border-slate-200/50 px-3 py-2 dark:border-slate-800/50"
        >
          <span class="text-sm text-slate-700 dark:text-slate-300">{{ img.asset?.filename || img.filename || `#${img.id}` }}</span>
          <button
            class="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600"
            type="button"
            :aria-label="$t('travel.action.delete', 'Supprimer')"
            @click="deleteImage(img)"
          >
            <TrashIcon class="h-4 w-4" />
          </button>
        </div>
        <p v-if="images.length === 0" class="py-4 text-center text-xs text-slate-400">
          {{ $t('travel.rentals.noImages', 'Aucune image.') }}
        </p>
      </div>
      <form class="mt-4 flex items-end gap-2" @submit.prevent="uploadImage">
        <div class="flex-1">
          <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="vehicle-image">
            {{ $t('travel.rentals.addImage', 'Ajouter une image') }}
          </label>
          <input id="vehicle-image" type="file" accept="image/jpeg,image/png,image/webp" class="form-input mt-1" @change="onFileChange" />
        </div>
        <button class="btn-primary" type="submit" :disabled="!selectedFile || uploadingImage">
          {{ uploadingImage ? $t('common.busy', 'En cours…') : $t('travel.action.add', 'Ajouter') }}
        </button>
      </form>
    </TravelModal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { TrashIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import TravelCrudSection from '@/components/travel/TravelCrudSection.vue'
import TravelModal from '@/components/travel/TravelModal.vue'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const toast = useToast()

const vehiclesSection = ref(null)
const rentalBookingsSection = ref(null)

/* ── lookups ────────────────────────────────────────────────── */
const cities = ref([])
const carriers = ref([])
const vehicles = ref([])

const cityOptions = computed(() =>
  cities.value.map((c) => ({ value: c.id, label: `${c.name}${c.country_iso2 ? ` (${c.country_iso2})` : ''}` }))
)
const carrierOptions = computed(() => carriers.value.map((c) => ({ value: c.id, label: c.name })))
const vehicleOptions = computed(() =>
  vehicles.value.map((v) => ({ value: v.id, label: `${v.title} (${v.code})` }))
)

function cityName(_row, value) {
  const city = cities.value.find((c) => String(c.id) === String(value))
  return city ? `${city.name}${city.country_iso2 ? ` (${city.country_iso2})` : ''}` : value
}
function carrierName(_row, value) {
  const carrier = carriers.value.find((c) => String(c.id) === String(value))
  return carrier ? carrier.name : value
}
function vehicleName(_row, value) {
  const vehicle = vehicles.value.find((v) => String(v.id) === String(value))
  return vehicle ? `${vehicle.title} (${vehicle.code})` : value
}

async function loadLookups() {
  try {
    const [cRes, caRes, vRes] = await Promise.all([
      api.get('/travel/cities', { params: { per_page: 100 }, _skipAuthRedirect: true }),
      api.get('/travel/carriers', { params: { per_page: 100 }, _skipAuthRedirect: true }),
      api.get('/travel/rental-vehicles', { params: { per_page: 100 }, _skipAuthRedirect: true })
    ])
    cities.value = cRes.data?.data || []
    carriers.value = caRes.data?.data || []
    vehicles.value = vRes.data?.data || []
  } catch {
    // lookups silencieux
  }
}

/* ── statuts ────────────────────────────────────────────────── */
const statusMap = {
  active: { labelKey: 'travel.status.active', color: 'green' },
  inactive: { labelKey: 'travel.status.inactive', color: 'yellow' },
  archived: { labelKey: 'travel.status.archived', color: 'gray' }
}

const rentalStatusMap = {
  pending: { labelKey: 'travel.bookingStatus.pending', color: 'yellow' },
  confirmed: { labelKey: 'travel.bookingStatus.confirmed', color: 'green' },
  cancelled: { labelKey: 'travel.bookingStatus.cancelled', color: 'gray' },
  completed: { labelKey: 'travel.rentalStatus.completed', color: 'blue' }
}

const paymentStatusOptions = [
  { value: 'pending', label: t('travel.paymentStatus.pending', 'En attente') },
  { value: 'paid', label: t('travel.paymentStatus.paid', 'Payé') },
  { value: 'refunded', label: t('travel.paymentStatus.refunded', 'Remboursé') }
]

/* ── véhicules de location ──────────────────────────────────── */
const vehicleConfig = computed(() => ({
  resource: 'rental-vehicles',
  titleKey: 'travel.rentals.vehicles',
  searchPlaceholderKey: 'travel.search.rentalVehicle',
  searchKeys: ['code', 'title', 'city_id'],
  defaultSort: 'title',
  statusField: 'status',
  statusMap,
  rowActions: [
    { key: 'images', label: 'travel.rentals.images' }
  ],
  columns: [
    { key: 'code', label: 'travel.field.code', sortable: true },
    { key: 'title', label: 'travel.field.title', sortable: true },
    { key: 'city_id', label: 'travel.field.city', sortable: true },
    { key: 'price_per_day_minor', label: 'travel.field.pricePerDay', sortable: true, type: 'money' },
    { key: 'status', label: 'travel.field.status', sortable: true }
  ],
  fields: [
    { key: 'code', label: 'travel.field.code', type: 'text', required: true, max: 40 },
    { key: 'title', label: 'travel.field.title', type: 'text', required: true, max: 160 },
    { key: 'city_id', label: 'travel.field.city', type: 'select', source: 'cities', required: true },
    { key: 'price_per_day_minor', label: 'travel.field.pricePerDay', type: 'money', required: true, min: 0 },
    { key: 'currency', label: 'travel.field.currency', type: 'text', required: true, max: 3 },
    { key: 'available_from', label: 'travel.field.availableFrom', type: 'date' },
    { key: 'available_until', label: 'travel.field.availableUntil', type: 'date' },
    { key: 'owner_carrier_id', label: 'travel.field.ownerCarrier', type: 'select', source: 'carriers' },
    { key: 'notes', label: 'travel.field.notes', type: 'textarea' },
    { key: 'status', label: 'travel.field.status', type: 'select', options: [
      { value: 'active', label: t('travel.status.active', 'Actif') },
      { value: 'inactive', label: t('travel.status.inactive', 'Inactif') },
      { value: 'archived', label: t('travel.status.archived', 'Archivé') }
    ] }
  ],
  defaults: { currency: 'XAF', status: 'active' }
}))

/* ── réservations de location ───────────────────────────────── */
const rentalBookingConfig = computed(() => ({
  resource: 'rental-bookings',
  titleKey: 'travel.rentals.bookings',
  searchPlaceholderKey: 'travel.search.rentalBooking',
  searchKeys: ['reference', 'vehicle_id', 'status'],
  defaultSort: 'start_date',
  defaultSortDir: 'desc',
  statusField: 'status',
  statusMap: rentalStatusMap,
  rowActions: [
    {
      key: 'cancel',
      label: 'travel.action.cancel',
      condition: (row) => !['cancelled', 'completed'].includes(row.status)
    }
  ],
  columns: [
    { key: 'reference', label: 'travel.field.reference', sortable: true },
    { key: 'vehicle_id', label: 'travel.field.vehicle', sortable: true },
    { key: 'start_date', label: 'travel.field.startDate', sortable: true },
    { key: 'end_date', label: 'travel.field.endDate', sortable: true },
    { key: 'total_amount_minor', label: 'travel.field.totalAmount', sortable: true, type: 'money' },
    { key: 'payment_status', label: 'travel.field.paymentStatus', sortable: true },
    { key: 'status', label: 'travel.field.status', sortable: true }
  ],
  fields: [
    { key: 'vehicle_id', label: 'travel.field.vehicle', type: 'select', source: 'vehicles', required: true },
    { key: 'start_date', label: 'travel.field.startDate', type: 'date', required: true },
    { key: 'end_date', label: 'travel.field.endDate', type: 'date', required: true },
    { key: 'deposit_amount_minor', label: 'travel.field.deposit', type: 'money', min: 0 },
    { key: 'currency', label: 'travel.field.currency', type: 'text', required: true, max: 3 },
    { key: 'payment_status', label: 'travel.field.paymentStatus', type: 'select', options: paymentStatusOptions },
    { key: 'status', label: 'travel.field.status', type: 'select', options: [
      { value: 'pending', label: t('travel.bookingStatus.pending', 'En attente') },
      { value: 'confirmed', label: t('travel.bookingStatus.confirmed', 'Confirmée') },
      { value: 'cancelled', label: t('travel.bookingStatus.cancelled', 'Annulée') }
    ] }
  ],
  defaults: { currency: 'XAF', payment_status: 'pending', status: 'pending' }
}))

/* ── hôtels ─────────────────────────────────────────────────── */
const hotelConfig = computed(() => ({
  resource: 'hotels',
  titleKey: 'travel.rentals.hotels',
  searchPlaceholderKey: 'travel.search.hotel',
  searchKeys: ['name', 'city_id'],
  defaultSort: 'name',
  statusField: 'status',
  statusMap,
  columns: [
    { key: 'name', label: 'travel.field.name', sortable: true },
    { key: 'city_id', label: 'travel.field.city', sortable: true },
    { key: 'classification', label: 'travel.field.classification', sortable: true },
    { key: 'contact_phone', label: 'travel.field.contactPhone', sortable: true },
    { key: 'status', label: 'travel.field.status', sortable: true }
  ],
  fields: [
    { key: 'name', label: 'travel.field.name', type: 'text', required: true, max: 160 },
    { key: 'city_id', label: 'travel.field.city', type: 'select', source: 'cities', required: true },
    { key: 'classification', label: 'travel.field.classification', type: 'number', min: 1, max: 5 },
    { key: 'address', label: 'travel.field.address', type: 'text' },
    { key: 'contact_phone', label: 'travel.field.contactPhone', type: 'text' },
    { key: 'description_redacted', label: 'travel.field.description', type: 'textarea' },
    { key: 'status', label: 'travel.field.status', type: 'select', options: [
      { value: 'active', label: t('travel.status.active', 'Actif') },
      { value: 'inactive', label: t('travel.status.inactive', 'Inactif') }
    ] }
  ],
  defaults: { classification: 3, status: 'active' },
  nested: {
    titleKey: 'travel.rentals.roomsTitle',
    resource: 'hotels/{id}/rooms',
    columns: [
      { key: 'room_number', label: 'travel.field.roomNumber' },
      { key: 'type_code', label: 'travel.field.roomType' },
      { key: 'capacity', label: 'travel.field.capacity' },
      { key: 'price_per_night_minor', label: 'travel.field.pricePerNight', type: 'money' },
      { key: 'status', label: 'travel.field.status' }
    ],
    fields: [
      { key: 'room_number', label: 'travel.field.roomNumber', type: 'text', required: true, max: 20 },
      { key: 'type_code', label: 'travel.field.roomType', type: 'text', required: true, max: 40 },
      { key: 'capacity', label: 'travel.field.capacity', type: 'number', required: true, min: 1 },
      { key: 'price_per_night_minor', label: 'travel.field.pricePerNight', type: 'money', required: true, min: 0 },
      { key: 'currency', label: 'travel.field.currency', type: 'text', required: true, max: 3 },
      { key: 'status', label: 'travel.field.status', type: 'select', options: [
        { value: 'active', label: t('travel.status.active', 'Actif') },
        { value: 'inactive', label: t('travel.status.inactive', 'Inactif') }
      ] }
    ],
    defaults: { capacity: 1, currency: 'XAF', status: 'active' }
  }
}))

/* ── images véhicules ───────────────────────────────────────── */
const imagesOpen = ref(false)
const imagesError = ref('')
const images = ref([])
const imagesVehicle = ref(null)
const selectedFile = ref(null)
const uploadingImage = ref(false)

const imagesTitle = computed(() =>
  imagesVehicle.value
    ? `${t('travel.rentals.images', 'Images')} — ${imagesVehicle.value.title || imagesVehicle.value.code}`
    : t('travel.rentals.images', 'Images')
)

function closeImages() {
  imagesOpen.value = false
}

async function onVehicleAction({ key, row }) {
  if (key !== 'images') return
  imagesVehicle.value = row
  imagesOpen.value = true
  imagesError.value = ''
  images.value = []
  try {
    const res = await api.get(`/travel/rental-vehicles/${row.id}/images`, { _skipAuthRedirect: true })
    images.value = res.data?.data || []
  } catch (err) {
    imagesError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  }
}

function onFileChange(event) {
  selectedFile.value = event.target.files?.[0] || null
}

async function uploadImage() {
  if (!selectedFile.value || !imagesVehicle.value) return
  uploadingImage.value = true
  imagesError.value = ''
  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)
    await api.post(`/travel/rental-vehicles/${imagesVehicle.value.id}/images`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      _skipAuthRedirect: true
    })
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    selectedFile.value = null
    const res = await api.get(`/travel/rental-vehicles/${imagesVehicle.value.id}/images`, { _skipAuthRedirect: true })
    images.value = res.data?.data || []
  } catch (err) {
    imagesError.value = err.response?.data?.message || t('travel.error.saveFailed', "Échec de l'enregistrement.")
  } finally {
    uploadingImage.value = false
  }
}

async function deleteImage(img) {
  if (!window.confirm(t('travel.confirm.deleteMessage', 'Supprimer définitivement cet élément ?'))) return
  try {
    await api.delete(`/travel/rental-vehicles/${imagesVehicle.value.id}/images/${img.id}`, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.deleted', 'Supprimé.'))
    const res = await api.get(`/travel/rental-vehicles/${imagesVehicle.value.id}/images`, { _skipAuthRedirect: true })
    images.value = res.data?.data || []
  } catch (err) {
    toast.error(err.response?.data?.message || t('travel.error.deleteFailed', 'Échec de la suppression.'))
  }
}

/* ── annulation réservation location ────────────────────────── */
async function onRentalBookingAction({ key, row }) {
  if (key !== 'cancel') return
  const ok = window.confirm(t('travel.confirm.cancelRental', 'Annuler cette réservation de location ?'))
  if (!ok) return
  try {
    await api.post(`/travel/rental-bookings/${row.id}/cancel`, {}, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.cancelled', 'Réservation annulée.'))
    rentalBookingsSection.value?.load()
  } catch (err) {
    toast.error(err.response?.data?.message || t('travel.error.actionFailed', "L'action a échoué."))
  }
}

onMounted(loadLookups)
</script>
