<template>
  <div class="space-y-4">
    <!-- Filtres -->
    <div class="glass-card flex flex-wrap items-end gap-4 p-4">
      <div>
        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="booking-status">
          {{ $t('travel.bookings.filterStatus', 'Statut') }}
        </label>
        <select id="booking-status" v-model="filters.status" class="form-input mt-1" @change="loadBookings">
          <option value="">{{ $t('travel.bookings.allStatuses', 'Tous') }}</option>
          <option v-for="opt in bookingStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="booking-trip">
          {{ $t('travel.bookings.filterTrip', 'Trajet') }}
        </label>
        <select id="booking-trip" v-model="filters.trip_id" class="form-input mt-1" @change="loadBookings">
          <option value="">{{ $t('travel.bookings.allTrips', 'Tous') }}</option>
          <option v-for="opt in tripOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="booking-from">
          {{ $t('travel.bookings.from', 'Du') }}
        </label>
        <input id="booking-from" v-model="filters.from" type="date" class="form-input mt-1" @change="loadBookings" />
      </div>
      <div>
        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="booking-to">
          {{ $t('travel.bookings.to', 'Au') }}
        </label>
        <input id="booking-to" v-model="filters.to" type="date" class="form-input mt-1" @change="loadBookings" />
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
      :search-keys="['reference', 'trip_id', 'status']"
      :search-placeholder="$t('travel.search.booking', 'Rechercher une réservation…')"
      default-sort="created_at"
      default-sort-dir="desc"
      :caption="$t('travel.bookings.title', 'Réservations')"
    >
      <template #cell-status="{ value }">
        <StatusBadge :status="value" :map="bookingStatusMap" />
      </template>
      <template #cell-payment_status="{ value }">
        <StatusBadge :status="value" :map="paymentStatusMap" />
      </template>
      <template #cell-trip_id="{ row, value }">
        {{ tripLabel(row, value) }}
      </template>
      <template #cell-total_amount_minor="{ row, value }">
        {{ money(value, row.currency) }}
      </template>
      <template #row-actions="{ row }">
        <div class="flex items-center justify-end gap-1">
          <button class="btn-secondary px-2 py-1 text-xs" type="button" @click="openDetail(row)">
            <EyeIcon class="mr-1 h-3.5 w-3.5 inline" />{{ $t('travel.action.view', 'Voir') }}
          </button>
          <button
            v-if="row.status === 'pending'"
            class="btn-primary px-2 py-1 text-xs"
            type="button"
            @click="confirmBooking(row)"
          >
            {{ $t('travel.action.confirm', 'Confirmer') }}
          </button>
          <button
            v-if="canCancelBooking(row)"
            class="rounded-md px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800"
            type="button"
            @click="askReason(row, 'cancel')"
          >
            {{ $t('travel.action.cancel', 'Annuler') }}
          </button>
          <button
            v-if="canRefundBooking(row)"
            class="rounded-md px-2 py-1 text-xs font-medium text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/30"
            type="button"
            @click="askReason(row, 'refund')"
          >
            {{ $t('travel.action.refund', 'Rembourser') }}
          </button>
          <button
            v-if="canIssueTicket(row)"
            class="rounded-md px-2 py-1 text-xs font-medium text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/30"
            type="button"
            @click="issueTickets(row)"
          >
            {{ $t('travel.action.issueTicket', 'Billet') }}
          </button>
        </div>
      </template>
    </DataTable>

    <!-- Détail réservation -->
    <TravelModal :open="detailOpen" :title="detailTitle" wide @close="closedetailOpen">
      <div v-if="detailLoading" class="py-10 text-center text-sm text-slate-500">
        {{ $t('travel.loading', 'Chargement…') }}
      </div>
      <div v-else-if="detail" class="space-y-6">
        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
          <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $t('travel.field.reference', 'Référence') }}</dt>
            <dd class="mt-0.5 text-sm font-semibold text-slate-900 dark:text-white">{{ detail.reference }}</dd>
          </div>
          <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $t('travel.field.trip', 'Trajet') }}</dt>
            <dd class="mt-0.5 text-sm text-slate-700 dark:text-slate-300">{{ tripLabel(detail, detail.trip_id) }}</dd>
          </div>
          <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $t('travel.field.status', 'Statut') }}</dt>
            <dd class="mt-0.5"><StatusBadge :status="detail.status" :map="bookingStatusMap" /></dd>
          </div>
          <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $t('travel.field.paymentStatus', 'Paiement') }}</dt>
            <dd class="mt-0.5"><StatusBadge :status="detail.payment_status" :map="paymentStatusMap" /></dd>
          </div>
          <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $t('travel.field.passengerCount', 'Passagers') }}</dt>
            <dd class="mt-0.5 text-sm text-slate-700 dark:text-slate-300">{{ detail.passenger_count }}</dd>
          </div>
          <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $t('travel.field.totalAmount', 'Montant') }}</dt>
            <dd class="mt-0.5 text-sm font-semibold text-slate-900 dark:text-white">{{ money(detail.total_amount_minor, detail.currency) }}</dd>
          </div>
        </dl>

        <div>
          <h4 class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ $t('travel.bookings.passengers', 'Passagers') }}</h4>
          <div class="mt-2 overflow-x-auto rounded-lg border border-slate-200/50 dark:border-slate-800/50">
            <table class="min-w-full divide-y divide-slate-200/50 text-sm dark:divide-slate-800/50">
              <thead class="bg-slate-50/50 dark:bg-slate-800/50">
                <tr>
                  <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ $t('travel.field.fullName', 'Nom complet') }}</th>
                  <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ $t('travel.field.ageCategory', 'Catégorie') }}</th>
                  <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ $t('travel.field.class', 'Classe') }}</th>
                  <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ $t('travel.field.seat', 'Siège') }}</th>
                  <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ $t('travel.field.unitPrice', 'Prix unitaire') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="pax in detail.passengers || []" :key="pax.id">
                  <td class="px-4 py-2 text-slate-700 dark:text-slate-300">{{ pax.full_name }}</td>
                  <td class="px-4 py-2 text-slate-700 dark:text-slate-300">{{ pax.age_category }}</td>
                  <td class="px-4 py-2 text-slate-700 dark:text-slate-300">{{ className(pax.class_id) }}</td>
                  <td class="px-4 py-2 text-slate-700 dark:text-slate-300">{{ pax.seat_number ?? '-' }}</td>
                  <td class="px-4 py-2 text-slate-700 dark:text-slate-300">{{ money(pax.unit_price_minor, detail.currency) }}</td>
                </tr>
                <tr v-if="!(detail.passengers || []).length">
                  <td colspan="5" class="px-4 py-4 text-center text-xs text-slate-400">{{ $t('travel.table.emptyNested', 'Aucun élément.') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="(detail.tickets || []).length">
          <h4 class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ $t('travel.bookings.tickets', 'Billets') }}</h4>
          <div class="mt-2 overflow-x-auto rounded-lg border border-slate-200/50 dark:border-slate-800/50">
            <table class="min-w-full divide-y divide-slate-200/50 text-sm dark:divide-slate-800/50">
              <thead class="bg-slate-50/50 dark:bg-slate-800/50">
                <tr>
                  <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ $t('travel.field.ticketNumber', 'N° billet') }}</th>
                  <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ $t('travel.field.passenger', 'Passager') }}</th>
                  <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ $t('travel.field.status', 'Statut') }}</th>
                  <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ $t('travel.field.issuedAt', 'Émis le') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="ticket in detail.tickets" :key="ticket.id">
                  <td class="px-4 py-2 text-slate-700 dark:text-slate-300">{{ ticket.ticket_number }}</td>
                  <td class="px-4 py-2 text-slate-700 dark:text-slate-300">{{ ticket.passenger_name || '-' }}</td>
                  <td class="px-4 py-2"><StatusBadge :status="ticket.status" :map="ticketStatusMap" /></td>
                  <td class="px-4 py-2 text-slate-700 dark:text-slate-300">{{ ticket.issued_at || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="(detail.payments || []).length">
          <h4 class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ $t('travel.bookings.payments', 'Paiements') }}</h4>
          <div class="mt-2 overflow-x-auto rounded-lg border border-slate-200/50 dark:border-slate-800/50">
            <table class="min-w-full divide-y divide-slate-200/50 text-sm dark:divide-slate-800/50">
              <thead class="bg-slate-50/50 dark:bg-slate-800/50">
                <tr>
                  <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ $t('travel.field.paymentRef', 'Référence') }}</th>
                  <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ $t('travel.field.provider', 'Prestataire') }}</th>
                  <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ $t('travel.field.amount', 'Montant') }}</th>
                  <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ $t('travel.field.status', 'Statut') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="pay in detail.payments" :key="pay.id">
                  <td class="px-4 py-2 text-slate-700 dark:text-slate-300">{{ pay.reference }}</td>
                  <td class="px-4 py-2 text-slate-700 dark:text-slate-300">{{ pay.provider_code }}</td>
                  <td class="px-4 py-2 text-slate-700 dark:text-slate-300">{{ money(pay.amount_minor, pay.currency) }}</td>
                  <td class="px-4 py-2"><StatusBadge :status="pay.status" :map="paymentStatusMap" /></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </TravelModal>

    <!-- Motif obligatoire (annulation / remboursement) -->
    <TravelModal
      :open="reasonOpen"
      :title="reasonAction === 'cancel' ? $t('travel.bookings.cancelReasonTitle', 'Motif d\u2019annulation') : $t('travel.bookings.refundReasonTitle', 'Motif de remboursement')"
      @close="closereasonOpen"
    >
      <form @submit.prevent="submitReason">
        <FormField
          id="booking-reason"
          :label="$t('travel.bookings.reason', 'Motif')"
          :error="reasonError"
          required
        >
          <template #default="{ ariaInvalid, describedBy }">
            <textarea
              v-model="reason"
              class="form-input"
              rows="3"
              :aria-invalid="ariaInvalid"
              :aria-describedby="describedBy"
              required
            ></textarea>
          </template>
        </FormField>
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="reasonOpen = false">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button type="submit" class="btn-primary" :disabled="reasonSaving">
            {{ reasonSaving ? $t('common.busy', 'En cours…') : $t('travel.action.confirm', 'Confirmer') }}
          </button>
        </div>
      </form>
    </TravelModal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { EyeIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
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
const classes = ref([])
const loading = ref(false)
const listError = ref('')
const filters = ref({ status: '', trip_id: '', from: '', to: '' })

const detailOpen = ref(false)
const detailLoading = ref(false)
const detail = ref(null)

const reasonOpen = ref(false)
const reasonAction = ref('cancel')
const reason = ref('')
const reasonError = ref('')
const reasonSaving = ref(false)
const reasonTarget = ref(null)

/* ── lookups ────────────────────────────────────────────────── */
const tripOptions = computed(() => trips.value.map((tr) => ({ value: tr.id, label: `${tr.code} — ${tr.departure_date}` })))

const bookingStatusOptions = computed(() => [
  { value: 'pending', label: t('travel.bookingStatus.pending', 'En attente') },
  { value: 'confirmed', label: t('travel.bookingStatus.confirmed', 'Confirmée') },
  { value: 'cancelled', label: t('travel.bookingStatus.cancelled', 'Annulée') },
  { value: 'refunded', label: t('travel.bookingStatus.refunded', 'Remboursée') }
])

const bookingStatusMap = {
  pending: { labelKey: 'travel.bookingStatus.pending', color: 'yellow' },
  confirmed: { labelKey: 'travel.bookingStatus.confirmed', color: 'green' },
  cancelled: { labelKey: 'travel.bookingStatus.cancelled', color: 'gray' },
  refunded: { labelKey: 'travel.bookingStatus.refunded', color: 'blue' },
  expired: { labelKey: 'travel.bookingStatus.expired', color: 'gray' }
}

const paymentStatusMap = {
  pending: { labelKey: 'travel.paymentStatus.pending', color: 'yellow' },
  paid: { labelKey: 'travel.paymentStatus.paid', color: 'green' },
  failed: { labelKey: 'travel.paymentStatus.failed', color: 'red' },
  refunded: { labelKey: 'travel.paymentStatus.refunded', color: 'blue' }
}

const ticketStatusMap = {
  issued: { labelKey: 'travel.ticketStatus.issued', color: 'green' },
  checked_in: { labelKey: 'travel.ticketStatus.checkedIn', color: 'blue' },
  revoked: { labelKey: 'travel.ticketStatus.revoked', color: 'gray' }
}

const columns = computed(() => [
  { key: 'reference', label: t('travel.field.reference', 'Référence'), sortable: true },
  { key: 'trip_id', label: t('travel.field.trip', 'Trajet'), sortable: true },
  { key: 'passenger_count', label: t('travel.field.passengerCount', 'Passagers'), sortable: true },
  { key: 'total_amount_minor', label: t('travel.field.totalAmount', 'Montant'), sortable: true, type: 'money' },
  { key: 'status', label: t('travel.field.status', 'Statut'), sortable: true },
  { key: 'payment_status', label: t('travel.field.paymentStatus', 'Paiement'), sortable: true },
  { key: 'created_at', label: t('travel.field.createdAt', 'Créée le'), sortable: true }
])

const detailTitle = computed(() =>
  detail.value ? `${t('travel.bookings.detailTitle', 'Réservation')} ${detail.value.reference}` : ''
)

function tripLabel(row, value) {
  const trip = trips.value.find((tr) => String(tr.id) === String(value))
  if (!trip) return value
  return `${trip.code} — ${trip.departure_date} ${trip.departure_time || ''}`
}

function className(classId) {
  const cls = classes.value.find((c) => String(c.id) === String(classId))
  return cls ? cls.label : (classId ?? '-')
}

function money(minor, currency) {
  if (minor === null || minor === undefined) return '-'
  try {
    return new Intl.NumberFormat(localeStore.current, { style: 'currency', currency: currency || 'XAF' }).format(Number(minor) / 100)
  } catch {
    return `${Number(minor) / 100} ${currency || 'XAF'}`
  }
}

/* ── chargement ─────────────────────────────────────────────── */
async function loadLookups() {
  try {
    const [trRes, clRes] = await Promise.all([
      api.get('/travel/trips', { params: { per_page: 100 }, _skipAuthRedirect: true }),
      api.get('/travel/classes', { params: { per_page: 100 }, _skipAuthRedirect: true })
    ])
    trips.value = trRes.data?.data || []
    classes.value = clRes.data?.data || []
  } catch {
    trips.value = []
    classes.value = []
  }
}

async function loadBookings() {
  loading.value = true
  listError.value = ''
  try {
    const params = { per_page: 100 }
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.trip_id) params.trip_id = filters.value.trip_id
    if (filters.value.from) params.from = filters.value.from
    if (filters.value.to) params.to = filters.value.to
    const res = await api.get('/travel/bookings', { params, _skipAuthRedirect: true })
    bookings.value = res.data?.data || []
  } catch (err) {
    listError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.value = { status: '', trip_id: '', from: '', to: '' }
  loadBookings()
}

/* ── détail ─────────────────────────────────────────────────── */
async function openDetail(row) {
  detailOpen.value = true
  detailLoading.value = true
  detail.value = null
  try {
    const res = await api.get(`/travel/bookings/${row.id}`, { _skipAuthRedirect: true })
    detail.value = res.data?.data || row
  } catch (err) {
    toast.error(err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.'))
    detailOpen.value = false
  } finally {
    detailLoading.value = false
  }
}

/* ── actions ────────────────────────────────────────────────── */
function canCancelBooking(row) {
  return ['pending', 'confirmed'].includes(row.status)
}

function canRefundBooking(row) {
  return row.status === 'confirmed'
}

function canIssueTicket(row) {
  return row.status === 'confirmed'
}

function closeDetail() {
  detailOpen.value = false
}

function closeReason() {
  reasonOpen.value = false
}

async function confirmBooking(row) {
  try {
    await api.post(`/travel/bookings/${row.id}/confirm`, {}, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.confirmed', 'Réservation confirmée.'))
    await loadBookings()
  } catch (err) {
    toast.error(err.response?.data?.message || t('travel.error.actionFailed', "L'action a échoué."))
  }
}

function askReason(row, action) {
  reasonAction.value = action
  reasonTarget.value = row
  reason.value = ''
  reasonError.value = ''
  reasonOpen.value = true
}

async function submitReason() {
  if (!reason.value.trim()) {
    reasonError.value = t('travel.bookings.reasonRequired', 'Le motif est obligatoire.')
    return
  }
  reasonSaving.value = true
  try {
    const payload = { reason: reason.value.trim() }
    const endpoint = reasonAction.value === 'cancel' ? 'cancel' : 'refund'
    await api.post(`/travel/bookings/${reasonTarget.value.id}/${endpoint}`, payload, { _skipAuthRedirect: true })
    toast.success(
      reasonAction.value === 'cancel'
        ? t('travel.toast.cancelled', 'Réservation annulée.')
        : t('travel.toast.refunded', 'Réservation remboursée.')
    )
    reasonOpen.value = false
    await loadBookings()
  } catch (err) {
    toast.error(err.response?.data?.message || t('travel.error.actionFailed', "L'action a échoué."))
  } finally {
    reasonSaving.value = false
  }
}

async function issueTickets(row) {
  try {
    await api.post(`/travel/bookings/${row.id}/issue-ticket`, {}, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.ticketsIssued', 'Billets émis.'))
    await loadBookings()
  } catch (err) {
    toast.error(err.response?.data?.message || t('travel.error.actionFailed', "L'action a échoué."))
  }
}

onMounted(() => {
  loadLookups()
  loadBookings()
})
</script>
