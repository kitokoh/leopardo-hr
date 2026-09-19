<template>
  <div class="space-y-8 animate-fade-in max-w-7xl">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
          {{ $t('accounting.bank.title') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
          {{ $t('accounting.bank.subtitle') }}
        </p>
      </div>
      <button type="button" class="btn-primary" @click="showImport = true">
        <ArrowUpTrayIcon class="mr-2 h-4 w-4" aria-hidden="true" />
        {{ $t('accounting.bank.import') }}
      </button>
    </div>

    <div v-if="loading" class="glass-card p-6 text-slate-500 dark:text-slate-400">
      {{ $t('common.busy', 'Chargement…') }}
    </div>

    <template v-else>
      <!-- Liste des relevés -->
      <section class="glass-card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ $t('accounting.bank.statements_title') }}</h2>
          <select v-model="statusFilter" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" @change="load">
            <option value="">{{ $t('accounting.bank.all_statuses') }}</option>
            <option value="imported">{{ $t('accounting.bank.status_imported') }}</option>
            <option value="reconciling">{{ $t('accounting.bank.status_reconciling') }}</option>
            <option value="reconciled">{{ $t('accounting.bank.status_reconciled') }}</option>
          </select>
        </div>
        <p v-if="statements.length === 0" class="mt-3 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('accounting.bank.empty') }}
        </p>
        <div v-else class="mt-4 overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <th class="py-2 pr-3 font-semibold">{{ $t('accounting.bank.col_period') }}</th>
                <th class="py-2 pr-3 font-semibold">{{ $t('accounting.bank.col_reference') }}</th>
                <th class="py-2 pr-3 text-right font-semibold">{{ $t('accounting.bank.col_opening') }}</th>
                <th class="py-2 pr-3 text-right font-semibold">{{ $t('accounting.bank.col_closing') }}</th>
                <th class="py-2 pr-3 font-semibold">{{ $t('accounting.bank.col_status') }}</th>
                <th class="py-2 font-semibold text-right">{{ $t('accounting.bank.col_actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="statement in statements"
                :key="statement.id"
                class="border-b border-slate-100 dark:border-slate-800/60 cursor-pointer hover:bg-slate-50/60 dark:hover:bg-slate-800/40"
                :class="selected?.id === statement.id ? 'bg-brand-50/60 dark:bg-brand-900/20' : ''"
                @click="select(statement)"
              >
                <td class="py-2.5 pr-3 font-mono text-slate-700 dark:text-slate-300">{{ statement.statement_period }}</td>
                <td class="py-2.5 pr-3 text-slate-700 dark:text-slate-300">{{ statement.import_reference }}</td>
                <td class="py-2.5 pr-3 text-right text-slate-500 dark:text-slate-400">{{ formatAmount(statement.opening_balance) }}</td>
                <td class="py-2.5 pr-3 text-right text-slate-500 dark:text-slate-400">{{ formatAmount(statement.closing_balance) }}</td>
                <td class="py-2.5 pr-3">
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusClass(statement.status)">
                    {{ $t(`accounting.bank.status_${statement.status}`) || statement.status }}
                  </span>
                </td>
                <td class="py-2.5 text-right whitespace-nowrap" @click.stop>
                  <button type="button" class="btn-secondary px-2.5 py-1 text-xs" :disabled="busy" @click="autoReconcile(statement)">
                    {{ $t('accounting.bank.auto_reconcile') }}
                  </button>
                  <button type="button" class="btn-secondary ml-2 px-2.5 py-1 text-xs" @click="exportStatement(statement)">
                    {{ $t('accounting.bank.export') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Détail du relevé sélectionné -->
      <section v-if="selected" class="space-y-4">
        <!-- État de rapprochement -->
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <div class="glass-card p-5">
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $t('accounting.bank.kpi_lines') }}</p>
            <p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">
              {{ reconciliation.matched_lines ?? 0 }} / {{ reconciliation.total_lines ?? 0 }}
            </p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('accounting.bank.kpi_lines_hint') }}</p>
          </div>
          <div class="glass-card p-5">
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $t('accounting.bank.kpi_matched') }}</p>
            <p class="mt-2 text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ formatAmount(reconciliation.matched_amount) }}</p>
          </div>
          <div class="glass-card p-5">
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $t('accounting.bank.kpi_pending') }}</p>
            <p class="mt-2 text-3xl font-black text-amber-600 dark:text-amber-400">{{ formatAmount(reconciliation.pending_amount) }}</p>
          </div>
          <div class="glass-card p-5">
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $t('accounting.bank.kpi_gap') }}</p>
            <p
              class="mt-2 text-3xl font-black"
              :class="Number(reconciliation.closing_gap ?? 0) === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
            >
              {{ reconciliation.closing_gap === null || reconciliation.closing_gap === undefined ? '—' : formatAmount(reconciliation.closing_gap) }}
            </p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('accounting.bank.kpi_gap_hint') }}</p>
          </div>
        </div>

        <!-- Lignes -->
        <div class="glass-card p-6 overflow-x-auto">
          <h2 class="text-lg font-bold text-slate-900 dark:text-white">
            {{ $t('accounting.bank.lines_title') }} — {{ selected.statement_period }}
          </h2>
          <table class="mt-4 w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <th class="py-2 pr-3 font-semibold">#</th>
                <th class="py-2 pr-3 font-semibold">{{ $t('accounting.bank.col_date') }}</th>
                <th class="py-2 pr-3 font-semibold">{{ $t('accounting.bank.col_label') }}</th>
                <th class="py-2 pr-3 text-right font-semibold">{{ $t('accounting.bank.col_amount') }}</th>
                <th class="py-2 pr-3 font-semibold">{{ $t('accounting.bank.col_status') }}</th>
                <th class="py-2 font-semibold">{{ $t('accounting.bank.col_match') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in selected.lines || []" :key="line.id" class="border-b border-slate-100 dark:border-slate-800/60">
                <td class="py-2 pr-3 text-slate-400">{{ line.line_number }}</td>
                <td class="py-2 pr-3 text-slate-500 dark:text-slate-400">{{ line.line_date }}</td>
                <td class="py-2 pr-3 text-slate-700 dark:text-slate-300">{{ line.label }}</td>
                <td class="py-2 pr-3 text-right font-semibold" :class="Number(line.amount) < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white'">
                  {{ formatAmount(line.amount) }}
                </td>
                <td class="py-2 pr-3">
                  <span
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="line.status === 'matched'
                      ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                      : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'"
                  >
                    {{ line.status === 'matched' ? $t('accounting.bank.line_matched') : $t('accounting.bank.line_pending') }}
                    <template v-if="line.confidence"> · {{ line.confidence }}%</template>
                  </span>
                </td>
                <td class="py-2">
                  <span v-if="line.status === 'matched'" class="text-xs text-slate-500 dark:text-slate-400">
                    {{ $t('accounting.bank.payment') }} #{{ line.matched_payment_id }}
                  </span>
                  <div v-else class="flex items-center gap-2">
                    <select
                      v-model="manualMatch[line.id]"
                      class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2 py-1 text-xs"
                    >
                      <option :value="undefined">{{ $t('accounting.bank.select_payment') }}</option>
                      <option
                        v-for="payment in payments"
                        :key="payment.id"
                        :value="payment.id"
                      >
                        #{{ payment.id }} · {{ formatAmount(payment.amount) }} · {{ payment.received_at || '' }}{{ line.proposed_payment_id === payment.id ? ` — ${t('accounting.bank.proposed')}` : '' }}
                      </option>
                    </select>
                    <button
                      type="button"
                      class="btn-secondary px-2.5 py-1 text-xs"
                      :disabled="busy || !manualMatch[line.id]"
                      @click="matchLine(line)"
                    >
                      {{ $t('accounting.bank.match') }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <!-- Modale d'import -->
    <div v-if="showImport" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="showImport = false">
      <div class="glass-card w-full max-w-md bg-white dark:bg-slate-900 p-6" role="dialog" aria-modal="true">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $t('accounting.bank.import_title') }}</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $t('accounting.bank.import_hint') }}</p>
        <form class="mt-4 space-y-4" @submit.prevent="submitImport">
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $t('accounting.bank.import_file') }}
            <input type="file" accept=".csv,.txt" required class="mt-1 w-full text-sm" @change="onFileChange" />
          </label>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $t('accounting.bank.col_period') }}
            <input v-model="importForm.statement_period" type="month" required class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
          </label>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $t('accounting.bank.col_reference') }}
            <input v-model="importForm.import_reference" type="text" required maxlength="120" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
          </label>
          <div class="flex justify-end gap-3">
            <button type="button" class="btn-secondary" @click="showImport = false">{{ $t('accounting.bank.cancel') }}</button>
            <button type="submit" class="btn-primary" :disabled="busy || !importFile">{{ $t('accounting.bank.import_submit') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ArrowUpTrayIcon } from '@heroicons/vue/24/outline'
import api, { downloadApiFile } from '@/services/api'
import { translate, toIntlLocale } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { useToast } from 'vue-toastification'

const toast = useToast()
const localeStore = useLocaleStore()

function t(key, fallback = '') {
  return translate(localeStore.current, key, fallback)
}

const loading = ref(true)
const busy = ref(false)
const statements = ref([])
const statusFilter = ref('')
const selected = ref(null)
const reconciliation = ref({})
const payments = ref([])
const manualMatch = reactive({})

const showImport = ref(false)
const importFile = ref(null)
const importForm = reactive({ statement_period: '', import_reference: '' })

function formatAmount(value) {
  if (value === null || value === undefined) return '—'
  return new Intl.NumberFormat(toIntlLocale(localeStore.current), {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value))
}

function statusClass(status) {
  const classes = {
    imported: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
    reconciling: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    reconciled: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
  }
  return classes[status] || 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
}

function errorMessage(err) {
  return err?.response?.data?.message || t('accounting.bank.load_error')
}

async function load() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (statusFilter.value) params.set('status', statusFilter.value)
    const query = params.toString()
    const { data: response } = await api.get(`/accounting/bank-statements${query ? `?${query}` : ''}`)
    statements.value = Array.isArray(response?.data) ? response.data : []
    if (selected.value) {
      const still = statements.value.find((statement) => statement.id === selected.value.id)
      selected.value = still || null
    }
  } catch (err) {
    toast.error(errorMessage(err))
  } finally {
    loading.value = false
  }
}

async function select(statement) {
  selected.value = statement
  try {
    const [detail, status] = await Promise.all([
      api.get(`/accounting/bank-statements/${statement.id}`),
      api.get(`/accounting/bank-statements/${statement.id}/status`),
    ])
    selected.value = detail.data?.data || statement
    reconciliation.value = status.data?.data || {}
  } catch (err) {
    toast.error(errorMessage(err))
  }
  loadPayments()
}

async function loadPayments() {
  try {
    const { data: response } = await api.get('/accounting/payments?limit=200')
    payments.value = Array.isArray(response?.data) ? response.data : []
  } catch {
    payments.value = []
  }
}

async function autoReconcile(statement) {
  busy.value = true
  try {
    const { data: response } = await api.post(`/accounting/bank-statements/${statement.id}/reconcile`)
    reconciliation.value = response?.data || {}
    toast.success(t('accounting.bank.reconciled_ok'))
    await load()
    if (selected.value?.id === statement.id) await select(selected.value)
  } catch (err) {
    toast.error(errorMessage(err))
  } finally {
    busy.value = false
  }
}

async function matchLine(line) {
  const paymentId = manualMatch[line.id]
  if (!paymentId) return
  busy.value = true
  try {
    await api.post(`/accounting/bank-statement-lines/${line.id}/match`, { payment_id: paymentId })
    toast.success(t('accounting.bank.matched_ok'))
    delete manualMatch[line.id]
    if (selected.value) await select(selected.value)
  } catch (err) {
    toast.error(errorMessage(err))
  } finally {
    busy.value = false
  }
}

function exportStatement(statement) {
  downloadApiFile(`/accounting/bank-statements/${statement.id}/export`).catch((err) => {
    toast.error(errorMessage(err))
  })
}

function onFileChange(event) {
  importFile.value = event.target.files?.[0] || null
}

async function submitImport() {
  if (!importFile.value) return
  busy.value = true
  try {
    const formData = new FormData()
    formData.append('file', importFile.value)
    formData.append('statement_period', importForm.statement_period)
    formData.append('import_reference', importForm.import_reference)
    const { data: response } = await api.post('/accounting/bank-statements/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    const result = response?.data || {}
    toast.success(
      t('accounting.bank.import_ok')
        .replace(':imported', String(result.imported ?? 0))
        .replace(':skipped', String(result.skipped ?? 0)),
    )
    showImport.value = false
    importFile.value = null
    importForm.statement_period = ''
    importForm.import_reference = ''
    await load()
  } catch (err) {
    toast.error(errorMessage(err))
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>
