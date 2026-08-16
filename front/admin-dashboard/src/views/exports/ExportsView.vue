<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="report in reportTypes"
        :key="report.key"
        class="rounded-lg p-5 shadow ring-1 ring-gray-200"
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
        <div class="mt-4">
          <div
            v-if="report.clientSpace"
            class="rounded-md bg-slate-100 px-3 py-2 text-xs text-slate-600 ring-1 ring-slate-200"
          >
            {{ $t('exports.clientSpaceNote', "Disponible dans l'espace client") }}
          </div>
          <div v-else class="flex items-center gap-2">
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
              {{ report.downloading ? 'Téléchargement…' : 'Télécharger' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
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

        <div v-if="hrReportError" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {{ hrReportError }}
        </div>

        <div v-if="hrReportResult" class="mt-6">
          <div class="overflow-x-auto rounded-md border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="glass-bg">
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
      :empty-message="historyError || 'Aucun export recent.'"
    >
      <template #cell-status="{ value }">
        <StatusBadge :status="value" :map="exportStatusMap" />
      </template>
      <template #row-actions="{ row }">
        <a v-if="row.download_url" :href="row.download_url" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
          Télécharger
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
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const toast = useToast()
const localeStore = useLocaleStore()

function t(key, fallback = '') {
  return translate(localeStore.current, key, fallback)
}
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'

const exportHistory = ref([])
const historyLoading = ref(false)
const historyError = ref('')
const hrReportResult = ref(null)
const generatingReport = ref(false)
const hrReportError = ref('')

const hrReport = reactive({
  type: 'headcount',
  start_date: new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0],
  end_date: new Date().toISOString().split('T')[0],
})

const reportTypes = reactive([
  // #3865 : ces exports appellent des endpoints TENANT (/v1/export/*, auth
  // employee/manager) — le super-admin n'a pas de contrat equivalent dans le
  // cockpit (/admin/*). Marques `clientSpace` : la carte affiche un etat
  // honnete au lieu d'un bouton qui echoue en 401.
  { key: 'employees', title: 'Employes', description: 'Liste complete avec postes, contrats, departements.', icon: UsersIcon, format: 'csv', supportsXlsx: true, downloading: false, endpoint: '/v1/export/employees', clientSpace: true },
  { key: 'attendance', title: 'Pointage', description: 'Registre de presence avec heures et anomalies.', icon: ClipboardDocumentListIcon, format: 'csv', supportsXlsx: true, downloading: false, endpoint: '/v1/export/attendance', clientSpace: true },
  { key: 'payslips', title: 'Bulletins de paie', description: 'Export mensuel bulletins avec details salaire.', icon: CurrencyEuroIcon, format: 'csv', supportsXlsx: false, downloading: false, endpoint: '/v1/export/pay-slips', clientSpace: true },
  { key: 'absences', title: 'Absences & conges', description: 'Historique demandes et soldes par employe.', icon: DocumentTextIcon, format: 'csv', supportsXlsx: true, downloading: false, endpoint: '/v1/export/absences', clientSpace: true },
  { key: 'training', title: 'Formations', description: 'Catalogue, sessions, inscriptions et progression.', icon: AcademicCapIcon, format: 'csv', supportsXlsx: false, downloading: false, endpoint: '/v1/export/training', clientSpace: true },
  { key: 'vehicles', title: 'Vehicules', description: 'Flotte, kilometrage, maintenances.', icon: TruckIcon, format: 'csv', supportsXlsx: false, downloading: false, endpoint: '/v1/export/vehicles', clientSpace: true },
])

const historyColumns = [
  { key: 'type', label: 'Type', sortable: true },
  { key: 'format', label: 'Format', sortable: true },
  { key: 'requested_by', label: 'Demande par', sortable: true },
  { key: 'created_at', label: 'Date', sortable: true },
  { key: 'status', label: 'Statut', sortable: true },
]

const exportStatusMap = {
  completed: { label: 'Terminé', color: 'green' },
  processing: { label: 'En cours', color: 'yellow' },
  failed: { label: 'Échec', color: 'red' },
}

async function downloadReport(report) {
  report.downloading = true
  try {
    // Issue #2710 — état de téléchargement réel (plus de setTimeout simulé).
    await downloadApiFile(`${report.endpoint}?format=${report.format}`, `${report.key}.${report.format}`)
  } catch (error) {
    console.error('Download failed:', error)
    toast.error('Erreur lors du téléchargement du rapport')
  } finally {
    report.downloading = false
  }
}

async function generateHrReport() {
  generatingReport.value = true
  hrReportError.value = ''
  try {
    const res = await api.get('/v1/admin/hr-reports', { params: hrReport })
    hrReportResult.value = res.data.data || res.data || null
  } catch (err) {
    hrReportResult.value = null
    hrReportError.value = err?.response?.data?.message || 'Impossible de générer le rapport HR (endpoint indisponible).'
  } finally {
    generatingReport.value = false
  }
}

async function fetchHistory() {
  historyLoading.value = true
  historyError.value = ''
  try {
    // Issue #2710 — un échec backend s'affiche comme une erreur explicite
    // (plus de catch silencieux qui ressemble à « aucun export »).
    const res = await api.get('/v1/export/history', { _skipAuthRedirect: true })
    exportHistory.value = res.data.data || res.data || []
  } catch (err) {
    // #3395 : état d'erreur visible + retry au lieu d'une liste vide trompeuse.
    // #3865 : l'historique des exports est un contrat TENANT (/v1/export/history)
    // — pour le super-admin, afficher un etat honnete au lieu d'une erreur generique.
    if (err?.response?.status === 401) {
      historyError.value = t('exports.historyClientOnly', "Historique des exports disponible dans l'espace client")
    } else {
      historyError.value = err?.response?.data?.message || t('exports.historyError', "Impossible de charger l'historique des exports.")
    }
  } finally {
    historyLoading.value = false
  }
}

onMounted(fetchHistory)
</script>

