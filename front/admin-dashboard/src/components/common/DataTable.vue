<template>
  <div class="rounded-lg bg-white shadow">
    <div class="flex flex-col gap-3 border-b border-gray-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <div class="relative">
          <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <input
            v-model="searchQuery"
            type="search"
            :placeholder="searchPlaceholder"
            :aria-label="searchPlaceholder"
            class="w-64 rounded-md border-gray-300 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500"
          />
        </div>
        <slot name="filters" />
      </div>
      <div class="flex items-center gap-2">
        <button
          v-if="exportable"
          class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          @click="$emit('export')"
        >
          <ArrowDownTrayIcon class="h-4 w-4" />
          Export CSV
        </button>
        <slot name="actions" />
      </div>
    </div>

    <div v-if="loading" class="p-8 text-center text-sm text-gray-500">Chargement...</div>
    <div v-else-if="error" class="p-8 text-center text-sm text-red-600">{{ error }}</div>
    <div v-else-if="filteredRows.length === 0" class="p-8 text-center text-sm text-gray-500">
      {{ emptyMessage }}
    </div>
    <div v-else class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200" role="table">
        <caption v-if="caption" class="sr-only">{{ caption }}</caption>
        <thead class="bg-gray-50">
          <tr>
            <th
              v-for="col in columns"
              :key="col.key"
              class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
              :class="{ 'cursor-pointer hover:text-gray-700': col.sortable }"
              :aria-sort="col.sortable && sortKey === col.key ? (sortDir === 'asc' ? 'ascending' : 'descending') : undefined"
              @click="col.sortable && toggleSort(col.key)"
            >
              <div class="flex items-center gap-1">
                {{ col.label }}
                <template v-if="col.sortable && sortKey === col.key">
                  <ChevronUpIcon v-if="sortDir === 'asc'" class="h-3 w-3" />
                  <ChevronDownIcon v-else class="h-3 w-3" />
                </template>
              </div>
            </th>
            <th v-if="$slots['row-actions']" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <tr v-for="row in paginatedRows" :key="rowKey(row)" class="hover:bg-gray-50">
            <td v-for="col in columns" :key="col.key" class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
              <slot :name="`cell-${col.key}`" :row="row" :value="getNestedValue(row, col.key)">
                {{ getNestedValue(row, col.key) }}
              </slot>
            </td>
            <td v-if="$slots['row-actions']" class="whitespace-nowrap px-6 py-4 text-right text-sm">
              <slot name="row-actions" :row="row" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="filteredRows.length > 0" class="border-t border-gray-200 px-6 py-4">
      <Pagination
        :current-page="currentPage"
        :total-pages="totalPages"
        :total-items="filteredRows.length"
        :per-page="perPage"
        @page-change="currentPage = $event"
        @per-page-change="perPage = $event; currentPage = 1"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { MagnifyingGlassIcon, ArrowDownTrayIcon, ChevronUpIcon, ChevronDownIcon } from '@heroicons/vue/24/outline'
import Pagination from './Pagination.vue'

const props = defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
  searchPlaceholder: { type: String, default: 'Rechercher...' },
  searchKeys: { type: Array, default: () => [] },
  exportable: { type: Boolean, default: false },
  emptyMessage: { type: String, default: 'Aucun resultat trouve.' },
  defaultSort: { type: String, default: '' },
  defaultSortDir: { type: String, default: 'asc' },
  keyField: { type: String, default: 'id' },
  caption: { type: String, default: '' }
})

defineEmits(['export'])

const searchQuery = ref('')
const sortKey = ref(props.defaultSort)
const sortDir = ref(props.defaultSortDir)
const currentPage = ref(1)
const perPage = ref(25)

watch(() => props.rows, () => { currentPage.value = 1 })

function rowKey(row) {
  return row[props.keyField] ?? JSON.stringify(row)
}

function getNestedValue(obj, path) {
  return path.split('.').reduce((acc, key) => acc?.[key], obj) ?? '-'
}

function toggleSort(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDir.value = 'asc'
  }
}

const filteredRows = computed(() => {
  let result = [...props.rows]
  if (searchQuery.value && props.searchKeys.length > 0) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(row =>
      props.searchKeys.some(key => {
        const val = getNestedValue(row, key)
        return String(val).toLowerCase().includes(q)
      })
    )
  }
  if (sortKey.value) {
    result.sort((a, b) => {
      const va = getNestedValue(a, sortKey.value)
      const vb = getNestedValue(b, sortKey.value)
      const cmp = String(va).localeCompare(String(vb), 'fr', { numeric: true })
      return sortDir.value === 'asc' ? cmp : -cmp
    })
  }
  return result
})

const totalPages = computed(() => Math.ceil(filteredRows.value.length / perPage.value) || 1)

const paginatedRows = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filteredRows.value.slice(start, start + perPage.value)
})
</script>
