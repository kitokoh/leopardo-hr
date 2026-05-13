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
        <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800" @click="viewContract(row.id)">
          Detail
        </button>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/services/api'
import StatsCard from '@/components/dashboard/StatsCard.vue'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'

const loading = ref(false)
const error = ref('')
const allContracts = ref([])
const statusFilter = ref('')

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

function viewContract(id) { /* TODO: detail modal */ }
function exportContracts() { window.open('/api/v1/export/contracts?format=csv', '_blank') }

onMounted(fetchData)
</script>
