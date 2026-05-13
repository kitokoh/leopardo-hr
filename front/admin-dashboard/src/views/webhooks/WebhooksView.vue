<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm text-gray-500">Configurez et surveillez vos endpoints de webhook.</p>
      </div>
      <button
        class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        @click="showCreateModal = true"
      >
        <PlusIcon class="h-4 w-4" />
        Nouveau webhook
      </button>
    </div>

    <DataTable
      :columns="columns"
      :rows="webhooks"
      :loading="loading"
      :error="error"
      :search-keys="['url', 'description']"
      search-placeholder="Rechercher un webhook..."
      default-sort="created_at"
      default-sort-dir="desc"
    >
      <template #cell-url="{ value }">
        <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-800">{{ value }}</code>
      </template>
      <template #cell-events="{ value }">
        <div class="flex flex-wrap gap-1">
          <span
            v-for="ev in (value || []).slice(0, 3)"
            :key="ev"
            class="inline-flex rounded bg-indigo-50 px-1.5 py-0.5 text-xs text-indigo-700"
          >
            {{ ev }}
          </span>
          <span v-if="(value || []).length > 3" class="text-xs text-gray-400">
            +{{ value.length - 3 }}
          </span>
        </div>
      </template>
      <template #cell-is_active="{ value }">
        <span :class="value ? 'text-green-600' : 'text-gray-400'" class="text-sm font-medium">
          {{ value ? 'Actif' : 'Inactif' }}
        </span>
      </template>
      <template #cell-last_delivery_status="{ value }">
        <StatusBadge v-if="value" :status="value" :map="deliveryStatusMap" />
        <span v-else class="text-xs text-gray-400">Jamais</span>
      </template>
      <template #row-actions="{ row }">
        <div class="flex justify-end gap-2">
          <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800" @click="testWebhook(row.id)">
            Tester
          </button>
          <button class="text-sm font-medium text-gray-600 hover:text-gray-800" @click="editWebhook(row)">
            Modifier
          </button>
          <button class="text-sm font-medium text-red-600 hover:text-red-800" @click="deleteWebhook(row.id)">
            Supprimer
          </button>
        </div>
      </template>
    </DataTable>

    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-600 bg-opacity-50">
      <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
        <h3 class="text-lg font-semibold text-gray-900">{{ editingWebhook ? 'Modifier' : 'Nouveau' }} webhook</h3>
        <form class="mt-4 space-y-4" @submit.prevent="saveWebhook">
          <div>
            <label class="block text-sm font-medium text-gray-700">URL</label>
            <input v-model="form.url" type="url" required class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="https://..." />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Description</label>
            <input v-model="form.description" type="text" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Evenements</label>
            <div class="mt-2 grid grid-cols-2 gap-2">
              <label v-for="ev in availableEvents" :key="ev" class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" :value="ev" v-model="form.events" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                {{ ev }}
              </label>
            </div>
          </div>
          <div>
            <label class="flex items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
              Actif
            </label>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="closeModal">
              Annuler
            </button>
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50" :disabled="saving">
              {{ saving ? 'Enregistrement...' : 'Enregistrer' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { PlusIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const webhooks = ref([])
const showCreateModal = ref(false)
const editingWebhook = ref(null)

const form = ref({ url: '', description: '', events: [], is_active: true })

const columns = [
  { key: 'url', label: 'URL', sortable: true },
  { key: 'description', label: 'Description' },
  { key: 'events', label: 'Evenements' },
  { key: 'is_active', label: 'Statut', sortable: true },
  { key: 'last_delivery_status', label: 'Dernier envoi', sortable: true },
]

const availableEvents = [
  'employee.created', 'employee.updated',
  'absence.created', 'absence.approved',
  'payroll.validated', 'contract.created',
  'applicant.hired', 'training.completed',
]

const deliveryStatusMap = {
  success: { label: 'Succes', color: 'green' },
  failed: { label: 'Echec', color: 'red' },
  pending: { label: 'En attente', color: 'yellow' },
}

async function fetchData() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/v1/webhooks')
    webhooks.value = res.data.data || res.data || []
  } catch {
    error.value = 'Impossible de charger les webhooks.'
  } finally {
    loading.value = false
  }
}

function editWebhook(wh) {
  editingWebhook.value = wh
  form.value = { url: wh.url, description: wh.description || '', events: [...(wh.events || [])], is_active: wh.is_active }
  showCreateModal.value = true
}

function closeModal() {
  showCreateModal.value = false
  editingWebhook.value = null
  form.value = { url: '', description: '', events: [], is_active: true }
}

async function saveWebhook() {
  saving.value = true
  try {
    if (editingWebhook.value) {
      await api.put(`/v1/webhooks/${editingWebhook.value.id}`, form.value)
    } else {
      await api.post('/v1/webhooks', form.value)
    }
    closeModal()
    fetchData()
  } catch {} finally {
    saving.value = false
  }
}

async function testWebhook(id) {
  try { await api.post(`/v1/webhooks/${id}/test`) } catch {}
}

async function deleteWebhook(id) {
  if (!confirm('Supprimer ce webhook ?')) return
  try { await api.delete(`/v1/webhooks/${id}`); fetchData() } catch {}
}

onMounted(fetchData)
</script>
