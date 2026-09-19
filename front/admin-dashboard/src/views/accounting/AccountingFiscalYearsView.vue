<template>
  <div class="space-y-8 animate-fade-in max-w-4xl">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
          {{ t('accountingModule.fyTitle') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
          {{ t('accountingModule.fySubtitle') }}
        </p>
      </div>
      <form class="flex items-end gap-2" @submit.prevent="open">
        <label class="block text-sm font-medium text-slate-600 dark:text-slate-300">
          {{ t('accountingModule.fyOpenTitle') }}
          <input
            v-model.number="newYear"
            type="number"
            min="2000"
            max="2100"
            required
            class="mt-1 w-28 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm"
          />
        </label>
        <button type="submit" class="btn-primary" :disabled="saving">
          <PlusIcon class="mr-2 h-4 w-4" aria-hidden="true" />
          {{ t('accountingModule.fyOpen') }}
        </button>
      </form>
    </div>

    <div v-if="loading" class="glass-card p-6 text-slate-500 dark:text-slate-400">
      {{ t('accountingModule.loading') }}
    </div>

    <section v-else class="glass-card p-6">
      <p v-if="years.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
        {{ t('accountingModule.fyEmpty') }}
      </p>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.fyYear') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.fyStatus') }}</th>
              <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.fyClosedAt') }}</th>
              <th class="py-2 text-right font-semibold">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="year in years" :key="year.year" class="border-b border-slate-100 dark:border-slate-800/60">
              <td class="py-2.5 pr-3 font-mono text-sm font-semibold text-slate-800 dark:text-slate-200">{{ year.year }}</td>
              <td class="py-2.5 pr-3">
                <span
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                  :class="year.status === 'closed'
                    ? 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                    : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'"
                >
                  {{ year.status === 'closed' ? t('accountingModule.fyStatusClosed') : t('accountingModule.fyStatusOpen') }}
                </span>
              </td>
              <td class="py-2.5 pr-3 text-slate-500 dark:text-slate-400">
                {{ year.closed_at ? year.closed_at.slice(0, 10) : '—' }}
              </td>
              <td class="py-2.5 text-right">
                <RowActionButton
                  v-if="year.status !== 'closed'"
                  :icon="LockClosedIcon"
                  :label="t('accountingModule.fyClose')"
                  tone="danger"
                  @click="closeTarget = year"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- US-5.3 — confirmation de clôture (irréversible) -->
    <div v-if="closeTarget" class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4" @click.self="closeTarget = null">
      <div class="w-full max-w-md rounded-2xl glass-card p-6">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">
          {{ t('accountingModule.fyCloseConfirmTitle').replace('{year}', String(closeTarget.year)) }}
        </h3>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ t('accountingModule.fyCloseConfirmBody') }}</p>
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="closeTarget = null">{{ t('accountingModule.chartCancel') }}</button>
          <button
            type="button"
            class="inline-flex items-center rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-md transition-all hover:bg-red-700 disabled:opacity-50"
            :disabled="saving"
            @click="close"
          >
            {{ t('accountingModule.fyConfirm') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { LockClosedIcon, PlusIcon } from '@heroicons/vue/24/outline'
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
const years = ref([])
const newYear = ref(new Date().getFullYear())
const closeTarget = ref(null)

async function load() {
  loading.value = true
  try {
    const { data: response } = await api.get('/accounting/fiscal-years')
    years.value = Array.isArray(response?.data) ? response.data : []
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.fyError'))
  } finally {
    loading.value = false
  }
}

async function open() {
  saving.value = true
  try {
    await api.post('/accounting/fiscal-years', { year: newYear.value })
    await load()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.fyOpenError'))
  } finally {
    saving.value = false
  }
}

async function close() {
  if (!closeTarget.value) return
  saving.value = true
  try {
    await api.post(`/accounting/fiscal-years/${closeTarget.value.year}/close`)
    closeTarget.value = null
    await load()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.fyCloseError'))
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
