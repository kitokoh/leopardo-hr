<template>
  <div class="space-y-8 animate-fade-in max-w-7xl">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
          {{ t('accountingModule.docsTitle') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
          {{ t('accountingModule.docsSubtitle') }}
        </p>
      </div>
      <button type="button" class="btn-primary" @click="openCreate">
        <PlusIcon class="mr-2 h-4 w-4" aria-hidden="true" />
        {{ t('accountingModule.docsNew') }}
      </button>
    </div>

    <!-- Filtres -->
    <div class="flex flex-wrap items-center gap-3">
      <select v-model="filters.type" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm">
        <option value="">{{ t('accountingModule.docsAllTypes') }}</option>
        <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
      </select>
      <select v-model="filters.status" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm">
        <option value="">{{ t('accountingModule.docsAllStatuses') }}</option>
        <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
      </select>
      <label class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300">
        {{ t('accounting.dashboard.from') }}
        <input v-model="filters.from" type="date" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
      </label>
      <label class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300">
        {{ t('accounting.dashboard.to') }}
        <input v-model="filters.to" type="date" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
      </label>
      <button type="button" class="btn-secondary" :disabled="loading" @click="load">
        {{ t('accountingModule.docsApply') }}
      </button>
    </div>

    <div v-if="loading" class="glass-card p-6 text-slate-500 dark:text-slate-400">
      {{ t('accountingModule.loading') }}
    </div>

    <section v-else class="glass-card p-6">
      <p v-if="documents.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
        {{ t('accountingModule.docsEmpty') }}
      </p>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.docsNumber') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.docsType') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.docsContact') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.docsIssueDate') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.docsDueDate') }}</th>
              <th class="py-2 pr-3 text-right font-semibold">{{ t('accountingModule.docsTotalTtc') }}</th>
              <th class="py-2 pr-3 text-right font-semibold">{{ t('accountingModule.docsPaid') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.docsStatus') }}</th>
              <th class="py-2 text-right font-semibold">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="doc in documents" :key="doc.id" class="border-b border-slate-100 dark:border-slate-800/60">
              <td class="py-2.5 pr-3 font-mono text-xs text-slate-700 dark:text-slate-300">{{ doc.number }}</td>
              <td class="py-2.5 pr-3 text-slate-600 dark:text-slate-300">{{ typeLabel(doc.type) }}</td>
              <td class="py-2.5 pr-3 text-slate-600 dark:text-slate-300">{{ doc.contact?.name || t('accountingModule.docsNoContact') }}</td>
              <td class="py-2.5 pr-3 text-slate-500 dark:text-slate-400">{{ doc.issue_date?.slice(0, 10) }}</td>
              <td class="py-2.5 pr-3 text-slate-500 dark:text-slate-400">{{ doc.due_date?.slice(0, 10) || '—' }}</td>
              <td class="py-2.5 pr-3 text-right font-semibold text-slate-700 dark:text-slate-300">{{ formatAmount(doc.total_ttc) }} {{ doc.currency }}</td>
              <td class="py-2.5 pr-3 text-right text-slate-500 dark:text-slate-400">{{ formatAmount(doc.paid_amount) }}</td>
              <td class="py-2.5 pr-3">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusClass(doc.status)">
                  {{ statusLabel(doc.status) }}
                </span>
              </td>
              <td class="py-2.5 text-right whitespace-nowrap">
                <RowActionButton :icon="EyeIcon" :label="t('accountingModule.docsView')" tone="primary" @click="openDetail(doc)" />
                <RowActionButton
                  v-if="doc.status === 'draft'"
                  :icon="PaperAirplaneIcon"
                  :label="t('accountingModule.docsSend')"
                  tone="success"
                  @click="send(doc)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Détail document -->
    <div v-if="detail" class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4" @click.self="detail = null">
      <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl glass-card bg-white/90 dark:bg-slate-900/90 p-6 space-y-5">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">
              {{ t('accountingModule.docsDetailTitle').replace('{number}', detail.number || '') }}
            </h3>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
              {{ typeLabel(detail.type) }} · {{ detail.contact?.name || t('accountingModule.docsNoContact') }}
            </p>
          </div>
          <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusClass(detail.status)">
            {{ statusLabel(detail.status) }}
          </span>
        </div>

        <!-- Lignes -->
        <section>
          <h4 class="text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ t('accountingModule.docsLines') }}</h4>
          <table class="mt-2 w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs text-slate-500 dark:text-slate-400">
                <th class="py-1.5 pr-3 font-semibold">{{ t('accountingModule.docsLineDescription') }}</th>
                <th class="py-1.5 pr-3 text-right font-semibold">{{ t('accountingModule.docsLineQty') }}</th>
                <th class="py-1.5 pr-3 text-right font-semibold">{{ t('accountingModule.docsLineUnitPrice') }}</th>
                <th class="py-1.5 text-right font-semibold">{{ t('accountingModule.docsTotalHt') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in detail.lines || []" :key="line.id" class="border-b border-slate-100 dark:border-slate-800/60">
                <td class="py-1.5 pr-3 text-slate-700 dark:text-slate-300">{{ line.description }}</td>
                <td class="py-1.5 pr-3 text-right text-slate-500 dark:text-slate-400">{{ line.quantity }}</td>
                <td class="py-1.5 pr-3 text-right text-slate-500 dark:text-slate-400">{{ formatAmount(line.unit_price) }}</td>
                <td class="py-1.5 text-right text-slate-700 dark:text-slate-300">{{ formatAmount(line.total_ht ?? line.line_total) }}</td>
              </tr>
            </tbody>
          </table>
          <dl class="mt-3 ml-auto w-56 space-y-1 text-sm">
            <div class="flex justify-between text-slate-500 dark:text-slate-400">
              <dt>{{ t('accountingModule.docsTotalHt') }}</dt>
              <dd>{{ formatAmount(detail.subtotal_ht) }}</dd>
            </div>
            <div class="flex justify-between text-slate-500 dark:text-slate-400">
              <dt>{{ t('accountingModule.docsTax') }}</dt>
              <dd>{{ formatAmount(detail.tax_amount) }}</dd>
            </div>
            <div class="flex justify-between font-bold text-slate-900 dark:text-white">
              <dt>{{ t('accountingModule.docsTotalTtc') }}</dt>
              <dd>{{ formatAmount(detail.total_ttc) }} {{ detail.currency }}</dd>
            </div>
          </dl>
        </section>

        <!-- Paiements -->
        <section>
          <h4 class="text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ t('accountingModule.docsPayments') }}</h4>
          <p v-if="!(detail.payments || []).length" class="mt-2 text-sm text-slate-500 dark:text-slate-400">
            {{ t('accountingModule.docsNoPayments') }}
          </p>
          <table v-else class="mt-2 w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs text-slate-500 dark:text-slate-400">
                <th class="py-1.5 pr-3 font-semibold">{{ t('accountingModule.docsPaymentDate') }}</th>
                <th class="py-1.5 pr-3 text-right font-semibold">{{ t('accountingModule.docsPaymentAmount') }}</th>
                <th class="py-1.5 pr-3 font-semibold">{{ t('accountingModule.docsPaymentMethod') }}</th>
                <th class="py-1.5 font-semibold">{{ t('accountingModule.docsPaymentReference') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="payment in detail.payments" :key="payment.id" class="border-b border-slate-100 dark:border-slate-800/60">
                <td class="py-1.5 pr-3 text-slate-500 dark:text-slate-400">{{ payment.received_at?.slice(0, 10) }}</td>
                <td class="py-1.5 pr-3 text-right text-slate-700 dark:text-slate-300">{{ formatAmount(payment.amount) }}</td>
                <td class="py-1.5 pr-3 text-slate-500 dark:text-slate-400">{{ methodLabel(payment.method) }}</td>
                <td class="py-1.5 text-slate-500 dark:text-slate-400">{{ payment.reference || '—' }}</td>
              </tr>
            </tbody>
          </table>

          <!-- Enregistrer un paiement -->
          <form
            v-if="['sent', 'partially_paid', 'overdue'].includes(detail.status)"
            class="mt-3 flex flex-wrap items-end gap-2"
            @submit.prevent="addPayment"
          >
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">
              {{ t('accountingModule.docsPaymentAmount') }}
              <input v-model.number="paymentForm.amount" type="number" step="0.01" min="0.01" required class="mt-1 w-32 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
            </label>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">
              {{ t('accountingModule.docsPaymentMethod') }}
              <select v-model="paymentForm.method" class="mt-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm">
                <option v-for="option in methodOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
              </select>
            </label>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">
              {{ t('accountingModule.docsPaymentDate') }}
              <input v-model="paymentForm.received_at" type="date" class="mt-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
            </label>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">
              {{ t('accountingModule.docsPaymentReference') }}
              <input v-model="paymentForm.reference" type="text" maxlength="120" class="mt-1 w-36 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
            </label>
            <button type="submit" class="btn-secondary" :disabled="saving">{{ t('accountingModule.docsAddPayment') }}</button>
          </form>
        </section>

        <!-- Actions -->
        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200 dark:border-slate-700 pt-4">
          <button v-if="detail.status === 'draft'" type="button" class="btn-primary" :disabled="saving" @click="send(detail)">
            <PaperAirplaneIcon class="mr-2 h-4 w-4" aria-hidden="true" />
            {{ t('accountingModule.docsSend') }}
          </button>
          <button type="button" class="btn-secondary" :disabled="saving" @click="post(detail)">
            <BookOpenIcon class="mr-2 h-4 w-4" aria-hidden="true" />
            {{ t('accountingModule.docsPost') }}
          </button>
          <button
            v-if="detail.type !== 'credit_note' && detail.status !== 'cancelled'"
            type="button"
            class="btn-secondary"
            :disabled="saving"
            @click="openCreditNote(detail)"
          >
            <ReceiptRefundIcon class="mr-2 h-4 w-4" aria-hidden="true" />
            {{ t('accountingModule.docsCreditNote') }}
          </button>
          <button
            v-if="!['cancelled', 'paid'].includes(detail.status)"
            type="button"
            class="inline-flex items-center rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 transition-all hover:bg-red-100 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300 disabled:opacity-50"
            :disabled="saving"
            @click="cancelOpen = true"
          >
            <XCircleIcon class="mr-2 h-4 w-4" aria-hidden="true" />
            {{ t('accountingModule.docsCancelAction') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Annulation motivée -->
    <div v-if="cancelOpen && detail" class="fixed inset-0 z-[70] flex items-center justify-center bg-gray-900/50 p-4" @click.self="cancelOpen = false">
      <form class="w-full max-w-md rounded-2xl glass-card bg-white/90 dark:bg-slate-900/90 p-6 space-y-4" @submit.prevent="cancelDocument">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ t('accountingModule.docsCancelTitle') }}</h3>
        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
          {{ t('accountingModule.docsCancelReason') }}
          <textarea v-model="cancelReason" rows="2" maxlength="500" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm"></textarea>
        </label>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="cancelOpen = false">{{ t('accountingModule.chartCancel') }}</button>
          <button
            type="submit"
            class="inline-flex items-center rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-md transition-all hover:bg-red-700 disabled:opacity-50"
            :disabled="saving"
          >
            {{ t('accountingModule.docsCancelConfirm') }}
          </button>
        </div>
      </form>
    </div>

    <!-- Création (document ou avoir lié) -->
    <div v-if="createOpen" class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4" @click.self="createOpen = false">
      <form class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl glass-card bg-white/90 dark:bg-slate-900/90 p-6 space-y-4" @submit.prevent="create">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">
          {{ creditNoteSource
            ? t('accountingModule.docsCreditNoteTitle').replace('{number}', creditNoteSource.number || '')
            : t('accountingModule.docsCreateTitle') }}
        </h3>

        <div v-if="!creditNoteSource" class="grid gap-3 md:grid-cols-2">
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ t('accountingModule.docsType') }}
            <select v-model="createForm.type" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" @change="loadNextNumber">
              <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <span v-if="nextNumber" class="mt-1 block text-xs font-normal text-slate-400">
              {{ t('accountingModule.docsNextNumber').replace('{number}', nextNumber) }}
            </span>
          </label>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ t('accountingModule.docsContact') }}
            <select v-model="createForm.contact_id" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm">
              <option :value="null">{{ t('accountingModule.docsSelectContact') }}</option>
              <option v-for="contact in contacts" :key="contact.id" :value="contact.id">{{ contact.name }}</option>
            </select>
          </label>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ t('accountingModule.docsIssueDate') }}
            <input v-model="createForm.issue_date" type="date" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
          </label>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ t('accountingModule.docsDueDate') }}
            <input v-model="createForm.due_date" type="date" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
          </label>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ t('accountingModule.docsTvaRate') }}
            <input v-model.number="createForm.tva_rate" type="number" step="0.01" min="0" max="100" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
          </label>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ t('accountingModule.docsNotes') }}
            <input v-model="createForm.notes" type="text" maxlength="2000" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
          </label>
        </div>

        <!-- Lignes -->
        <section>
          <h4 class="text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ t('accountingModule.docsLines') }}</h4>
          <div v-for="(line, index) in createForm.lines" :key="index" class="mt-2 flex flex-wrap items-end gap-2">
            <label class="block min-w-0 flex-1 text-xs font-medium text-slate-600 dark:text-slate-300">
              {{ t('accountingModule.docsLineDescription') }}
              <input v-model="line.description" type="text" required maxlength="500" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
            </label>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">
              {{ t('accountingModule.docsLineQty') }}
              <input v-model.number="line.quantity" type="number" step="0.01" min="0" class="mt-1 w-20 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
            </label>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">
              {{ t('accountingModule.docsLineUnitPrice') }}
              <input v-model.number="line.unit_price" type="number" step="0.01" min="0" class="mt-1 w-28 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
            </label>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">
              {{ t('accountingModule.docsLineDiscount') }}
              <input v-model.number="line.discount" type="number" step="0.01" min="0" class="mt-1 w-24 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
            </label>
            <RowActionButton
              v-if="createForm.lines.length > 1"
              :icon="TrashIcon"
              :label="t('accountingModule.docsRemoveLine')"
              tone="danger"
              @click="createForm.lines.splice(index, 1)"
            />
          </div>
          <button type="button" class="btn-secondary mt-3" @click="addLine">
            <PlusIcon class="mr-2 h-4 w-4" aria-hidden="true" />
            {{ t('accountingModule.docsAddLine') }}
          </button>
        </section>

        <div class="flex justify-end gap-2 border-t border-slate-200 dark:border-slate-700 pt-4">
          <button type="button" class="btn-secondary" @click="createOpen = false">{{ t('accountingModule.chartCancel') }}</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ t('accountingModule.docsCreate') }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  BookOpenIcon,
  EyeIcon,
  PaperAirplaneIcon,
  PlusIcon,
  ReceiptRefundIcon,
  TrashIcon,
  XCircleIcon
} from '@heroicons/vue/24/outline'
import api from '@/services/api'
import RowActionButton from '@/components/common/RowActionButton.vue'
import { translate, toIntlLocale } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { useToast } from 'vue-toastification'

const toast = useToast()
const localeStore = useLocaleStore()

function t(key, fallback = '') {
  return translate(localeStore.current, key, fallback)
}

const loading = ref(true)
const saving = ref(false)
const documents = ref([])
const contacts = ref([])
const detail = ref(null)
const createOpen = ref(false)
const cancelOpen = ref(false)
const cancelReason = ref('')
const creditNoteSource = ref(null)
const nextNumber = ref('')
const filters = ref({ type: '', status: '', from: '', to: '' })
const createForm = ref(emptyForm())
const paymentForm = ref(emptyPayment())

function emptyForm() {
  return {
    type: 'invoice',
    contact_id: null,
    issue_date: '',
    due_date: '',
    tva_rate: null,
    notes: '',
    lines: [{ description: '', quantity: 1, unit_price: 0, discount: 0 }],
  }
}

function emptyPayment() {
  return { amount: null, method: 'bank_transfer', reference: '', received_at: '' }
}

const typeOptions = computed(() => [
  { value: 'invoice', label: t('accountingModule.docsTypeInvoice') },
  { value: 'proforma', label: t('accountingModule.docsTypeProforma') },
  { value: 'quote', label: t('accountingModule.docsTypeQuote') },
  { value: 'credit_note', label: t('accountingModule.docsTypeCreditNote') },
  { value: 'delivery_note', label: t('accountingModule.docsTypeDeliveryNote') },
  { value: 'receipt', label: t('accountingModule.docsTypeReceipt') },
])

const statusOptions = computed(() => [
  { value: 'draft', label: t('accountingModule.docsStatusDraft') },
  { value: 'sent', label: t('accountingModule.docsStatusSent') },
  { value: 'partially_paid', label: t('accountingModule.docsStatusPartiallyPaid') },
  { value: 'paid', label: t('accountingModule.docsStatusPaid') },
  { value: 'overdue', label: t('accountingModule.docsStatusOverdue') },
  { value: 'cancelled', label: t('accountingModule.docsStatusCancelled') },
])

const methodOptions = computed(() => [
  { value: 'bank_transfer', label: t('accountingModule.docsMethodBankTransfer') },
  { value: 'cash', label: t('accountingModule.docsMethodCash') },
  { value: 'check', label: t('accountingModule.docsMethodCheck') },
  { value: 'card', label: t('accountingModule.docsMethodCard') },
  { value: 'other', label: t('accountingModule.docsMethodOther') },
])

function typeLabel(type) {
  return typeOptions.value.find((option) => option.value === type)?.label || type
}

function statusLabel(status) {
  return statusOptions.value.find((option) => option.value === status)?.label || status
}

function methodLabel(method) {
  return methodOptions.value.find((option) => option.value === method)?.label || method
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

function formatAmount(value) {
  return new Intl.NumberFormat(toIntlLocale(localeStore.current), {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value ?? 0))
}

async function load() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    Object.entries(filters.value).forEach(([key, value]) => {
      if (value) params.set(key, value)
    })
    params.set('per_page', '50')
    const { data: response } = await api.get(`/accounting/documents?${params.toString()}`)
    documents.value = Array.isArray(response?.data) ? response.data : []
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.docsError'))
  } finally {
    loading.value = false
  }
}

async function loadContacts() {
  try {
    const { data: response } = await api.get('/accounting/contacts')
    contacts.value = Array.isArray(response?.data) ? response.data : []
  } catch {
    contacts.value = []
  }
}

async function loadNextNumber() {
  nextNumber.value = ''
  try {
    const { data: response } = await api.get(`/accounting/documents/next-number?type=${createForm.value.type}`)
    nextNumber.value = response?.data?.number || ''
  } catch {
    nextNumber.value = ''
  }
}

function openCreate() {
  creditNoteSource.value = null
  createForm.value = emptyForm()
  createOpen.value = true
  loadNextNumber()
}

function openCreditNote(doc) {
  creditNoteSource.value = doc
  createForm.value = emptyForm()
  createForm.value.lines = (doc.lines || []).map((line) => ({
    description: line.description,
    quantity: Number(line.quantity ?? 1),
    unit_price: Number(line.unit_price ?? 0),
    discount: Number(line.discount ?? 0),
  }))
  if (!createForm.value.lines.length) {
    createForm.value.lines = [{ description: '', quantity: 1, unit_price: 0, discount: 0 }]
  }
  createOpen.value = true
}

function addLine() {
  createForm.value.lines.push({ description: '', quantity: 1, unit_price: 0, discount: 0 })
}

async function create() {
  saving.value = true
  try {
    if (creditNoteSource.value) {
      await api.post(`/accounting/documents/${creditNoteSource.value.id}/credit-note`, {
        lines: createForm.value.lines,
        notes: createForm.value.notes || null,
      })
      toast.success(t('accountingModule.docsCreditNoteCreated'))
    } else {
      const payload = { ...createForm.value }
      Object.keys(payload).forEach((key) => {
        if (payload[key] === '' || payload[key] === null) delete payload[key]
      })
      await api.post('/accounting/documents', payload)
      toast.success(t('accountingModule.docsCreated'))
    }
    createOpen.value = false
    detail.value = null
    await load()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.errorGeneric'))
  } finally {
    saving.value = false
  }
}

async function openDetail(doc) {
  paymentForm.value = emptyPayment()
  try {
    const { data: response } = await api.get(`/accounting/documents/${doc.id}`)
    detail.value = response?.data || doc
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.docsError'))
  }
}

async function send(doc) {
  saving.value = true
  try {
    await api.post(`/accounting/documents/${doc.id}/send`)
    toast.success(t('accountingModule.docsSentDone'))
    if (detail.value?.id === doc.id) await openDetail(doc)
    await load()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.errorGeneric'))
  } finally {
    saving.value = false
  }
}

async function cancelDocument() {
  if (!detail.value) return
  saving.value = true
  try {
    await api.post(`/accounting/documents/${detail.value.id}/cancel`, {
      reason: cancelReason.value || null,
    })
    toast.success(t('accountingModule.docsCancelledDone'))
    cancelOpen.value = false
    cancelReason.value = ''
    await openDetail(detail.value)
    await load()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.errorGeneric'))
  } finally {
    saving.value = false
  }
}

async function post(doc) {
  saving.value = true
  try {
    const { data: response } = await api.post(`/accounting/documents/${doc.id}/journal`)
    toast.success(t('accountingModule.docsPosted').replace('{count}', String(response?.entries ?? 0)))
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.errorGeneric'))
  } finally {
    saving.value = false
  }
}

async function addPayment() {
  if (!detail.value) return
  saving.value = true
  try {
    const payload = { ...paymentForm.value }
    if (!payload.reference) delete payload.reference
    if (!payload.received_at) delete payload.received_at
    await api.post(`/accounting/documents/${detail.value.id}/payments`, payload)
    toast.success(t('accountingModule.docsPaymentAdded'))
    paymentForm.value = emptyPayment()
    await openDetail(detail.value)
    await load()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.errorGeneric'))
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  load()
  loadContacts()
})
</script>
