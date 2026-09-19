<template>
  <div class="space-y-8 animate-fade-in max-w-5xl">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
          {{ $t('accounting.fiscalYears.title') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
          {{ $t('accounting.fiscalYears.subtitle') }}
        </p>
      </div>
      <form class="flex items-end gap-3" @submit.prevent="openYear">
        <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
          {{ $t('accounting.fiscalYears.year') }}
          <input v-model.number="newYear" type="number" min="2000" max="2100" required class="mt-1 block w-28 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
        </label>
        <button type="submit" class="btn-primary" :disabled="busy">
          <PlusIcon class="mr-2 h-4 w-4" aria-hidden="true" />
          {{ $t('accounting.fiscalYears.open') }}
        </button>
      </form>
    </div>

    <div v-if="loading" class="glass-card p-6 text-slate-500 dark:text-slate-400">
      {{ $t('common.busy', 'Chargement…') }}
    </div>

    <section v-else class="glass-card p-6">
      <p v-if="years.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
        {{ $t('accounting.fiscalYears.empty') }}
      </p>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.fiscalYears.col_year') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.fiscalYears.col_status') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.fiscalYears.col_closed_at') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.fiscalYears.col_closed_by') }}</th>
              <th class="py-2 font-semibold text-right">{{ $t('accounting.fiscalYears.col_actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="fiscalYear in years" :key="fiscalYear.year" class="border-b border-slate-100 dark:border-slate-800/60">
              <td class="py-2.5 pr-3 font-mono font-semibold text-slate-900 dark:text-white">{{ fiscalYear.year }}</td>
              <td class="py-2.5 pr-3">
                <span
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                  :class="fiscalYear.status === 'closed'
                    ? 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200'
                    : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'"
                >
                  <LockClosedIcon v-if="fiscalYear.status === 'closed'" class="mr-1 h-3 w-3" aria-hidden="true" />
                  {{ fiscalYear.status === 'closed' ? $t('accounting.fiscalYears.status_closed') : $t('accounting.fiscalYears.status_open') }}
                </span>
              </td>
              <td class="py-2.5 pr-3 text-slate-500 dark:text-slate-400">{{ fiscalYear.closed_at ? fiscalYear.closed_at.slice(0, 10) : '—' }}</td>
              <td class="py-2.5 pr-3 text-slate-500 dark:text-slate-400">{{ fiscalYear.closed_by || '—' }}</td>
              <td class="py-2.5 text-right">
                <button
                  v-if="fiscalYear.status !== 'closed'"
                  type="button"
                  class="btn-danger px-3 py-1.5 text-xs"
                  :disabled="busy"
                  @click="askClose(fiscalYear)"
                >
                  <LockClosedIcon class="mr-1.5 h-3.5 w-3.5" aria-hidden="true" />
                  {{ $t('accounting.fiscalYears.close') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Résultat de la dernière clôture -->
    <section v-if="lastCloseResult" class="glass-card p-6 border border-emerald-300/60 dark:border-emerald-800/60">
      <h2 class="text-lg font-bold text-slate-900 dark:text-white">
        {{ $t('accounting.fiscalYears.result_title') }} {{ lastCloseResult.year }}
      </h2>
      <ul class="mt-3 space-y-1 text-sm text-slate-600 dark:text-slate-300">
        <li>
          {{ $t('accounting.fiscalYears.result_net') }} :
          <strong :class="Number(lastCloseResult.result) < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'">
            {{ formatAmount(lastCloseResult.result) }}
          </strong>
        </li>
        <li>{{ $t('accounting.fiscalYears.result_entries') }} : <strong>{{ lastCloseResult.entry_count }}</strong></li>
        <li>{{ $t('accounting.fiscalYears.result_periods') }} : <strong>{{ (lastCloseResult.closed_periods || []).join(', ') }}</strong></li>
      </ul>
    </section>

    <!-- Confirmation de clôture (irréversible) -->
    <div v-if="closing" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="closing = null">
      <div class="glass-card w-full max-w-md bg-white dark:bg-slate-900 p-6" role="alertdialog" aria-modal="true">
        <div class="flex items-start gap-3">
          <ExclamationTriangleIcon class="h-6 w-6 shrink-0 text-red-500" aria-hidden="true" />
          <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">
              {{ $t('accounting.fiscalYears.close_title') }} {{ closing.year }}
            </h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
              {{ $t('accounting.fiscalYears.close_warning') }}
            </p>
            <label class="mt-3 block text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ $t('accounting.fiscalYears.close_type_year').replace(':year', String(closing.year)) }}
              <input v-model="confirmYear" type="text" inputmode="numeric" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm font-mono" />
            </label>
          </div>
        </div>
        <div class="mt-5 flex justify-end gap-3">
          <button type="button" class="btn-secondary" @click="closing = null">{{ $t('accounting.fiscalYears.cancel') }}</button>
          <button
            type="button"
            class="btn-danger"
            :disabled="busy || confirmYear !== String(closing.year)"
            @click="confirmClose"
          >
            {{ $t('accounting.fiscalYears.close_confirm') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { ExclamationTriangleIcon, LockClosedIcon, PlusIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
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
const years = ref([])
const newYear = ref(new Date().getFullYear())
const closing = ref(null)
const confirmYear = ref('')
const lastCloseResult = ref(null)

function formatAmount(value) {
  return new Intl.NumberFormat(toIntlLocale(localeStore.current), {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value ?? 0))
}

function errorMessage(err) {
  return err?.response?.data?.message || t('accounting.fiscalYears.load_error')
}

async function load() {
  loading.value = true
  try {
    const { data: response } = await api.get('/accounting/fiscal-years')
    years.value = Array.isArray(response?.data) ? response.data : []
  } catch (err) {
    toast.error(errorMessage(err))
  } finally {
    loading.value = false
  }
}

async function openYear() {
  busy.value = true
  try {
    await api.post('/accounting/fiscal-years', { year: newYear.value })
    toast.success(t('accounting.fiscalYears.opened'))
    await load()
  } catch (err) {
    toast.error(errorMessage(err))
  } finally {
    busy.value = false
  }
}

function askClose(fiscalYear) {
  closing.value = fiscalYear
  confirmYear.value = ''
}

async function confirmClose() {
  if (!closing.value) return
  busy.value = true
  try {
    const { data: response } = await api.post(`/accounting/fiscal-years/${closing.value.year}/close`)
    lastCloseResult.value = response?.data || null
    toast.success(t('accounting.fiscalYears.closed_ok'))
    closing.value = null
    await load()
  } catch (err) {
    toast.error(errorMessage(err))
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>
