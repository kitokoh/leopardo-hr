<template>
  <div class="space-y-8 animate-fade-in max-w-7xl">
    <div>
      <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
        {{ t('bankRecon.title') }}
      </h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
        {{ t('bankRecon.subtitle') }}
      </p>
    </div>

    <!-- Import de relevé -->
    <section class="glass-card p-6">
      <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ t('bankRecon.importTitle') }}</h2>
      <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="importStatement">
        <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
          {{ t('bankRecon.period') }}
          <input v-model="importForm.period" type="month" required class="mt-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
        </label>
        <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
          {{ t('bankRecon.reference') }}
          <input v-model="importForm.reference" type="text" required maxlength="120" class="mt-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
        </label>
        <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
          {{ t('bankRecon.importFile') }}
          <input
            ref="fileInput"
            type="file"
            accept=".csv,text/csv"
            required
            class="mt-1 block w-64 text-sm text-slate-500 file:mr-3 file:rounded-xl file:border-0 file:bg-emerald-500 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-emerald-600 dark:text-slate-400"
          />
        </label>
        <button type="submit" class="btn-primary" :disabled="saving">
          <ArrowUpTrayIcon class="mr-2 h-4 w-4" aria-hidden="true" />
          {{ t('bankRecon.importSubmit') }}
        </button>
      </form>
    </section>

    <div v-if="loading" class="glass-card p-6 text-slate-500 dark:text-slate-400">
      {{ t('bankRecon.loading') }}
    </div>

    <!-- Relevés -->
    <section v-else class="glass-card p-6">
      <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ t('bankRecon.statementsTitle') }}</h2>
      <p v-if="statements.length === 0" class="mt-3 text-sm text-slate-500 dark:text-slate-400">
        {{ t('bankRecon.noStatements') }}
      </p>
      <div v-else class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-3 font-semibold">{{ t('bankRecon.period') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('bankRecon.reference') }}</th>
              <th class="py-2 pr-3 text-right font-semibold">{{ t('bankRecon.opening') }}</th>
              <th class="py-2 pr-3 text-right font-semibold">{{ t('bankRecon.closingReported') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('bankRecon.status') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('bankRecon.createdAt') }}</th>
              <th class="py-2 text-right font-semibold">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="statement in statements" :key="statement.id" class="border-b border-slate-100 dark:border-slate-800/60">
              <td class="py-2.5 pr-3 font-mono text-xs text-slate-700 dark:text-slate-300">{{ statement.statement_period }}</td>
              <td class="py-2.5 pr-3 text-slate-600 dark:text-slate-300">{{ statement.import_reference }}</td>
              <td class="py-2.5 pr-3 text-right text-slate-500 dark:text-slate-400">{{ formatAmount(statement.opening_balance) }}</td>
              <td class="py-2.5 pr-3 text-right text-slate-500 dark:text-slate-400">{{ formatAmount(statement.closing_balance) }}</td>
              <td class="py-2.5 pr-3">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusClass(statement.status)">
                  {{ statusLabel(statement.status) }}
                </span>
              </td>
              <td class="py-2.5 pr-3 text-slate-500 dark:text-slate-400">{{ statement.created_at?.slice(0, 10) }}</td>
              <td class="py-2.5 text-right whitespace-nowrap">
                <RowActionButton :icon="EyeIcon" :label="t('bankRecon.view')" tone="primary" @click="openDetail(statement)" />
                <RowActionButton :icon="ArrowDownTrayIcon" :label="t('bankRecon.exportCsv')" tone="brand" @click="exportStatement(statement)" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Détail relevé + matching -->
    <section v-if="detail" class="glass-card p-6 space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">
            {{ detail.statement_period }} · {{ detail.import_reference }}
          </h2>
          <p v-if="status" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            {{ t('bankRecon.matchedLines') }} : {{ status.matched_lines ?? 0 }} / {{ status.total_lines ?? 0 }} ·
            {{ t('bankRecon.matchedAmount') }} : {{ formatAmount(status.matched_amount) }} ·
            {{ t('bankRecon.pendingAmount') }} : {{ formatAmount(status.pending_amount) }}
            <template v-if="status.closing_gap !== undefined">
              · {{ t('bankRecon.gap') }} : {{ formatAmount(status.closing_gap) }}
            </template>
          </p>
        </div>
        <button type="button" class="btn-primary" :disabled="saving" @click="autoReconcile">
          <SparklesIcon class="mr-2 h-4 w-4" aria-hidden="true" />
          {{ t('bankRecon.autoReconcile') }}
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-3 font-semibold">{{ t('bankRecon.lineDate') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('bankRecon.lineLabel') }}</th>
              <th class="py-2 pr-3 text-right font-semibold">{{ t('bankRecon.lineAmount') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('bankRecon.lineStatus') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('bankRecon.confidence') }}</th>
              <th class="py-2 font-semibold">{{ t('bankRecon.matchPayment') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="line in detail.lines || []" :key="line.id" class="border-b border-slate-100 dark:border-slate-800/60">
              <td class="py-2.5 pr-3 text-slate-500 dark:text-slate-400">{{ line.line_date }}</td>
              <td class="py-2.5 pr-3 text-slate-700 dark:text-slate-300">{{ line.label }}</td>
              <td class="py-2.5 pr-3 text-right font-semibold text-slate-700 dark:text-slate-300">{{ formatAmount(line.amount) }}</td>
              <td class="py-2.5 pr-3">
                <span
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                  :class="line.status === 'matched'
                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                    : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'"
                >
                  {{ line.status === 'matched' ? t('bankRecon.statusMatched') : t('bankRecon.statusPending') }}
                </span>
              </td>
              <td class="py-2.5 pr-3 text-slate-500 dark:text-slate-400">
                {{ line.confidence !== null && line.confidence !== undefined ? `${line.confidence}%` : '—' }}
              </td>
              <td class="py-2.5">
                <span v-if="line.status === 'matched'" class="font-mono text-xs text-slate-500 dark:text-slate-400">
                  #{{ line.matched_payment_id }}
                </span>
                <div v-else class="flex items-center gap-2">
                  <select
                    v-model="matchSelection[line.id]"
                    class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2 py-1.5 text-xs"
                  >
                    <option :value="undefined" disabled>{{ t('bankRecon.matchPayment') }}</option>
                    <option v-for="payment in paymentOptions(line)" :key="payment.id" :value="payment.id">
                      #{{ payment.id }} · {{ formatAmount(payment.amount) }} · {{ payment.received_at || '' }}
                    </option>
                  </select>
                  <button
                    type="button"
                    class="btn-secondary !px-3 !py-1.5 text-xs"
                    :disabled="saving || !matchSelection[line.id]"
                    @click="matchLine(line)"
                  >
                    {{ t('bankRecon.match') }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!payments.length" class="mt-3 text-sm text-slate-500 dark:text-slate-400">
          {{ t('bankRecon.noPayments') }}
        </p>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { ArrowDownTrayIcon, ArrowUpTrayIcon, EyeIcon, SparklesIcon } from '@heroicons/vue/24/outline'
import api, { downloadApiFile } from '@/services/api'
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
const statements = ref([])
const detail = ref(null)
const status = ref(null)
const payments = ref([])
const matchSelection = ref({})
const fileInput = ref(null)
const importForm = ref({ period: '', reference: '' })

function formatAmount(value) {
  return new Intl.NumberFormat(toIntlLocale(localeStore.current), {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value ?? 0))
}

function statusLabel(value) {
  if (value === 'reconciled') return t('bankRecon.statusReconciled')
  if (value === 'reconciling') return t('bankRecon.statusReconciling')
  return t('bankRecon.statusImported')
}

function statusClass(value) {
  if (value === 'reconciled') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
  if (value === 'reconciling') return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
  return 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300'
}

function paymentOptions(line) {
  // Le paiement proposé par le matching auto en tête de liste.
  const proposedId = line.proposed_payment_id
  const list = payments.value.filter((payment) => payment.status !== 'matched')
  if (!proposedId) return list
  return [...list].sort((a, b) => (a.id === proposedId ? -1 : b.id === proposedId ? 1 : 0))
}

async function load() {
  loading.value = true
  try {
    const { data: response } = await api.get('/accounting/bank-statements')
    statements.value = Array.isArray(response?.data) ? response.data : []
  } catch (err) {
    toast.error(err?.response?.data?.message || t('bankRecon.loadError'))
  } finally {
    loading.value = false
  }
}

async function loadPayments() {
  try {
    const { data: response } = await api.get('/accounting/payments')
    payments.value = Array.isArray(response?.data) ? response.data : []
  } catch {
    payments.value = []
  }
}

async function importStatement() {
  const file = fileInput.value?.files?.[0]
  if (!file) return
  saving.value = true
  try {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('statement_period', importForm.value.period)
    formData.append('import_reference', importForm.value.reference)
    const { data: response } = await api.post('/accounting/bank-statements/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    const result = response?.data || {}
    toast.success(
      t('bankRecon.importDone')
        .replace('{imported}', String(result.imported ?? 0))
        .replace('{skipped}', String(result.skipped ?? 0)),
    )
    importForm.value = { period: '', reference: '' }
    if (fileInput.value) fileInput.value.value = ''
    await load()
    if (result.statement) await openDetail(result.statement)
  } catch (err) {
    toast.error(err?.response?.data?.message || t('bankRecon.importError'))
  } finally {
    saving.value = false
  }
}

async function openDetail(statement) {
  matchSelection.value = {}
  try {
    const [{ data: detailResponse }, { data: statusResponse }] = await Promise.all([
      api.get(`/accounting/bank-statements/${statement.id}`),
      api.get(`/accounting/bank-statements/${statement.id}/status`),
    ])
    detail.value = detailResponse?.data || statement
    status.value = statusResponse?.data || null
  } catch (err) {
    toast.error(err?.response?.data?.message || t('bankRecon.loadError'))
  }
}

async function autoReconcile() {
  if (!detail.value) return
  saving.value = true
  try {
    await api.post(`/accounting/bank-statements/${detail.value.id}/reconcile`)
    toast.success(t('bankRecon.autoReconcileDone'))
    await Promise.all([load(), openDetail(detail.value), loadPayments()])
  } catch (err) {
    toast.error(err?.response?.data?.message || t('bankRecon.matchError'))
  } finally {
    saving.value = false
  }
}

async function matchLine(line) {
  const paymentId = matchSelection.value[line.id]
  if (!paymentId) return
  saving.value = true
  try {
    await api.post(`/accounting/bank-statement-lines/${line.id}/match`, { payment_id: paymentId })
    toast.success(t('bankRecon.matchDone'))
    await Promise.all([openDetail(detail.value), loadPayments()])
  } catch (err) {
    toast.error(err?.response?.data?.message || t('bankRecon.matchError'))
  } finally {
    saving.value = false
  }
}

function exportStatement(statement) {
  downloadApiFile(`/accounting/bank-statements/${statement.id}/export`).catch((err) => {
    toast.error(err?.response?.data?.message || t('bankRecon.exportError'))
  })
}

onMounted(() => {
  load()
  loadPayments()
})
</script>
