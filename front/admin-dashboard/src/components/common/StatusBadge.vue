<template>
  <span :class="classes">{{ label }}</span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: { type: String, required: true },
  map: {
    type: Object,
    default: () => ({
      active: { label: 'Actif', color: 'green' },
      pending: { label: 'En attente', color: 'yellow' },
      approved: { label: 'Approuve', color: 'green' },
      rejected: { label: 'Refuse', color: 'red' },
      cancelled: { label: 'Annule', color: 'gray' },
      draft: { label: 'Brouillon', color: 'gray' },
      validated: { label: 'Valide', color: 'green' },
      completed: { label: 'Termine', color: 'blue' },
      expired: { label: 'Expire', color: 'red' },
      paid: { label: 'Paye', color: 'green' },
      overdue: { label: 'En retard', color: 'red' },
    })
  }
})

const colorClasses = {
  green: 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
  yellow: 'bg-yellow-50 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 border-yellow-200 dark:border-yellow-800',
  red: 'bg-red-50 dark:bg-red-950/30 text-red-700 dark:text-red-400 border-red-200 dark:border-red-900/40',
  blue: 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-800',
  gray: 'bg-slate-50 dark:bg-slate-900/30 text-slate-700 dark:text-slate-400 border-slate-200 dark:border-slate-800',
  purple: 'bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-400 border-brand-200 dark:border-brand-800',
  indigo: 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800',
}

const entry = computed(() => props.map[props.status] || { label: props.status, color: 'gray' })
const label = computed(() => entry.value.label)
const classes = computed(() => [
  'inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-widest',
  colorClasses[entry.value.color] || colorClasses.gray
])
</script>
