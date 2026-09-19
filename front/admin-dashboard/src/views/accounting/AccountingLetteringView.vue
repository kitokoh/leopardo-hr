<template>
  <div class="space-y-8 animate-fade-in max-w-6xl">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
          {{ t('accountingModule.lgTitle') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
          {{ t('accountingModule.lgSubtitle') }}
        </p>
      </div>
      <label class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300">
        {{ t('accountingModule.periodLabel') }}
        <input v-model="period" type="month" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" @change="load" />
      </label>
    </div>

    <div v-if="loading" class="glass-card p-6 text-slate-500 dark:text-slate-400">
      {{ t('accountingModule.loading') }}
    </div>

    <template v-else>
      <!-- Barre d'action lettrage -->
      <div class="glass-card flex flex-wrap items-center justify-between gap-3 p-4">
        <div class="flex flex-wrap items-center gap-2">
          <span
            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
            :class="closed
              ? 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
              : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'"
          >
            {{ closed ? t('accountingModule.lgClosed') : (balanced ? t('accountingModule.lgBalanced') : t('accountingModule.lgUnbalanced')) }}
          </span>
          <span class="text-sm text-slate-500 dark:text-slate-400">
            {{ selectedIds.length }} / {{ entries.length }}
          </span>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <input
            v-model="letter"
            type="text"
            maxlength="32"
            :placeholder="t('accountingModule.lgLetter')"
            :disabled="closed"
            class="w-32 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm"
          />
          <button type="button" class="btn-primary" :disabled="closed || saving" @click="applyLettering">
            <LinkIcon class="mr-2 h-4 w-4" aria-hidden="true" />
            {{ t('accountingModule.lgApply') }}
          </button>
          <input
            v-model="unletterValue"
            type="text"
            maxlength="32"
            :placeholder="t('accountingModule.lgUnletterPlaceholder')"
            :disabled="closed"
            class="w-40 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm"
          />
          <button type="button" class="btn-secondary" :disabled="closed || saving || !unletterValue" @click="unletter">
            {{ t('accountingModule.lgUnletter') }}
          </button>
        </div>
      </div>

      <section class="glass-card p-6">
        <p v-if="entries.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
          {{ t('accountingModule.lgEmpty') }}
        </p>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <th class="py-2 pr-3 font-semibold">
                  <span class="sr-only">{{ t('accountingModule.lgSelect') }}</span>
                </th>
                <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.ledgerDate') }}</th>
                <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.ledgerPiece') }}</th>
                <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.ledgerAccount') }}</th>
                <th class="py-2 pr-3 font-semibold">{{ t('accountingModule.ledgerDesc') }}</th>
                <th class="py-2 pr-3 text-right font-semibold">{{ t('accountingModule.ledgerDebit') }}</th>
                <th class="py-2 text-right font-semibold">{{ t('accountingModule.ledgerCredit') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="entry in entries" :key="entry.id" class="border-b border-slate-100 dark:border-slate-800/60">
                <td class="py-2.5 pr-3">
                  <input
                    v-model="selectedIds"
                    type="checkbox"
                    :value="entry.id"
                    :disabled="closed"
                    :aria-label="t('accountingModule.lgSelect')"
                    class="rounded border-slate-300 dark:border-slate-600"
                  />
                </td>
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
    </template>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { LinkIcon } from '@heroicons/vue/24/outline'
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
const saving = ref(false)
const period = ref(currentPeriod())
const entries = ref([])
const balanced = ref(true)
const closed = ref(false)
const selectedIds = ref([])
const letter = ref('')
const unletterValue = ref('')

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

async function load() {
  loading.value = true
  selectedIds.value = []
  try {
    const { data: response } = await api.get(`/accounting/journal?period=${period.value}`)
    entries.value = Array.isArray(response?.entries) ? response.entries : []
    balanced.value = Boolean(response?.balanced)
    closed.value = Boolean(response?.closed)
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accountingModule.errorGeneric'))
  } finally {
    loading.value = false
  }
}

async function applyLettering() {
  // US-6.3 — < 2 écritures sélectionnées : message d'erreur, aucun appel API.
  if (selectedIds.value.length < 2) {
    toast.error(t('accountingModule.lgNeedSelection'))
    return
  }
  if (!letter.value.trim()) {
    toast.error(t('accountingModule.lgNeedLetter'))
    return
  }
  saving.value = true
  try {
    await api.post('/accounting/journal/lettering', {
      letter: letter.value.trim(),
      entry_ids: selectedIds.value,
    })
    toast.success(t('accountingModule.lgDone'))
    letter.value = ''
    await load()
  } catch (err) {
    toast.error(
      t('accountingModule.lgError').replace('{message}', err?.response?.data?.message || ''),
    )
  } finally {
    saving.value = false
  }
}

async function unletter() {
  if (!unletterValue.value.trim()) return
  saving.value = true
  try {
    await api.delete(`/accounting/journal/lettering/${encodeURIComponent(unletterValue.value.trim())}`)
    toast.success(t('accountingModule.lgUnlettered'))
    unletterValue.value = ''
    await load()
  } catch (err) {
    toast.error(
      t('accountingModule.lgError').replace('{message}', err?.response?.data?.message || ''),
    )
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
