<template>
  <div class="space-y-8 animate-fade-in max-w-7xl">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
          {{ $t('accounting.dashboard.title') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
          {{ $t('accounting.dashboard.subtitle') }}
        </p>
      </div>

      <!-- Période + export CSV -->
      <div class="flex flex-wrap items-center gap-3">
        <label class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300">
          {{ $t('accounting.dashboard.from') }}
          <input v-model="from" type="date" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
        </label>
        <label class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300">
          {{ $t('accounting.dashboard.to') }}
          <input v-model="to" type="date" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
        </label>
        <button type="button" class="btn-secondary" :disabled="loading" @click="load">
          {{ $t('accounting.dashboard.apply') }}
        </button>
        <button type="button" class="btn-primary" :disabled="loading" @click="downloadCsv">
          <ArrowDownTrayIcon class="mr-2 h-4 w-4" aria-hidden="true" />
          {{ $t('accounting.dashboard.export') }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="glass-card p-6 text-slate-500 dark:text-slate-400">
      {{ $t('common.busy', 'Chargement…') }}
    </div>

    <div v-else class="space-y-6">
      <!-- Cartes KPI -->
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="glass-card p-5">
          <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">
              {{ $t('accounting.dashboard.invoices_title') }}
            </p>
            <DocumentTextIcon class="h-5 w-5 text-brand-500" aria-hidden="true" />
          </div>
          <p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ formatAmount(data.invoices?.total_ttc) }}</p>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            {{ data.invoices?.count }} {{ $t('accounting.dashboard.invoices_count') }}
          </p>
        </div>

        <div class="glass-card p-5">
          <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">
              {{ $t('accounting.dashboard.collections_title') }}
            </p>
            <BanknotesIcon class="h-5 w-5 text-emerald-500" aria-hidden="true" />
          </div>
          <p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ formatAmount(data.collections?.total) }}</p>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            {{ data.collections?.count }} {{ $t('accounting.dashboard.collections_count') }}
          </p>
        </div>

        <div class="glass-card p-5">
          <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">
              {{ $t('accounting.dashboard.expenses_title') }}
            </p>
            <ShoppingCartIcon class="h-5 w-5 text-amber-500" aria-hidden="true" />
          </div>
          <p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ formatAmount(data.expenses?.total_ttc) }}</p>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            {{ data.expenses?.count }} {{ $t('accounting.dashboard.expenses_count') }}
          </p>
        </div>

        <div class="glass-card p-5">
          <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">
              {{ $t('accounting.dashboard.outstanding_title') }}
            </p>
            <ExclamationTriangleIcon class="h-5 w-5 text-red-500" aria-hidden="true" />
          </div>
          <p class="mt-2 text-3xl font-black text-red-600 dark:text-red-400">{{ formatAmount(data.outstanding?.total_due) }}</p>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            {{ data.outstanding?.count }} {{ $t('accounting.dashboard.outstanding_count') }}
          </p>
        </div>
      </div>

      <p class="text-xs text-slate-400 dark:text-slate-500">
        {{ $t('accounting.dashboard.currency_hint') }}
      </p>

      <!-- Aging par bucket -->
      <section class="glass-card p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">
          {{ $t('accounting.dashboard.aging_title') }}
        </h2>
        <div class="mt-4 grid gap-3 md:grid-cols-4">
          <div
            v-for="bucket in aging"
            :key="bucket.bucket"
            class="rounded-2xl border p-4"
            :class="bucketClass(bucket.bucket)"
          >
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ bucketLabel(bucket.bucket) }}</p>
            <p class="mt-1 text-xl font-black text-slate-900 dark:text-white">{{ formatAmount(bucket.total_due) }}</p>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ bucket.count }} {{ $t('accounting.dashboard.invoices_count') }}</p>
          </div>
        </div>
      </section>

      <!-- Tableau des impayés -->
      <section class="glass-card p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">
          {{ $t('accounting.dashboard.list_title') }}
        </h2>
        <p v-if="outstandingList.length === 0" class="mt-3 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('accounting.dashboard.empty') }}
        </p>
        <div v-else class="mt-4 overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <th class="py-2 pr-3 font-semibold">{{ $t('accounting.dashboard.col_number') }}</th>
                <th class="py-2 pr-3 font-semibold">{{ $t('accounting.dashboard.col_contact') }}</th>
                <th class="py-2 pr-3 font-semibold">{{ $t('accounting.dashboard.col_due_date') }}</th>
                <th class="py-2 pr-3 font-semibold">{{ $t('accounting.dashboard.col_days_late') }}</th>
                <th class="py-2 pr-3 text-right font-semibold">{{ $t('accounting.dashboard.col_total') }}</th>
                <th class="py-2 pr-3 text-right font-semibold">{{ $t('accounting.dashboard.col_paid') }}</th>
                <th class="py-2 pr-3 text-right font-semibold">{{ $t('accounting.dashboard.col_due') }}</th>
                <th class="py-2 font-semibold">{{ $t('accounting.dashboard.col_status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in outstandingList" :key="row.id" class="border-b border-slate-100 dark:border-slate-800/60">
                <td class="py-2.5 pr-3 font-mono text-xs text-slate-700 dark:text-slate-300">{{ row.number }}</td>
                <td class="py-2.5 pr-3 text-slate-700 dark:text-slate-300">{{ row.contact }}</td>
                <td class="py-2.5 pr-3 text-slate-500 dark:text-slate-400">{{ row.due_date }}</td>
                <td class="py-2.5 pr-3">
                  <span
                    v-if="row.days_late > 0"
                    class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300"
                  >
                    {{ row.days_late }} {{ $t('accounting.dashboard.days') }}
                  </span>
                  <span v-else class="text-xs text-slate-400">—</span>
                </td>
                <td class="py-2.5 pr-3 text-right text-slate-700 dark:text-slate-300">{{ formatAmount(row.total_ttc) }}</td>
                <td class="py-2.5 pr-3 text-right text-slate-500 dark:text-slate-400">{{ formatAmount(row.paid_amount) }}</td>
                <td class="py-2.5 pr-3 text-right font-semibold text-red-600 dark:text-red-400">{{ formatAmount(row.due_amount) }}</td>
                <td class="py-2.5">
                  <span
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="statusClass(row.status)"
                  >
                    {{ statusLabel(row.status) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  ArrowDownTrayIcon,
  BanknotesIcon,
  DocumentTextIcon,
  ExclamationTriangleIcon,
  ShoppingCartIcon
} from '@heroicons/vue/24/outline'
import api, { downloadApiFile } from '@/services/api'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { toIntlLocale } from '@/i18n/index.js'
import { useToast } from 'vue-toastification'

const toast = useToast()
const localeStore = useLocaleStore()

function t(key, fallback = '') {
  return translate(localeStore.current, key, fallback)
}

const loading = ref(true)
const data = ref({})
const from = ref(startOfMonth())
const to = ref(today())

function startOfMonth() {
  const now = new Date()
  const y = now.getFullYear()
  const m = String(now.getMonth() + 1).padStart(2, '0')
  return `${y}-${m}-01`
}

function today() {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}

function formatAmount(value) {
  const amount = Number(value ?? 0)
  return new Intl.NumberFormat(toIntlLocale(localeStore.current), {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount)
}

const aging = computed(() => Array.isArray(data.value.outstanding?.aging) ? data.value.outstanding.aging : [])

const outstandingList = computed(() => Array.isArray(data.value.outstanding?.list) ? data.value.outstanding.list : [])

function downloadCsv() {
  const params = new URLSearchParams()
  if (from.value) params.set('from', from.value)
  if (to.value) params.set('to', to.value)
  const qs = params.toString()
  downloadApiFile(`/accounting/dashboard/export${qs ? `?${qs}` : ''}`).catch((err) => {
    toast.error(err?.response?.data?.message || t('accounting.dashboard.load_error'))
  })
}

function bucketLabel(bucket) {
  const key = `accounting.dashboard.aging_${bucket}`
  return t(key) || bucket
}

function bucketClass(bucket) {
  const base = 'border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/40'
  if (bucket === '90_plus') return 'border-red-300 dark:border-red-800 bg-red-50/60 dark:bg-red-950/30'
  if (bucket === '61_90') return 'border-amber-300 dark:border-amber-800 bg-amber-50/60 dark:bg-amber-950/30'
  if (bucket === '31_60') return 'border-amber-200 dark:border-amber-900 bg-amber-50/40 dark:bg-amber-950/20'
  return base
}

function statusLabel(status) {
  const key = `accounting.dashboard.status_${status}`
  return t(key) || status
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

async function load() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (from.value) params.set('from', from.value)
    if (to.value) params.set('to', to.value)
    const { data: response } = await api.get(`/accounting/dashboard?${params.toString()}`)
    data.value = response?.data || {}
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accounting.dashboard.load_error'))
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
