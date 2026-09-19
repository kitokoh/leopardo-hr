<template>
  <div class="space-y-8 animate-fade-in max-w-7xl">
    <div>
      <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
        {{ $t('accounting.statements.title') }}
      </h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
        {{ $t('accounting.statements.subtitle') }}
      </p>
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
        {{ $t(`accounting.statements.tab_${tab}`) }}
      </button>
    </div>

    <!-- TVA -->
    <section v-if="activeTab === 'vat'" class="space-y-4">
      <div class="flex flex-wrap items-end gap-3">
        <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
          {{ $t('accounting.statements.period') }}
          <input v-model="vatPeriod" type="month" class="mt-1 block rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
        </label>
        <button type="button" class="btn-secondary" :disabled="vatLoading" @click="loadVat">
          {{ $t('accounting.statements.apply') }}
        </button>
        <button type="button" class="btn-primary" @click="exportVatCsv">
          <ArrowDownTrayIcon class="mr-2 h-4 w-4" aria-hidden="true" />
          {{ $t('accounting.statements.export_csv') }}
        </button>
      </div>

      <div v-if="vatLoading" class="glass-card p-6 text-slate-500 dark:text-slate-400">
        {{ $t('common.busy', 'Chargement…') }}
      </div>
      <template v-else-if="vat">
        <div class="grid gap-4 md:grid-cols-3">
          <div class="glass-card p-5">
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $t('accounting.statements.vat_collected') }}</p>
            <p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ formatAmount(vat.collected?.tax) }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('accounting.statements.vat_base') }} : {{ formatAmount(vat.collected?.base) }}</p>
          </div>
          <div class="glass-card p-5">
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $t('accounting.statements.vat_deductible') }}</p>
            <p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ formatAmount(vat.deductible?.tax) }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('accounting.statements.vat_base') }} : {{ formatAmount(vat.deductible?.base) }}</p>
          </div>
          <div class="glass-card p-5 border" :class="Number(vat.net?.tax ?? 0) >= 0 ? 'border-amber-300/60 dark:border-amber-800/60' : 'border-emerald-300/60 dark:border-emerald-800/60'">
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $t('accounting.statements.vat_net') }}</p>
            <p class="mt-2 text-3xl font-black" :class="Number(vat.net?.tax ?? 0) >= 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'">
              {{ formatAmount(vat.net?.tax) }}
            </p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
              {{ Number(vat.net?.tax ?? 0) >= 0 ? $t('accounting.statements.vat_to_pay') : $t('accounting.statements.vat_credit') }}
              <template v-if="vat.currency"> · {{ vat.currency }}</template>
            </p>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div v-for="side in ['collected', 'deductible']" :key="side" class="glass-card p-6">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
              {{ side === 'collected' ? $t('accounting.statements.vat_collected') : $t('accounting.statements.vat_deductible') }}
              — {{ $t('accounting.statements.vat_by_rate') }}
            </h3>
            <p v-if="!(vat[side]?.by_rate || []).length" class="mt-2 text-sm text-slate-500 dark:text-slate-400">
              {{ $t('accounting.statements.empty') }}
            </p>
            <table v-else class="mt-3 w-full text-sm">
              <thead>
                <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  <th class="py-2 pr-3 font-semibold">{{ $t('accounting.statements.vat_rate') }}</th>
                  <th class="py-2 pr-3 text-right font-semibold">{{ $t('accounting.statements.vat_base') }}</th>
                  <th class="py-2 text-right font-semibold">{{ $t('accounting.statements.vat_tax') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in vat[side].by_rate" :key="row.rate" class="border-b border-slate-100 dark:border-slate-800/60">
                  <td class="py-2 pr-3 text-slate-700 dark:text-slate-300">{{ row.rate }} %</td>
                  <td class="py-2 pr-3 text-right text-slate-700 dark:text-slate-300">{{ formatAmount(row.base) }}</td>
                  <td class="py-2 text-right font-semibold text-slate-900 dark:text-white">{{ formatAmount(row.tax) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </section>

    <!-- Bilan -->
    <section v-else-if="activeTab === 'balance_sheet'" class="space-y-4">
      <div class="flex flex-wrap items-end gap-3">
        <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
          {{ $t('accounting.statements.year') }}
          <input v-model.number="sheetYear" type="number" min="2000" max="2100" class="mt-1 block w-28 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
        </label>
        <button type="button" class="btn-secondary" :disabled="sheetLoading" @click="loadBalanceSheet">
          {{ $t('accounting.statements.apply') }}
        </button>
        <span
          v-if="sheet"
          class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
          :class="sheet.balanced
            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
            : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'"
        >
          {{ sheet.balanced ? $t('accounting.statements.sheet_balanced') : $t('accounting.statements.sheet_unbalanced') }}
        </span>
      </div>

      <div v-if="sheetLoading" class="glass-card p-6 text-slate-500 dark:text-slate-400">
        {{ $t('common.busy', 'Chargement…') }}
      </div>
      <div v-else-if="sheet" class="grid gap-4 lg:grid-cols-2">
        <!-- Actif -->
        <div class="glass-card p-6">
          <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ $t('accounting.statements.assets') }}</h3>
          <div v-for="section in sheet.actif" :key="section.section" class="mt-4">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ section.section }}</p>
            <ul class="mt-1 space-y-0.5 text-sm text-slate-700 dark:text-slate-300">
              <li v-for="account in section.accounts" :key="account.code" class="flex justify-between gap-2">
                <span><span class="font-mono text-xs">{{ account.code }}</span> {{ account.label }}</span>
                <span>{{ formatAmount(account.balance) }}</span>
              </li>
            </ul>
            <p class="mt-1 flex justify-between border-t border-slate-200 dark:border-slate-700 pt-1 text-sm font-semibold text-slate-900 dark:text-white">
              <span>{{ $t('accounting.statements.section_total') }}</span><span>{{ formatAmount(section.total) }}</span>
            </p>
          </div>
          <p class="mt-4 flex justify-between rounded-xl bg-slate-100 dark:bg-slate-800 px-3 py-2 text-sm font-black text-slate-900 dark:text-white">
            <span>{{ $t('accounting.statements.total_assets') }}</span><span>{{ formatAmount(sheet.total_actif) }}</span>
          </p>
        </div>

        <!-- Passif + capitaux -->
        <div class="glass-card p-6">
          <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ $t('accounting.statements.liabilities_equity') }}</h3>
          <div v-for="section in [...sheet.passif, ...sheet.capitaux_propres]" :key="section.section" class="mt-4">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ section.section }}</p>
            <ul class="mt-1 space-y-0.5 text-sm text-slate-700 dark:text-slate-300">
              <li v-for="account in section.accounts" :key="account.code" class="flex justify-between gap-2">
                <span><span class="font-mono text-xs">{{ account.code }}</span> {{ account.label }}</span>
                <span>{{ formatAmount(account.balance) }}</span>
              </li>
            </ul>
            <p class="mt-1 flex justify-between border-t border-slate-200 dark:border-slate-700 pt-1 text-sm font-semibold text-slate-900 dark:text-white">
              <span>{{ $t('accounting.statements.section_total') }}</span><span>{{ formatAmount(section.total) }}</span>
            </p>
          </div>
          <p class="mt-4 flex justify-between rounded-xl bg-slate-100 dark:bg-slate-800 px-3 py-2 text-sm font-black text-slate-900 dark:text-white">
            <span>{{ $t('accounting.statements.total_liabilities_equity') }}</span><span>{{ formatAmount(sheet.total_passif_et_capitaux) }}</span>
          </p>
          <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
            {{ $t('accounting.statements.net_result') }} :
            <strong :class="Number(sheet.resultat_net) < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'">
              {{ formatAmount(sheet.resultat_net) }}
            </strong>
          </p>
        </div>
      </div>
    </section>

    <!-- Compte de résultat -->
    <section v-else class="space-y-4">
      <div class="flex flex-wrap items-end gap-3">
        <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
          {{ $t('accounting.statements.period') }}
          <input v-model="incomePeriod" type="month" class="mt-1 block rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
        </label>
        <button type="button" class="btn-secondary" :disabled="incomeLoading" @click="loadIncome">
          {{ $t('accounting.statements.apply') }}
        </button>
      </div>

      <div v-if="incomeLoading" class="glass-card p-6 text-slate-500 dark:text-slate-400">
        {{ $t('common.busy', 'Chargement…') }}
      </div>
      <template v-else-if="income">
        <div class="glass-card p-5 max-w-md">
          <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $t('accounting.statements.net_result') }}</p>
          <p class="mt-2 text-3xl font-black" :class="Number(income.resultat) < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'">
            {{ formatAmount(income.resultat) }}
          </p>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div v-for="side in ['produits', 'charges']" :key="side" class="glass-card p-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">
              {{ side === 'produits' ? $t('accounting.statements.revenues') : $t('accounting.statements.expenses') }}
            </h3>
            <div v-for="section in income[side]?.sections || []" :key="section.section" class="mt-4">
              <p class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ section.section }}</p>
              <ul class="mt-1 space-y-0.5 text-sm text-slate-700 dark:text-slate-300">
                <li v-for="account in section.accounts" :key="account.code" class="flex justify-between gap-2">
                  <span><span class="font-mono text-xs">{{ account.code }}</span> {{ account.label }}</span>
                  <span>{{ formatAmount(account.amount) }}</span>
                </li>
              </ul>
              <p class="mt-1 flex justify-between border-t border-slate-200 dark:border-slate-700 pt-1 text-sm font-semibold text-slate-900 dark:text-white">
                <span>{{ $t('accounting.statements.section_total') }}</span><span>{{ formatAmount(section.total) }}</span>
              </p>
            </div>
            <p class="mt-4 flex justify-between rounded-xl bg-slate-100 dark:bg-slate-800 px-3 py-2 text-sm font-black text-slate-900 dark:text-white">
              <span>{{ $t('accounting.statements.section_total') }}</span><span>{{ formatAmount(income[side]?.total) }}</span>
            </p>
          </div>
        </div>
      </template>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
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

const tabs = ['vat', 'balance_sheet', 'income']
const activeTab = ref('vat')

const vatPeriod = ref(currentPeriod())
const vatLoading = ref(false)
const vat = ref(null)

const sheetYear = ref(new Date().getFullYear())
const sheetLoading = ref(false)
const sheet = ref(null)

const incomePeriod = ref(currentPeriod())
const incomeLoading = ref(false)
const income = ref(null)

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
  return err?.response?.data?.message || t('accounting.statements.load_error')
}

async function loadVat() {
  vatLoading.value = true
  try {
    const { data: response } = await api.get(`/accounting/reports/vat-declaration?period=${vatPeriod.value}`)
    vat.value = response?.data || null
  } catch (err) {
    toast.error(errorMessage(err))
  } finally {
    vatLoading.value = false
  }
}

function exportVatCsv() {
  downloadApiFile(`/accounting/reports/vat-declaration?period=${vatPeriod.value}&format=csv`).catch((err) => {
    toast.error(errorMessage(err))
  })
}

async function loadBalanceSheet() {
  sheetLoading.value = true
  try {
    const { data: response } = await api.get(`/accounting/statements/balance-sheet?year=${sheetYear.value}`)
    sheet.value = response?.data || null
  } catch (err) {
    toast.error(errorMessage(err))
  } finally {
    sheetLoading.value = false
  }
}

async function loadIncome() {
  incomeLoading.value = true
  try {
    const { data: response } = await api.get(`/accounting/statements/income-statement?period=${incomePeriod.value}`)
    income.value = response?.data || null
  } catch (err) {
    toast.error(errorMessage(err))
  } finally {
    incomeLoading.value = false
  }
}

onMounted(() => {
  loadVat()
  loadBalanceSheet()
  loadIncome()
})
</script>
