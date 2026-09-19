<template>
  <div class="space-y-8 animate-fade-in max-w-7xl">
    <!-- En-tête + action de création -->
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
          {{ $t('accounting.documents.title') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
          {{ $t('accounting.documents.subtitle') }}
        </p>
      </div>
      <button type="button" class="btn-primary" @click="openCreate">
        <PlusIcon class="mr-2 h-4 w-4" aria-hidden="true" />
        {{ $t('accounting.documents.new') }}
      </button>
    </div>

    <!-- Filtres -->
    <div class="glass-card p-4 flex flex-wrap items-end gap-3">
      <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
        {{ $t('accounting.documents.filter_type') }}
        <select v-model="filters.type" class="mt-1 block rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm">
          <option value="">{{ $t('accounting.documents.all_types') }}</option>
          <option v-for="docType in documentTypes" :key="docType" :value="docType">{{ typeLabel(docType) }}</option>
        </select>
      </label>
      <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
        {{ $t('accounting.documents.filter_status') }}
        <select v-model="filters.status" class="mt-1 block rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm">
          <option value="">{{ $t('accounting.documents.all_statuses') }}</option>
          <option v-for="status in documentStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
        </select>
      </label>
      <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
        {{ $t('accounting.documents.filter_from') }}
        <input v-model="filters.from" type="date" class="mt-1 block rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
      </label>
      <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
        {{ $t('accounting.documents.filter_to') }}
        <input v-model="filters.to" type="date" class="mt-1 block rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
      </label>
      <button type="button" class="btn-secondary" :disabled="loading" @click="load">
        {{ $t('accounting.documents.apply') }}
      </button>
    </div>

    <div v-if="loading" class="glass-card p-6 text-slate-500 dark:text-slate-400">
      {{ $t('common.busy', 'Chargement…') }}
    </div>

    <!-- Liste -->
    <section v-else class="glass-card p-6">
      <p v-if="documents.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
        {{ $t('accounting.documents.empty') }}
      </p>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.documents.col_number') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.documents.col_type') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.documents.col_contact') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.documents.col_issue_date') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.documents.col_due_date') }}</th>
              <th class="py-2 pr-3 text-right font-semibold">{{ $t('accounting.documents.col_total') }}</th>
              <th class="py-2 pr-3 text-right font-semibold">{{ $t('accounting.documents.col_paid') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.documents.col_status') }}</th>
              <th class="py-2 font-semibold text-right">{{ $t('accounting.documents.col_actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="doc in documents" :key="doc.id">
              <tr
                class="border-b border-slate-100 dark:border-slate-800/60 cursor-pointer hover:bg-slate-50/60 dark:hover:bg-slate-800/40"
                @click="toggleDetail(doc)"
              >
                <td class="py-2.5 pr-3 font-mono text-xs text-slate-700 dark:text-slate-300">{{ doc.number }}</td>
                <td class="py-2.5 pr-3 text-slate-700 dark:text-slate-300">{{ typeLabel(doc.type) }}</td>
                <td class="py-2.5 pr-3 text-slate-700 dark:text-slate-300">{{ doc.contact?.name || '—' }}</td>
                <td class="py-2.5 pr-3 text-slate-500 dark:text-slate-400">{{ dateOnly(doc.issue_date) }}</td>
                <td class="py-2.5 pr-3 text-slate-500 dark:text-slate-400">{{ dateOnly(doc.due_date) }}</td>
                <td class="py-2.5 pr-3 text-right font-semibold text-slate-900 dark:text-white">
                  {{ formatAmount(doc.total_ttc) }} {{ doc.currency }}
                </td>
                <td class="py-2.5 pr-3 text-right text-slate-500 dark:text-slate-400">{{ formatAmount(doc.paid_amount) }}</td>
                <td class="py-2.5 pr-3">
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusClass(doc.status)">
                    {{ statusLabel(doc.status) }}
                  </span>
                </td>
                <td class="py-2.5 text-right whitespace-nowrap" @click.stop>
                  <button
                    v-if="doc.status === 'draft'"
                    type="button"
                    class="btn-secondary px-2.5 py-1 text-xs"
                    :disabled="busyId === doc.id"
                    @click="sendDocument(doc)"
                  >
                    {{ $t('accounting.documents.action_send') }}
                  </button>
                  <button
                    v-if="doc.type === 'invoice' && ['sent', 'partially_paid', 'overdue', 'paid'].includes(doc.status)"
                    type="button"
                    class="btn-secondary ml-2 px-2.5 py-1 text-xs"
                    :disabled="busyId === doc.id"
                    @click="createCreditNote(doc)"
                  >
                    {{ $t('accounting.documents.action_credit_note') }}
                  </button>
                  <button
                    v-if="!['cancelled', 'paid'].includes(doc.status)"
                    type="button"
                    class="btn-danger ml-2 px-2.5 py-1 text-xs"
                    :disabled="busyId === doc.id"
                    @click="cancelDocument(doc)"
                  >
                    {{ $t('accounting.documents.action_cancel') }}
                  </button>
                </td>
              </tr>

              <!-- Détail (lignes + paiements + encaissement) -->
              <tr v-if="expandedId === doc.id" class="border-b border-slate-100 dark:border-slate-800/60 bg-slate-50/60 dark:bg-slate-800/30">
                <td colspan="9" class="p-4">
                  <div class="grid gap-6 lg:grid-cols-2">
                    <div>
                      <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $t('accounting.documents.lines_title') }}</h3>
                      <table class="mt-2 w-full text-xs">
                        <thead>
                          <tr class="text-left text-slate-500 dark:text-slate-400">
                            <th class="py-1 pr-2 font-semibold">{{ $t('accounting.documents.line_description') }}</th>
                            <th class="py-1 pr-2 text-right font-semibold">{{ $t('accounting.documents.line_quantity') }}</th>
                            <th class="py-1 pr-2 text-right font-semibold">{{ $t('accounting.documents.line_unit_price') }}</th>
                            <th class="py-1 text-right font-semibold">{{ $t('accounting.documents.line_total') }}</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="line in doc.lines || []" :key="line.id" class="text-slate-700 dark:text-slate-300">
                            <td class="py-1 pr-2">{{ line.description }}</td>
                            <td class="py-1 pr-2 text-right">{{ line.quantity }}</td>
                            <td class="py-1 pr-2 text-right">{{ formatAmount(line.unit_price) }}</td>
                            <td class="py-1 text-right">{{ formatAmount(line.total_ht ?? line.total ?? lineTotal(line)) }}</td>
                          </tr>
                        </tbody>
                      </table>
                      <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        {{ $t('accounting.documents.totals_hint') }} :
                        <span class="font-semibold">{{ formatAmount(doc.subtotal_ht) }}</span> HT ·
                        <span class="font-semibold">{{ formatAmount(doc.tax_amount) }}</span> {{ $t('accounting.documents.tax') }} ·
                        <span class="font-semibold">{{ formatAmount(doc.total_ttc) }}</span> TTC
                      </p>
                    </div>

                    <div>
                      <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $t('accounting.documents.payments_title') }}</h3>
                      <p v-if="!(doc.payments || []).length" class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        {{ $t('accounting.documents.payments_empty') }}
                      </p>
                      <ul v-else class="mt-2 space-y-1 text-xs text-slate-700 dark:text-slate-300">
                        <li v-for="payment in doc.payments" :key="payment.id" class="flex justify-between gap-2">
                          <span>{{ dateOnly(payment.received_at) }} — {{ methodLabel(payment.method) }} {{ payment.reference ? `(${payment.reference})` : '' }}</span>
                          <span class="font-semibold">{{ formatAmount(payment.amount) }}</span>
                        </li>
                      </ul>

                      <!-- Encaissement -->
                      <form
                        v-if="!['cancelled', 'paid', 'draft'].includes(doc.status)"
                        class="mt-3 flex flex-wrap items-end gap-2"
                        @submit.prevent="registerPayment(doc)"
                      >
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">
                          {{ $t('accounting.documents.payment_amount') }}
                          <input v-model.number="paymentForm.amount" type="number" step="0.01" min="0.01" required class="mt-1 w-28 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2 py-1.5 text-xs" />
                        </label>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">
                          {{ $t('accounting.documents.payment_method') }}
                          <select v-model="paymentForm.method" class="mt-1 block rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2 py-1.5 text-xs">
                            <option v-for="method in paymentMethods" :key="method" :value="method">{{ methodLabel(method) }}</option>
                          </select>
                        </label>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">
                          {{ $t('accounting.documents.payment_reference') }}
                          <input v-model="paymentForm.reference" type="text" maxlength="255" class="mt-1 w-32 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2 py-1.5 text-xs" />
                        </label>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">
                          {{ $t('accounting.documents.payment_date') }}
                          <input v-model="paymentForm.received_at" type="date" class="mt-1 block rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2 py-1.5 text-xs" />
                        </label>
                        <button type="submit" class="btn-primary px-3 py-1.5 text-xs" :disabled="busyId === doc.id">
                          {{ $t('accounting.documents.payment_submit') }}
                        </button>
                      </form>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Modale de création -->
    <div v-if="showCreate" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4" @click.self="showCreate = false">
      <div class="glass-card w-full max-w-3xl bg-white dark:bg-slate-900 p-6 my-8" role="dialog" aria-modal="true">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $t('accounting.documents.create_title') }}</h2>
        <p v-if="nextNumber" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('accounting.documents.next_number') }} : <span class="font-mono font-semibold">{{ nextNumber }}</span>
        </p>

        <form class="mt-4 space-y-4" @submit.prevent="submitCreate">
          <div class="grid gap-4 md:grid-cols-3">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ $t('accounting.documents.filter_type') }}
              <select v-model="createForm.type" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" @change="loadNextNumber">
                <option v-for="docType in documentTypes" :key="docType" :value="docType">{{ typeLabel(docType) }}</option>
              </select>
            </label>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ $t('accounting.documents.col_contact') }}
              <select v-model="createForm.contact_id" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm">
                <option :value="null">—</option>
                <option v-for="contact in contacts" :key="contact.id" :value="contact.id">{{ contact.name }}</option>
              </select>
            </label>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ $t('accounting.documents.tva_rate') }}
              <input v-model.number="createForm.tva_rate" type="number" step="0.01" min="0" max="100" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
            </label>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ $t('accounting.documents.col_issue_date') }}
              <input v-model="createForm.issue_date" type="date" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
            </label>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ $t('accounting.documents.col_due_date') }}
              <input v-model="createForm.due_date" type="date" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
            </label>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ $t('accounting.documents.notes') }}
              <input v-model="createForm.notes" type="text" maxlength="2000" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
            </label>
          </div>

          <!-- Lignes -->
          <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $t('accounting.documents.lines_title') }}</h3>
            <div v-for="(line, index) in createForm.lines" :key="index" class="mt-2 flex flex-wrap items-center gap-2">
              <input
                v-model="line.description"
                type="text"
                required
                maxlength="500"
                :placeholder="$t('accounting.documents.line_description')"
                class="min-w-0 flex-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm"
              />
              <input v-model.number="line.quantity" type="number" step="0.01" min="0" :placeholder="$t('accounting.documents.line_quantity')" class="w-20 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
              <input v-model.number="line.unit_price" type="number" step="0.01" min="0" :placeholder="$t('accounting.documents.line_unit_price')" class="w-28 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
              <span class="w-24 text-right text-sm font-semibold text-slate-700 dark:text-slate-300">{{ formatAmount(lineTotal(line)) }}</span>
              <button type="button" class="btn-danger px-2.5 py-1" :aria-label="$t('accounting.documents.remove_line')" @click="createForm.lines.splice(index, 1)">✕</button>
            </div>
            <button type="button" class="btn-secondary mt-3" @click="addLine">
              + {{ $t('accounting.documents.add_line') }}
            </button>
          </div>

          <div class="flex justify-end gap-3">
            <button type="button" class="btn-secondary" @click="showCreate = false">{{ $t('accounting.documents.cancel') }}</button>
            <button type="submit" class="btn-primary" :disabled="creating || createForm.lines.length === 0">
              {{ $t('accounting.documents.create_submit') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { PlusIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import { translate, toIntlLocale } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { useToast } from 'vue-toastification'

const toast = useToast()
const localeStore = useLocaleStore()

function t(key, fallback = '') {
  return translate(localeStore.current, key, fallback)
}

const documentTypes = ['invoice', 'proforma', 'quote', 'credit_note', 'delivery_note', 'receipt']
const documentStatuses = ['draft', 'sent', 'partially_paid', 'paid', 'overdue', 'cancelled']
const paymentMethods = ['cash', 'bank_transfer', 'check', 'card', 'other']

const loading = ref(true)
const documents = ref([])
const contacts = ref([])
const filters = reactive({ type: '', status: '', from: '', to: '' })
const expandedId = ref(null)
const busyId = ref(null)

const showCreate = ref(false)
const creating = ref(false)
const nextNumber = ref('')
const createForm = reactive({
  type: 'invoice',
  contact_id: null,
  issue_date: '',
  due_date: '',
  tva_rate: null,
  notes: '',
  lines: [{ description: '', quantity: 1, unit_price: 0 }],
})

const paymentForm = reactive({ amount: null, method: 'bank_transfer', reference: '', received_at: '' })

function formatAmount(value) {
  return new Intl.NumberFormat(toIntlLocale(localeStore.current), {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value ?? 0))
}

function dateOnly(value) {
  if (!value) return '—'
  return String(value).slice(0, 10)
}

function lineTotal(line) {
  return Number(line.quantity ?? 0) * Number(line.unit_price ?? 0) - Number(line.discount ?? 0)
}

function typeLabel(type) {
  return t(`accounting.documents.type_${type}`) || type
}

function statusLabel(status) {
  return t(`accounting.documents.status_${status}`) || status
}

function methodLabel(method) {
  return t(`accounting.documents.method_${method}`) || method
}

function statusClass(status) {
  const classes = {
    sent: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
    partially_paid: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    paid: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    overdue: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    draft: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    cancelled: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
  }
  return classes[status] || classes.draft
}

function errorMessage(err, fallbackKey) {
  return err?.response?.data?.message || t(fallbackKey, t('accounting.documents.load_error'))
}

async function load() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('per_page', '100')
    if (filters.type) params.set('type', filters.type)
    if (filters.status) params.set('status', filters.status)
    if (filters.from) params.set('from', filters.from)
    if (filters.to) params.set('to', filters.to)
    const { data: response } = await api.get(`/accounting/documents?${params.toString()}`)
    documents.value = Array.isArray(response?.data) ? response.data : []
  } catch (err) {
    toast.error(errorMessage(err, 'accounting.documents.load_error'))
  } finally {
    loading.value = false
  }
}

async function loadContacts() {
  try {
    const { data: response } = await api.get('/accounting/contacts?per_page=100')
    contacts.value = Array.isArray(response?.data) ? response.data : []
  } catch {
    contacts.value = []
  }
}

async function toggleDetail(doc) {
  if (expandedId.value === doc.id) {
    expandedId.value = null
    return
  }
  expandedId.value = doc.id
  try {
    const { data: response } = await api.get(`/accounting/documents/${doc.id}`)
    const fresh = response?.data
    if (fresh) Object.assign(doc, fresh)
  } catch (err) {
    toast.error(errorMessage(err, 'accounting.documents.load_error'))
  }
}

function openCreate() {
  showCreate.value = true
  loadNextNumber()
}

async function loadNextNumber() {
  nextNumber.value = ''
  try {
    const { data: response } = await api.get(`/accounting/documents/next-number?type=${createForm.type}`)
    nextNumber.value = response?.data?.number || ''
  } catch {
    nextNumber.value = ''
  }
}

function addLine() {
  createForm.lines.push({ description: '', quantity: 1, unit_price: 0 })
}

async function submitCreate() {
  creating.value = true
  try {
    const payload = {
      type: createForm.type,
      contact_id: createForm.contact_id || undefined,
      issue_date: createForm.issue_date || undefined,
      due_date: createForm.due_date || undefined,
      tva_rate: createForm.tva_rate ?? undefined,
      notes: createForm.notes || undefined,
      lines: createForm.lines.map((line) => ({
        description: line.description,
        quantity: line.quantity ?? undefined,
        unit_price: line.unit_price ?? undefined,
      })),
    }
    await api.post('/accounting/documents', payload)
    toast.success(t('accounting.documents.created'))
    showCreate.value = false
    createForm.lines = [{ description: '', quantity: 1, unit_price: 0 }]
    createForm.notes = ''
    await load()
  } catch (err) {
    toast.error(errorMessage(err, 'accounting.documents.create_error'))
  } finally {
    creating.value = false
  }
}

async function sendDocument(doc) {
  busyId.value = doc.id
  try {
    await api.post(`/accounting/documents/${doc.id}/send`)
    toast.success(t('accounting.documents.sent'))
    await load()
  } catch (err) {
    toast.error(errorMessage(err, 'accounting.documents.action_error'))
  } finally {
    busyId.value = null
  }
}

async function cancelDocument(doc) {
  if (!window.confirm(t('accounting.documents.cancel_confirm'))) return
  busyId.value = doc.id
  try {
    await api.post(`/accounting/documents/${doc.id}/cancel`)
    toast.success(t('accounting.documents.cancelled_ok'))
    await load()
  } catch (err) {
    toast.error(errorMessage(err, 'accounting.documents.action_error'))
  } finally {
    busyId.value = null
  }
}

async function createCreditNote(doc) {
  if (!window.confirm(t('accounting.documents.credit_note_confirm'))) return
  busyId.value = doc.id
  try {
    const { data: detail } = await api.get(`/accounting/documents/${doc.id}`)
    const lines = (detail?.data?.lines || []).map((line) => ({
      description: line.description,
      quantity: line.quantity ?? undefined,
      unit_price: line.unit_price ?? undefined,
    }))
    await api.post(`/accounting/documents/${doc.id}/credit-note`, {
      lines: lines.length > 0 ? lines : [{ description: doc.number, quantity: 1, unit_price: doc.total_ttc }],
    })
    toast.success(t('accounting.documents.credit_note_ok'))
    await load()
  } catch (err) {
    toast.error(errorMessage(err, 'accounting.documents.action_error'))
  } finally {
    busyId.value = null
  }
}

async function registerPayment(doc) {
  busyId.value = doc.id
  try {
    await api.post(`/accounting/documents/${doc.id}/payments`, {
      amount: paymentForm.amount,
      method: paymentForm.method,
      reference: paymentForm.reference || undefined,
      received_at: paymentForm.received_at || undefined,
    })
    toast.success(t('accounting.documents.payment_ok'))
    paymentForm.amount = null
    paymentForm.reference = ''
    await load()
  } catch (err) {
    toast.error(errorMessage(err, 'accounting.documents.action_error'))
  } finally {
    busyId.value = null
  }
}

onMounted(() => {
  load()
  loadContacts()
})
</script>
