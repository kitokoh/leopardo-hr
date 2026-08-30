<template>
  <div class="space-y-4">
    <div class="glass-card flex flex-wrap items-end gap-4 p-4">
      <div class="min-w-64 flex-1">
        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="checkin-trip">
          {{ $t('travel.checkin.selectTrip', 'Sélectionner un trajet') }}
        </label>
        <select id="checkin-trip" v-model="selectedTripId" class="form-input mt-1" @change="loadManifest">
          <option value="">{{ $t('travel.checkin.chooseTrip', '— Choisir un trajet —') }}</option>
          <option v-for="trip in trips" :key="trip.id" :value="trip.id">
            {{ trip.code }} — {{ trip.departure_date }} {{ trip.departure_time || '' }}
          </option>
        </select>
      </div>
      <button class="btn-secondary" type="button" :disabled="!selectedTripId || manifestLoading" @click="loadManifest">
        {{ $t('travel.checkin.refresh', 'Actualiser') }}
      </button>
    </div>

    <div
      v-if="manifestError"
      class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
      role="alert"
    >
      {{ manifestError }}
    </div>

    <template v-if="manifest !== null">
      <div class="glass-card flex flex-wrap items-center gap-6 p-4">
        <div>
          <span class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $t('travel.checkin.boarded', 'Embarqués') }}</span>
          <p class="text-2xl font-black text-emerald-600">
            {{ boardedCount }}<span class="text-base font-semibold text-slate-400"> / {{ manifest.length }}</span>
          </p>
        </div>
        <div>
          <span class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $t('travel.checkin.pending', 'En attente') }}</span>
          <p class="text-2xl font-black text-amber-500">{{ manifest.length - boardedCount }}</p>
        </div>
        <div class="ml-auto min-w-56">
          <label class="block text-xs font-bold uppercase tracking-wide text-slate-400" for="checkin-search">
            {{ $t('travel.checkin.searchManifest', 'Rechercher passager / billet') }}
          </label>
          <input
            id="checkin-search"
            v-model="manifestSearch"
            type="search"
            class="form-input mt-1"
            :placeholder="$t('travel.checkin.searchManifestPlaceholder', 'Nom ou n° de billet…')"
          />
        </div>
      </div>

      <div class="overflow-x-auto rounded-xl border border-slate-200/50 dark:border-slate-800/50">
        <table class="min-w-full divide-y divide-slate-200/50 text-sm dark:divide-slate-800/50">
          <thead class="bg-slate-50/50 dark:bg-slate-800/50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ $t('travel.field.seat', 'Siège') }}</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ $t('travel.field.passenger', 'Passager') }}</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ $t('travel.field.ticketNumber', 'N° billet') }}</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ $t('travel.field.status', 'Statut') }}</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{{ $t('travel.table.actions', 'Actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr v-for="item in filteredManifest" :key="manifestKey(item)" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/70">
              <td class="px-4 py-2.5 font-semibold text-slate-700 dark:text-slate-300">{{ item.seat_number ?? '-' }}</td>
              <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300">{{ item.passenger_name || item.full_name || '-' }}</td>
              <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300">{{ item.ticket_number || item.ticket?.ticket_number || '-' }}</td>
              <td class="px-4 py-2.5">
                <StatusBadge
                  :status="item.status || item.ticket?.status || 'unknown'"
                  :map="manifestStatusMap"
                />
              </td>
              <td class="whitespace-nowrap px-4 py-2.5 text-right">
                <button
                  v-if="!isCheckedIn(item)"
                  class="btn-primary px-3 py-1.5 text-xs"
                  type="button"
                  :disabled="checkingIn === ticketId(item)"
                  @click="checkIn(item)"
                >
                  {{ checkingIn === ticketId(item) ? $t('common.busy', 'En cours…') : $t('travel.checkin.checkIn', 'Embarquer') }}
                </button>
                <span v-else class="text-xs font-semibold text-emerald-600">
                  {{ $t('travel.checkin.checkedIn', 'Embarqué ✓') }}
                </span>
              </td>
            </tr>
            <tr v-if="filteredManifest.length === 0 && !manifestLoading">
              <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">
                {{ $t('travel.checkin.emptyManifest', 'Aucun passager sur ce trajet.') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const toast = useToast()

const trips = ref([])
const selectedTripId = ref('')
const manifest = ref(null)
const manifestLoading = ref(false)
const manifestError = ref('')
const manifestSearch = ref('')
const checkingIn = ref(null)

const manifestStatusMap = {
  unknown: { labelKey: 'travel.ticketStatus.unknown', color: 'gray' },
  issued: { labelKey: 'travel.ticketStatus.issued', color: 'green' },
  checked_in: { labelKey: 'travel.ticketStatus.checkedIn', color: 'blue' },
  revoked: { labelKey: 'travel.ticketStatus.revoked', color: 'gray' }
}

const boardedCount = computed(() =>
  (manifest.value || []).filter((item) => isCheckedIn(item)).length
)

const filteredManifest = computed(() => {
  if (!manifest.value) return []
  const q = manifestSearch.value.trim().toLowerCase()
  if (!q) return manifest.value
  return manifest.value.filter((item) => {
    const passenger = String(item.passenger_name || item.full_name || '').toLowerCase()
    const ticket = String(item.ticket_number || item.ticket?.ticket_number || '').toLowerCase()
    return passenger.includes(q) || ticket.includes(q)
  })
})

function isCheckedIn(item) {
  return item.status === 'checked_in' || item.ticket?.status === 'checked_in' || Boolean(item.checked_in_at)
}

function ticketId(item) {
  return item.ticket_id ?? item.ticket?.id ?? null
}

function manifestKey(item) {
  return ticketId(item) ?? item.seat_number ?? `${item.passenger_name || ''}-${Math.random()}`
}

async function loadTrips() {
  try {
    const res = await api.get('/travel/trips', { params: { per_page: 100 }, _skipAuthRedirect: true })
    trips.value = res.data?.data || []
  } catch {
    trips.value = []
  }
}

async function loadManifest() {
  if (!selectedTripId.value) return
  manifestLoading.value = true
  manifestError.value = ''
  manifest.value = null
  try {
    const res = await api.get(`/travel/trips/${selectedTripId.value}/manifest`, { _skipAuthRedirect: true })
    manifest.value = res.data?.data || res.data || []
  } catch (err) {
    manifestError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    manifestLoading.value = false
  }
}

async function checkIn(item) {
  const ticket = ticketId(item)
  if (ticket === null) {
    toast.error(t('travel.checkin.noTicket', 'Aucun billet associé à ce passager.'))
    return
  }
  checkingIn.value = ticket
  try {
    await api.post(`/travel/tickets/${ticket}/check-in`, {}, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.checkedIn', 'Passager embarqué.'))
    await loadManifest()
  } catch (err) {
    toast.error(err.response?.data?.message || t('travel.error.actionFailed', "L'action a échoué."))
  } finally {
    checkingIn.value = null
  }
}

onMounted(loadTrips)
</script>
