<template>
  <div v-if="mode === 'feature'" class="rounded-2xl glass-card p-8 text-center" role="status">
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/30">
      <LockClosedIcon class="h-6 w-6 text-amber-600 dark:text-amber-400" />
    </div>
    <h2 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">
      {{ t('travel.common.featureDisabledTitle', 'Module Agence de voyage inactif') }}
    </h2>
    <p class="mx-auto mt-2 max-w-md text-sm text-slate-500 dark:text-slate-400">
      {{ t('travel.common.featureDisabledBody', 'Le flag travelagency n\u2019est pas activé pour le tenant connecté. Contactez un administrateur plateforme pour l\u2019activer.') }}
    </p>
  </div>

  <div v-else-if="mode === 'tenant'" class="rounded-2xl glass-card p-8 text-center" role="status">
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-sky-100 dark:bg-sky-900/30">
      <BuildingOfficeIcon class="h-6 w-6 text-sky-600 dark:text-sky-400" />
    </div>
    <h2 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">
      {{ t('travel.common.tenantRequiredTitle', 'Contexte tenant requis') }}
    </h2>
    <p class="mx-auto mt-2 max-w-md text-sm text-slate-500 dark:text-slate-400">
      {{ t('travel.common.tenantRequiredBody', 'Les données de l\u2019agence de voyage sont accessibles avec une session tenant (impersonation ou compte manager).') }}
    </p>
  </div>

  <div v-else-if="mode === 'error'" class="rounded-2xl glass-card p-8 text-center" role="alert">
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-red-100 dark:bg-red-900/30">
      <ExclamationTriangleIcon class="h-6 w-6 text-red-600 dark:text-red-400" />
    </div>
    <h2 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">
      {{ t('travel.common.loadErrorTitle', 'Chargement impossible') }}
    </h2>
    <p class="mx-auto mt-2 max-w-md text-sm text-slate-500 dark:text-slate-400">
      {{ message || t('travel.common.loadErrorBody', 'Une erreur est survenue en interrogeant l\u2019API travel.') }}
    </p>
    <button class="btn-primary mt-6" @click="$emit('retry')">
      {{ t('travel.common.retry', 'Réessayer') }}
    </button>
  </div>

  <slot v-else />
</template>

<script setup>
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { BuildingOfficeIcon, ExclamationTriangleIcon, LockClosedIcon } from '@heroicons/vue/24/outline'

/**
 * États module de la verticale TravelAgency (BC-24 TRAVEL).
 *
 * - feature : flag `travelagency` inactif (403 FEATURE_NOT_ENABLED) — l'écran
 *   est remplacé par un état « module inactif » (TRAVEL-601, 403 géré).
 * - tenant  : 401 (session sans contexte tenant) — état explicite, la session
 *   admin n'est pas détruite (pattern FleetView #4710).
 * - error   : erreur applicative — message + bouton réessayer.
 *
 * Props :
 * - mode: 'feature' | 'tenant' | 'error' | '' (par défaut rend le slot).
 * - message: détail d'erreur optionnel pour le mode 'error'.
 */
defineProps({
  mode: { type: String, default: '' },
  message: { type: String, default: '' }
})

defineEmits(['retry'])

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
</script>
