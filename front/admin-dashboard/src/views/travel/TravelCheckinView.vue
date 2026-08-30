<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('travel.checkin.title', 'Check-in & manifeste') }}
      </h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        {{ t('travel.checkin.subtitle', 'Embarquement par trajet et compteur de passagers.') }}
      </p>
    </div>

    <TravelGate :mode="gateMode" :message="loadError" @retry="init" />

    <template v-if="!gateMode">
      <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-64 flex-1 space-y-1">
          <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">
            {{ t('travel.checkin.trip', 'Trajet') }}
          </label>
          <select
            v-model="selectedTripId"
            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            @change="loadManifest"
          >
            <option value="">{{ t('travel.checkin.selectTrip', 'Sélectionner un trajet…') }}</option>
            <option v-for="trip in trips" :key="trip.id" :value="trip.id">
              {{ trip.code }} — {{ trip.departure_date }} {{ trip.departure_time }} ({{ tripStatusMap[trip.status]?.label || trip.status }})
            </option>
          </select>
        </div>
        <div
          v-if="manifest.length"
          class="rounded-xl bg-slate-50 px-4 py-2 text-sm dark:bg-slate-800/50"
        >
          <span class="font-semibold text-slate-900 dark:text-white">{{ boardedCount }}</span>
          <span class="text-slate-500 dark:text-slate-400"> / {{ manifest.length }} {{ t('travel.checkin.boarded', 'embarqués') }}</span>
        </div>
      </div>

      <DataTable
        :columns="manifestColumns"
        :rows="manifest"
        :loading="loading"
        :error="error"
        :empty-message="t('travel.checkin.emptyManifest', 'Aucun passager pour ce trajet')"
        key-field="passenger_id"
      >
        <template #cell-seat="{ value }">
          <span class="font-mono text-sm">{{ value || '—' }}</span>
        </template>
        <template #cell-ticket_status="{ value }">
          <StatusBadge v-if="value" :status="value" :map="ticketStatusMap" />
          <span v-else class="text-xs text-slate-400">{{ t('travel.checkin.noTicket', 'Sans billet') }}</span>
        </template>
        <template #cell-checkin="{ row }">
          <button
            v-if="row.ticket_id && row.ticket_status === 'issued'"
            class="btn-primary px-3 py-1.5 text-xs"
            :disabled="busyTicketId === row.ticket_id"
            @click="checkIn(row)"
          >
            {{ busyTicketId === row.ticket_id ? '…' : t('travel.checkin.checkIn', 'Embarquer') }}
          </button>
          <span v-else-if="row.ticket_status === 'checked_in'" class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
            <CheckCircleIcon class="h-4 w-4" />
            {{ t('travel.checkin.done', 'Embarqué') }}
          </span>
          <span v-else class="text-xs text-slate-400">—</span>
        </template>
      </DataTable>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { useTravelStore } from '@/stores/travel'
import TravelGate from '@/components/travel/TravelGate.vue'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { CheckCircleIcon } from '@heroicons/vue/24/outline'
import { listTravel, getTravel, travelAction, travelList, travelItem } from '@/services/travel'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const travelStore = useTravelStore()

const trips = ref([])
const selectedTripId = ref('')
const manifest = ref([])
const loading = ref(false)
const error = ref('')
const loadError = ref('')
const busyTicketId = ref(null)

const gateMode = computed(() => {
  if (!travelStore.isReady) return ''
  if (travelStore.noTenantContext) return 'tenant'
  if (!travelStore.flagActive) return 'feature'
  return ''
})

const tripStatusMap = {
  draft: { label: t('travel.tripStatus.draft', 'Brouillon'), color: 'gray' },
  scheduled: { label: t('travel.tripStatus.scheduled', 'Planifié'), color: 'blue' },
  published: { label: t('travel.tripStatus.published', 'Publié'), color: 'green' },
  cancelled: { label: t('travel.tripStatus.cancelled', 'Annulé'), color: 'red' }
}

const ticketStatusMap = {
  issued: { label: t('travel.ticketStatus.issued', 'Émis'), color: 'green' },
  checked_in: { label: t('travel.ticketStatus.checked_in', 'Enregistré'), color: 'blue' },
  void: { label: t('travel.ticketStatus.void', 'Annulé'), color: 'red' }
}

const manifestColumns = computed(() => [
  { key: 'seat', label: t('travel.checkin.seat', 'Siège'), sortable: true },
  { key: 'full_name', label: t('travel.checkin.passenger', 'Passager'), sortable: true },
  { key: 'age_category', label: t('travel.checkin.age', 'Âge'), sortable: true },
  { key: 'class_label', label: t('travel.checkin.class', 'Classe') },
  { key: 'ticket_number', label: t('travel.checkin.ticket', 'Billet'), sortable: true },
  { key: 'ticket_status', label: t('travel.common.status', 'Statut'), sortable: true },
  { key: 'checkin', label: t('travel.checkin.action', 'Embarquement') }
])

const boardedCount = computed(() =>
  manifest.value.filter((row) => row.ticket_status === 'checked_in').length
)

async function init() {
  await travelStore.checkFlag(true)
  if (gateMode.value) return
  loadError.value = ''
  try {
    const response = await listTravel('trips', { per_page: 200, status: 'published' })
    trips.value = travelList(response)
    const scheduled = await listTravel('trips', { per_page: 200, status: 'scheduled' })
    trips.value = [...travelList(scheduled), ...trips.value]
  } catch (e) {
    loadError.value = e?.response?.data?.message || e?.message || t('travel.common.loadErrorBody', 'Une erreur est survenue.')
  }
}

async function loadManifest() {
  manifest.value = []
  if (!selectedTripId.value) return
  loading.value = true
  error.value = ''
  try {
    // 1. Manifeste officiel (passagers triés par siège, sans PII).
    const manifestResponse = await listTravel(`trips/${selectedTripId.value}/manifest`)
    const passengers = travelList(manifestResponse)

    // 2. Billets par réservation (le manifeste n'expose pas les n° de billets —
    //    on les résout via les réservations du trajet, PII non exposée).
    const bookingsResponse = await listTravel('bookings', { trip_id: selectedTripId.value, per_page: 100 })
    const bookings = travelList(bookingsResponse)
    const ticketByPassenger = new Map()
    for (const booking of bookings) {
      const detailResponse = await getTravel('bookings', booking.id)
      const detail = travelItem(detailResponse)
      for (const ticket of detail.tickets || []) {
        if (ticket.passenger_id) {
          ticketByPassenger.set(Number(ticket.passenger_id), ticket)
        }
      }
    }

    const classById = new Map(travelList(await listTravel('classes', { per_page: 1000 })).map((c) => [c.id, c.label]))

    manifest.value = passengers.map((passenger) => {
      const ticket = ticketByPassenger.get(Number(passenger.id))
      return {
        passenger_id: passenger.id,
        seat: passenger.seat_number,
        full_name: passenger.full_name,
        age_category: ageLabel(passenger.age_category),
        class_label: classById.get(passenger.class_id) || passenger.class_id,
        ticket_id: ticket?.id || null,
        ticket_number: ticket?.ticket_number || '—',
        ticket_status: ticket?.status || null
      }
    }).sort((a, b) => {
      const sa = a.seat ? Number(a.seat) : Infinity
      const sb = b.seat ? Number(b.seat) : Infinity
      return sa - sb
    })
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || t('travel.common.loadErrorBody', 'Une erreur est survenue.')
  } finally {
    loading.value = false
  }
}

function ageLabel(category) {
  const labels = {
    infant: t('travel.ageCategory.infant', 'Bébé'),
    child: t('travel.ageCategory.child', 'Enfant'),
    adult: t('travel.ageCategory.adult', 'Adulte')
  }
  return labels[category] || category
}

async function checkIn(row) {
  if (!row.ticket_id) return
  busyTicketId.value = row.ticket_id
  error.value = ''
  try {
    await travelAction('tickets', row.ticket_id, 'check-in')
    row.ticket_status = 'checked_in'
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || t('travel.common.loadErrorBody', 'Une erreur est survenue.')
  } finally {
    busyTicketId.value = null
  }
}

onMounted(init)
</script>
