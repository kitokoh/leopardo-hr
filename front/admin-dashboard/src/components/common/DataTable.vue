<template>
  <div class="card overflow-hidden">
    <div class="flex flex-col gap-4 border-b border-slate-200/50 dark:border-slate-800/50 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex flex-wrap items-center gap-4">
        <div class="relative group">
          <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 transition-colors group-focus-within:text-brand-500" />
          <input
            v-model="searchQuery"
            type="search"
            :placeholder="searchPlaceholder"
            :aria-label="searchPlaceholder"
            class="w-64 rounded-xl border-slate-200/50 dark:border-slate-700/50 bg-slate-50/50 dark:bg-slate-800/50 pl-10 pr-4 py-2 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 transition-all duration-200"
          />
        </div>
        <slot name="filters" />
      </div>
      <div class="flex items-center gap-3">
        <button
          v-if="exportable"
          class="btn-secondary py-2"
          @click="$emit('export')"
        >
          <ArrowDownTrayIcon class="h-4 w-4 mr-2" />
          Export CSV
        </button>
        <slot name="actions" />
      </div>
    </div>

    <div v-if="loading" class="p-12 text-center text-sm text-slate-500 dark:text-slate-400">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-600 mx-auto mb-4"></div>
      Chargement des données...
    </div>
    <div v-else-if="error" class="p-8 text-center text-sm text-red-600">{{ error }}</div>
    <div v-else-if="filteredRows.length === 0" class="p-8 text-center text-sm text-gray-500">
      {{ emptyMessage }}
    </div>
    <div v-else class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200/50 dark:divide-slate-800/50" role="table">
        <caption v-if="caption" class="sr-only">{{ caption }}</caption>
        <thead class="bg-slate-50/50 dark:bg-slate-800/50">
          <tr>
            <th
              v-for="col in columns"
              :key="col.key"
              class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400"
              :class="{ 'cursor-pointer hover:text-slate-700 dark:hover:text-slate-200 transition-colors': col.sortable }"
              :aria-sort="col.sortable && sortKey === col.key ? (sortDir === 'asc' ? 'ascending' : 'descending') : undefined"
              @click="col.sortable && toggleSort(col.key)"
            >
              <div class="flex items-center gap-1.5">
                {{ col.label }}
                <template v-if="col.sortable && sortKey === col.key">
                  <ChevronUpIcon v-if="sortDir === 'asc'" class="h-3.5 w-3.5 text-brand-500" />
                  <ChevronDownIcon v-else class="h-3.5 w-3.5 text-brand-500" />
                </template>
              </div>
            </th>
            <th v-if="$slots['row-actions']" class="px-6 py-4 text-right text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200/50 dark:divide-slate-800/50 bg-white/40 dark:bg-slate-900/40 backdrop-blur-sm">
          <tr v-for="row in paginatedRows" :key="rowKey(row)" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/80 transition-colors">
            <td v-for="col in columns" :key="col.key" class="whitespace-nowrap px-6 py-4 text-sm text-slate-700 dark:text-slate-300">
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
