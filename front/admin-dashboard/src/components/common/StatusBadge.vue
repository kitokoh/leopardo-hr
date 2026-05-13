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
  green: 'bg-green-100 text-green-800',
  yellow: 'bg-yellow-100 text-yellow-800',
  red: 'bg-red-100 text-red-800',
  blue: 'bg-blue-100 text-blue-800',
  gray: 'bg-gray-100 text-gray-800',
  purple: 'bg-purple-100 text-purple-800',
  indigo: 'bg-indigo-100 text-indigo-800',
}

const entry = computed(() => props.map[props.status] || { label: props.status, color: 'gray' })
const label = computed(() => entry.value.label)
const classes = computed(() => [
  'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
  colorClasses[entry.value.color] || colorClasses.gray
])
</script>
