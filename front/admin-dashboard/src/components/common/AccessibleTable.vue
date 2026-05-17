<template>
  <!-- W5 — Accessible data table with caption and scope attributes -->
  <div class="overflow-x-auto" role="region" :aria-label="caption" tabindex="0">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
      <caption v-if="caption" class="sr-only">{{ caption }}</caption>
      <thead class="bg-gray-50 dark:bg-gray-800">
        <tr>
          <th
            v-for="column in columns"
            :key="column.key"
            scope="col"
            :class="[
              'px-6 py-3 text-xs font-semibold uppercase tracking-wider',
              column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : 'text-left',
              'text-gray-600 dark:text-gray-400'
            ]"
            :aria-sort="sortKey === column.key ? (sortDir === 'asc' ? 'ascending' : 'descending') : undefined"
          >
            <button
              v-if="column.sortable"
              @click="$emit('sort', column.key)"
              class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-gray-200 focus:outline-none focus:text-indigo-600"
            >
              {{ column.label }}
              <span class="text-gray-400" aria-hidden="true">
                {{ sortKey === column.key ? (sortDir === 'asc' ? '&#9650;' : '&#9660;') : '&#8693;' }}
              </span>
            </button>
            <span v-else>{{ column.label }}</span>
          </th>
        </tr>
      </thead>
      <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
        <slot />
        <tr v-if="empty">
          <td :colspan="columns.length" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
            {{ emptyMessage || 'Aucune donnee' }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
defineProps({
  columns: { type: Array, required: true },
  caption: { type: String, default: '' },
  empty: { type: Boolean, default: false },
  emptyMessage: { type: String, default: '' },
  sortKey: { type: String, default: '' },
  sortDir: { type: String, default: 'asc' },
})

defineEmits(['sort'])
</script>
