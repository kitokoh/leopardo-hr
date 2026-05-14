<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
      <StatsCard title="Runs ce mois" :value="stats.runs_this_month" icon="ChartBarIcon" color="blue" />
      <StatsCard title="Bulletins generes" :value="stats.slips_generated" icon="UsersIcon" color="green" />
      <StatsCard title="Masse salariale" :value="formattedMasse" icon="CurrencyEuroIcon" color="purple" />
      <StatsCard title="En attente validation" :value="stats.pending_validation" icon="ChartBarIcon" color="yellow" />
    </div>

    <div class="flex gap-2">
      <button
        v-for="tab in tabs"
        :key="tab.key"
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
      v-if="activeTab === 'runs'"
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
          <button v-if="row.status === 'draft'" class="text-sm font-medium text-indigo-600 hover:text-indigo-800" @click="calculateRun(row.id)">
            Calculer
          </button>
          <button v-if="row.status === 'calculated'" class="text-sm font-medium text-green-600 hover:text-green-800" @click="validateRun(row.id)">
            Valider
          </button>
          <button class="text-sm font-medium text-gray-600 hover:text-gray-800" @click="viewRun(row.id)">
            Detail
          </button>
        </div>
      </template>
    </DataTable>

    <DataTable
      v-else
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
        <a :href="`/api/v1/pay-slips/${row.id}/pdf`" target="_blank" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
          PDF
        </a>
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

const stats = ref({ runs_this_month: 0, slips_generated: 0, total_net: 0, pending_validation: 0 })

const tabs = [
  { key: 'runs', label: 'Runs de paie' },
  { key: 'slips', label: 'Bulletins' }
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

const runStatusMap = {
  draft: { label: 'Brouillon', color: 'gray' },
  calculated: { label: 'Calcule', color: 'yellow' },
  validated: { label: 'Valide', color: 'green' },
  cancelled: { label: 'Annule', color: 'red' },
}

const formattedMasse = computed(() => formatCurrency(stats.value.total_net))

function formatCurrency(value) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(value || 0)
}

async function fetchData() {
  loading.value = true
  error.value = ''
  try {
    const [runsRes, slipsRes] = await Promise.all([
      api.get('/v1/payroll-runs'),
      api.get('/v1/pay-slips'),
    ])
    runs.value = runsRes.data.data || runsRes.data || []
    slips.value = slipsRes.data.data || slipsRes.data || []
    stats.value = {
      runs_this_month: runs.value.length,
      slips_generated: slips.value.length,
      total_net: runs.value.reduce((s, r) => s + (r.total_net || 0), 0),
      pending_validation: runs.value.filter(r => r.status === 'calculated').length,
    }
  } catch (e) {
    error.value = 'Impossible de charger les donnees de paie.'
  } finally {
    loading.value = false
  }
}

async function calculateRun(id) {
  try {
    await api.post(`/v1/payroll-runs/${id}/calculate`)
    fetchData()
  } catch (err) {
    console.warn('Failed to calculate payroll run', err)
  }
}
async function validateRun(id) {
  try {
    await api.post(`/v1/payroll-runs/${id}/validate`)
    fetchData()
  } catch (err) {
    console.warn('Failed to validate payroll run', err)
  }
}
function viewRun(id) { /* TODO: navigate to detail */ }
function exportRuns() { window.open('/api/v1/export/payroll-runs?format=csv', '_blank') }
function exportSlips() { window.open('/api/v1/export/pay-slips?format=csv', '_blank') }

onMounted(fetchData)
</script>
