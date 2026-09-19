<template>
  <div class="space-y-8 animate-fade-in max-w-7xl">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
          {{ $t('accounting.ledger.title') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
          {{ $t('accounting.ledger.subtitle') }}
        </p>
      </div>
      <div class="flex flex-wrap items-end gap-3">
        <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
          {{ $t('accounting.ledger.period') }}
          <input v-model="period" type="month" class="mt-1 block rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
        </label>
        <button type="button" class="btn-secondary" :disabled="loading" @click="loadAll">
          {{ $t('accounting.ledger.apply') }}
        </button>
      </div>
    </div>

    <!-- Onglets -->
    <div class="flex gap-2 border-b border-slate-200 dark:border-slate-700">
      <button
        v-for="tab in tabs"
        :key="tab"
        type="button"
        class="px-4 py-2 text-sm font-semibold rounded-t-xl transition-colors"
        :class="activeTab === tab
          ? 'bg-white dark:bg-slate-800 text-brand-600 dark:text-brand-400 border border-b-0 border-slate-200 dark:border-slate-700'
          : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
        @click="activeTab = tab"
      >
        {{ $t(`accounting.ledger.tab_${tab}`) }}
      </button>
    </div>

    <div v-if="loading" class="glass-card p-6 text-slate-500 dark:text-slate-400">
      {{ $t('common.busy', 'Chargement…') }}
    </div>

    <!-- Journal -->
    <section v-else-if="activeTab === 'journal'" class="space-y-4">
      <div class="flex flex-wrap items-center gap-3">
        <span
          class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
          :class="journal.balanced
            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
            : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'"
        >
          {{ journal.balanced ? $t('accounting.ledger.balanced') : $t('accounting.ledger.unbalanced') }}
        </span>
        <span
          v-if="journal.closed"
          class="inline-flex items-center rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-200"
        >
          <LockClosedIcon class="mr-1 h-3.5 w-3.5" aria-hidden="true" />
          {{ $t('accounting.ledger.period_closed') }}
        </span>
        <span class="text-sm text-slate-500 dark:text-slate-400">
          {{ $t('accounting.ledger.totals') }} :
          <strong>{{ formatAmount(journal.totals?.debit ?? journal.totals?.total_debit) }}</strong> {{ $t('accounting.ledger.debit') }} /
          <strong>{{ formatAmount(journal.totals?.credit ?? journal.totals?.total_credit) }}</strong> {{ $t('accounting.ledger.credit') }}
        </span>
        <div class="ml-auto flex gap-2">
          <button type="button" class="btn-secondary" @click="exportJournalCsv">
            <ArrowDownTrayIcon class="mr-2 h-4 w-4" aria-hidden="true" />
            {{ $t('accounting.ledger.export_csv') }}
          </button>
          <button v-if="!journal.closed" type="button" class="btn-danger" @click="closePeriod">
            <LockClosedIcon class="mr-2 h-4 w-4" aria-hidden="true" />
            {{ $t('accounting.ledger.close_period') }}
          </button>
        </div>
      </div>

      <div class="glass-card p-6 overflow-x-auto">
        <p v-if="journal.entries.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
          {{ $t('accounting.ledger.empty') }}
        </p>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.ledger.col_date') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.ledger.col_piece') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.ledger.col_account') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.ledger.col_description') }}</th>
              <th class="py-2 pr-3 text-right font-semibold">{{ $t('accounting.ledger.debit') }}</th>
              <th class="py-2 text-right font-semibold">{{ $t('accounting.ledger.credit') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in journal.entries" :key="entry.id" class="border-b border-slate-100 dark:border-slate-800/60">
              <td class="py-2 pr-3 text-slate-500 dark:text-slate-400">{{ entry.date }}</td>
              <td class="py-2 pr-3 font-mono text-xs text-slate-700 dark:text-slate-300">{{ entry.piece }}</td>
              <td class="py-2 pr-3 text-slate-700 dark:text-slate-300">
                <span class="font-mono text-xs">{{ entry.account_code }}</span> — {{ entry.account_label }}
              </td>
              <td class="py-2 pr-3 text-slate-500 dark:text-slate-400">{{ entry.description }}</td>
              <td class="py-2 pr-3 text-right text-slate-700 dark:text-slate-300">{{ entry.debit ? formatAmount(entry.debit) : '' }}</td>
              <td class="py-2 text-right text-slate-700 dark:text-slate-300">{{ entry.credit ? formatAmount(entry.credit) : '' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Grand livre -->
    <section v-else-if="activeTab === 'ledger'" class="space-y-4">
      <div class="flex flex-wrap items-end gap-3">
        <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
          {{ $t('accounting.ledger.account_filter') }}
          <input
            v-model="accountCode"
            type="text"
            maxlength="20"
            :placeholder="$t('accounting.ledger.account_placeholder')"
            class="mt-1 block rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm"
          />
        </label>
        <button type="button" class="btn-secondary" @click="loadLedger(1)">{{ $t('accounting.ledger.apply') }}</button>
        <p v-if="accountCode && ledgerMeta.opening_balance !== undefined" class="ml-auto text-sm text-slate-500 dark:text-slate-400">
          {{ $t('accounting.ledger.opening_balance') }} :
          <strong class="text-slate-900 dark:text-white">{{ formatAmount(ledgerMeta.opening_balance) }}</strong>
        </p>
      </div>

      <div class="glass-card p-6 overflow-x-auto">
        <p v-if="ledgerEntries.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
          {{ $t('accounting.ledger.empty') }}
        </p>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.ledger.col_date') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.ledger.col_account') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.ledger.col_piece') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.ledger.col_description') }}</th>
              <th class="py-2 pr-3 text-right font-semibold">{{ $t('accounting.ledger.debit') }}</th>
              <th class="py-2 pr-3 text-right font-semibold">{{ $t('accounting.ledger.credit') }}</th>
              <th class="py-2 text-right font-semibold">{{ $t('accounting.ledger.running_balance') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in ledgerEntries" :key="entry.id" class="border-b border-slate-100 dark:border-slate-800/60">
              <td class="py-2 pr-3 text-slate-500 dark:text-slate-400">{{ entry.entry_date }}</td>
              <td class="py-2 pr-3 text-slate-700 dark:text-slate-300">
                <span class="font-mono text-xs">{{ entry.account_code }}</span> — {{ entry.account_label }}
              </td>
              <td class="py-2 pr-3 font-mono text-xs text-slate-700 dark:text-slate-300">{{ entry.piece }}</td>
              <td class="py-2 pr-3 text-slate-500 dark:text-slate-400">{{ entry.description }}</td>
              <td class="py-2 pr-3 text-right text-slate-700 dark:text-slate-300">{{ entry.debit ? formatAmount(entry.debit) : '' }}</td>
              <td class="py-2 pr-3 text-right text-slate-700 dark:text-slate-300">{{ entry.credit ? formatAmount(entry.credit) : '' }}</td>
              <td class="py-2 text-right font-semibold" :class="entry.running_balance < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white'">
                {{ formatAmount(entry.running_balance) }}
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="ledgerMeta.last_page > 1" class="mt-4 flex items-center justify-between text-sm text-slate-500 dark:text-slate-400">
          <button type="button" class="btn-secondary px-3 py-1.5" :disabled="ledgerMeta.current_page <= 1" @click="loadLedger(ledgerMeta.current_page - 1)">
            ← {{ $t('accounting.ledger.previous') }}
          </button>
          <span>{{ ledgerMeta.current_page }} / {{ ledgerMeta.last_page }} — {{ ledgerMeta.total }} {{ $t('accounting.ledger.entries_count') }}</span>
          <button type="button" class="btn-secondary px-3 py-1.5" :disabled="ledgerMeta.current_page >= ledgerMeta.last_page" @click="loadLedger(ledgerMeta.current_page + 1)">
            {{ $t('accounting.ledger.next') }} →
          </button>
        </div>
      </div>
    </section>

    <!-- Balance -->
    <section v-else class="space-y-4">
      <div class="flex flex-wrap items-center gap-3">
        <span
          class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
          :class="balance.balanced
            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
            : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'"
        >
          {{ balance.balanced ? $t('accounting.ledger.balanced') : $t('accounting.ledger.unbalanced') }}
        </span>
        <span class="text-sm text-slate-500 dark:text-slate-400">
          {{ $t('accounting.ledger.totals') }} :
          <strong>{{ formatAmount(balance.totals.total_debit) }}</strong> {{ $t('accounting.ledger.debit') }} /
          <strong>{{ formatAmount(balance.totals.total_credit) }}</strong> {{ $t('accounting.ledger.credit') }}
          <template v-if="balance.totals.difference">
            — {{ $t('accounting.ledger.difference') }} : <strong class="text-red-600 dark:text-red-400">{{ formatAmount(balance.totals.difference) }}</strong>
          </template>
        </span>
        <button type="button" class="btn-primary ml-auto" @click="exportFec">
          <ArrowDownTrayIcon class="mr-2 h-4 w-4" aria-hidden="true" />
          {{ $t('accounting.ledger.export_fec') }}
        </button>
      </div>

      <div class="glass-card p-6 overflow-x-auto">
        <p v-if="balance.data.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
          {{ $t('accounting.ledger.empty') }}
        </p>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.ledger.col_account') }}</th>
              <th class="py-2 pr-3 text-right font-semibold">{{ $t('accounting.ledger.debit') }}</th>
              <th class="py-2 pr-3 text-right font-semibold">{{ $t('accounting.ledger.credit') }}</th>
              <th class="py-2 text-right font-semibold">{{ $t('accounting.ledger.col_balance') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in balance.data" :key="row.account_code" class="border-b border-slate-100 dark:border-slate-800/60">
              <td class="py-2 pr-3 text-slate-700 dark:text-slate-300">
                <span class="font-mono text-xs">{{ row.account_code }}</span> — {{ row.account_label }}
              </td>
              <td class="py-2 pr-3 text-right text-slate-700 dark:text-slate-300">{{ formatAmount(row.total_debit) }}</td>
              <td class="py-2 pr-3 text-right text-slate-700 dark:text-slate-300">{{ formatAmount(row.total_credit) }}</td>
              <td class="py-2 text-right font-semibold" :class="row.balance < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white'">
                {{ formatAmount(row.balance) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ArrowDownTrayIcon, LockClosedIcon } from '@heroicons/vue/24/outline'
import api, { downloadApiFile } from '@/services/api'
import { translate, toIntlLocale } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { useToast } from 'vue-toastification'

const toast = useToast()
const localeStore = useLocaleStore()

function t(key, fallback = '') {
  return translate(localeStore.current, key, fallback)
}

const tabs = ['journal', 'ledger', 'balance']
const activeTab = ref('journal')
const loading = ref(true)

const period = ref(currentPeriod())
const accountCode = ref('')

const journal = reactive({ entries: [], totals: {}, balanced: true, closed: false })
const ledgerEntries = ref([])
const ledgerMeta = reactive({ current_page: 1, last_page: 1, total: 0, opening_balance: undefined })
const balance = reactive({ data: [], totals: {}, balanced: true })

function currentPeriod() {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
}

function formatAmount(value) {
  return new Intl.NumberFormat(toIntlLocale(localeStore.current), {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value ?? 0))
}

function errorMessage(err) {
  return err?.response?.data?.message || t('accounting.ledger.load_error')
}

async function loadJournal() {
  const { data: response } = await api.get(`/accounting/journal?period=${period.value}`)
  journal.entries = Array.isArray(response?.entries) ? response.entries : []
  journal.totals = response?.totals || {}
  journal.balanced = Boolean(response?.balanced)
  journal.closed = Boolean(response?.closed)
}

async function loadLedger(page = 1) {
  try {
    const params = new URLSearchParams()
    params.set('period', period.value)
    params.set('per_page', '25')
    params.set('page', String(page))
    if (accountCode.value.trim()) params.set('account_code', accountCode.value.trim())
    const { data: response } = await api.get(`/accounting/ledger?${params.toString()}`)
    ledgerEntries.value = Array.isArray(response?.data) ? response.data : []
    Object.assign(ledgerMeta, response?.meta || {})
  } catch (err) {
    toast.error(errorMessage(err))
  }
}

async function loadBalance() {
  const { data: response } = await api.get(`/accounting/balance?period=${period.value}`)
  balance.data = Array.isArray(response?.data) ? response.data : []
  balance.totals = response?.meta?.totals || {}
  balance.balanced = Boolean(response?.meta?.balanced)
}

async function loadAll() {
  loading.value = true
  try {
    await Promise.all([loadJournal(), loadLedger(1), loadBalance()])
  } catch (err) {
    toast.error(errorMessage(err))
  } finally {
    loading.value = false
  }
}

function exportJournalCsv() {
  downloadApiFile(`/accounting/journal/export.csv?period=${period.value}`).catch((err) => {
    toast.error(errorMessage(err))
  })
}

function exportFec() {
  downloadApiFile(`/accounting/journal/export-fec?period=${period.value}`).catch((err) => {
    toast.error(errorMessage(err))
  })
}

async function closePeriod() {
  if (!window.confirm(t('accounting.ledger.close_confirm').replace(':period', period.value))) return
  try {
    await api.post(`/accounting/journal/periods/${period.value}/close`)
    toast.success(t('accounting.ledger.closed_ok'))
    await loadAll()
  } catch (err) {
    toast.error(errorMessage(err))
  }
}

onMounted(loadAll)
</script>
