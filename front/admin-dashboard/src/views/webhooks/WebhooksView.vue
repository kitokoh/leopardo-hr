<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm text-gray-500">{{ $t('webhooks.subtitle') }}</p>
      </div>
      <button
        class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        @click="showCreateModal = true"
      >
        <PlusIcon class="h-4 w-4" />
        {{ $t('webhooks.titleNew') }}
      </button>
    </div>

    <DataTable
      :columns="columns"
      :rows="webhooks"
      :loading="loading"
      :error="error"
      :search-keys="['url', 'company_name']"
      :search-placeholder="$t('webhooks.searchPlaceholder')"
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
      <template #cell-active="{ value }">
        <span :class="value ? 'text-green-600' : 'text-gray-400'" class="text-sm font-medium">
          {{ value ? $t('webhooks.active') : $t('webhooks.inactive') }}
        </span>
      </template>
      <template #cell-last_triggered_at="{ value }">
        <span v-if="value" class="text-xs text-gray-500">{{ formatDate(value) }}</span>
        <span v-else class="text-xs text-gray-400">{{ $t('webhooks.never') }}</span>
      </template>
      <template #row-actions="{ row }">
        <div class="flex justify-end gap-2">
          <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800" @click="testWebhook(row.id)">
            {{ $t('webhooks.test') }}
          </button>
          <button class="text-sm font-medium text-gray-600 hover:text-gray-800" @click="editWebhook(row)">
            {{ $t('webhooks.edit') }}
          </button>
          <button class="text-sm font-medium text-red-600 hover:text-red-800" @click="deleteWebhook(row.id)">
            {{ $t('webhooks.delete') }}
          </button>
        </div>
      </template>
    </DataTable>

    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-600 bg-opacity-50">
      <div class="w-full max-w-lg card p-6-xl">
        <h3 class="text-lg font-semibold text-gray-900">{{ editingWebhook ? $t('webhooks.titleEdit') : $t('webhooks.titleNew') }}</h3>
        <form class="mt-4 space-y-4" @submit.prevent="saveWebhook">
          <div v-if="!editingWebhook">
            <label class="block text-sm font-medium text-gray-700">{{ $t('webhooks.company') }}</label>
            <div v-if="companiesError" class="mb-2 rounded-md border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800" role="alert">
              {{ $t('webhooks.companiesLoadError') }}
              <button type="button" class="ml-1 font-semibold text-indigo-600 hover:text-indigo-800" @click="fetchCompanies">
                {{ $t('webhooks.retry') }}
              </button>
            </div>
            <select v-model="form.company_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
              <option :value="null" disabled>{{ $t('webhooks.selectCompany') }}</option>
              <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">{{ $t('webhooks.url') }}</label>
            <input v-model="form.url" type="url" required class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="https://..." />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">{{ $t('webhooks.events') }}</label>
            <div class="mt-2 grid grid-cols-2 gap-2">
              <label v-for="ev in availableEvents" :key="ev" class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" :value="ev" v-model="form.events" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                {{ ev }}
              </label>
            </div>
          </div>
          <div>
            <label class="flex items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" v-model="form.active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
              {{ $t('webhooks.active') }}
            </label>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 glass-bg-hover" @click="closeModal">
              {{ $t('webhooks.cancel') }}
            </button>
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50" :disabled="saving">
              {{ saving ? $t('webhooks.saving') : $t('webhooks.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- QA #3494 : dialog de suppression (remplace confirm() natif) -->
    <div v-if="deleteOpen" class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4" @click.self="deleteOpen = false">
      <div class="w-full max-w-md rounded-2xl glass-card p-6">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $t('webhooks.confirm_delete') }}</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">{{ $t('webhooks.confirmDeleteHint') }}</p>
        <div class="mt-4 flex justify-end gap-2">
          <button class="btn-secondary" @click="deleteOpen = false">{{ $t('webhooks.cancel') }}</button>
          <button class="btn-danger" @click="confirmDeleteWebhook">{{ $t('webhooks.delete') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const toast = useToast()
import { ref, computed, onMounted } from 'vue'
import { PlusIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import { useToast } from 'vue-toastification'
import DataTable from '@/components/common/DataTable.vue'
import { useLocaleStore } from '@/stores/locale'
import { translate, toIntlLocale } from '@/i18n/index.js'

const localeStore = useLocaleStore()

function t(key, fallback = '') {
  return translate(localeStore.current, key, fallback)
}

// #4517 : dates au format de la locale active (pas la locale du navigateur).
const formatDate = (value) =>
  new Intl.DateTimeFormat(toIntlLocale(localeStore.current), {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const webhooks = ref([])
const companies = ref([])
const companiesError = ref(false)
const showCreateModal = ref(false)
const editingWebhook = ref(null)

const form = ref({ company_id: null, url: '', events: [], active: true })

const columns = computed(() => [
  { key: 'company_name', label: t('webhooks.company'), sortable: true },
  { key: 'url', label: t('webhooks.url'), sortable: true },
  { key: 'events', label: t('webhooks.events') },
  { key: 'active', label: t('webhooks.status'), sortable: true },
  { key: 'last_triggered_at', label: t('webhooks.lastTriggered'), sortable: true },
])

// #4512 : la liste des events vient du backend (GET /admin/webhooks/events) —
// la whitelist en dur dérivait (5/8 events invalides → 422 systématique au save).
const availableEvents = ref([])

async function fetchEvents() {
  try {
    const res = await api.get('/admin/webhooks/events')
    const data = res.data?.data ?? res.data ?? []
    availableEvents.value = Array.isArray(data) ? data : []
  } catch (err) {
    // Fallback : liste canonique connue du backend (PlatformAdminWebhookController).
    availableEvents.value = [
      'employee.created', 'employee.updated',
      'leave.approved', 'leave.rejected',
      'attendance.synced', 'payroll.processed', 'payroll.validated',
      'loan.disbursed', 'expense.submitted', 'expense.approved',
      'webhook.test',
    ]
    console.warn('Failed to load webhook events from backend, using fallback', err)
  }
}

async function fetchData() {
  fetchEvents()
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/admin/webhooks') // #2634 : console cross-tenant
    webhooks.value = res.data.data || res.data || []
  } catch {
    error.value = t('webhooks.loadError')
  } finally {
    loading.value = false
  }
}

async function fetchCompanies() {
  try {
    const res = await api.get('/platform/companies')
    companies.value = res.data.data || res.data || []
    companiesError.value = false
  } catch (err) {
    // #4333 : ne pas vider la liste au catch — le select « entreprise cible »
    // devenait silencieusement vide (indistinguable d'un vide réel). État
    // d'erreur visible + retry ; l'intercepteur global toast déjà.
    companiesError.value = true
    console.warn('Failed to load target companies', err)
  }
}

function editWebhook(wh) {
  editingWebhook.value = wh
  form.value = { company_id: wh.company_id ?? null, url: wh.url, events: [...(wh.events || [])], active: wh.active }
  showCreateModal.value = true
}

function closeModal() {
  showCreateModal.value = false
  editingWebhook.value = null
  form.value = { company_id: null, url: '', events: [], active: true }
}

async function saveWebhook() {
  saving.value = true
  try {
    if (editingWebhook.value) {
      await api.put(`/admin/webhooks/${editingWebhook.value.id}`, form.value) // #2634
    } else {
      await api.post('/admin/webhooks', form.value) // #2634
    }
    closeModal()
    fetchData()
    toast.success(t('webhooks.saved'))
  } catch (err) {
    console.warn('Failed to save webhook', err)
    toast.error(t('webhooks.saveError'))
  } finally {
    saving.value = false
  }
}

async function testWebhook(id) {
  try {
    await api.post(`/admin/webhooks/${id}/test`) // #2634
    toast.success(t('webhooks.tested'))
  } catch (err) {
    console.warn('Failed to test webhook', err)
    toast.error(t('webhooks.testError'))
  }
}

const deleteOpen = ref(false)
const deleteTarget = ref(null)

async function deleteWebhook(id) {
  // QA #3494 : confirm() natif (non i18n, bloque le rendu) → dialog in-app.
  deleteTarget.value = id
  deleteOpen.value = true
}

async function confirmDeleteWebhook() {
  const id = deleteTarget.value
  if (!id) return
  deleteOpen.value = false
  try {
    await api.delete(`/admin/webhooks/${id}`) // #2634
    fetchData()
    toast.success(t('webhooks.deleted'))
  } catch (err) {
    console.warn('Failed to delete webhook', err)
    toast.error(t('webhooks.deleteError'))
  }
}

onMounted(() => { fetchData(); fetchCompanies() })
</script>
