<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
      <StatsCard title="Demandes en attente" :value="stats.pending" icon="ChartBarIcon" color="yellow" />
      <StatsCard title="Approuvees ce mois" :value="stats.approved_this_month" icon="ChartBarIcon" color="green" />
      <StatsCard title="Demandes (total charge)" :value="stats.total_requests" icon="UsersIcon" color="blue" />
      <StatsCard title="Solde restant (moy.)" :value="stats.avg_balance" icon="ChartBarIcon" color="purple" />
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
        {{ formatDays(row) }}
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
      <h2 class="mb-4 text-lg font-semibold text-gray-900">
        Politiques de conges
      </h2>
      <div v-if="loading" class="text-sm text-gray-500">
        Chargement...
      </div>
      <div v-else class="space-y-3">
        <div
          v-for="policy in policies"
          :key="policy.id"
          class="flex items-center justify-between rounded-md border border-gray-200 p-4"
        >
          <div>
            <p class="font-medium text-gray-900">
              {{ policy.name }}
            </p>
            <p class="text-sm text-gray-500">
              {{ formatPolicyDetail(policy) }}
            </p>
          </div>
          <span
            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
            :class="policy.active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'"
          >
            {{ policy.active ? 'Actif' : 'Inactive' }}
          </span>
        </div>
        <div v-if="policies.length === 0" class="text-sm text-gray-400">
          Aucune politique configuree.
        </div>
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

const stats = ref({
  pending: 0,
  approved_this_month: 0,
  total_requests: 0,
  avg_balance: 0,
})

const tabs = [
  { key: 'requests', label: 'Demandes' },
  { key: 'balances', label: 'Soldes' },
  { key: 'policies', label: 'Politiques' },
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
  { key: 'total_days', label: 'Potentiel', sortable: true },
  { key: 'used_days', label: 'Utilises', sortable: true },
  { key: 'remaining_days', label: 'Restants', sortable: true },
]

function computeCalendarDays(start, end) {
  if (!start || !end) return '-'
  const diff = new Date(end) - new Date(start)
  return Math.ceil(diff / (1000 * 60 * 60 * 24)) + 1
}

function formatDays(row) {
  if (row.days_count != null && row.days_count !== '') {
    return `${row.days_count}j`
  }
  const d = computeCalendarDays(row.start_date, row.end_date)
  return d === '-' ? '-' : `${d}j`
}

function formatPolicyDetail(policy) {
  const amt = policy.accrual_amount ?? '-'
  const typ = policy.accrual_type ?? '-'
  const carry = policy.carry_forward ? ' · Report autorise' : ''
  return `${amt} j (${typ})${carry}`
}

function absenceTypeName(row) {
  return row.type || row.absence_type?.name || row.absenceType?.name || '-'
}

async function fetchPaginated(resourceUrl, params = {}) {
  const rows = []
  let page = 1
  let lastPage = 1
  const perPage = params.per_page ?? 80

  do {
    const res = await api.get(resourceUrl, { params: { ...params, page, per_page: perPage } })
    rows.push(...(res.data.data || []))
    lastPage = Number(res.data.meta?.last_page) || 1
    page++
  } while (page <= lastPage && page <= 30)

  return rows
}

function mapBalanceRow(b) {
  let employeeName = ''
  if (b.employee && (b.employee.first_name || b.employee.last_name)) {
    employeeName = `${b.employee.first_name ?? ''} ${b.employee.last_name ?? ''}`.trim()
  }
  const typeLabel = (b.absence_type || b.absenceType)?.name ?? '-'
  const balance = Number(b.balance) || 0
  const used = Number(b.used) || 0
  const pending = Number(b.pending) || 0

  return {
    employee_name: employeeName || (b.employee_id ? `Employe #${b.employee_id}` : ''),
    policy_name: typeLabel,
    total_days: balance + used + pending,
    used_days: used,
    remaining_days: balance,
  }
}

function isCurrentMonthIso(iso) {
  if (!iso) return false
  const d = new Date(iso)
  const n = new Date()
  return d.getFullYear() === n.getFullYear() && d.getMonth() === n.getMonth()
}

async function fetchData() {
  loading.value = true
  error.value = ''
  try {
    const absRaw = await fetchPaginated('/v1/absences', { per_page: 80 }).catch(() => [])
    requests.value = absRaw.map(a => ({
      ...a,
      type: absenceTypeName(a),
      employee_name: a.employee_name || '-',
    }))

    let balRaw = []
    try {
      const balRes = await api.get('/v1/leave-balances')
      balRaw = balRes.data.data || []
    } catch {
      balRaw = []
    }
    balances.value = balRaw.map(mapBalanceRow)

    let polRaw = []
    try {
      const polRes = await api.get('/v1/leave-policies')
      polRaw = polRes.data.data || []
    } catch {
      polRaw = []
    }
    policies.value = polRaw

    const pending = requests.value.filter(r => r.status === 'pending')
    const approvedMonth = requests.value.filter(r => r.status === 'approved' && isCurrentMonthIso(r.updated_at))

    stats.value = {
      pending: pending.length,
      approved_this_month: approvedMonth.length,
      total_requests: requests.value.length,
      avg_balance: balances.value.length > 0
        ? Math.round(balances.value.reduce((s, b) => s + (Number(b.remaining_days) || 0), 0) / balances.value.length)
        : 0,
    }
  } catch {
    error.value = 'Impossible de charger les donnees de conges.'
  } finally {
    loading.value = false
  }
}

async function approveRequest(id) {
  try {
    await api.put(`/v1/absences/${id}/approve`)
    await fetchData()
  } catch (err) {
    console.warn('Failed to approve leave request', err)
  }
}

async function rejectRequest(id, comment) {
  try {
    await api.put(`/v1/absences/${id}/reject`, { rejected_reason: comment })
    await fetchData()
  } catch (err) {
    console.warn('Failed to reject leave request', err)
  }
}

function escapeCsvCell(value) {
  const s = String(value ?? '')
  if (/[;"'\n]/.test(s)) {
    return `"${s.replace(/"/g, '""')}"`
  }
  return s
}

function exportBalances() {
  const header = ['employe', 'politique', 'potentiel', 'utilises', 'restants']
  const lines = balances.value.map(b =>
    [b.employee_name, b.policy_name, b.total_days, b.used_days, b.remaining_days].map(escapeCsvCell).join(';'),
  )
  const bom = '\ufeff'
  const blob = new Blob([bom + [header.join(';'), ...lines].join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'leave-balances.csv'
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(fetchData)
</script>
