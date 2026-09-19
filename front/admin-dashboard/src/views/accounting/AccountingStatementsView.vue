<template>
  <div class="space-y-8 animate-fade-in max-w-6xl">
    <div>
      <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
        {{ t('accountingModule.statementsTitle') }}
      </h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
        {{ t('accountingModule.statementsSubtitle') }}
      </p>
    </div>

    <!-- Onglets -->
    <div class="flex flex-wrap gap-2">
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

    <!-- Bilan -->
    <section v-else-if="tab === 'balance-sheet'" class="glass-card p-6 space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <label class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300">
          {{ t('accountingModule.yearLabel') }}
          <input v-model.number="year" type="number" min="2000" max="2100" class="w-28 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" @change="loadBalanceSheet" />
        </label>
        <span
          v-if="balanceSheet"
          class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
          :class="balanceSheet.balanced
            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
            : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'"
        >
          {{ balanceSheet.balanced ? t('accountingModule.statementBalanced') : t('accountingModule.statementUnbalanced') }}
        </span>
      </div>

      <p v-if="!balanceSheet" class="text-sm text-slate-500 dark:text-slate-400">
        {{ t('accountingModule.statementEmpty') }}
      </p>
      <div v-else class="grid gap-6 lg:grid-cols-3">
        <div v-for="column in balanceSheetColumns" :key="column.key">
          <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ column.title }}</h2>
          <div v-for="section in column.sections" :key="section.section" class="mt-3">
            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ section.section }}</h3>
            <table class="mt-1 w-full text-sm">
              <tbody>
                <tr v-for="account in section.accounts" :key="account.code" class="border-b border-slate-100 dark:border-slate-800/60">
                  <td class="py-1.5 pr-3 text-slate-600 dark:text-slate-300">
                    <span class="font-mono text-xs">{{ account.code }}</span>
                    {{ account.label }}
                  </td>
                  <td class="py-1.5 text-right text-slate-700 dark:text-slate-300">{{ formatAmount(account.balance) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="mt-3 flex justify-between border-t border-slate-200 pt-2 font-bold text-slate-900 dark:border-slate-700 dark:text-white">
            <span>{{ column.totalLabel }}</span>
            <span>{{ formatAmount(column.total) }}</span>
          </p>
        </div>
      </div>
      <p v-if="balanceSheet" class="text-sm font-semibold text-slate-600 dark:text-slate-300">
        {{ t('accountingModule.statementResultat') }} : {{ formatAmount(balanceSheet.resultat_net) }}
      </p>
    </section>

    <!-- Compte de résultat -->
    <section v-else-if="tab === 'income'" class="glass-card p-6 space-y-5">
      <label class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300">
        {{ t('accountingModule.periodLabel') }}
        <input v-model="period" type="month" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" @change="loadIncome" />
      </label>

      <p v-if="!income" class="text-sm text-slate-500 dark:text-slate-400">
        {{ t('accountingModule.statementEmpty') }}
      </p>
      <div v-else class="grid gap-6 lg:grid-cols-2">
        <div v-for="column in incomeColumns" :key="column.key">
          <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ column.title }}</h2>
          <div v-for="section in column.sections" :key="section.section" class="mt-3">
            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ section.section }}</h3>
            <table class="mt-1 w-full text-sm">
              <tbody>
                <tr v-for="account in section.accounts" :key="account.code" class="border-b border-slate-100 dark:border-slate-800/60">
                  <td class="py-1.5 pr-3 text-slate-600 dark:text-slate-300">
                    <span class="font-mono text-xs">{{ account.code }}</span>
                    {{ account.label }}
                  </td>
                  <td class="py-1.5 text-right text-slate-700 dark:text-slate-300">{{ formatAmount(account.amount) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="mt-3 flex justify-between border-t border-slate-200 pt-2 font-bold text-slate-900 dark:border-slate-700 dark:text-white">
            <span>{{ column.title }}</span>
            <span>{{ formatAmount(column.total) }}</span>
          </p>
        </div>
      </div>
      <p v-if="income" class="text-xl font-black" :class="Number(incomeResultat) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
        {{ t('accountingModule.statementResultat') }} : {{ formatAmount(incomeResultat) }}
      </p>
    </section>

    <!-- Déclaration TVA -->
    <section v-else class="glass-card p-6 space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <label class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300">
          {{ t('accountingModule.periodLabel') }}
          <input v-model="period" type="month" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" @change="loadVat" />
        </label>
        <button type="button" class="btn-secondary" @click="exportVat">
          <ArrowDownTrayIcon class="mr-2 h-4 w-4" aria-hidden="true" />
          {{ t('accountingModule.vatExport') }}
        </button>
      </div>

      <p v-if="!vat" class="text-sm text-slate-500 dark:text-slate-400">
        {{ t('accountingModule.vatEmpty') }}
      </p>
      <template v-else>
        <div class="grid gap-4 md:grid-cols-3">
          <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-800/40">
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ t('accountingModule.vatCollected') }}</p>
            <p class="mt-1 text-2xl font-black text-slate-900 dark:text-white">{{ formatAmount(vat.collected?.tax) }}</p>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ t('accountingModule.vatBase') }} : {{ formatAmount(vat.collected?.base) }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-800/40">
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ t('accountingModule.vatDeductible') }}</p>
            <p class="mt-1 text-2xl font-black text-slate-900 dark:text-white">{{ formatAmount(vat.deductible?.tax) }}</p>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ t('accountingModule.vatBase') }} : {{ formatAmount(vat.deductible?.base) }}</p>
          </div>
          <div class="rounded-2xl border border-emerald-300 bg-emerald-50/60 p-4 dark:border-emerald-800 dark:bg-emerald-950/30">
            <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">{{ t('accountingModule.vatNet') }}</p>
            <p class="mt-1 text-2xl font-black text-emerald-700 dark:text-emerald-300">{{ formatAmount(vat.net?.tax) }}</p>
            <p class="mt-0.5 text-xs text-emerald-700/80 dark:text-emerald-400/80">{{ vat.currency }}</p>
          </div>
        </div>

        <div>
          <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ t('accountingModule.vatByRate') }}</h2>
          <div class="mt-2 overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.vatRate') }}</th>
                  <th class="py-2 pr-3 text-right font-semibold">{{ t('accountingModule.vatCollected') }} — {{ t('accountingModule.vatBase') }}</th>
                  <th class="py-2 pr-3 text-right font-semibold">{{ t('accountingModule.vatCollected') }} — {{ t('accountingModule.vatTax') }}</th>
                  <th class="py-2 pr-3 text-right font-semibold">{{ t('accountingModule.vatDeductible') }} — {{ t('accountingModule.vatBase') }}</th>
                  <th class="py-2 text-right font-semibold">{{ t('accountingModule.vatDeductible') }} — {{ t('accountingModule.vatTax') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="rate in vatRates" :key="rate.rate" class="border-b border-slate-100 dark:border-slate-800/60">
                  <td class="py-2.5 pr-3 font-semibold text-slate-700 dark:text-slate-300">{{ rate.rate }} %</td>
                  <td class="py-2.5 pr-3 text-right text-slate-600 dark:text-slate-300">{{ formatAmount(rate.collected?.base) }}</td>
                  <td class="py-2.5 pr-3 text-right text-slate-600 dark:text-slate-300">{{ formatAmount(rate.collected?.tax) }}</td>
                  <td class="py-2.5 pr-3 text-right text-slate-600 dark:text-slate-300">{{ formatAmount(rate.deductible?.base) }}</td>
                  <td class="py-2.5 text-right text-slate-600 dark:text-slate-300">{{ formatAmount(rate.deductible?.tax) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline'
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
const tab = ref('balance-sheet')
const year = ref(new Date().getFullYear())
const period = ref(currentPeriod())
const balanceSheet = ref(null)
const income = ref(null)
const vat = ref(null)

const tabs = computed(() => [
  { id: 'balance-sheet', label: t('accountingModule.tabBalanceSheet') },
  { id: 'income', label: t('accountingModule.tabIncomeStatement') },
  { id: 'vat', label: t('accountingModule.tabVat') },
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

function asSections(value) {
  // Bilan : liste de sections ; compte de résultat : {sections, total}.
  if (Array.isArray(value)) return value
  if (value && typeof value === 'object' && Array.isArray(value.sections)) return value.sections
  return []
}

const balanceSheetColumns = computed(() => {
  if (!balanceSheet.value) return []
  const data = balanceSheet.value
  return [
    {
      key: 'actif',
      title: t('accountingModule.statementActif'),
      sections: asSections(data.actif),
      total: data.total_actif,
      totalLabel: t('accountingModule.statementTotalActif'),
    },
    {
      key: 'passif',
      title: t('accountingModule.statementPassif'),
      sections: asSections(data.passif),
      total: data.total_passif,
      totalLabel: t('accountingModule.statementTotalPassif'),
    },
    {
      key: 'capitaux',
      title: t('accountingModule.statementCapitaux'),
      sections: asSections(data.capitaux_propres),
      total: data.total_capitaux,
      totalLabel: t('accountingModule.statementTotalCapitaux'),
    },
  ]
})

const incomeColumns = computed(() => {
  if (!income.value) return []
  const data = income.value
  return [
    {
      key: 'produits',
      title: t('accountingModule.chartTypeRevenue'),
      sections: asSections(data.produits),
      total: data.produits?.total ?? 0,
    },
    {
      key: 'charges',
      title: t('accountingModule.chartTypeExpense'),
      sections: asSections(data.charges),
      total: data.charges?.total ?? 0,
    },
  ]
})

const incomeResultat = computed(() => income.value?.resultat ?? 0)

const vatRates = computed(() => {
  if (!vat.value) return []
  const collected = Array.isArray(vat.value.collected?.by_rate) ? vat.value.collected.by_rate : []
  const deductible = Array.isArray(vat.value.deductible?.by_rate) ? vat.value.deductible.by_rate : []
  const rates = new Map()
  collected.forEach((row) => rates.set(String(row.rate), { rate: row.rate, collected: row, deductible: null }))
  deductible.forEach((row) => {
    const key = String(row.rate)
    if (rates.has(key)) rates.get(key).deductible = row
    else rates.set(key, { rate: row.rate, collected: null, deductible: row })
  })
  return [...rates.values()]
})

function switchTab(id) {
  tab.value = id
  if (id === 'balance-sheet') loadBalanceSheet()
  else if (id === 'income') loadIncome()
  else loadVat()
}

async function loadBalanceSheet() {
  loading.value = true
  try {
    const { data: response } = await api.get(`/accounting/statements/balance-sheet?year=${year.value}`)
    balanceSheet.value = response?.data || null
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.errorGeneric'))
  } finally {
    loading.value = false
  }
}

async function loadIncome() {
  loading.value = true
  try {
    const { data: response } = await api.get(`/accounting/statements/income-statement?period=${period.value}`)
    income.value = response?.data || null
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.errorGeneric'))
  } finally {
    loading.value = false
  }
}

async function loadVat() {
  loading.value = true
  try {
    const { data: response } = await api.get(`/accounting/reports/vat-declaration?period=${period.value}`)
    vat.value = response?.data || null
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.vatError'))
  } finally {
    loading.value = false
  }
}

function exportVat() {
  downloadApiFile(`/accounting/reports/vat-declaration?period=${period.value}&format=csv`).catch((err) => {
    toast.error(err?.response?.data?.message || t('accountingModule.vatError'))
  })
}

onMounted(loadBalanceSheet)
</script>
