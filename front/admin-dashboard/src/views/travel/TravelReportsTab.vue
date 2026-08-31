<template>
  <div class="space-y-6">
    <div class="glass-card flex flex-wrap items-end gap-4 p-4">
      <div>
        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="report-from">
          {{ $t('travel.bookings.from', 'Du') }}
        </label>
        <input id="report-from" v-model="filters.from" type="date" class="form-input mt-1" @change="loadAll" />
      </div>
      <div>
        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="report-to">
          {{ $t('travel.bookings.to', 'Au') }}
        </label>
        <input id="report-to" v-model="filters.to" type="date" class="form-input mt-1" @change="loadAll" />
      </div>
      <button class="btn-secondary" type="button" @click="resetFilters">
        {{ $t('travel.bookings.reset', 'Réinitialiser') }}
      </button>
      <button class="btn-primary" type="button" :disabled="exporting" @click="exportCsv">
        <ArrowDownTrayIcon class="mr-2 h-4 w-4" />
        {{ exporting ? $t('common.busy', 'En cours…') : $t('travel.reports.exportCsv', 'Exporter CSV') }}
      </button>
    </div>

    <div v-if="kpiError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
      {{ kpiError }}
    </div>

    <!-- KPIs -->
    <div v-if="kpis && Object.keys(kpis).length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div
        v-for="(value, key) in kpis"
        :key="key"
        class="glass-card p-5"
      >
        <div class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ humanizeKey(key) }}</div>
        <div class="mt-1 text-2xl font-black text-slate-900 dark:text-white">{{ formatKpi(value) }}</div>
      </div>
    </div>

    <div class="flex flex-wrap gap-2" role="tablist" :aria-label="$t('travel.reports.tabsLabel', 'Rapports')">
      <button
        v-for="rep in reportTypes"
        :key="rep.key"
        type="button"
        role="tab"
        :aria-selected="activeReport === rep.key"
        :class="[
          'rounded-md px-3.5 py-1.5 text-sm font-medium transition-colors',
          activeReport === rep.key
            ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
        ]"
        @click="selectReport(rep)"
      >
        {{ rep.label }}
      </button>
    </div>

    <DataTable
      :columns="reportColumns"
      :rows="reportRows"
      :loading="reportLoading"
      :error="reportError"
      :search-keys="searchKeys"
      :search-placeholder="$t('travel.search.report', 'Rechercher dans le rapport…')"
      :caption="activeReportLabel"
      :empty-message="$t('travel.reports.empty', 'Aucune donnée sur la période.')"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline'
import api, { downloadApiFile } from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const toast = useToast()

const activeReport = ref('sales')
const filters = ref({ from: '', to: '' })
const kpis = ref({})
const kpiError = ref('')
const reports = ref({ sales: [], occupancy: [], revenue: [], cancellations: [] })
const reportLoading = ref(false)
const reportError = ref('')
const exporting = ref(false)

const reportTypes = computed(() => [
  { key: 'sales', label: t('travel.reports.sales', 'Ventes') },
  { key: 'occupancy', label: t('travel.reports.occupancy', 'Occupation') },
  { key: 'revenue', label: t('travel.reports.revenue', 'Recettes') },
  { key: 'cancellations', label: t('travel.reports.cancellations', 'Annulations') }
])

const activeReportLabel = computed(() => reportTypes.value.find((r) => r.key === activeReport.value)?.label || '')

const reportRows = computed(() => reports.value[activeReport.value] || [])

const reportColumns = computed(() => {
  const rows = reportRows.value
  if (!rows.length) return []
  const first = rows[0]
  return Object.keys(first)
    .filter((key) => key !== 'id')
    .map((key) => ({ key, label: humanizeKey(key), sortable: true }))
})

const searchKeys = computed(() => reportColumns.value.map((c) => c.key).slice(0, 4))

function selectReport(rep) {
  activeReport.value = rep.key
}

function reportParams() {
  const params = {}
  if (filters.value.from) params.from = filters.value.from
  if (filters.value.to) params.to = filters.value.to
  return params
}

function humanizeKey(key) {
  return String(key)
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (ch) => ch.toUpperCase())
}

function formatKpi(value) {
  if (value === null || value === undefined) return '-'
  if (typeof value === 'object') return JSON.stringify(value)
  if (typeof value === 'number') {
    if (Number.isInteger(value) && Math.abs(value) > 1000) {
      return new Intl.NumberFormat(localeStore.current).format(value)
    }
    return String(value)
  }
  return String(value)
}

async function unwrap(res) {
  let data = res.data
  if (data && typeof data === 'object' && 'data' in data) data = data.data
  if (Array.isArray(data)) return { rows: data, kpis: {} }
  if (data && typeof data === 'object') {
    const { rows, kpis } = extractRowsAndKpis(data)
    return { rows, kpis }
  }
  return { rows: [], kpis: {} }
}

function extractRowsAndKpis(payload) {
  const kpis = {}
  const rows = []
  for (const [key, value] of Object.entries(payload)) {
    if (Array.isArray(value)) {
      rows.push(...value)
    } else if (value && typeof value === 'object') {
      const nested = extractRowsAndKpis(value)
      rows.push(...nested.rows)
      Object.assign(kpis, nested.kpis)
    } else {
      kpis[key] = value
    }
  }
  return { rows, kpis }
}

async function loadReport(key) {
  try {
    const res = await api.get(`/travel/reports/${key}`, { params: reportParams(), _skipAuthRedirect: true })
    const { rows } = await unwrap(res)
    reports.value[key] = rows
  } catch (err) {
    reports.value[key] = []
    if (key === activeReport.value) {
      reportError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
    }
  }
}

async function loadKpis() {
  kpiError.value = ''
  try {
    const res = await api.get('/travel/reports/dashboard', { params: reportParams(), _skipAuthRedirect: true })
    const { kpis } = await unwrap(res)
    kpis.value = kpis
  } catch (err) {
    kpiError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  }
}

async function loadAll() {
  reportLoading.value = true
  reportError.value = ''
  await Promise.all([
    loadKpis(),
    loadReport('sales'),
    loadReport('occupancy'),
    loadReport('revenue'),
    loadReport('cancellations')
  ])
  reportLoading.value = false
}

function resetFilters() {
  filters.value = { from: '', to: '' }
  loadAll()
}

async function exportCsv() {
  exporting.value = true
  try {
    const params = new URLSearchParams(reportParams())
    const query = params.toString() ? `?${params.toString()}` : ''
    await downloadApiFile(`/travel/reports/export${query}`, 'rapport-travel.csv', { _skipAuthRedirect: true })
    toast.success(t('travel.toast.exported', 'Export CSV téléchargé.'))
  } catch (err) {
    toast.error(err.response?.data?.message || t('travel.reports.exportError', 'Export impossible.'))
  } finally {
    exporting.value = false
  }
}

onMounted(loadAll)
</script>
