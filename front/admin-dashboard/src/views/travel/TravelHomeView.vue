<template>
  <div class="space-y-6">
    <div class="flex items-start justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
          {{ t('travel.home.title', 'Agence de voyage') }}
        </h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          {{ t('travel.home.subtitle', 'Gestion de la verticale TravelAgency : réseau, ventes, billetterie et pilotage.') }}
        </p>
      </div>
      <span
        v-if="travelStore.flagActive"
        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400"
      >
        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
        {{ t('travel.home.flagActive', 'Module actif') }}
      </span>
    </div>

    <TravelGate
      :mode="gateMode"
      :message="loadError"
      @retry="init"
    />

    <div v-if="!gateMode" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <router-link
        v-for="section in sections"
        :key="section.name"
        :to="section.path"
        class="group rounded-2xl glass-card p-5 transition-all duration-200 hover:shadow-premium"
      >
        <div class="flex items-start justify-between">
          <div
            class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-cyan-600 shadow-lg shadow-brand-500/20"
          >
            <component :is="section.icon" class="h-5 w-5 text-white" />
          </div>
          <ArrowRightIcon class="h-4 w-4 text-slate-300 transition-all group-hover:translate-x-0.5 group-hover:text-brand-500 dark:text-slate-600" />
        </div>
        <h2 class="mt-4 text-sm font-semibold text-slate-900 dark:text-white">
          {{ section.title }}
        </h2>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
          {{ section.description }}
        </p>
      </router-link>
    </div>

    <div v-if="!gateMode" class="rounded-2xl glass-card p-5">
      <h2 class="text-sm font-semibold text-slate-900 dark:text-white">
        {{ t('travel.home.flowTitle', 'Flux métier') }}
      </h2>
      <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
        {{ t('travel.home.flowBody', 'Référentiel & réseau → programmation des trajets → publication → vente au guichet → billetterie & check-in → rapports.') }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { useTravelStore } from '@/stores/travel'
import TravelGate from '@/components/travel/TravelGate.vue'
import {
  ArrowRightIcon,
  BuildingOfficeIcon,
  ClipboardDocumentListIcon,
  MapIcon,
  NewspaperIcon,
  QrCodeIcon,
  TicketIcon,
  ChartBarIcon,
  MegaphoneIcon
} from '@heroicons/vue/24/outline'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const travelStore = useTravelStore()

const loadError = ref('')

const gateMode = computed(() => {
  if (!travelStore.isReady) return ''
  if (travelStore.noTenantContext) return 'tenant'
  if (!travelStore.flagActive) return 'feature'
  return ''
})

const sections = computed(() => [
  {
    name: 'travel-referential',
    path: '/travel/referential',
    icon: BuildingOfficeIcon,
    title: t('travel.referential.title', 'Référentiel'),
    description: t('travel.referential.subtitle', 'Pays, villes, gares, bureaux, compagnies, classes et véhicules.')
  },
  {
    name: 'travel-network',
    path: '/travel/network',
    icon: MapIcon,
    title: t('travel.network.title', 'Routes & trajets'),
    description: t('travel.network.subtitle', 'Lignes, étapes, programmation, tarifs et publication.')
  },
  {
    name: 'travel-bookings',
    path: '/travel/bookings',
    icon: ClipboardDocumentListIcon,
    title: t('travel.bookings.title', 'Réservations'),
    description: t('travel.bookings.subtitle', 'Ventes au guichet, confirmation, annulation, remboursement et billets.')
  },
  {
    name: 'travel-checkin',
    path: '/travel/checkin',
    icon: QrCodeIcon,
    title: t('travel.checkin.title', 'Check-in & manifeste'),
    description: t('travel.checkin.subtitle', 'Embarquement par trajet et compteur de passagers.')
  },
  {
    name: 'travel-tickets',
    path: '/travel/tickets',
    icon: TicketIcon,
    title: t('travel.tickets.title', 'Billets'),
    description: t('travel.tickets.subtitle', 'Consultation, téléchargement PDF et révocation.')
  },
  {
    name: 'travel-reports',
    path: '/travel/reports',
    icon: ChartBarIcon,
    title: t('travel.reports.title', 'Rapports'),
    description: t('travel.reports.subtitle', 'Ventes, occupation, recettes, annulations et exports CSV.')
  },
  {
    name: 'travel-content',
    path: '/travel/content',
    icon: MegaphoneIcon,
    title: t('travel.content.title', 'Contenu & annonces'),
    description: t('travel.content.subtitle', 'Quiz, annonces payantes et sites touristiques.')
  },
  {
    name: 'travel-catalog',
    path: '/travel/catalog',
    icon: NewspaperIcon,
    title: t('travel.catalog.title', 'Locations & hôtels'),
    description: t('travel.catalog.subtitle', 'Véhicules en location, réservations et catalogue hôtelier.')
  }
])

async function init() {
  loadError.value = ''
  await travelStore.checkFlag(true)
}

onMounted(init)
</script>
