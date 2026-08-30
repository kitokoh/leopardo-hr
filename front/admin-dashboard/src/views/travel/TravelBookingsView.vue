<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('travel.bookings.title', 'Réservations') }}
      </h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        {{ t('travel.bookings.subtitle', 'Ventes au guichet, confirmation, annulation, remboursement et billets.') }}
      </p>
    </div>

    <TravelGate :mode="gateMode" :message="loadError" @retry="init" />

    <template v-if="!gateMode">
      <div class="flex flex-wrap items-end gap-2">
        <div class="space-y-1">
          <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">
            {{ t('travel.common.status', 'Statut') }}
          </label>
          <select
            v-model="statusFilter"
            class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            @change="reload"
          >
            <option value="">{{ t('travel.common.all', 'Tous') }}</option>
            <option v-for="s in bookingStatusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>
        <div class="space-y-1">
          <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">
            {{ t('travel.network.route', 'Trajet') }}
          </label>
          <select
            v-model="tripFilter"
            class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            @change="reload"
          >
            <option value="">{{ t('travel.common.all', 'Tous') }}</option>
            <option v-for="trip in trips" :key="trip.id" :value="trip.id">{{ trip.code }}</option>
          </select>
        </div>
        <div class="flex-1 space-y-1">
          <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">
            {{ t('travel.bookings.reference', 'Référence') }}
          </label>
          <input
            v-model="referenceQuery"
            type="search"
            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            :placeholder="t('travel.bookings.referencePlaceholder', 'Ex. GV-2026-0001…')"
            @keyup.enter="reload"
          />
        </div>
        <button class="btn-secondary" @click="reload">
          {{ t('travel.common.search', 'Rechercher') }}
        </button>
      </div>

      <DataTable
        :columns="bookingColumns"
        :rows="bookings"
        :loading="loading"
        :error="error"
        :search-keys="['reference', 'trip_code']"
        :empty-message="t('travel.common.noData', 'Aucune donnée')"
        key-field="id"
      >
        <template #cell-status="{ value }">
          <StatusBadge :status="value" :map="bookingStatusMap" />
        </template>
        <template #cell-payment_status="{ value }">
          <StatusBadge :status="value" :map="paymentStatusMap" />
        </template>
        <template #cell-total_amount_minor="{ value, row }">
          {{ formatMinor(value, row.currency) }}
        </template>
        <template #row-actions="{ row }">
          <div class="flex flex-wrap justify-end gap-2">
            <button class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400" @click="openDetail(row)">
              {{ t('travel.bookings.detail', 'Détail') }}
            </button>
            <button
              v-if="row.status === 'pending'"
              class="text-sm font-medium text-emerald-600 hover:text-emerald-800 dark:text-emerald-400"
              @click="confirmBooking(row)"
            >
              {{ t('travel.bookings.confirm', 'Confirmer') }}
            </button>
            <button
              v-if="isActiveBookingStatus(row.status)"
              class="text-sm font-medium text-amber-600 hover:text-amber-800 dark:text-amber-400"
              @click="openCancel(row)"
            >
              {{ t('travel.bookings.cancel', 'Annuler') }}
            </button>
            <button
              v-if="row.status === 'confirmed'"
              class="text-sm font-medium text-purple-600 hover:text-purple-800 dark:text-purple-400"
              @click="openRefund(row)"
            >
              {{ t('travel.bookings.refund', 'Rembourser') }}
            </button>
            <button
              v-if="row.status === 'confirmed'"
              class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
              @click="issueTickets(row)"
            >
              {{ t('travel.bookings.issueTickets', 'Émettre billet') }}
            </button>
          </div>
        </template>
      </DataTable>

      <!-- Détail réservation -->
      <div v-if="detail" class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4" role="dialog" aria-modal="true">
        <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl glass-card p-6 shadow-premium">
          <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">
              {{ t('travel.bookings.detailTitle', 'Réservation') }} {{ detail.reference }}
            </h3>
            <button class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800" @click="closeDetail">
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>

          <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
            <div class="flex justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800/50">
              <dt class="text-slate-500 dark:text-slate-400">{{ t('travel.common.status', 'Statut') }}</dt>
              <dd><StatusBadge :status="detail.status" :map="bookingStatusMap" /></dd>
            </div>
            <div class="flex justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800/50">
              <dt class="text-slate-500 dark:text-slate-400">{{ t('travel.bookings.paymentStatus', 'Paiement') }}</dt>
              <dd><StatusBadge :status="detail.payment_status" :map="paymentStatusMap" /></dd>
            </div>
            <div class="flex justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800/50">
              <dt class="text-slate-500 dark:text-slate-400">{{ t('travel.bookings.trip', 'Trajet') }}</dt>
              <dd class="text-right">{{ detail.trip_id }}</dd>
            </div>
            <div class="flex justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800/50">
              <dt class="text-slate-500 dark:text-slate-400">{{ t('travel.bookings.source', 'Source') }}</dt>
              <dd>{{ sourceLabel(detail.booking_source) }}</dd>
            </div>
            <div class="flex justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800/50">
              <dt class="text-slate-500 dark:text-slate-400">{{ t('travel.bookings.passengerCount', 'Passagers') }}</dt>
              <dd>{{ detail.passenger_count }}</dd>
            </div>
            <div class="flex justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800/50">
              <dt class="text-slate-500 dark:text-slate-400">{{ t('travel.bookings.total', 'Total') }}</dt>
              <dd class="font-semibold">{{ formatMinor(detail.total_amount_minor, detail.currency) }}</dd>
            </div>
          </dl>

          <h4 class="mt-5 text-sm font-semibold text-slate-900 dark:text-white">
            {{ t('travel.bookings.passengers', 'Passagers') }}
          </h4>
          <div v-if="(detail.passengers || []).length === 0" class="mt-2 text-sm text-slate-500">
            {{ t('travel.common.noData', 'Aucune donnée') }}
          </div>
          <div v-else class="mt-2 space-y-2">
            <div
              v-for="passenger in detail.passengers"
              :key="passenger.id"
              class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/50"
            >
              <span class="font-medium text-slate-800 dark:text-slate-200">{{ passenger.full_name }}</span>
              <span class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                <span>{{ ageLabel(passenger.age_category) }}</span>
                <span v-if="passenger.seat_number">{{ t('travel.bookings.seat', 'Siège') }} {{ passenger.seat_number }}</span>
                <span>{{ formatMinor(passenger.unit_price_minor, detail.currency) }}</span>
              </span>
            </div>
          </div>

          <h4 class="mt-5 text-sm font-semibold text-slate-900 dark:text-white">
            {{ t('travel.tickets.title', 'Billets') }}
          </h4>
          <div v-if="(detail.tickets || []).length === 0" class="mt-2 text-sm text-slate-500">
            {{ t('travel.bookings.noTickets', 'Aucun billet émis') }}
          </div>
          <div v-else class="mt-2 space-y-2">
            <div
              v-for="ticket in detail.tickets"
              :key="ticket.id"
              class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/50"
            >
              <span class="font-medium text-slate-800 dark:text-slate-200">{{ ticket.ticket_number }}</span>
              <StatusBadge :status="ticket.status" :map="ticketStatusMap" />
            </div>
          </div>

          <div class="mt-6 flex justify-end gap-2">
            <button class="btn-secondary" @click="closeDetail">
              {{ t('travel.common.close', 'Fermer') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Modale motif (annulation / remboursement) -->
      <TravelFormModal
        :open="reasonModalOpen"
        :title="reasonTitle"
        :fields="reasonFields"
        :values="{}"
        :busy="saving"
        :error="formError"
        @save="submitReason"
        @cancel="closeReasonModal"
      />

      <ConfirmDialog
        :open="confirmOpen"
        :title="confirmTitle"
        :message="confirmMessage"
        :confirm-label="confirmLabel"
        @confirm="runConfirm"
        @cancel="closeConfirm"
      />
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { useTravelStore } from '@/stores/travel'
import TravelGate from '@/components/travel/TravelGate.vue'
import TravelFormModal from '@/components/travel/TravelFormModal.vue'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import {
  listTravel,
  getTravel,
  travelAction,
  travelList,
  travelItem,
  formatMinor
} from '@/services/travel'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const travelStore = useTravelStore()

const bookings = ref([])
const trips = ref([])
const loading = ref(false)
const error = ref('')
const loadError = ref('')

const statusFilter = ref('')
const tripFilter = ref('')
const referenceQuery = ref('')

const detail = ref(null)
const reasonModalOpen = ref(false)
const reasonMode = ref('cancel') // 'cancel' | 'refund'
const reasonTarget = ref(null)
const saving = ref(false)
const formError = ref('')

const confirmOpen = ref(false)
const confirmAction = ref(null)
const confirmTitle = ref('')
const confirmMessage = ref('')
const confirmLabel = ref('')

const gateMode = computed(() => {
  if (!travelStore.isReady) return ''
  if (travelStore.noTenantContext) return 'tenant'
  if (!travelStore.flagActive) return 'feature'
  return ''
})

const bookingStatusMap = {
  pending: { label: t('travel.bookingStatus.pending', 'En attente'), color: 'yellow' },
  confirmed: { label: t('travel.bookingStatus.confirmed', 'Confirmée'), color: 'green' },
  cancelled: { label: t('travel.bookingStatus.cancelled', 'Annulée'), color: 'red' },
  refunded: { label: t('travel.bookingStatus.refunded', 'Remboursée'), color: 'purple' },
  completed: { label: t('travel.bookingStatus.completed', 'Terminée'), color: 'blue' }
}

const paymentStatusMap = {
  pending: { label: t('travel.paymentStatus.pending', 'En attente'), color: 'yellow' },
  confirmed: { label: t('travel.paymentStatus.confirmed', 'Confirmé'), color: 'green' },
  failed: { label: t('travel.paymentStatus.failed', 'Échoué'), color: 'red' },
  refunded: { label: t('travel.paymentStatus.refunded', 'Remboursé'), color: 'purple' }
}

const ticketStatusMap = {
  issued: { label: t('travel.ticketStatus.issued', 'Émis'), color: 'green' },
  checked_in: { label: t('travel.ticketStatus.checked_in', 'Enregistré'), color: 'blue' },
  void: { label: t('travel.ticketStatus.void', 'Annulé'), color: 'red' }
}

const bookingStatusOptions = computed(() =>
  Object.keys(bookingStatusMap).map((value) => ({ value, label: bookingStatusMap[value].label }))
)

const bookingColumns = computed(() => [
  { key: 'reference', label: t('travel.bookings.reference', 'Référence'), sortable: true },
  { key: 'trip_code', label: t('travel.bookings.trip', 'Trajet'), sortable: true },
  { key: 'passenger_count', label: t('travel.bookings.passengerCount', 'Passagers'), sortable: true },
  { key: 'total_amount_minor', label: t('travel.bookings.total', 'Total'), sortable: true },
  { key: 'booking_source', label: t('travel.bookings.source', 'Source'), sortable: true },
  { key: 'payment_status', label: t('travel.bookings.paymentStatus', 'Paiement'), sortable: true },
  { key: 'status', label: t('travel.common.status', 'Statut'), sortable: true },
  { key: 'created_at', label: t('travel.common.createdAt', 'Créé le'), sortable: true }
])

const reasonFields = computed(() => [
  { key: 'reason', label: 'travel.common.reason', type: 'textarea', required: true, max: 500, rows: 3 }
])

const reasonTitle = computed(() =>
  reasonMode.value === 'refund'
    ? t('travel.bookings.refundTitle', 'Rembourser la réservation')
    : t('travel.bookings.cancelTitle', 'Annuler la réservation')
)

function isActiveBookingStatus(status) {
  return ['pending', 'confirmed'].includes(status)
}

function closeDetail() {
  detail.value = null
}

function closeReasonModal() {
  reasonModalOpen.value = false
  reasonTarget.value = null
}

function closeConfirm() {
  confirmOpen.value = false
  confirmAction.value = null
}

function sourceLabel(source) {
  const labels = {
    office: t('travel.bookingSource.office', 'Guichet'),
    phone: t('travel.bookingSource.phone', 'Téléphone'),
    online: t('travel.bookingSource.online', 'En ligne')
  }
  return labels[source] || source
}

function ageLabel(category) {
  const labels = {
    infant: t('travel.ageCategory.infant', 'Bébé'),
    child: t('travel.ageCategory.child', 'Enfant'),
    adult: t('travel.ageCategory.adult', 'Adulte')
  }
  return labels[category] || category
}

async function reload() {
  loading.value = true
  error.value = ''
  try {
    const params = { per_page: 100 }
    if (statusFilter.value) params.status = statusFilter.value
    if (tripFilter.value) params.trip_id = tripFilter.value
    if (referenceQuery.value.trim()) params.reference = referenceQuery.value.trim()
    const response = await listTravel('bookings', params)
    const rows = travelList(response)
    const tripById = new Map(trips.value.map((trip) => [trip.id, trip]))
    bookings.value = rows.map((booking) => ({
      ...booking,
      trip_code: tripById.get(booking.trip_id)?.code ?? String(booking.trip_id)
    }))
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || t('travel.common.loadErrorBody', 'Une erreur est survenue.')
  } finally {
    loading.value = false
  }
}

async function init() {
  await travelStore.checkFlag(true)
  if (gateMode.value) return
  loadError.value = ''
  try {
    const tripsResponse = await listTravel('trips', { per_page: 1000 })
    trips.value = travelList(tripsResponse)
  } catch (e) {
    loadError.value = e?.response?.data?.message || e?.message || ''
  }
  await reload()
}

async function openDetail(row) {
  try {
    const response = await getTravel('bookings', row.id)
    detail.value = travelItem(response)
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || t('travel.common.loadErrorBody', 'Une erreur est survenue.')
  }
}

function runConfirmAction(fn, title, message, label) {
  confirmAction.value = fn
  confirmTitle.value = title
  confirmMessage.value = message
  confirmLabel.value = label
  confirmOpen.value = true
}

function confirmBooking(row) {
  runConfirmAction(
    async () => {
      await travelAction('bookings', row.id, 'confirm')
      await reload()
    },
    t('travel.bookings.confirmTitle', 'Confirmer la réservation'),
    t('travel.bookings.confirmBody', 'Confirmer la réservation {ref} ? Les sièges seront marqués vendus.').replace('{ref}', row.reference),
    t('travel.bookings.confirm', 'Confirmer')
  )
}

function issueTickets(row) {
  runConfirmAction(
    async () => {
      await travelAction('bookings', row.id, 'issue-ticket')
      await reload()
    },
    t('travel.bookings.issueTicketsTitle', 'Émettre les billets'),
    t('travel.bookings.issueTicketsBody', 'Émettre un billet nominatif pour chaque passager de {ref} ?').replace('{ref}', row.reference),
    t('travel.bookings.issueTickets', 'Émettre billet')
  )
}

function openCancel(row) {
  reasonMode.value = 'cancel'
  reasonTarget.value = row
  formError.value = ''
  reasonModalOpen.value = true
}

function openRefund(row) {
  reasonMode.value = 'refund'
  reasonTarget.value = row
  formError.value = ''
  reasonModalOpen.value = true
}

async function submitReason(values) {
  if (!reasonTarget.value) return
  saving.value = true
  formError.value = ''
  try {
    const action = reasonMode.value === 'refund' ? 'refund' : 'cancel'
    await travelAction('bookings', reasonTarget.value.id, action, { reason: values.reason })
    reasonModalOpen.value = false
    reasonTarget.value = null
    await reload()
  } catch (e) {
    formError.value = e?.response?.data?.errors
      ? Object.values(e.response.data.errors).flat()[0]
      : e?.response?.data?.message || e?.message || t('travel.common.loadErrorBody', 'Une erreur est survenue.')
  } finally {
    saving.value = false
  }
}

async function runConfirm() {
  if (!confirmAction.value) return
  confirmOpen.value = false
  try {
    await confirmAction.value()
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || t('travel.common.loadErrorBody', 'Une erreur est survenue.')
  } finally {
    confirmAction.value = null
  }
}

onMounted(init)
</script>
