<template>
  <div class="space-y-8 animate-fade-in max-w-6xl">
    <div>
      <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
        {{ $t('accounting.settings.title') }}
      </h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
        {{ $t('accounting.settings.subtitle') }}
      </p>
    </div>

    <div v-if="loading" class="glass-card p-6 text-slate-500 dark:text-slate-400">
      {{ $t('common.busy', 'Chargement…') }}
    </div>

    <form v-else class="space-y-6" @submit.prevent="save">
      <!-- Identité & langue des documents -->
      <section class="glass-card p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">
          {{ $t('accounting.settings.company_title') }}
        </h2>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('accounting.settings.company_subtitle') }}
        </p>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $t('accounting.settings.currency') }}
            <select v-model="form.currency" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm">
              <option v-for="code in supportedCurrencies" :key="code" :value="code">{{ code }}</option>
            </select>
          </label>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $t('accounting.settings.document_language') }}
            <select v-model="form.document_language" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm">
              <option v-for="lang in documentLanguages" :key="lang.code" :value="lang.code">{{ lang.label }}</option>
            </select>
          </label>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $t('accounting.settings.template_style') }}
            <select v-model="form.template_style" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm">
              <option value="modern">{{ $t('accounting.settings.template_modern') }}</option>
              <option value="classic">{{ $t('accounting.settings.template_classic') }}</option>
            </select>
          </label>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $t('accounting.settings.payment_terms') }}
            <input v-model="form.payment_terms" type="text" maxlength="60" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
          </label>
        </div>
      </section>

      <!-- Taux de TVA -->
      <section class="glass-card p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">
          {{ $t('accounting.settings.tva_title') }}
        </h2>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('accounting.settings.tva_subtitle') }}
        </p>
        <div class="mt-4 space-y-3">
          <div v-for="(row, index) in form.tva_rates" :key="index" class="flex flex-wrap items-center gap-3">
            <input
              v-model="row.label"
              type="text"
              maxlength="80"
              :placeholder="$t('accounting.settings.rate_label')"
              class="min-w-0 flex-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm"
            />
            <input
              v-model.number="row.rate"
              type="number"
              step="0.01"
              min="0"
              max="100"
              :placeholder="$t('accounting.settings.rate_value')"
              class="w-28 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm"
            />
            <button type="button" class="btn-danger py-1 px-2.5" :disabled="saving" :aria-label="$t('accounting.settings.remove_rate')" @click="removeTvaRate(index)">
              ✕
            </button>
          </div>
        </div>
        <button type="button" class="btn-secondary mt-4" :disabled="saving" @click="addTvaRate">
          + {{ $t('accounting.settings.add_rate') }}
        </button>
      </section>

      <!-- Séries de numérotation -->
      <section class="glass-card p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">
          {{ $t('accounting.settings.series_title') }}
        </h2>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('accounting.settings.series_subtitle') }}
        </p>
        <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          <label v-for="row in form.series" :key="row.key" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ t(row.labelKey) }}
            <input v-model="row.prefix" type="text" maxlength="20" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
          </label>
        </div>
      </section>

      <!-- Mentions légales -->
      <section class="glass-card p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">
          {{ $t('accounting.settings.mentions_title') }}
        </h2>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('accounting.settings.mentions_subtitle') }}
        </p>
        <textarea
          v-model="form.legal_mentions"
          rows="4"
          maxlength="2000"
          :placeholder="$t('accounting.settings.mentions_placeholder')"
          class="mt-4 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm"
        ></textarea>
      </section>

      <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="btn-primary" :disabled="saving">
          {{ saving ? $t('common.busy', 'Enregistrement…') : $t('accounting.settings.save') }}
        </button>
        <button type="button" class="btn-secondary" :disabled="saving" @click="load">
          {{ $t('accounting.settings.reload') }}
        </button>
        <span v-if="saved" class="text-sm font-medium text-emerald-600 dark:text-emerald-400" role="status">
          {{ $t('accounting.settings.saved') }}
        </span>
      </div>
    </form>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import api from '@/services/api'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { useToast } from 'vue-toastification'

const toast = useToast()
const localeStore = useLocaleStore()

function t(key, fallback = '') {
  return translate(localeStore.current, key, fallback)
}

/**
 * Devises supportées v1 — registre CountryDefaults (DZ/MA/TN/SN/CI/ML/BF/BJ/
 * TG/NE/CM/GA/CG/TD/CF/GQ/FR/TR/GB/US/CA). Miroir de la validation backend
 * (UpdateAccountingSettingsRequest). Multi-devises hors périmètre v1 (#5270).
 */
const supportedCurrencies = ['DZD', 'MAD', 'TND', 'XOF', 'XAF', 'EUR', 'TRY', 'GBP', 'USD', 'CAD']

const documentLanguages = [
  { code: 'fr', label: t('accounting.settings.lang_fr') },
  { code: 'ar', label: t('accounting.settings.lang_ar') },
  { code: 'tr', label: t('accounting.settings.lang_tr') },
  { code: 'en', label: t('accounting.settings.lang_en') },
]

const seriesTypes = [
  { key: 'invoice', labelKey: 'accounting.settings.series_invoice' },
  { key: 'proforma', labelKey: 'accounting.settings.series_proforma' },
  { key: 'quote', labelKey: 'accounting.settings.series_quote' },
  { key: 'credit_note', labelKey: 'accounting.settings.series_credit_note' },
  { key: 'delivery_note', labelKey: 'accounting.settings.series_delivery_note' },
  { key: 'receipt', labelKey: 'accounting.settings.series_receipt' },
]

const loading = ref(true)
const saving = ref(false)
const saved = ref(false)

const form = reactive({
  currency: 'DZD',
  document_language: 'fr',
  template_style: 'modern',
  payment_terms: '',
  legal_mentions: '',
  tva_rates: [],
  series: [],
})

function emptyForm() {
  form.currency = 'DZD'
  form.document_language = 'fr'
  form.template_style = 'modern'
  form.payment_terms = ''
  form.legal_mentions = ''
  form.tva_rates = [{ label: t('accounting.settings.rate_default_label', 'TVA standard'), rate: 19 }]
  form.series = seriesTypes.map((type) => ({ key: type.key, labelKey: type.labelKey, prefix: '' }))
}

function applySettings(settings) {
  form.currency = settings.currency || 'DZD'
  form.document_language = settings.document_language || 'fr'
  form.template_style = settings.template_style || 'modern'
  form.payment_terms = settings.payment_terms || ''
  form.legal_mentions = settings.legal_mentions || ''
  form.tva_rates = Array.isArray(settings.tva_rates) && settings.tva_rates.length > 0
    ? settings.tva_rates.map((row) => ({ label: row.label || '', rate: Number(row.rate) }))
    : [{ label: t('accounting.settings.rate_default_label', 'TVA standard'), rate: 19 }]
  const storedSeries = settings.number_series || {}
  form.series = seriesTypes.map((type) => ({
    key: type.key,
    labelKey: type.labelKey,
    prefix: storedSeries[type.key] || '',
  }))
}

function addTvaRate() {
  form.tva_rates.push({ label: '', rate: 19 })
}

function removeTvaRate(index) {
  form.tva_rates.splice(index, 1)
}

async function load() {
  loading.value = true
  saved.value = false
  try {
    const { data } = await api.get('/accounting/settings')
    applySettings(data.data || {})
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accounting.settings.load_error'))
    emptyForm()
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  saved.value = false
  try {
    const { data } = await api.put('/accounting/settings', {
      currency: form.currency,
      document_language: form.document_language,
      template_style: form.template_style,
      payment_terms: form.payment_terms || null,
      legal_mentions: form.legal_mentions || null,
      tva_rates: form.tva_rates.map((row) => ({
        label: row.label,
        rate: Number(row.rate),
      })),
      number_series: Object.fromEntries(
        form.series.map((row) => [row.key, row.prefix.trim()]).filter(([, prefix]) => prefix !== ''),
      ),
    })
    applySettings(data.data || {})
    saved.value = true
    toast.success(t('accounting.settings.saved'))
  } catch (err) {
    const serverMessage = err?.response?.data?.message
    const firstFieldError = err?.response?.data?.errors
      ? Object.values(err.response.data.errors)[0]?.[0]
      : null
    toast.error(serverMessage || firstFieldError || t('accounting.settings.save_error'))
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
