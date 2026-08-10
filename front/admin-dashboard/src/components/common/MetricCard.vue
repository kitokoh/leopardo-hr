<template>
  <div class="rounded-xl border border-slate-200/50 dark:border-slate-700/50 glass-card p-5 shadow-sm">
    <p class="text-sm font-medium text-slate-500 dark:text-slate-400 truncate">
      {{ title }}
    </p>
    <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
      {{ formattedValue }}
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useLocaleStore } from '@/stores/locale'
import { toIntlLocale } from '@/i18n/index.js'

/**
 * Carte de métrique simple (titre + valeur formatée).
 * Utilisée dans les vues Rapports et Analytics pour afficher
 * des indicateurs synthétiques sans icône ni tendance.
 *
 * Props :
 *   title   — libellé affiché au-dessus de la valeur
 *   value   — valeur numérique (ou chaîne passée telle quelle)
 *   format  — 'percent' | 'currency' | undefined  (formatage de la valeur)
 */
const localeStore = useLocaleStore()

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  value: {
    type: [Number, String],
    default: 0,
  },
  format: {
    type: String,
    default: null,
    validator: (v) => v === null || ['percent', 'currency'].includes(v),
  },
})

const formattedValue = computed(() => {
  const v = props.value

  if (typeof v === 'string') {
    return v
  }

  if (props.format === 'percent') {
    return `${Number(v).toFixed(1)} %`
  }

  if (props.format === 'currency') {
    // Formatage monétaire sans devise fixe — unité définie par le contexte
    return new Intl.NumberFormat(toIntlLocale(localeStore.current), {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(v)
  }

  // Valeur entière ou décimale sans unité
  if (Number.isInteger(v)) {
    return new Intl.NumberFormat(toIntlLocale(localeStore.current)).format(v)
  }
  return Number(v).toFixed(2)
})
</script>
