<template>
  <div class="space-y-8 animate-fade-in max-w-6xl">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
          {{ $t('accounting.chart.title') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
          {{ $t('accounting.chart.subtitle') }}
        </p>
      </div>
      <button type="button" class="btn-primary" @click="showCreate = true">
        <PlusIcon class="mr-2 h-4 w-4" aria-hidden="true" />
        {{ $t('accounting.chart.new') }}
      </button>
    </div>

    <!-- Filtres -->
    <div class="glass-card p-4 flex flex-wrap items-end gap-3">
      <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
        {{ $t('accounting.chart.filter_type') }}
        <select v-model="filterType" class="mt-1 block rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" @change="load">
          <option value="">{{ $t('accounting.chart.all_types') }}</option>
          <option v-for="accountType in accountTypes" :key="accountType" :value="accountType">{{ typeLabel(accountType) }}</option>
        </select>
      </label>
      <label class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300 pb-2">
        <input v-model="activeOnly" type="checkbox" class="rounded border-slate-300 dark:border-slate-600" @change="load" />
        {{ $t('accounting.chart.active_only') }}
      </label>
      <label class="block text-sm font-medium text-slate-600 dark:text-slate-300 ml-auto">
        {{ $t('accounting.chart.search') }}
        <input v-model="search" type="search" :placeholder="$t('accounting.chart.search_placeholder')" class="mt-1 block w-64 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
      </label>
    </div>

    <div v-if="loading" class="glass-card p-6 text-slate-500 dark:text-slate-400">
      {{ $t('common.busy', 'Chargement…') }}
    </div>

    <section v-else class="glass-card p-6">
      <p class="text-sm text-slate-500 dark:text-slate-400">
        {{ filteredAccounts.length }} {{ $t('accounting.chart.count') }}
      </p>
      <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.chart.col_code') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.chart.col_label') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.chart.col_type') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.chart.col_class') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ $t('accounting.chart.col_state') }}</th>
              <th class="py-2 font-semibold text-right">{{ $t('accounting.chart.col_actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="account in filteredAccounts" :key="account.code" class="border-b border-slate-100 dark:border-slate-800/60">
              <td class="py-2.5 pr-3 font-mono text-xs text-slate-700 dark:text-slate-300">{{ account.code }}</td>
              <td class="py-2.5 pr-3 text-slate-700 dark:text-slate-300">
                {{ account.label }}
                <span
                  v-if="account.is_system"
                  class="ml-2 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                >
                  {{ $t('accounting.chart.system') }}
                </span>
              </td>
              <td class="py-2.5 pr-3 text-slate-500 dark:text-slate-400">{{ typeLabel(account.type) }}</td>
              <td class="py-2.5 pr-3 text-slate-500 dark:text-slate-400">{{ account.class }}</td>
              <td class="py-2.5 pr-3">
                <span
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                  :class="account.is_active
                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                    : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                >
                  {{ account.is_active ? $t('accounting.chart.active') : $t('accounting.chart.inactive') }}
                </span>
              </td>
              <td class="py-2.5 text-right whitespace-nowrap">
                <button
                  type="button"
                  class="btn-secondary px-2.5 py-1 text-xs"
                  :disabled="busyCode === account.code"
                  @click="toggleActive(account)"
                >
                  {{ account.is_active ? $t('accounting.chart.deactivate') : $t('accounting.chart.activate') }}
                </button>
                <button
                  v-if="!account.is_system"
                  type="button"
                  class="btn-danger ml-2 px-2.5 py-1 text-xs"
                  :disabled="busyCode === account.code"
                  @click="removeAccount(account)"
                >
                  {{ $t('accounting.chart.delete') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="filteredAccounts.length === 0" class="mt-4 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('accounting.chart.empty') }}
        </p>
      </div>
    </section>

    <!-- Modale de création -->
    <div v-if="showCreate" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4" @click.self="showCreate = false">
      <div class="glass-card w-full max-w-lg bg-white dark:bg-slate-900 p-6 my-16" role="dialog" aria-modal="true">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $t('accounting.chart.create_title') }}</h2>
        <form class="mt-4 space-y-4" @submit.prevent="submitCreate">
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $t('accounting.chart.col_code') }}
            <input v-model="createForm.code" type="text" required pattern="[0-9]+" maxlength="20" :placeholder="$t('accounting.chart.code_placeholder')" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
          </label>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $t('accounting.chart.col_label') }}
            <input v-model="createForm.label" type="text" required maxlength="255" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
          </label>
          <div class="grid gap-4 md:grid-cols-2">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ $t('accounting.chart.col_type') }}
              <select v-model="createForm.type" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm">
                <option v-for="accountType in accountTypes" :key="accountType" :value="accountType">{{ typeLabel(accountType) }}</option>
              </select>
            </label>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ $t('accounting.chart.col_class') }}
              <select v-model.number="createForm.class" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm">
                <option v-for="classNumber in 8" :key="classNumber" :value="classNumber">{{ classNumber }}</option>
              </select>
            </label>
          </div>
          <div class="flex justify-end gap-3">
            <button type="button" class="btn-secondary" @click="showCreate = false">{{ $t('accounting.chart.cancel') }}</button>
            <button type="submit" class="btn-primary" :disabled="creating">{{ $t('accounting.chart.create_submit') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { PlusIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { useToast } from 'vue-toastification'

const toast = useToast()
const localeStore = useLocaleStore()

function t(key, fallback = '') {
  return translate(localeStore.current, key, fallback)
}

const accountTypes = ['asset', 'liability', 'equity', 'revenue', 'expense']

const loading = ref(true)
const accounts = ref([])
const filterType = ref('')
const activeOnly = ref(true)
const search = ref('')
const busyCode = ref(null)

const showCreate = ref(false)
const creating = ref(false)
const createForm = reactive({ code: '', label: '', type: 'asset', class: 1 })

const filteredAccounts = computed(() => {
  const needle = search.value.trim().toLowerCase()
  if (!needle) return accounts.value
  return accounts.value.filter(
    (account) => account.code.includes(needle) || String(account.label || '').toLowerCase().includes(needle),
  )
})

function typeLabel(type) {
  return t(`accounting.chart.type_${type}`) || type
}

function errorMessage(err) {
  return err?.response?.data?.message || t('accounting.chart.load_error')
}

async function load() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('active_only', activeOnly.value ? '1' : '0')
    if (filterType.value) params.set('type', filterType.value)
    const { data: response } = await api.get(`/accounting/chart?${params.toString()}`)
    accounts.value = Array.isArray(response?.data) ? response.data : []
  } catch (err) {
    toast.error(errorMessage(err))
  } finally {
    loading.value = false
  }
}

async function submitCreate() {
  creating.value = true
  try {
    await api.post('/accounting/chart', { ...createForm })
    toast.success(t('accounting.chart.created'))
    showCreate.value = false
    createForm.code = ''
    createForm.label = ''
    await load()
  } catch (err) {
    toast.error(errorMessage(err))
  } finally {
    creating.value = false
  }
}

async function toggleActive(account) {
  busyCode.value = account.code
  try {
    await api.put(`/accounting/chart/${account.code}`, { is_active: !account.is_active })
    account.is_active = !account.is_active
    toast.success(t('accounting.chart.updated'))
    if (activeOnly.value && !account.is_active) await load()
  } catch (err) {
    toast.error(errorMessage(err))
  } finally {
    busyCode.value = null
  }
}

async function removeAccount(account) {
  if (!window.confirm(t('accounting.chart.delete_confirm'))) return
  busyCode.value = account.code
  try {
    await api.delete(`/accounting/chart/${account.code}`)
    toast.success(t('accounting.chart.deleted'))
    await load()
  } catch (err) {
    toast.error(errorMessage(err))
  } finally {
    busyCode.value = null
  }
}

onMounted(load)
</script>
