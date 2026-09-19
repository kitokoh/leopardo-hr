<template>
  <div class="space-y-8 animate-fade-in max-w-6xl">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
          {{ t('accountingModule.chartTitle') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
          {{ t('accountingModule.chartSubtitle') }}
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <select v-model="typeFilter" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" @change="load">
          <option value="">{{ t('accountingModule.allTypes') }}</option>
          <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <label class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300">
          <input v-model="activeOnly" type="checkbox" class="rounded border-slate-300 dark:border-slate-600" @change="load" />
          {{ t('accountingModule.chartActive') }}
        </label>
        <button type="button" class="btn-primary" @click="openCreate">
          <PlusIcon class="mr-2 h-4 w-4" aria-hidden="true" />
          {{ t('accountingModule.chartAdd') }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="glass-card p-6 text-slate-500 dark:text-slate-400">
      {{ t('accountingModule.loading') }}
    </div>

    <section v-else class="glass-card p-6">
      <p v-if="accounts.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
        {{ t('accountingModule.chartEmpty') }}
      </p>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.chartCode') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.chartLabel') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.chartType') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.chartClass') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.chartStatus') }}</th>
              <th class="py-2 text-right font-semibold">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="account in accounts" :key="account.code" class="border-b border-slate-100 dark:border-slate-800/60">
              <td class="py-2.5 pr-3 font-mono text-xs text-slate-700 dark:text-slate-300">{{ account.code }}</td>
              <td class="py-2.5 pr-3 text-slate-700 dark:text-slate-300">
                {{ account.label }}
                <span
                  v-if="account.is_system"
                  class="ml-2 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                  :title="t('accountingModule.chartSystemNote')"
                >
                  {{ t('accountingModule.chartSystemBadge') }}
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
                  {{ account.is_active ? t('accountingModule.chartActive') : t('accountingModule.chartInactive') }}
                </span>
              </td>
              <td class="py-2.5 text-right">
                <RowActionButton
                  :icon="PowerIcon"
                  :label="t('accountingModule.chartToggle')"
                  :tone="account.is_active ? 'warning' : 'success'"
                  @click="toggle(account)"
                />
                <RowActionButton
                  v-if="!account.is_system"
                  :icon="TrashIcon"
                  :label="t('accountingModule.chartDelete')"
                  tone="danger"
                  @click="askDelete(account)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Création de compte -->
    <div v-if="createOpen" class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4" @click.self="createOpen = false">
      <form class="w-full max-w-md rounded-2xl glass-card p-6 space-y-4" @submit.prevent="create">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ t('accountingModule.chartAddTitle') }}</h3>
        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
          {{ t('accountingModule.chartCode') }}
          <input v-model="form.code" type="text" required pattern="[0-9]{1,20}" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
        </label>
        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
          {{ t('accountingModule.chartLabel') }}
          <input v-model="form.label" type="text" required maxlength="150" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
        </label>
        <div class="grid grid-cols-2 gap-3">
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ t('accountingModule.chartType') }}
            <select v-model="form.type" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm">
              <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </label>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ t('accountingModule.chartClass') }}
            <select v-model.number="form.class" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm">
              <option v-for="klass in 8" :key="klass" :value="klass">{{ klass }}</option>
            </select>
          </label>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="createOpen = false">{{ t('accountingModule.chartCancel') }}</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ t('accountingModule.chartSave') }}</button>
        </div>
      </form>
    </div>

    <!-- Confirmation de suppression (in-app, jamais confirm() natif — #3494) -->
    <div v-if="deleteTarget" class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4" @click.self="deleteTarget = null">
      <div class="w-full max-w-md rounded-2xl glass-card p-6">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ t('accountingModule.chartDelete') }}</h3>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ deleteTarget.code }} — {{ deleteTarget.label }}</p>
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="deleteTarget = null">{{ t('accountingModule.chartCancel') }}</button>
          <button
            type="button"
            class="inline-flex items-center rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-md transition-all hover:bg-red-700 disabled:opacity-50"
            :disabled="saving"
            @click="confirmDelete"
          >
            {{ t('accountingModule.chartDelete') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { PlusIcon, PowerIcon, TrashIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import RowActionButton from '@/components/common/RowActionButton.vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { useToast } from 'vue-toastification'

const toast = useToast()
const localeStore = useLocaleStore()

function t(key, fallback = '') {
  return translate(localeStore.current, key, fallback)
}

const loading = ref(true)
const saving = ref(false)
const accounts = ref([])
const typeFilter = ref('')
const activeOnly = ref(false)
const createOpen = ref(false)
const deleteTarget = ref(null)
const form = ref({ code: '', label: '', type: 'asset', class: 1 })

const typeOptions = computed(() => [
  { value: 'asset', label: t('accountingModule.chartTypeAsset') },
  { value: 'liability', label: t('accountingModule.chartTypeLiability') },
  { value: 'equity', label: t('accountingModule.chartTypeEquity') },
  { value: 'revenue', label: t('accountingModule.chartTypeRevenue') },
  { value: 'expense', label: t('accountingModule.chartTypeExpense') },
])

function typeLabel(type) {
  const found = typeOptions.value.find((option) => option.value === type)
  return found ? found.label : type
}

function openCreate() {
  form.value = { code: '', label: '', type: 'asset', class: 1 }
  createOpen.value = true
}

async function load() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (typeFilter.value) params.set('type', typeFilter.value)
    params.set('active_only', activeOnly.value ? '1' : '0')
    const { data: response } = await api.get(`/accounting/chart?${params.toString()}`)
    accounts.value = Array.isArray(response?.data) ? response.data : []
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.chartError'))
  } finally {
    loading.value = false
  }
}

async function create() {
  saving.value = true
  try {
    await api.post('/accounting/chart', { ...form.value })
    createOpen.value = false
    await load()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.errorGeneric'))
  } finally {
    saving.value = false
  }
}

async function toggle(account) {
  try {
    await api.put(`/accounting/chart/${account.code}`, { is_active: !account.is_active })
    await load()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.errorGeneric'))
  }
}

function askDelete(account) {
  deleteTarget.value = account
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  saving.value = true
  try {
    await api.delete(`/accounting/chart/${deleteTarget.value.code}`)
    deleteTarget.value = null
    await load()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.errorGeneric'))
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
