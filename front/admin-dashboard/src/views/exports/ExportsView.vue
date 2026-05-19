<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="report in reportTypes"
        :key="report.key"
        class="rounded-lg bg-white p-5 shadow ring-1 ring-gray-200"
      >
        <div class="flex items-start gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50">
            <component :is="report.icon" class="h-5 w-5 text-indigo-600" />
          </div>
          <div class="flex-1">
            <h3 class="text-sm font-semibold text-gray-900">{{ report.title }}</h3>
            <p class="mt-1 text-xs text-gray-500">{{ report.description }}</p>
          </div>
        </div>
        <div class="mt-4 flex items-center gap-2">
          <select v-model="report.format" class="rounded-md border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
            <option value="csv">CSV</option>
            <option value="json">JSON</option>
            <option value="xlsx" v-if="report.supportsXlsx">Excel</option>
          </select>
          <button
            class="flex-1 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
            :disabled="report.downloading"
            @click="downloadReport(report)"
          >
            {{ report.downloading ? 'Telechargement...' : 'Telecharger' }}
          </button>
        </div>
      </div>
    </div>

    <div class="rounded-lg bg-white shadow">
      <div class="border-b border-gray-200 px-6 py-4">
        <h2 class="text-lg font-semibold text-gray-900">Rapports RH personnalises</h2>
        <p class="text-sm text-gray-500">Generez des rapports avances avec filtres de periode et departement.</p>
      </div>
      <div class="p-6">
        <form class="grid grid-cols-1 gap-4 md:grid-cols-4" @submit.prevent="generateHrReport">
          <div>
            <label class="block text-sm font-medium text-gray-700">Type de rapport</label>
            <select v-model="hrReport.type" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
              <option value="headcount">Effectifs</option>
              <option value="turnover">Turnover</option>
              <option value="absenteeism">Absenteisme</option>
              <option value="payroll_summary">Resume paie</option>
              <option value="training_progress">Formations</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Date debut</label>
            <input v-model="hrReport.start_date" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Date fin</label>
            <input v-model="hrReport.end_date" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
          </div>
          <div class="flex items-end">
            <button
              type="submit"
              class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
              :disabled="generatingReport"
            >
              {{ generatingReport ? 'Generation...' : 'Generer' }}
            </button>
          </div>
        </form>

        <div v-if="hrReportResult" class="mt-6">
          <div class="overflow-x-auto rounded-md border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th v-for="col in hrReportResult.columns" :key="col" class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">
                    {{ col }}
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <tr v-for="(row, i) in hrReportResult.rows" :key="i">
                  <td v-for="col in hrReportResult.columns" :key="col" class="px-4 py-2 text-sm text-gray-700">
                    {{ row[col] ?? '-' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <DataTable
      :columns="historyColumns"
      :rows="exportHistory"
      :loading="historyLoading"
      :search-keys="['type', 'requested_by']"
      search-placeholder="Rechercher dans l'historique..."
      default-sort="created_at"
      default-sort-dir="desc"
      empty-message="Aucun export recent."
    >
      <template #cell-status="{ value }">
        <StatusBadge :status="value" :map="exportStatusMap" />
      </template>
      <template #row-actions="{ row }">
        <a v-if="row.download_url" :href="row.download_url" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
          Telecharger
        </a>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import {
  UsersIcon, DocumentTextIcon, CurrencyEuroIcon,
  AcademicCapIcon, TruckIcon, ClipboardDocumentListIcon
} from '@heroicons/vue/24/outline'
import api, { downloadApiFile } from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'

const exportHistory = ref([])
const historyLoading = ref(false)
const hrReportResult = ref(null)
const generatingReport = ref(false)

const hrReport = reactive({
  type: 'headcount',
  start_date: new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0],
  end_date: new Date().toISOString().split('T')[0],
})

const reportTypes = reactive([
  { key: 'employees', title: 'Employes', description: 'Liste complete avec postes, contrats, departements.', icon: UsersIcon, format: 'csv', supportsXlsx: true, downloading: false, endpoint: '/v1/export/employees' },
  { key: 'attendance', title: 'Pointage', description: 'Registre de presence avec heures et anomalies.', icon: ClipboardDocumentListIcon, format: 'csv', supportsXlsx: true, downloading: false, endpoint: '/v1/export/attendance' },
  { key: 'payslips', title: 'Bulletins de paie', description: 'Export mensuel bulletins avec details salaire.', icon: CurrencyEuroIcon, format: 'csv', supportsXlsx: false, downloading: false, endpoint: '/v1/export/pay-slips' },
  { key: 'absences', title: 'Absences & conges', description: 'Historique demandes et soldes par employe.', icon: DocumentTextIcon, format: 'csv', supportsXlsx: true, downloading: false, endpoint: '/v1/export/absences' },
  { key: 'training', title: 'Formations', description: 'Catalogue, sessions, inscriptions et progression.', icon: AcademicCapIcon, format: 'csv', supportsXlsx: false, downloading: false, endpoint: '/v1/export/training' },
  { key: 'vehicles', title: 'Vehicules', description: 'Flotte, kilometrage, maintenances.', icon: TruckIcon, format: 'csv', supportsXlsx: false, downloading: false, endpoint: '/v1/export/vehicles' },
])

const historyColumns = [
  { key: 'type', label: 'Type', sortable: true },
  { key: 'format', label: 'Format', sortable: true },
  { key: 'requested_by', label: 'Demande par', sortable: true },
  { key: 'created_at', label: 'Date', sortable: true },
  { key: 'status', label: 'Statut', sortable: true },
]

const exportStatusMap = {
  completed: { label: 'Termine', color: 'green' },
  processing: { label: 'En cours', color: 'yellow' },
  failed: { label: 'Echec', color: 'red' },
}

async function downloadReport(report) {
  report.downloading = true
  try {
    await downloadApiFile(`${report.endpoint}?format=${report.format}`, `${report.key}.${report.format}`)
  } finally {
    setTimeout(() => { report.downloading = false }, 1000)
  }
}

async function generateHrReport() {
  generatingReport.value = true
  try {
    const res = await api.get('/v1/hr-reports', { params: hrReport })
    hrReportResult.value = res.data.data || res.data || null
  } catch {
    hrReportResult.value = null
  } finally {
    generatingReport.value = false
  }
}

async function fetchHistory() {
  historyLoading.value = true
  try {
    const res = await api.get('/v1/export/history').catch(() => ({ data: { data: [] } }))
    exportHistory.value = res.data.data || res.data || []
  } finally {
    historyLoading.value = false
  }
}

onMounted(fetchHistory)
</script>
