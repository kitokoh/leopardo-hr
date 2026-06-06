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
  green: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
  yellow: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-800',
  red: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-800',
  blue: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-800',
  gray: 'bg-slate-100 dark:bg-slate-800/30 text-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-800',
  purple: 'bg-brand-100 dark:bg-brand-900/30 text-brand-800 dark:text-brand-300 border border-brand-200 dark:border-brand-800',
  indigo: 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-800 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800',
}

const entry = computed(() => props.map[props.status] || { label: props.status, color: 'gray' })
const label = computed(() => entry.value.label)
const classes = computed(() => [
  'inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-widest',
  colorClasses[entry.value.color] || colorClasses.gray
])
</script>
