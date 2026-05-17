<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
      <StatsCard title="Runs ce mois" :value="stats.runs_this_month" icon="ChartBarIcon" color="blue" />
      <StatsCard title="Bulletins generes" :value="stats.slips_generated" icon="UsersIcon" color="green" />
      <StatsCard title="Masse salariale (runs charges)" :value="formattedMasse" icon="CurrencyEuroIcon" color="purple" />
      <StatsCard title="En attente validation" :value="stats.pending_validation" icon="ChartBarIcon" color="yellow" />
    </div>

    <div v-if="runSummary" class="rounded-lg border border-indigo-200 bg-indigo-50/60 p-4 shadow-sm">
      <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h3 class="font-semibold text-gray-900">
            Resume du run {{ runSummary.run?.id }}
          </h3>
          <p class="mt-1 text-sm text-gray-600">
            {{ formatPeriod(runSummary.run?.period_start, runSummary.run?.period_end) }}
            · Statut {{ runSummary.run?.status }}
          </p>
        </div>
        <button type="button" class="text-sm font-medium text-gray-600 hover:text-gray-900" @click="runSummary = null">
          Fermer
        </button>
      </div>
      <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
        <div>
          <dt class="text-gray-500">
            Brut
          </dt>
          <dd class="font-medium">
            {{ formatCurrency(runSummary.total_gross) }}
          </dd>
        </div>
        <div>
          <dt class="text-gray-500">
            Retenues
          </dt>
          <dd class="font-medium">
            {{ formatCurrency(runSummary.total_deductions) }}
          </dd>
        </div>
        <div>
          <dt class="text-gray-500">
            Net
          </dt>
          <dd class="font-medium">
            {{ formatCurrency(runSummary.total_net) }}
          </dd>
        </div>
        <div>
          <dt class="text-gray-500">
            Employes
          </dt>
          <dd class="font-medium">
            {{ runSummary.employee_count }}
          </dd>
        </div>
      </dl>
      <div v-if="(runSummary.slips || []).length" class="mt-4 max-h-48 overflow-y-auto border-t border-indigo-100 pt-3">
        <ul class="space-y-1 text-sm">
          <li v-for="s in runSummary.slips" :key="s.id" class="flex justify-between gap-2">
            <span>{{ slipEmployeeLabel(s) }}</span>
            <span class="font-medium">{{ formatCurrency(s.net_salary) }}</span>
          </li>
        </ul>
      </div>
    </div>

    <div class="flex gap-2">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        :class="[
          'rounded-md px-4 py-2 text-sm font-medium',
          activeTab === tab.key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50'
        ]"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <DataTable
      v-if="activeTab === 'structures'"
      :columns="structureColumns"
      :rows="structures"
      :loading="loading"
      :error="error"
      :search-keys="['name', 'country_code']"
      search-placeholder="Rechercher une structure..."
      default-sort="name"
      exportable
      @export="exportStructures"
    >
      <template #cell-components_count="{ row }">
        {{ (row.components || []).length }}
      </template>
      <template #cell-status="{ value }">
        <StatusBadge :status="value" :map="structureStatusMap" />
      </template>
    </DataTable>

    <DataTable
      v-else-if="activeTab === 'runs'"
      :columns="runColumns"
      :rows="runs"
      :loading="loading"
      :error="error"
      :search-keys="['reference', 'period']"
      search-placeholder="Rechercher un run..."
      default-sort="period"
      default-sort-dir="desc"
      exportable
      @export="exportRuns"
    >
      <template #cell-status="{ value }">
        <StatusBadge :status="value" :map="runStatusMap" />
      </template>
      <template #cell-total_net="{ value }">
        {{ formatCurrency(value) }}
      </template>
      <template #row-actions="{ row }">
        <div class="flex justify-end gap-2">
          <button v-if="row.status === 'draft'" type="button" class="text-sm font-medium text-indigo-600 hover:text-indigo-800" @click="calculateRun(row.id)">
            Calculer
          </button>
          <button v-if="row.status === 'calculated'" type="button" class="text-sm font-medium text-green-600 hover:text-green-800" @click="validateRun(row.id)">
            Valider
          </button>
          <button type="button" class="text-sm font-medium text-gray-600 hover:text-gray-800" @click="viewRun(row.id)">
            Detail
          </button>
        </div>
      </template>
    </DataTable>

    <DataTable
      v-else-if="activeTab === 'slips'"
      :columns="slipColumns"
      :rows="slips"
      :loading="loading"
      :error="error"
      :search-keys="['employee_name', 'reference']"
      search-placeholder="Rechercher un bulletin..."
      default-sort="created_at"
      default-sort-dir="desc"
      exportable
      @export="exportSlips"
    >
      <template #cell-net_pay="{ value }">
        {{ formatCurrency(value) }}
      </template>
      <template #row-actions="{ row }">
        <button type="button" class="text-sm font-medium text-indigo-600 hover:text-indigo-800" @click="downloadPaySlipPdf(row.id)">
          PDF
        </button>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'

const loading = ref(false)
const error = ref('')
const runs = ref([])
const slips = ref([])
const activeTab = ref('runs')
const runSummary = ref(null)

const stats = ref({ runs_this_month: 0, slips_generated: 0, total_net: 0, pending_validation: 0 })

const structures = ref([])

const tabs = [
  { key: 'structures', label: 'Structures salariales' },
  { key: 'runs', label: 'Runs de paie' },
  { key: 'slips', label: 'Bulletins' },
]

const runColumns = [
  { key: 'reference', label: 'Reference', sortable: true },
  { key: 'period', label: 'Periode', sortable: true },
  { key: 'employees_count', label: 'Employes', sortable: true },
  { key: 'total_net', label: 'Net total', sortable: true },
  { key: 'status', label: 'Statut', sortable: true },
]

const slipColumns = [
  { key: 'employee_name', label: 'Employe', sortable: true },
  { key: 'reference', label: 'Reference', sortable: true },
  { key: 'period', label: 'Periode', sortable: true },
  { key: 'net_pay', label: 'Net a payer', sortable: true },
  { key: 'created_at', label: 'Date', sortable: true },
]

const structureColumns = [
  { key: 'name', label: 'Nom', sortable: true },
  { key: 'country_code', label: 'Pays', sortable: true },
  { key: 'components_count', label: 'Composants' },
  { key: 'status', label: 'Statut', sortable: true },
]

const structureStatusMap = {
  active: { label: 'Actif', color: 'green' },
  draft: { label: 'Brouillon', color: 'gray' },
  archived: { label: 'Archive', color: 'red' },
}

const runStatusMap = {
  draft: { label: 'Brouillon', color: 'gray' },
  calculating: { label: 'Calcul...', color: 'yellow' },
  calculated: { label: 'Calcule', color: 'yellow' },
  validated: { label: 'Valide', color: 'green' },
  paid: { label: 'Paye', color: 'green' },
  cancelled: { label: 'Annule', color: 'red' },
}

const formattedMasse = computed(() => formatCurrency(stats.value.total_net))

function formatCurrency(value) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(Number(value) || 0)
}

function formatPeriod(start, end) {
  if (!start || !end) return '-'
  const a = typeof start === 'string' ? start.slice(0, 10) : start
  const b = typeof end === 'string' ? end.slice(0, 10) : end
  return `${a} → ${b}`
}

function slipEmployeeLabel(slip) {
  const e = slip?.employee
  if (e?.first_name || e?.last_name) {
    return `${e.first_name ?? ''} ${e.last_name ?? ''}`.trim()
  }
  return slip?.employee_id ? `Employe #${slip.employee_id}` : '-'
}

async function fetchAllPayrollRuns() {
  const perPage = 50
  const first = await api.get('/v1/payroll-runs', { params: { per_page: perPage, page: 1 } })
  const meta = first.data.meta || {}
  let items = [...(first.data.data || [])]
  const lastPage = Math.min(Number(meta.last_page) || 1, 25)

  for (let page = 2; page <= lastPage; page++) {
    const res = await api.get('/v1/payroll-runs', { params: { per_page: perPage, page } })
    items.push(...(res.data.data || []))
  }

  return items
}

function mapRunRow(raw) {
  return {
    id: raw.id,
    reference: `RUN-${raw.id}`,
    period: formatPeriod(raw.period_start, raw.period_end),
    employees_count: raw.pay_slips_count ?? 0,
    total_net: raw.total_net ?? 0,
    status: raw.status,
    period_start: raw.period_start,
    period_end: raw.period_end,
  }
}

async function fetchAllPaySlips() {
  const perPage = 100
  const first = await api.get('/v1/pay-slips', { params: { per_page: perPage, page: 1 } })
  const meta = first.data.meta || {}
  let raw = [...(first.data.data || [])]
  const lastPage = Math.min(Number(meta.last_page) || 1, 50)

  for (let page = 2; page <= lastPage; page++) {
    const res = await api.get('/v1/pay-slips', { params: { per_page: perPage, page } })
    raw.push(...(res.data.data || []))
  }

  return raw.map(slip => ({
    id: slip.id,
    payroll_run_reference: slip.payroll_run_id != null ? `RUN-${slip.payroll_run_id}` : '-',
    employee_name: slipEmployeeLabel(slip),
    reference: `SLIP-${slip.id}`,
    period: formatPeriod(slip.period_start, slip.period_end),
    net_pay: slip.net_salary ?? 0,
    created_at: slip.created_at ? String(slip.created_at).slice(0, 10) : '-',
  }))
}

function currentMonthBounds() {
  const now = new Date()
  return { y: now.getFullYear(), m: now.getMonth() }
}

function runStartsThisMonth(run) {
  const { y, m } = currentMonthBounds()
  const d = run.period_start ? new Date(run.period_start) : null
  return d && d.getFullYear() === y && d.getMonth() === m
}

async function fetchData() {
  loading.value = true
  error.value = ''
  try {
    const rawRuns = await fetchAllPayrollRuns()
    runs.value = rawRuns.map(mapRunRow)

    slips.value = await fetchAllPaySlips()

    try {
      const structRes = await api.get('/v1/salary-structures', { params: { per_page: 100 } })
      structures.value = (structRes.data.data || []).map(s => ({
        id: s.id,
        name: s.name || `Structure #${s.id}`,
        country_code: s.country_code || '-',
        components: s.components || [],
        status: s.is_active !== false ? 'active' : 'draft',
      }))
    } catch (e) {
      console.warn('Salary structures not available', e)
      structures.value = []
    }

    stats.value = {
      runs_this_month: runs.value.filter(runStartsThisMonth).length,
      slips_generated: slips.value.length,
      total_net: rawRuns.reduce((s, r) => s + (Number(r.total_net) || 0), 0),
      pending_validation: runs.value.filter(r => r.status === 'calculated').length,
    }
  } catch (e) {
    error.value = 'Impossible de charger les donnees de paie.'
    console.warn('PayrollView fetch failed', e)
  } finally {
    loading.value = false
  }
}

async function calculateRun(id) {
  try {
    await api.post(`/v1/payroll-runs/${id}/calculate`)
    await fetchData()
  } catch (err) {
    console.warn('Failed to calculate payroll run', err)
  }
}

async function validateRun(id) {
  try {
    await api.post(`/v1/payroll-runs/${id}/validate`)
    await fetchData()
  } catch (err) {
    console.warn('Failed to validate payroll run', err)
  }
}

async function viewRun(id) {
  try {
    const res = await api.get(`/v1/payroll-runs/${id}/summary`)
    runSummary.value = res.data.data
  } catch (err) {
    console.warn('Failed to load payroll summary', err)
  }
}

function escapeCsvCell(value) {
  const s = String(value ?? '')
  if (/[;"'\n]/.test(s)) {
    return `"${s.replace(/"/g, '""')}"`
  }
  return s
}

function downloadCsv(filename, headerRow, lines) {
  const bom = '\ufeff'
  const blob = new Blob([bom + [headerRow.join(';'), ...lines].join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}

function exportRuns() {
  const header = ['reference', 'periode', 'employes', 'net_total', 'statut']
  const lines = runs.value.map(r =>
    [r.reference, r.period, r.employees_count, r.total_net, r.status].map(escapeCsvCell).join(';'),
  )
  downloadCsv('payroll-runs.csv', header, lines)
}

function exportStructures() {
  const header = ['nom', 'pays', 'composants', 'statut']
  const lines = structures.value.map(s =>
    [s.name, s.country_code, (s.components || []).length, s.status].map(escapeCsvCell).join(';'),
  )
  downloadCsv('salary-structures.csv', header, lines)
}

function exportSlips() {
  const header = ['bulletin', 'run', 'employe', 'periode', 'net', 'cree_le']
  const lines = slips.value.map(s =>
    [s.reference, s.payroll_run_reference, s.employee_name, s.period, s.net_pay, s.created_at].map(escapeCsvCell).join(';'),
  )
  downloadCsv('pay-slips.csv', header, lines)
}

async function downloadPaySlipPdf(id) {
  try {
    const res = await api.get(`/v1/pay-slips/${id}/pdf`, {
      responseType: 'blob',
      headers: { Accept: 'application/pdf' },
    })
    const blob = res.data instanceof Blob ? res.data : new Blob([res.data])
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `bulletin_${id}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch (err) {
    console.warn('Failed to download pay slip PDF', err)
  }
}

onMounted(fetchData)
</script>
