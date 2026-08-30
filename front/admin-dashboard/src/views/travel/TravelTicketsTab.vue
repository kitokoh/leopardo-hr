<template>
  <div class="space-y-4">
    <div class="glass-card flex flex-wrap items-end gap-4 p-4">
      <div class="min-w-64 flex-1">
        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="ticket-trip">
          {{ $t('travel.tickets.filterTrip', 'Trajet') }}
        </label>
        <select id="ticket-trip" v-model="filters.trip_id" class="form-input mt-1" @change="loadBookings">
          <option value="">{{ $t('travel.bookings.allTrips', 'Tous') }}</option>
          <option v-for="opt in tripOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>
      <button class="btn-secondary" type="button" @click="resetFilters">
        {{ $t('travel.bookings.reset', 'Réinitialiser') }}
      </button>
    </div>

    <DataTable
      :columns="columns"
      :rows="bookings"
      :loading="loading"
      :error="listError"
      :search-keys="['reference', 'trip_id']"
      :search-placeholder="$t('travel.search.booking', 'Rechercher une réservation…')"
      default-sort="created_at"
      default-sort-dir="desc"
      :caption="$t('travel.tickets.title', 'Billetterie')"
    >
      <template #cell-trip_id="{ row, value }">
        {{ tripLabel(row, value) }}
      </template>
      <template #cell-status="{ value }">
        <StatusBadge :status="value" :map="bookingStatusMap" />
      </template>
      <template #row-actions="{ row }">
        <button class="btn-secondary px-2 py-1 text-xs" type="button" @click="openTickets(row)">
          <TicketIcon class="mr-1 h-3.5 w-3.5 inline" />
          {{ $t('travel.tickets.manage', 'Billets') }}
        </button>
      </template>
    </DataTable>

    <!-- Gestion des billets d'une réservation -->
    <TravelModal
      :open="ticketsOpen"
      :title="ticketsTitle"
      wide
      @close="ticketsOpen = false"
    >
      <div v-if="ticketsLoading" class="py-10 text-center text-sm text-slate-500">
        {{ $t('travel.loading', 'Chargement…') }}
      </div>
      <div v-else-if="ticketsError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
        {{ ticketsError }}
      </div>
      <div v-else-if="tickets.length === 0" class="py-10 text-center text-sm text-slate-400">
        {{ $t('travel.tickets.empty', "Aucun billet émis pour cette réservation. Utilisez l'action « Billet » depuis l'onglet Réservations.") }}
      </div>
      <div v-else class="space-y-3">
        <div
          v-for="ticket in tickets"
          :key="ticket.id"
          class="flex flex-wrap items-center gap-4 rounded-lg border border-slate-200/50 p-4 dark:border-slate-800/50"
        >
          <div class="min-w-0 flex-1">
            <p class="text-sm font-bold text-slate-900 dark:text-white">{{ ticket.ticket_number }}</p>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
              {{ $t('travel.field.passenger', 'Passager') }} : {{ ticket.passenger_name || '-' }}
            </p>
            <p v-if="ticket.validation_code" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
              {{ $t('travel.field.validationCode', 'Code de validation') }} : <code class="rounded bg-slate-100 px-1 py-0.5 dark:bg-slate-800">{{ ticket.validation_code }}</code>
            </p>
            <div class="mt-1.5">
              <StatusBadge :status="ticket.status" :map="ticketStatusMap" />
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button
              v-if="['issued', 'checked_in'].includes(ticket.status)"
              class="btn-secondary px-3 py-1.5 text-xs"
              type="button"
              :disabled="downloadingPdf === ticket.id"
              @click="downloadPdf(ticket)"
            >
              <ArrowDownTrayIcon class="mr-1.5 h-3.5 w-3.5 inline" />
              {{ downloadingPdf === ticket.id ? $t('common.busy', 'En cours…') : $t('travel.tickets.downloadPdf', 'PDF') }}
            </button>
            <button
              v-if="['issued', 'checked_in'].includes(ticket.status)"
              class="rounded-md px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30"
              type="button"
              @click="askRevoke(ticket)"
            >
              {{ $t('travel.tickets.revoke', 'Révoquer') }}
            </button>
          </div>
        </div>
      </div>
    </TravelModal>

    <!-- Motif de révocation -->
    <TravelModal
      :open="revokeOpen"
      :title="$t('travel.tickets.revokeTitle', 'Motif de révocation')"
      @close="revokeOpen = false"
    >
      <form @submit.prevent="submitRevoke">
        <FormField id="ticket-revoke-reason" :label="$t('travel.bookings.reason', 'Motif')" :error="revokeError" required>
          <template #default="{ ariaInvalid, describedBy }">
            <textarea
              v-model="revokeReason"
              class="form-input"
              rows="3"
              :aria-invalid="ariaInvalid"
              :aria-describedby="describedBy"
              required
            ></textarea>
          </template>
        </FormField>
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="revokeOpen = false">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button type="submit" class="btn-primary" :disabled="revokeSaving">
            {{ revokeSaving ? $t('common.busy', 'En cours…') : $t('travel.tickets.revoke', 'Révoquer') }}
          </button>
        </div>
      </form>
    </TravelModal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { ArrowDownTrayIcon, TicketIcon } from '@heroicons/vue/24/outline'
import api, { downloadApiFile } from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import FormField from '@/components/common/FormField.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import TravelModal from '@/components/travel/TravelModal.vue'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const toast = useToast()

const bookings = ref([])
const trips = ref([])
const loading = ref(false)
const listError = ref('')
const filters = ref({ trip_id: '' })

const ticketsOpen = ref(false)
const ticketsLoading = ref(false)
const ticketsError = ref('')
const tickets = ref([])
const currentBooking = ref(null)
const downloadingPdf = ref(null)

const revokeOpen = ref(false)
const revokeSaving = ref(false)
const revokeError = ref('')
const revokeReason = ref('')
const revokeTarget = ref(null)

const tripOptions = computed(() => trips.value.map((tr) => ({ value: tr.id, label: `${tr.code} — ${tr.departure_date}` })))

const columns = computed(() => [
  { key: 'reference', label: t('travel.field.reference', 'Référence'), sortable: true },
  { key: 'trip_id', label: t('travel.field.trip', 'Trajet'), sortable: true },
  { key: 'passenger_count', label: t('travel.field.passengerCount', 'Passagers'), sortable: true },
  { key: 'status', label: t('travel.field.status', 'Statut'), sortable: true },
  { key: 'created_at', label: t('travel.field.createdAt', 'Créée le'), sortable: true }
])

const bookingStatusMap = {
  pending: { labelKey: 'travel.bookingStatus.pending', color: 'yellow' },
  confirmed: { labelKey: 'travel.bookingStatus.confirmed', color: 'green' },
  cancelled: { labelKey: 'travel.bookingStatus.cancelled', color: 'gray' },
  refunded: { labelKey: 'travel.bookingStatus.refunded', color: 'blue' }
}

const ticketStatusMap = {
  issued: { labelKey: 'travel.ticketStatus.issued', color: 'green' },
  checked_in: { labelKey: 'travel.ticketStatus.checkedIn', color: 'blue' },
  revoked: { labelKey: 'travel.ticketStatus.revoked', color: 'gray' }
}

const ticketsTitle = computed(() =>
  currentBooking.value
    ? `${t('travel.tickets.manage', 'Billets')} — ${currentBooking.value.reference}`
    : t('travel.tickets.manage', 'Billets')
)

function tripLabel(_row, value) {
  const trip = trips.value.find((tr) => String(tr.id) === String(value))
  return trip ? `${trip.code} — ${trip.departure_date} ${trip.departure_time || ''}` : value
}

async function loadTrips() {
  try {
    const res = await api.get('/travel/trips', { params: { per_page: 100 }, _skipAuthRedirect: true })
    trips.value = res.data?.data || []
  } catch {
    trips.value = []
  }
}

async function loadBookings() {
  loading.value = true
  listError.value = ''
  try {
    const params = { per_page: 100 }
    if (filters.value.trip_id) params.trip_id = filters.value.trip_id
    const res = await api.get('/travel/bookings', { params, _skipAuthRedirect: true })
    bookings.value = res.data?.data || []
  } catch (err) {
    listError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.value = { trip_id: '' }
  loadBookings()
}

async function openTickets(row) {
  currentBooking.value = row
  ticketsOpen.value = true
  ticketsLoading.value = true
  ticketsError.value = ''
  tickets.value = []
  try {
    const res = await api.get(`/travel/bookings/${row.id}`, { _skipAuthRedirect: true })
    const detail = res.data?.data || row
    tickets.value = detail.tickets || []
  } catch (err) {
    ticketsError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    ticketsLoading.value = false
  }
}

async function downloadPdf(ticket) {
  downloadingPdf.value = ticket.id
  try {
    await downloadApiFile(
      `/travel/tickets/${ticket.id}/pdf`,
      `${ticket.ticket_number || 'billet'}.pdf`,
      { _skipAuthRedirect: true }
    )
  } catch (err) {
    toast.error(err.response?.data?.message || t('travel.tickets.pdfError', 'Téléchargement impossible.'))
  } finally {
    downloadingPdf.value = null
  }
}

function askRevoke(ticket) {
  revokeTarget.value = ticket
  revokeReason.value = ''
  revokeError.value = ''
  revokeOpen.value = true
}

async function submitRevoke() {
  if (!revokeReason.value.trim()) {
    revokeError.value = t('travel.bookings.reasonRequired', 'Le motif est obligatoire.')
    return
  }
  revokeSaving.value = true
  try {
    await api.post(`/travel/tickets/${revokeTarget.value.id}/revoke`, { reason: revokeReason.value.trim() }, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.ticketRevoked', 'Billet révoqué.'))
    revokeOpen.value = false
    await openTickets(currentBooking.value)
  } catch (err) {
    toast.error(err.response?.data?.message || t('travel.error.actionFailed', "L'action a échoué."))
  } finally {
    revokeSaving.value = false
  }
}

onMounted(() => {
  loadTrips()
  loadBookings()
})
</script>
