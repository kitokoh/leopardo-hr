<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('travel.tickets.title', 'Billets') }}
      </h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        {{ t('travel.tickets.subtitle', 'Consultation, téléchargement PDF et révocation.') }}
      </p>
    </div>

    <TravelGate :mode="gateMode" :message="loadError" @retry="init" />

    <template v-if="!gateMode">
      <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-56 space-y-1">
          <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">
            {{ t('travel.checkin.trip', 'Trajet') }}
          </label>
          <select
            v-model="tripFilter"
            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            @change="reload"
          >
            <option value="">{{ t('travel.common.all', 'Tous') }}</option>
            <option v-for="trip in trips" :key="trip.id" :value="trip.id">{{ trip.code }}</option>
          </select>
        </div>
        <div class="min-w-56 flex-1 space-y-1">
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
        :columns="columns"
        :rows="rows"
        :loading="loading"
        :error="error"
        :search-keys="['ticket_number', 'booking_reference', 'passenger_name']"
        :empty-message="t('travel.common.noData', 'Aucune donnée')"
        key-field="ticket_id"
      >
        <template #cell-seat="{ value }">
          <span class="font-mono text-sm">{{ value || '—' }}</span>
        </template>
        <template #cell-status="{ value }">
          <StatusBadge :status="value" :map="ticketStatusMap" />
        </template>
        <template #row-actions="{ row }">
          <div class="flex justify-end gap-2">
            <button
              v-if="row.status === 'issued'"
              class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
              :disabled="pdfBusyId === row.ticket_id"
              @click="downloadPdf(row)"
            >
              {{ pdfBusyId === row.ticket_id ? '…' : t('travel.tickets.download', 'PDF') }}
            </button>
            <button
              v-if="row.status === 'issued'"
              class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400"
              @click="askRevoke(row)"
            >
              {{ t('travel.tickets.revoke', 'Révoquer') }}
            </button>
          </div>
        </template>
      </DataTable>

      <ConfirmDialog
        :open="revokeOpen"
        :title="t('travel.tickets.revokeTitle', 'Révoquer le billet')"
        :message="revokeTarget ? t('travel.tickets.revokeBody', 'Révoquer le billet {number} ? Le PDF deviendra invalide et le billet passera au statut annulé.').replace('{number}', revokeTarget.ticket_number) : ''"
        :confirm-label="t('travel.tickets.revoke', 'Révoquer')"
        @confirm="confirmRevoke"
        @cancel="closeRevoke"
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
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import { listTravel, getTravel, travelAction, travelGetAction, travelList, travelItem } from '@/services/travel'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const travelStore = useTravelStore()

const trips = ref([])
const rows = ref([])
const tripFilter = ref('')
const referenceQuery = ref('')
const loading = ref(false)
const error = ref('')
const loadError = ref('')
const pdfBusyId = ref(null)
const revokeOpen = ref(false)
const revokeTarget = ref(null)

const gateMode = computed(() => {
  if (!travelStore.isReady) return ''
  if (travelStore.noTenantContext) return 'tenant'
  if (!travelStore.flagActive) return 'feature'
  return ''
})

const ticketStatusMap = {
  issued: { label: t('travel.ticketStatus.issued', 'Émis'), color: 'green' },
  checked_in: { label: t('travel.ticketStatus.checked_in', 'Enregistré'), color: 'blue' },
  void: { label: t('travel.ticketStatus.void', 'Annulé'), color: 'red' }
}

const columns = computed(() => [
  { key: 'ticket_number', label: t('travel.checkin.ticket', 'Billet'), sortable: true },
  { key: 'booking_reference', label: t('travel.bookings.reference', 'Réservation'), sortable: true },
  { key: 'passenger_name', label: t('travel.checkin.passenger', 'Passager'), sortable: true },
  { key: 'seat', label: t('travel.checkin.seat', 'Siège'), sortable: true },
  { key: 'status', label: t('travel.common.status', 'Statut'), sortable: true }
])

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

async function reload() {
  loading.value = true
  error.value = ''
  try {
    const params = { per_page: 50 }
    if (tripFilter.value) params.trip_id = tripFilter.value
    if (referenceQuery.value.trim()) params.reference = referenceQuery.value.trim()
    const bookingsResponse = await listTravel('bookings', params)
    const bookings = travelList(bookingsResponse)

    const tickets = []
    for (const booking of bookings) {
      const detailResponse = await getTravel('bookings', booking.id)
      const detail = travelItem(detailResponse)
      const passengersById = new Map((detail.passengers || []).map((p) => [p.id, p]))
      for (const ticket of detail.tickets || []) {
        const passenger = passengersById.get(ticket.passenger_id)
        tickets.push({
          ticket_id: ticket.id,
          ticket_number: ticket.ticket_number,
          booking_reference: detail.reference,
          passenger_name: passenger?.full_name || '—',
          seat: passenger?.seat_number || null,
          status: ticket.status
        })
      }
    }
    rows.value = tickets.sort((a, b) => String(b.ticket_number).localeCompare(String(a.ticket_number)))
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || t('travel.common.loadErrorBody', 'Une erreur est survenue.')
  } finally {
    loading.value = false
  }
}

async function downloadPdf(row) {
  pdfBusyId.value = row.ticket_id
  error.value = ''
  try {
    const response = await travelGetAction('tickets', row.ticket_id, 'pdf')
    const data = travelItem(response)
    if (data?.pdf_url) {
      window.open(data.pdf_url, '_blank', ['noopener', 'noreferrer'].join(','))
    }
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || t('travel.common.loadErrorBody', 'Une erreur est survenue.')
  } finally {
    pdfBusyId.value = null
  }
}

function closeRevoke() {
  revokeOpen.value = false
  revokeTarget.value = null
}

function askRevoke(row) {
  revokeTarget.value = row
  revokeOpen.value = true
}

async function confirmRevoke() {
  if (!revokeTarget.value) return
  const ticketId = revokeTarget.value.ticket_id
  revokeOpen.value = false
  revokeTarget.value = null
  error.value = ''
  try {
    await travelAction('tickets', ticketId, 'revoke')
    await reload()
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || t('travel.common.loadErrorBody', 'Une erreur est survenue.')
  }
}

onMounted(init)
</script>
