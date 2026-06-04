<template>
  <div class="stat-card">
    <div class="flex items-center justify-between">
      <p class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
        {{ title }}
      </p>
      <span
        v-if="trend !== null && trend !== undefined"
        class="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-medium"
        :class="trendUp ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
      >
        <svg class="h-3 w-3" :class="{ 'rotate-180': !trendUp }" viewBox="0 0 12 12" fill="currentColor">
          <path d="M6 3l4 5H2z" />
        </svg>
        {{ Math.abs(trend) }}%
      </span>
    </div>
    <p class="mt-2 text-3xl font-black tracking-tight text-slate-900 dark:text-white">
      {{ formattedValue }}
    </p>
    <p v-if="subtitle" class="mt-1 text-xs font-medium text-slate-400 dark:text-slate-500">
      {{ subtitle }}
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: { type: String, required: true },
  value: { type: [Number, String], default: 0 },
  subtitle: { type: String, default: '' },
  trend: { type: Number, default: null },
  format: { type: String, default: 'number' },
  currency: { type: String, default: 'EUR' },
})

const trendUp = computed(() => (props.trend ?? 0) >= 0)

const formattedValue = computed(() => {
  const v = Number(props.value) || 0
  if (props.format === 'currency') {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: props.currency }).format(v)
  }
  if (props.format === 'percent') {
    return `${v}%`
  }
  return new Intl.NumberFormat('fr-FR').format(v)
})
</script>
