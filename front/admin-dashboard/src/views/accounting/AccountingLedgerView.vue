<template>
  <div class="space-y-8 animate-fade-in max-w-7xl">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
          {{ t('accountingModule.ledgerTitle') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
          {{ t('accountingModule.ledgerSubtitle') }}
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <label class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300">
          {{ t('accountingModule.periodLabel') }}
          <input v-model="period" type="month" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" @change="reload" />
        </label>
        <button type="button" class="btn-primary" @click="exportFec">
          <ArrowDownTrayIcon class="mr-2 h-4 w-4" aria-hidden="true" />
          {{ t('accountingModule.ledgerExportFec') }}
        </button>
      </div>
    </div>

    <!-- Onglets -->
    <div class="flex gap-2">
      <button
        v-for="item in tabs"
        :key="item.id"
        type="button"
        class="rounded-xl px-4 py-2 text-sm font-semibold transition-all"
        :class="tab === item.id
          ? 'bg-emerald-500 text-white shadow-md'
          : 'bg-white/60 text-slate-600 hover:bg-white dark:bg-slate-800/60 dark:text-slate-300 dark:hover:bg-slate-800'"
        @click="switchTab(item.id)"
      >
        {{ item.label }}
      </button>
    </div>

    <div v-if="loading" class="glass-card p-6 text-slate-500 dark:text-slate-400">
      {{ t('accountingModule.loading') }}
    </div>

    <!-- Grand livre -->
    <section v-else-if="tab === 'ledger'" class="glass-card p-6 space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <label class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300">
          {{ t('accountingModule.ledgerAccountFilter') }}
          <input v-model="accountCode" type="text" maxlength="20" class="w-32 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" @keyup.enter="loadLedger" />
        </label>
        <p v-if="accountCode && ledgerMeta.opening_balance !== undefined" class="text-sm font-semibold text-slate-600 dark:text-slate-300">
          {{ t('accountingModule.ledgerOpening') }} : {{ formatAmount(ledgerMeta.opening_balance) }}
        </p>
      </div>
      <p v-if="ledgerEntries.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
        {{ t('accountingModule.ledgerEmpty') }}
      </p>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.ledgerDate') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.ledgerPiece') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.ledgerAccount') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.ledgerDesc') }}</th>
              <th class="py-2 pr-3 text-right font-semibold">{{ t('accountingModule.ledgerDebit') }}</th>
              <th class="py-2 pr-3 text-right font-semibold">{{ t('accountingModule.ledgerCredit') }}</th>
              <th class="py-2 text-right font-semibold">{{ t('accountingModule.ledgerBalance') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in ledgerEntries" :key="entry.id" class="border-b border-slate-100 dark:border-slate-800/60">
              <td class="py-2.5 pr-3 text-slate-500 dark:text-slate-400">{{ entry.entry_date }}</td>
              <td class="py-2.5 pr-3 font-mono text-xs text-slate-700 dark:text-slate-300">{{ entry.piece }}</td>
              <td class="py-2.5 pr-3 text-slate-600 dark:text-slate-300">{{ entry.account_code }} — {{ entry.account_label }}</td>
              <td class="py-2.5 pr-3 text-slate-500 dark:text-slate-400">{{ entry.description }}</td>
              <td class="py-2.5 pr-3 text-right text-slate-700 dark:text-slate-300">{{ formatAmount(entry.debit) }}</td>
              <td class="py-2.5 pr-3 text-right text-slate-700 dark:text-slate-300">{{ formatAmount(entry.credit) }}</td>
              <td class="py-2.5 text-right font-semibold text-slate-900 dark:text-white">{{ formatAmount(entry.running_balance) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="ledgerMeta.last_page > 1" class="flex items-center justify-end gap-2 text-sm">
        <button type="button" class="btn-secondary" :disabled="ledgerPage <= 1" @click="ledgerPage -= 1; loadLedger()">‹</button>
        <span class="text-slate-500 dark:text-slate-400">{{ ledgerPage }} / {{ ledgerMeta.last_page }}</span>
        <button type="button" class="btn-secondary" :disabled="ledgerPage >= ledgerMeta.last_page" @click="ledgerPage += 1; loadLedger()">›</button>
      </div>
    </section>

    <!-- Balance -->
    <section v-else-if="tab === 'balance'" class="glass-card p-6 space-y-4">
      <div
        class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border p-4"
        :class="balance.balanced
          ? 'border-emerald-300 bg-emerald-50/60 dark:border-emerald-800 dark:bg-emerald-950/30'
          : 'border-red-300 bg-red-50/60 dark:border-red-800 dark:bg-red-950/30'"
      >
        <p class="text-sm font-bold" :class="balance.balanced ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300'">
          {{ balance.balanced ? t('accountingModule.balanceBalanced') : t('accountingModule.balanceUnbalanced') }}
        </p>
        <p class="text-sm text-slate-600 dark:text-slate-300">
          {{ t('accountingModule.balanceTotals') }} :
          {{ t('accountingModule.ledgerDebit') }} {{ formatAmount(balance.totals?.total_debit) }} ·
          {{ t('accountingModule.ledgerCredit') }} {{ formatAmount(balance.totals?.total_credit) }} ·
          {{ t('accountingModule.balanceDifference') }} {{ formatAmount(balance.totals?.difference) }}
        </p>
      </div>
      <p v-if="balanceRows.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
        {{ t('accountingModule.balanceEmpty') }}
      </p>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.ledgerAccount') }}</th>
              <th class="py-2 pr-3 text-right font-semibold">{{ t('accountingModule.ledgerDebit') }}</th>
              <th class="py-2 pr-3 text-right font-semibold">{{ t('accountingModule.ledgerCredit') }}</th>
              <th class="py-2 text-right font-semibold">{{ t('accountingModule.ledgerBalance') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in balanceRows" :key="row.account_code" class="border-b border-slate-100 dark:border-slate-800/60">
              <td class="py-2.5 pr-3 text-slate-600 dark:text-slate-300">
                <span class="font-mono text-xs">{{ row.account_code }}</span> — {{ row.account_label }}
              </td>
              <td class="py-2.5 pr-3 text-right text-slate-700 dark:text-slate-300">{{ formatAmount(row.total_debit) }}</td>
              <td class="py-2.5 pr-3 text-right text-slate-700 dark:text-slate-300">{{ formatAmount(row.total_credit) }}</td>
              <td class="py-2.5 text-right font-semibold text-slate-900 dark:text-white">{{ formatAmount(row.balance) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Journal -->
    <section v-else class="glass-card p-6 space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
          <span
            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
            :class="journal.balanced
              ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
              : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'"
          >
            {{ journal.balanced ? t('accountingModule.lgBalanced') : t('accountingModule.lgUnbalanced') }}
          </span>
          <span
            v-if="journal.closed"
            class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
          >
            {{ t('accountingModule.lgClosed') }}
          </span>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button type="button" class="btn-secondary" @click="exportJournalCsv">
            <ArrowDownTrayIcon class="mr-2 h-4 w-4" aria-hidden="true" />
            {{ t('accountingModule.journalExportCsv') }}
          </button>
          <button v-if="!journal.closed" type="button" class="btn-secondary" @click="closeOpen = true">
            <LockClosedIcon class="mr-2 h-4 w-4" aria-hidden="true" />
            {{ t('accountingModule.journalClose') }}
          </button>
        </div>
      </div>
      <p v-if="journalEntries.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
        {{ t('accountingModule.ledgerEmpty') }}
      </p>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.ledgerDate') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.ledgerPiece') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.ledgerAccount') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.ledgerDesc') }}</th>
              <th class="py-2 pr-3 text-right font-semibold">{{ t('accountingModule.ledgerDebit') }}</th>
              <th class="py-2 text-right font-semibold">{{ t('accountingModule.ledgerCredit') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in journalEntries" :key="entry.id" class="border-b border-slate-100 dark:border-slate-800/60">
              <td class="py-2.5 pr-3 text-slate-500 dark:text-slate-400">{{ entry.date }}</td>
              <td class="py-2.5 pr-3 font-mono text-xs text-slate-700 dark:text-slate-300">{{ entry.piece }}</td>
              <td class="py-2.5 pr-3 text-slate-600 dark:text-slate-300">{{ entry.account_code }} — {{ entry.account_label }}</td>
              <td class="py-2.5 pr-3 text-slate-500 dark:text-slate-400">{{ entry.description }}</td>
              <td class="py-2.5 pr-3 text-right text-slate-700 dark:text-slate-300">{{ formatAmount(entry.debit) }}</td>
              <td class="py-2.5 text-right text-slate-700 dark:text-slate-300">{{ formatAmount(entry.credit) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Confirmation clôture de période -->
    <div v-if="closeOpen" class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4" @click.self="closeOpen = false">
      <div class="w-full max-w-md rounded-2xl glass-card p-6">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">
          {{ t('accountingModule.journalCloseConfirmTitle').replace('{period}', period) }}
        </h3>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ t('accountingModule.journalCloseConfirmBody') }}</p>
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="closeOpen = false">{{ t('accountingModule.chartCancel') }}</button>
          <button
            type="button"
            class="inline-flex items-center rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-md transition-all hover:bg-red-700 disabled:opacity-50"
            :disabled="saving"
            @click="closePeriod"
          >
            {{ t('accountingModule.journalClose') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
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

const loading = ref(true)
const saving = ref(false)
const tab = ref('ledger')
const period = ref(currentPeriod())
const accountCode = ref('')
const ledgerEntries = ref([])
const ledgerMeta = ref({})
const ledgerPage = ref(1)
const balance = ref({ totals: {}, balanced: true })
const balanceRows = ref([])
const journal = ref({ balanced: true, closed: false })
const journalEntries = ref([])
const closeOpen = ref(false)

const tabs = computed(() => [
  { id: 'ledger', label: t('accountingModule.tabLedger') },
  { id: 'balance', label: t('accountingModule.tabBalance') },
  { id: 'journal', label: t('accountingModule.tabJournal') },
])

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

function switchTab(id) {
  tab.value = id
  reload()
}

async function reload() {
  ledgerPage.value = 1
  if (tab.value === 'ledger') await loadLedger()
  else if (tab.value === 'balance') await loadBalance()
  else await loadJournal()
}

async function loadLedger() {
  loading.value = true
  try {
    const params = new URLSearchParams({ period: period.value, per_page: '50', page: String(ledgerPage.value) })
    if (accountCode.value) params.set('account_code', accountCode.value)
    const { data: response } = await api.get(`/accounting/ledger?${params.toString()}`)
    ledgerEntries.value = Array.isArray(response?.data) ? response.data : []
    ledgerMeta.value = response?.meta || {}
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.errorGeneric'))
  } finally {
    loading.value = false
  }
}

async function loadBalance() {
  loading.value = true
  try {
    const { data: response } = await api.get(`/accounting/balance?period=${period.value}`)
    balanceRows.value = Array.isArray(response?.data) ? response.data : []
    balance.value = {
      totals: response?.meta?.totals || {},
      balanced: Boolean(response?.meta?.balanced),
    }
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.errorGeneric'))
  } finally {
    loading.value = false
  }
}

async function loadJournal() {
  loading.value = true
  try {
    const { data: response } = await api.get(`/accounting/journal?period=${period.value}`)
    journal.value = { balanced: Boolean(response?.balanced), closed: Boolean(response?.closed) }
    journalEntries.value = Array.isArray(response?.entries) ? response.entries : []
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.errorGeneric'))
  } finally {
    loading.value = false
  }
}

function exportFec() {
  downloadApiFile(`/accounting/journal/export-fec?period=${period.value}`).catch((err) => {
    toast.error(err?.response?.data?.message || t('accountingModule.fecError'))
  })
}

function exportJournalCsv() {
  downloadApiFile(`/accounting/journal/export.csv?period=${period.value}`).catch((err) => {
    toast.error(err?.response?.data?.message || t('accountingModule.errorGeneric'))
  })
}

async function closePeriod() {
  saving.value = true
  try {
    await api.post(`/accounting/journal/periods/${period.value}/close`)
    toast.success(t('accountingModule.journalCloseDone').replace('{period}', period.value))
    closeOpen.value = false
    await loadJournal()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.journalCloseError'))
  } finally {
    saving.value = false
  }
}

onMounted(loadLedger)
</script>
