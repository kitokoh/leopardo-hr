<template>
  <div class="card p-5">
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
          {{ label }}
        </p>
        <p class="mt-2 text-2xl font-black text-slate-900 dark:text-white">
          <template v-if="value === null || value === undefined">
            <span class="text-slate-400 dark:text-slate-500">{{ unavailableLabel }}</span>
          </template>
          <template v-else>{{ formatted }}</template>
        </p>
      </div>
      <span
        v-if="reliability"
        :class="reliabilityClass"
        class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-black uppercase tracking-tight"
        :title="reliabilityTitle"
      >
        {{ reliabilityLabel }}
      </span>
    </div>

    <p v-if="hint" class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ hint }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

/**
 * Carte de KPI du module Growth & Search Intelligence (GSI-10, #8521).
 *
 * Deux exigences de la spec sont portees par ce composant, pas par les vues :
 *
 *  1. **Un chiffre absent n'est pas un zero.** `value === null` affiche
 *     « indisponible » : le backend renvoie `null` quand aucune source n'est
 *     branchee, et afficher `0` ferait croire a une mesure.
 *  2. **Chaque chiffre porte sa fiabilite.** Le badge `measured` / `declared` /
 *     `unavailable` vient du backend (`reliability`) ; sans lui, un compteur est
 *     une affirmation sans preuve.
 */
const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], default: null },
  reliability: { type: String, default: null },
  hint: { type: String, default: '' },
  format: { type: String, default: 'number' },
  currency: { type: String, default: 'EUR' },
})


const unavailableLabel = computed(() => t('searchIntel.common.unavailable', 'Indisponible'))

const formatted = computed(() => {
  if (props.value === null || props.value === undefined) return ''
  if (props.format === 'currency') {
    const cents = Number(props.value)
    if (!Number.isFinite(cents)) return String(props.value)
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: props.currency,
    }).format(cents / 100)
  }
  if (props.format === 'percent') {
    const n = Number(props.value)
    return Number.isFinite(n) ? `${n}%` : String(props.value)
  }
  const n = Number(props.value)
  return Number.isFinite(n) ? new Intl.NumberFormat().format(n) : String(props.value)
})

const reliabilityLabel = computed(() => {
  const key = `searchIntel.reliability.${props.reliability}`
  const fallback = {
    measured: t('searchIntel.reliability.measured', 'Mesure'),
    declared: t('searchIntel.reliability.declared', 'Declare'),
    unavailable: t('searchIntel.reliability.unavailable', 'Non branche'),
  }
  return t(key, fallback[props.reliability] ?? props.reliability)
})

const reliabilityTitle = computed(() => {
  const titles = {
    measured: t('searchIntel.reliability.measuredHint', 'Compteur direct sur nos tables : exact par construction.'),
    declared: t('searchIntel.reliability.declaredHint', 'Saisi ou valide par un humain : exact sur ce qui a ete saisi.'),
    unavailable: t('searchIntel.reliability.unavailableHint', 'Aucune source branchee : la valeur est inconnue, pas nulle.'),
  }
  return titles[props.reliability] ?? ''
})

const reliabilityClass = computed(() => {
  switch (props.reliability) {
    case 'measured':
      return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400'
    case 'declared':
      return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-400'
    default:
      return 'border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400'
  }
})
</script>
