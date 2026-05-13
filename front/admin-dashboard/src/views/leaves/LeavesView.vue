<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
      <StatsCard title="Demandes en attente" :value="stats.pending" icon="ChartBarIcon" color="yellow" />
      <StatsCard title="Approuvees ce mois" :value="stats.approved" icon="ChartBarIcon" color="green" />
      <StatsCard title="Taux d'absence" :value="stats.absence_rate + '%'" icon="UsersIcon" color="blue" />
      <StatsCard title="Jours restants (moy)" :value="stats.avg_balance" icon="ChartBarIcon" color="purple" />
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
      v-if="activeTab === 'requests'"
      :columns="requestColumns"
      :rows="requests"
      :loading="loading"
      :error="error"
      :search-keys="['employee_name', 'type']"
      search-placeholder="Rechercher une demande..."
      default-sort="start_date"
      default-sort-dir="desc"
    >
      <template #cell-status="{ value }">
        <StatusBadge :status="value" />
      </template>
      <template #cell-days="{ row }">
        {{ computeDays(row.start_date, row.end_date) }}j
      </template>
      <template #row-actions="{ row }">
        <ApprovalWidget
          v-if="row.status === 'pending'"
          @approve="approveRequest(row.id)"
          @reject="(comment) => rejectRequest(row.id, comment)"
        />
        <span v-else class="text-sm text-gray-400">-</span>
      </template>
    </DataTable>

    <DataTable
      v-else-if="activeTab === 'balances'"
      :columns="balanceColumns"
      :rows="balances"
      :loading="loading"
      :error="error"
      :search-keys="['employee_name']"
      search-placeholder="Rechercher un employe..."
      default-sort="employee_name"
      exportable
      @export="exportBalances"
    />

    <div v-else class="rounded-lg bg-white p-6 shadow">
      <h2 class="mb-4 text-lg font-semibold text-gray-900">Politiques de conges</h2>
      <div v-if="loading" class="text-sm text-gray-500">Chargement...</div>
      <div v-else class="space-y-3">
        <div
          v-for="policy in policies"
          :key="policy.id"
          class="flex items-center justify-between rounded-md border border-gray-200 p-4"
        >
          <div>
            <p class="font-medium text-gray-900">{{ policy.name }}</p>
            <p class="text-sm text-gray-500">{{ policy.days_per_year }}j/an &middot; Accumulation : {{ policy.accrual_type }}</p>
          </div>
          <StatusBadge :status="policy.is_active ? 'active' : 'draft'" />
        </div>
        <div v-if="policies.length === 0" class="text-sm text-gray-400">Aucune politique configuree.</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import ApprovalWidget from '@/components/common/ApprovalWidget.vue'

const loading = ref(false)
const error = ref('')
const requests = ref([])
const balances = ref([])
const policies = ref([])
const activeTab = ref('requests')

const stats = ref({ pending: 0, approved: 0, absence_rate: 0, avg_balance: 0 })

const tabs = [
  { key: 'requests', label: 'Demandes' },
  { key: 'balances', label: 'Soldes' },
  { key: 'policies', label: 'Politiques' }
]

const requestColumns = [
  { key: 'employee_name', label: 'Employe', sortable: true },
  { key: 'type', label: 'Type', sortable: true },
  { key: 'start_date', label: 'Debut', sortable: true },
  { key: 'end_date', label: 'Fin', sortable: true },
  { key: 'days', label: 'Jours' },
  { key: 'status', label: 'Statut', sortable: true },
]

const balanceColumns = [
  { key: 'employee_name', label: 'Employe', sortable: true },
  { key: 'policy_name', label: 'Politique', sortable: true },
  { key: 'total_days', label: 'Total', sortable: true },
  { key: 'used_days', label: 'Utilises', sortable: true },
  { key: 'remaining_days', label: 'Restants', sortable: true },
]

function computeDays(start, end) {
  if (!start || !end) return '-'
  const diff = new Date(end) - new Date(start)
  return Math.ceil(diff / (1000 * 60 * 60 * 24)) + 1
}

async function fetchData() {
  loading.value = true
  error.value = ''
  try {
    const [absRes, balRes, polRes] = await Promise.all([
      api.get('/v1/absences'),
      api.get('/v1/leave-balances').catch(() => ({ data: { data: [] } })),
      api.get('/v1/leave-policies').catch(() => ({ data: { data: [] } })),
    ])
    requests.value = absRes.data.data || absRes.data || []
    balances.value = balRes.data.data || balRes.data || []
    policies.value = polRes.data.data || polRes.data || []
    const pending = requests.value.filter(r => r.status === 'pending')
    const approved = requests.value.filter(r => r.status === 'approved')
    stats.value = {
      pending: pending.length,
      approved: approved.length,
      absence_rate: balances.value.length > 0 ? Math.round(approved.length / balances.value.length * 100) : 0,
      avg_balance: balances.value.length > 0 ? Math.round(balances.value.reduce((s, b) => s + (b.remaining_days || 0), 0) / balances.value.length) : 0,
    }
  } catch {
    error.value = 'Impossible de charger les donnees de conges.'
  } finally {
    loading.value = false
  }
}

async function approveRequest(id) {
  try { await api.post(`/v1/absences/${id}/approve`); fetchData() } catch {}
}
async function rejectRequest(id, comment) {
  try { await api.post(`/v1/absences/${id}/reject`, { comment }); fetchData() } catch {}
}
function exportBalances() { window.open('/api/v1/export/leave-balances?format=csv', '_blank') }

onMounted(fetchData)
</script>
