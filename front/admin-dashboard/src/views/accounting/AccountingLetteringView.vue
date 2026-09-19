<template>
  <div class="space-y-8 animate-fade-in max-w-7xl">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
          {{ $t('accounting.lettering.title') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
          {{ $t('accounting.lettering.subtitle') }}
        </p>
      </div>
      <div class="flex flex-wrap items-end gap-3">
        <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
          {{ $t('accounting.lettering.period') }}
          <input v-model="period" type="month" class="mt-1 block rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
        </label>
        <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
          {{ $t('accounting.lettering.account_filter') }}
          <input v-model="accountFilter" type="text" maxlength="20" :placeholder="$t('accounting.lettering.account_placeholder')" class="mt-1 block rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
        </label>
        <button type="button" class="btn-secondary" :disabled="loading" @click="load">
          {{ $t('accounting.lettering.apply') }}
        </button>
      </div>
    </div>

    <!-- Période clôturée → lecture seule -->
    <div
      v-if="closed"
      class="flex items-center gap-3 rounded-2xl border border-amber-300/70 dark:border-amber-700/60 bg-amber-50/80 dark:bg-amber-950/40 p-4"
      role="status"
    >
      <LockClosedIcon class="h-5 w-5 text-amber-500" aria-hidden="true" />
      <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
        {{ $t('accounting.lettering.period_closed') }}
      </p>
    </div>

    <div v-if="loading" class="glass-card p-6 text-slate-500 dark:text-slate-400">
      {{ $t('common.busy', 'Chargement…') }}
    </div>

    <template v-else>
      <!-- Barre de lettrage -->
      <div class="glass-card p-4 flex flex-wrap items-end gap-3">
        <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
          {{ $t('accounting.lettering.letter') }}
          <input
            v-model="letter"
            type="text"
            maxlength="32"
            :placeholder="$t('accounting.lettering.letter_placeholder')"
            class="mt-1 block w-32 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm font-mono uppercase"
          />
        </label>
        <div class="text-sm text-slate-500 dark:text-slate-400 pb-2.5">
          {{ selectedIds.length }} {{ $t('accounting.lettering.selected') }} —
          <strong>{{ formatAmount(selectionDebit) }}</strong> {{ $t('accounting.lettering.debit') }} /
          <strong>{{ formatAmount(selectionCredit) }}</strong> {{ $t('accounting.lettering.credit') }}
          <span
            v-if="selectedIds.length >= 2"
            class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
            :class="selectionBalanced
              ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
              : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'"
          >
            {{ selectionBalanced ? $t('accounting.lettering.selection_balanced') : $t('accounting.lettering.selection_unbalanced') }}
          </span>
        </div>
        <button
          type="button"
          class="btn-primary ml-auto"
          :disabled="closed || submitting || selectedIds.length < 2 || !letter.trim()"
          @click="submitLettering"
        >
          {{ $t('accounting.lettering.submit') }}
        </button>
      </div>
      <p v-if="selectedIds.length > 0 && selectedIds.length < 2" class="text-xs text-amber-600 dark:text-amber-400">
        {{ $t('accounting.lettering.min_entries') }}
      </p>

      <!-- Écritures -->
      <section class="glass-card p-6 overflow-x-auto">
        <p v-if="filteredEntries.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
          {{ $t('accounting.lettering.empty') }}
        </p>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-3 font-semibold"></th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.lettering.col_date') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.lettering.col_piece') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.lettering.col_account') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.lettering.col_description') }}</th>
              <th class="py-2 pr-3 text-right font-semibold">{{ $t('accounting.lettering.debit') }}</th>
              <th class="py-2 text-right font-semibold">{{ $t('accounting.lettering.credit') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="entry in filteredEntries"
              :key="entry.id"
              class="border-b border-slate-100 dark:border-slate-800/60"
              :class="selectedIds.includes(entry.id) ? 'bg-brand-50/60 dark:bg-brand-900/20' : ''"
            >
              <td class="py-2 pr-3">
                <input
                  type="checkbox"
                  class="rounded border-slate-300 dark:border-slate-600"
                  :checked="selectedIds.includes(entry.id)"
                  :disabled="closed"
                  :aria-label="`${entry.account_code} ${entry.description || ''}`"
                  @change="toggleSelection(entry.id)"
                />
              </td>
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
      </section>

      <!-- Délettrage -->
      <section class="glass-card p-6">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">
          {{ $t('accounting.lettering.unletter_title') }}
        </h2>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('accounting.lettering.unletter_hint') }}
        </p>
        <form class="mt-3 flex flex-wrap items-end gap-3" @submit.prevent="unletter">
          <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
            {{ $t('accounting.lettering.letter') }}
            <input
              v-model="unletterValue"
              type="text"
              maxlength="32"
              required
              class="mt-1 block w-32 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm font-mono uppercase"
            />
          </label>
          <button type="submit" class="btn-danger" :disabled="submitting || !unletterValue.trim()">
            {{ $t('accounting.lettering.unletter_submit') }}
          </button>
        </form>
      </section>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { LockClosedIcon } from '@heroicons/vue/24/outline'
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
const submitting = ref(false)
const period = ref(currentPeriod())
const accountFilter = ref('')
const entries = ref([])
const closed = ref(false)
const selectedIds = ref([])
const letter = ref('')
const unletterValue = ref('')

function currentPeriod() {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
}

const filteredEntries = computed(() => {
  const needle = accountFilter.value.trim()
  if (!needle) return entries.value
  return entries.value.filter((entry) => String(entry.account_code || '').startsWith(needle))
})

const selectedEntries = computed(() => entries.value.filter((entry) => selectedIds.value.includes(entry.id)))
const selectionDebit = computed(() => selectedEntries.value.reduce((sum, entry) => sum + Number(entry.debit ?? 0), 0))
const selectionCredit = computed(() => selectedEntries.value.reduce((sum, entry) => sum + Number(entry.credit ?? 0), 0))
const selectionBalanced = computed(() => Math.abs(selectionDebit.value - selectionCredit.value) < 0.005)

function formatAmount(value) {
  return new Intl.NumberFormat(toIntlLocale(localeStore.current), {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value ?? 0))
}

function toggleSelection(id) {
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter((selected) => selected !== id)
  } else {
    selectedIds.value = [...selectedIds.value, id]
  }
}

function errorMessage(err) {
  const code = err?.response?.data?.code
  if (code) {
    const known = t(`accounting.lettering.error_${code.toLowerCase()}`)
    if (known) return known
  }
  return err?.response?.data?.message || t('accounting.lettering.load_error')
}

async function load() {
  loading.value = true
  selectedIds.value = []
  try {
    const { data: response } = await api.get(`/accounting/journal?period=${period.value}`)
    entries.value = Array.isArray(response?.entries) ? response.entries : []
    closed.value = Boolean(response?.closed)
  } catch (err) {
    toast.error(errorMessage(err))
  } finally {
    loading.value = false
  }
}

async function submitLettering() {
  if (selectedIds.value.length < 2 || !letter.value.trim()) {
    toast.error(t('accounting.lettering.min_entries'))
    return
  }
  submitting.value = true
  try {
    const { data: response } = await api.post('/accounting/journal/lettering', {
      letter: letter.value.trim().toUpperCase(),
      entry_ids: selectedIds.value,
    })
    const result = response?.data || {}
    toast.success(
      t('accounting.lettering.success')
        .replace(':count', String(result.count ?? selectedIds.value.length))
        .replace(':letter', String(result.letter ?? letter.value)),
    )
    letter.value = ''
    await load()
  } catch (err) {
    toast.error(errorMessage(err))
  } finally {
    submitting.value = false
  }
}

async function unletter() {
  if (!window.confirm(t('accounting.lettering.unletter_confirm').replace(':letter', unletterValue.value.trim().toUpperCase()))) return
  submitting.value = true
  try {
    await api.delete(`/accounting/journal/lettering/${encodeURIComponent(unletterValue.value.trim().toUpperCase())}`)
    toast.success(t('accounting.lettering.unletter_ok'))
    unletterValue.value = ''
    await load()
  } catch (err) {
    toast.error(errorMessage(err))
  } finally {
    submitting.value = false
  }
}

onMounted(load)
</script>
