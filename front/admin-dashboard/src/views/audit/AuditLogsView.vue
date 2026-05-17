<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Journal d'audit</h1>
        <p class="mt-1 text-sm text-gray-500">Historique des actions et modifications.</p>
      </div>
      <button
        class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        @click="exportAuditLogs"
      >
        Exporter CSV
      </button>
    </div>

    <div class="flex flex-wrap gap-3">
      <select v-model="filters.action" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">Toutes les actions</option>
        <option value="created">Creation</option>
        <option value="updated">Modification</option>
        <option value="deleted">Suppression</option>
        <option value="login">Connexion</option>
        <option value="logout">Deconnexion</option>
        <option value="exported">Export</option>
      </select>
      <select v-model="filters.target_type" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">Tous les types</option>
        <option value="Employee">Employe</option>
        <option value="Contract">Contrat</option>
        <option value="Absence">Absence</option>
        <option value="PayrollRun">Paie</option>
        <option value="TrainingCourse">Formation</option>
        <option value="WebhookEndpoint">Webhook</option>
      </select>
      <input
        v-model="filters.search"
        type="text"
        placeholder="Rechercher par utilisateur ou cible..."
        class="w-64 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
      />
    </div>

    <DataTable
      :columns="columns"
      :rows="filteredLogs"
      :loading="loading"
      :error="error"
      :search-keys="['user_name', 'auditable_type', 'description']"
      search-placeholder="Rechercher..."
      default-sort="created_at"
      default-sort-dir="desc"
    >
      <template #cell-action="{ value }">
        <span
          class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
          :class="actionBadgeClass(value)"
        >
          {{ actionLabel(value) }}
        </span>
      </template>
      <template #cell-auditable_type="{ value }">
        <span class="text-xs text-gray-600">{{ formatType(value) }}</span>
      </template>
      <template #cell-created_at="{ value }">
        <span class="text-xs text-gray-500">{{ formatDate(value) }}</span>
      </template>
      <template #row-actions="{ row }">
        <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800" @click="viewDetail(row)">
          Detail
        </button>
      </template>
    </DataTable>

    <!-- Audit Log Detail Panel -->
    <div v-if="selectedLog" class="fixed inset-0 z-50 overflow-hidden" @click.self="closeDetail">
      <div class="absolute inset-0 bg-gray-500/50 transition-opacity" @click="closeDetail" />
      <div class="absolute inset-y-0 right-0 flex max-w-full pl-10">
        <div class="w-screen max-w-lg">
          <div class="flex h-full flex-col overflow-y-auto bg-white shadow-xl">
            <div class="border-b border-gray-200 px-6 py-4">
              <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Detail audit</h2>
                <button class="rounded-md text-gray-400 hover:text-gray-600" @click="closeDetail">
                  <span class="text-xl">&times;</span>
                </button>
              </div>
            </div>
            <div class="flex-1 px-6 py-4">
              <dl class="space-y-4">
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">Utilisateur</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ selectedLog.user_name || selectedLog.user_id }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">Action</dt>
                  <dd>
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="actionBadgeClass(selectedLog.action)">
                      {{ actionLabel(selectedLog.action) }}
                    </span>
                  </dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">Type cible</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ formatType(selectedLog.auditable_type) }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">ID cible</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ selectedLog.auditable_id }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">Date</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ formatDate(selectedLog.created_at) }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-sm text-gray-500">IP</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ selectedLog.ip_address || '-' }}</dd>
                </div>
                <div v-if="selectedLog.user_agent" class="flex justify-between">
                  <dt class="text-sm text-gray-500">User-Agent</dt>
                  <dd class="max-w-xs truncate text-sm text-gray-700">{{ selectedLog.user_agent }}</dd>
                </div>
              </dl>

              <div v-if="selectedLog.old_values || selectedLog.new_values" class="mt-6 border-t pt-4">
                <h3 class="mb-3 text-sm font-semibold text-gray-900">Modifications</h3>
                <div class="grid grid-cols-2 gap-4">
                  <div v-if="selectedLog.old_values">
                    <p class="mb-1 text-xs font-medium text-red-600">Avant</p>
                    <pre class="max-h-48 overflow-auto rounded bg-red-50 p-2 text-xs text-red-800">{{ formatJson(selectedLog.old_values) }}</pre>
                  </div>
                  <div v-if="selectedLog.new_values">
                    <p class="mb-1 text-xs font-medium text-green-600">Apres</p>
                    <pre class="max-h-48 overflow-auto rounded bg-green-50 p-2 text-xs text-green-800">{{ formatJson(selectedLog.new_values) }}</pre>
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
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'

const loading = ref(false)
const error = ref('')
const auditLogs = ref([])
const selectedLog = ref(null)
const filters = ref({ action: '', target_type: '', search: '' })

const columns = [
  { key: 'created_at', label: 'Date', sortable: true },
  { key: 'user_name', label: 'Utilisateur', sortable: true },
  { key: 'action', label: 'Action', sortable: true },
  { key: 'auditable_type', label: 'Type', sortable: true },
  { key: 'description', label: 'Description', sortable: false },
]

const filteredLogs = computed(() => {
  let result = auditLogs.value
  if (filters.value.action) {
    result = result.filter(l => l.action === filters.value.action)
  }
  if (filters.value.target_type) {
    result = result.filter(l => (l.auditable_type || '').includes(filters.value.target_type))
  }
  if (filters.value.search) {
    const q = filters.value.search.toLowerCase()
    result = result.filter(l =>
      (l.user_name || '').toLowerCase().includes(q) ||
      (l.description || '').toLowerCase().includes(q) ||
      (l.auditable_type || '').toLowerCase().includes(q)
    )
  }
  return result
})

function actionBadgeClass(action) {
  const classes = {
    created: 'bg-green-100 text-green-800',
    updated: 'bg-blue-100 text-blue-800',
    deleted: 'bg-red-100 text-red-800',
    login: 'bg-purple-100 text-purple-800',
    logout: 'bg-gray-100 text-gray-800',
    exported: 'bg-yellow-100 text-yellow-800',
  }
  return classes[action] || 'bg-gray-100 text-gray-700'
}

function actionLabel(action) {
  const labels = {
    created: 'Creation',
    updated: 'Modification',
    deleted: 'Suppression',
    login: 'Connexion',
    logout: 'Deconnexion',
    exported: 'Export',
  }
  return labels[action] || action
}

function formatType(type) {
  if (!type) return '-'
  return type.replace(/^App\\Models\\/, '').replace(/([A-Z])/g, ' $1').trim()
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatJson(data) {
  if (!data) return ''
  if (typeof data === 'string') {
    try { return JSON.stringify(JSON.parse(data), null, 2) } catch { return data }
  }
  return JSON.stringify(data, null, 2)
}

function viewDetail(log) {
  selectedLog.value = log
}

function closeDetail() {
  selectedLog.value = null
}

async function fetchAuditLogs() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/v1/audit-logs', { params: { per_page: 100 } })
    auditLogs.value = res.data.data || res.data || []
  } catch {
    error.value = 'Impossible de charger les logs d\'audit.'
  } finally {
    loading.value = false
  }
}

function exportAuditLogs() {
  window.open('/api/v1/audit-logs/export?format=csv', '_blank')
}

onMounted(fetchAuditLogs)
</script>
