<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
      <StatsCard title="Contrats actifs" :value="stats.active" icon="UsersIcon" color="green" />
      <StatsCard title="Expirant sous 30j" :value="stats.expiring_soon" icon="ChartBarIcon" color="yellow" />
      <StatsCard title="En brouillon" :value="stats.draft" icon="ChartBarIcon" color="gray" />
      <StatsCard title="Expires" :value="stats.expired" icon="ChartBarIcon" color="red" />
    </div>

    <DataTable
      :columns="columns"
      :rows="contracts"
      :loading="loading"
      :error="error"
      :search-keys="['employee_name', 'reference', 'type']"
      search-placeholder="Rechercher un contrat..."
      default-sort="start_date"
      default-sort-dir="desc"
      exportable
      @export="exportContracts"
    >
      <template #filters>
        <select v-model="statusFilter" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
          <option value="">Tous les statuts</option>
          <option value="active">Actif</option>
          <option value="draft">Brouillon</option>
          <option value="expired">Expire</option>
          <option value="terminated">Resilie</option>
        </select>
      </template>
      <template #cell-type="{ value }">
        <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800">
          {{ value.toUpperCase() }}
        </span>
      </template>
      <template #cell-base_salary="{ row }">
        {{ formatCurrency(row.base_salary, row.currency) }}
      </template>
      <template #cell-status="{ value }">
        <StatusBadge :status="value" />
      </template>
      <template #cell-end_date="{ row }">
        <span :class="{ 'text-red-600 font-medium': isExpiringSoon(row.end_date) }">
          {{ row.end_date || 'CDI' }}
        </span>
      </template>
      <template #row-actions="{ row }">
        <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800" @click="viewContract(row)">
          Detail
        </button>
      </template>
    </DataTable>

    <!-- Contract Detail Panel -->
    <div v-if="selectedContract" class="fixed inset-0 z-50 overflow-hidden" @click.self="closeDetail">
      <div class="absolute inset-0 bg-gray-500/50 transition-opacity" @click="closeDetail" />
      <div class="absolute inset-y-0 right-0 flex max-w-full pl-10">
        <div class="w-screen max-w-lg">
          <div class="flex h-full flex-col overflow-y-auto bg-white shadow-xl">
            <div class="border-b border-gray-200 px-6 py-4">
              <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Detail contrat</h2>
                <button class="rounded-md text-gray-400 hover:text-gray-600" @click="closeDetail">
                  <span class="text-xl">&times;</span>
                </button>
              </div>
            </div>
            <div class="flex-1 px-6 py-4">
              <dl class="space-y-4">
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">Reference</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ selectedContract.reference }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">Employe</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ selectedContract.employee_name }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">Type</dt>
                  <dd>
                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800">
                      {{ (selectedContract.type || '').toUpperCase() }}
                    </span>
                  </dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">Statut</dt>
                  <dd><StatusBadge :status="selectedContract.status" /></dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">Date debut</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ selectedContract.start_date }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">Date fin</dt>
                  <dd class="text-sm font-medium" :class="isExpiringSoon(selectedContract.end_date) ? 'text-red-600' : 'text-gray-900'">
                    {{ selectedContract.end_date || 'CDI (duree indeterminee)' }}
                  </dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">Salaire de base</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ formatCurrency(selectedContract.base_salary, selectedContract.currency) }}</dd>
                </div>
                <div v-if="selectedContract.position" class="flex justify-between">
                  <dt class="text-sm text-gray-500">Poste</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ selectedContract.position }}</dd>
                </div>
                <div v-if="selectedContract.department_name" class="flex justify-between">
                  <dt class="text-sm text-gray-500">Departement</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ selectedContract.department_name }}</dd>
                </div>
                <div v-if="selectedContract.trial_end_date" class="flex justify-between">
                  <dt class="text-sm text-gray-500">Fin periode d'essai</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ selectedContract.trial_end_date }}</dd>
                </div>
                <div v-if="selectedContract.notice_period_days" class="flex justify-between">
                  <dt class="text-sm text-gray-500">Preavis (jours)</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ selectedContract.notice_period_days }}</dd>
                </div>
                <div v-if="selectedContract.notes" class="border-t pt-4">
                  <dt class="mb-1 text-sm text-gray-500">Notes</dt>
                  <dd class="text-sm text-gray-700">{{ selectedContract.notes }}</dd>
                </div>
              </dl>

              <!-- Alerts -->
              <div v-if="contractAlerts.length > 0" class="mt-6">
                <h3 class="mb-3 text-sm font-semibold text-gray-900">Alertes</h3>
                <div class="space-y-2">
                  <div
                    v-for="alert in contractAlerts"
                    :key="alert.message"
                    class="rounded-md p-3 text-sm"
                    :class="alert.level === 'danger' ? 'bg-red-50 text-red-700' : 'bg-yellow-50 text-yellow-700'"
                  >
                    {{ alert.message }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api, { downloadApiFile } from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'

const loading = ref(false)
const error = ref('')
const allContracts = ref([])
const statusFilter = ref('')
const selectedContract = ref(null)

const stats = ref({ active: 0, expiring_soon: 0, draft: 0, expired: 0 })

const columns = [
  { key: 'employee_name', label: 'Employe', sortable: true },
  { key: 'reference', label: 'Reference', sortable: true },
  { key: 'type', label: 'Type', sortable: true },
  { key: 'start_date', label: 'Debut', sortable: true },
  { key: 'end_date', label: 'Fin', sortable: true },
  { key: 'base_salary', label: 'Salaire base', sortable: true },
  { key: 'status', label: 'Statut', sortable: true },
]

const contracts = computed(() => {
  if (!statusFilter.value) return allContracts.value
  return allContracts.value.filter(c => c.status === statusFilter.value)
})

const contractAlerts = computed(() => {
  if (!selectedContract.value) return []
  const alerts = []
  const c = selectedContract.value
  if (isExpiringSoon(c.end_date)) {
    const days = Math.ceil((new Date(c.end_date) - new Date()) / (1000 * 60 * 60 * 24))
    alerts.push({ level: 'danger', message: `Ce contrat expire dans ${days} jours (${c.end_date}).` })
  }
  if (c.status === 'expired') {
    alerts.push({ level: 'danger', message: 'Ce contrat est expire. Veuillez le renouveler ou le cloturer.' })
  }
  if (c.trial_end_date && new Date(c.trial_end_date) > new Date()) {
    const trialDays = Math.ceil((new Date(c.trial_end_date) - new Date()) / (1000 * 60 * 60 * 24))
    alerts.push({ level: 'warning', message: `Periode d'essai en cours — ${trialDays} jours restants.` })
  }
  return alerts
})

function formatCurrency(value, currency = 'EUR') {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: currency || 'EUR' }).format(value || 0)
}

function isExpiringSoon(endDate) {
  if (!endDate) return false
  const diff = new Date(endDate) - new Date()
  return diff > 0 && diff < 30 * 24 * 60 * 60 * 1000
}

async function fetchData() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/v1/contracts')
    allContracts.value = res.data.data || res.data || []
    const now = new Date()
    const soon = new Date(now.getTime() + 30 * 24 * 60 * 60 * 1000)
    stats.value = {
      active: allContracts.value.filter(c => c.status === 'active').length,
      expiring_soon: allContracts.value.filter(c => c.end_date && new Date(c.end_date) <= soon && new Date(c.end_date) > now).length,
      draft: allContracts.value.filter(c => c.status === 'draft').length,
      expired: allContracts.value.filter(c => c.status === 'expired').length,
    }
  } catch {
    error.value = 'Impossible de charger les contrats.'
  } finally {
    loading.value = false
  }
}

function viewContract(contract) {
  selectedContract.value = contract
}

function closeDetail() {
  selectedContract.value = null
}

function exportContracts() {
  downloadApiFile('/v1/export/contracts?format=csv', 'contracts.csv')
}

onMounted(fetchData)
</script>
