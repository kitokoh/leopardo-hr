<template>
  <div class="space-y-6">
    <!-- Gate feature flag : 403 = module non activé pour le tenant connecté -->
    <div
      v-if="gateStatus === 'checking'"
      class="flex items-center justify-center py-24"
      role="status"
    >
      <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-brand-600"></div>
    </div>

    <div
      v-else-if="gateStatus === 'disabled'"
      class="mx-auto max-w-xl rounded-2xl border border-amber-200 bg-amber-50 px-8 py-10 text-center dark:border-amber-800 dark:bg-amber-900/20"
      role="alert"
    >
      <ExclamationTriangleIcon class="mx-auto h-12 w-12 text-amber-500" />
      <h2 class="mt-4 text-xl font-bold text-slate-900 dark:text-white">
        {{ $t('travel.gate.disabledTitle', 'Module Agence de voyage non activé') }}
      </h2>
      <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
        {{ $t('travel.gate.disabledBody', "La verticale TravelAgency n'est pas activée pour le tenant connecté. Activez la fonctionnalité « travelagency » pour accéder à ce module.") }}
      </p>
      <button class="btn-secondary mt-6" type="button" @click="checkGate">
        {{ $t('travel.gate.retry', 'Réessayer') }}
      </button>
    </div>

    <div
      v-else-if="gateStatus === 'error'"
      class="mx-auto max-w-xl rounded-2xl border border-red-200 bg-red-50 px-8 py-10 text-center dark:border-red-800 dark:bg-red-900/20"
      role="alert"
    >
      <XCircleIcon class="mx-auto h-12 w-12 text-red-500" />
      <h2 class="mt-4 text-xl font-bold text-slate-900 dark:text-white">
        {{ $t('travel.gate.errorTitle', 'Module temporairement indisponible') }}
      </h2>
      <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ gateError }}</p>
      <button class="btn-secondary mt-6" type="button" @click="checkGate">
        {{ $t('travel.gate.retry', 'Réessayer') }}
      </button>
    </div>

    <template v-else>
      <div>
        <h1 class="text-3xl font-black tracking-tight text-slate-900 dark:text-white">
          {{ $t('travel.section.title', 'Agence de voyage') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400">
          {{ $t('travel.section.subtitle', "Gestion de la verticale TravelAgency : référentiel, réseau, ventes, billetterie et rapports.") }}
        </p>
      </div>

      <div class="flex flex-wrap gap-2" role="tablist" :aria-label="$t('travel.section.title', 'Agence de voyage')">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          role="tab"
          :aria-selected="activeTab === tab.key"
          :class="[
            'rounded-md px-4 py-2 text-sm font-medium transition-colors',
            activeTab === tab.key
              ? 'bg-brand-600 text-white shadow-lg shadow-brand-600/25'
              : 'glass-card text-slate-600 ring-1 ring-slate-300 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-800 hover:bg-slate-50'
          ]"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>

      <KeepAlive>
        <component :is="activeComponent" :key="activeTab" />
      </KeepAlive>
    </template>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { ExclamationTriangleIcon, XCircleIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import TravelReferentielTab from '@/views/travel/TravelReferentielTab.vue'
import TravelRoutesTripsTab from '@/views/travel/TravelRoutesTripsTab.vue'
import TravelBookingsTab from '@/views/travel/TravelBookingsTab.vue'
import TravelCheckInTab from '@/views/travel/TravelCheckInTab.vue'
import TravelTicketsTab from '@/views/travel/TravelTicketsTab.vue'
import TravelReportsTab from '@/views/travel/TravelReportsTab.vue'
import TravelRentalsHotelsTab from '@/views/travel/TravelRentalsHotelsTab.vue'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

/** checking | ready | disabled (403 flag) | error (autre) */
const gateStatus = ref('checking')
const gateError = ref('')

const activeTab = ref('referentiel')

const tabs = [
  { key: 'referentiel', label: t('travel.tab.referentiel', 'Référentiel'), component: TravelReferentielTab },
  { key: 'routes', label: t('travel.tab.routes', 'Routes & Trajets'), component: TravelRoutesTripsTab },
  { key: 'bookings', label: t('travel.tab.bookings', 'Réservations'), component: TravelBookingsTab },
  { key: 'checkin', label: t('travel.tab.checkin', 'Check-in'), component: TravelCheckInTab },
  { key: 'tickets', label: t('travel.tab.tickets', 'Billets'), component: TravelTicketsTab },
  { key: 'reports', label: t('travel.tab.reports', 'Rapports'), component: TravelReportsTab },
  { key: 'rentals', label: t('travel.tab.rentals', 'Locations & Hôtels'), component: TravelRentalsHotelsTab }
]

const activeComponent = computed(() => tabs.find((tab) => tab.key === activeTab.value)?.component)

/**
 * TRAVEL-601 (#6078) : gate du module par feature flag.
 * GET /travel/ping est protégé par le middleware module.travelagency — un 403
 * signifie que le flag « travelagency » est désactivé pour le tenant connecté.
 * _skipAuthRedirect (#4170) : un 401 (super-admin hors contexte tenant) ne
 * doit pas détruire la session admin.
 */
async function checkGate() {
  gateStatus.value = 'checking'
  gateError.value = ''
  try {
    await api.get('/travel/ping', { _skipAuthRedirect: true })
    gateStatus.value = 'ready'
  } catch (err) {
    const status = err.response?.status
    if (status === 403) {
      gateStatus.value = 'disabled'
    } else {
      gateStatus.value = 'error'
      gateError.value = err.response?.data?.message
        || err.response?.data?.localized_message
        || t('travel.gate.errorBody', 'Impossible de joindre le service TravelAgency. Réessayez dans un instant.')
    }
  }
}

checkGate()
</script>
