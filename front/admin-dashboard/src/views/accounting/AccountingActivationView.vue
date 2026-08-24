<template>
  <div class="space-y-8 animate-fade-in max-w-6xl">
    <div>
      <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
        {{ $t('accounting.activation.title') }}
      </h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
        {{ $t('accounting.activation.subtitle') }}
      </p>
    </div>

    <div v-if="loading" class="glass-card p-6 text-slate-500 dark:text-slate-400">
      {{ $t('common.busy', 'Chargement…') }}
    </div>

    <!-- ── État final : module activé ───────────────────────────────────── -->
    <div v-else-if="activation.completed" class="space-y-6">
      <div class="glass-card p-6">
        <div class="flex items-start gap-4">
          <div
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400"
            aria-hidden="true"
          >
            <CheckBadgeIcon class="h-6 w-6" />
          </div>
          <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">
              {{ $t('accounting.activation.done_title') }}
            </h2>
            <p class="mt-1 text-slate-600 dark:text-slate-300">
              {{ $t('accounting.activation.done_subtitle') }}
            </p>
          </div>
        </div>

        <!-- Check-list d'activation -->
        <ul class="mt-6 grid gap-3 md:grid-cols-3" role="list">
          <li class="rounded-2xl border border-emerald-200 dark:border-emerald-900 bg-emerald-50/60 dark:bg-emerald-950/30 p-4">
            <div class="flex items-center gap-2 text-sm font-semibold text-emerald-700 dark:text-emerald-400">
              <CheckBadgeIcon class="h-5 w-5" aria-hidden="true" />
              {{ $t('accounting.activation.check_settings') }}
            </div>
            <p class="mt-1 text-xs text-emerald-700/80 dark:text-emerald-400/70">
              {{ $t('accounting.activation.completed') }}
            </p>
          </li>
          <li class="rounded-2xl border border-emerald-200 dark:border-emerald-900 bg-emerald-50/60 dark:bg-emerald-950/30 p-4">
            <div class="flex items-center gap-2 text-sm font-semibold text-emerald-700 dark:text-emerald-400">
              <CheckBadgeIcon class="h-5 w-5" aria-hidden="true" />
              {{ $t('accounting.activation.check_contact') }}
            </div>
            <p v-if="activation.contact" class="mt-1 truncate text-xs text-emerald-700/80 dark:text-emerald-400/70">
              {{ activation.contact.name }}
            </p>
          </li>
          <li class="rounded-2xl border border-emerald-200 dark:border-emerald-900 bg-emerald-50/60 dark:bg-emerald-950/30 p-4">
            <div class="flex items-center gap-2 text-sm font-semibold text-emerald-700 dark:text-emerald-400">
              <CheckBadgeIcon class="h-5 w-5" aria-hidden="true" />
              {{ $t('accounting.activation.check_invoice') }}
            </div>
            <p v-if="activation.example_invoice" class="mt-1 text-xs text-emerald-700/80 dark:text-emerald-400/70">
              {{ $t('accounting.activation.invoice_number') }} {{ activation.example_invoice.number }}
            </p>
          </li>
        </ul>

        <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('accounting.activation.done_note') }}
        </p>

        <div class="mt-6 flex flex-wrap gap-3">
          <router-link to="/accounting/settings" class="btn-primary">
            {{ $t('accounting.activation.go_settings') }}
          </router-link>
          <button type="button" class="btn-secondary" @click="load">
            {{ $t('common.reload', 'Recharger') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── Wizard : étapes guidées ───────────────────────────────────────── -->
    <div v-else class="space-y-6">
      <!-- Progression -->
      <div class="flex items-center justify-between gap-4">
        <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">
          {{ t('accounting.activation.progress', 'Étape {current} sur 4').replace('{current}', String(currentStep)) }}
        </p>
        <div class="flex items-center gap-1.5" role="tablist">
          <button
            v-for="step in 4"
            :key="step"
            type="button"
            class="h-2 rounded-full transition-all duration-300"
            :class="step === currentStep ? 'w-8 bg-brand-500' : 'w-2 bg-slate-300 dark:bg-slate-700'"
            :aria-label="`Étape ${step}`"
            @click="currentStep = step"
          />
        </div>
      </div>

      <form class="space-y-6" @submit.prevent="finish">
        <!-- Étape 1 — Entreprise & documents -->
        <section v-if="currentStep === 1" class="glass-card p-6">
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">
            {{ $t('accounting.activation.step1_title') }}
          </h2>
          <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            {{ $t('accounting.activation.step1_subtitle') }}
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

        <!-- Étape 2 — TVA & numérotation -->
        <section v-else-if="currentStep === 2" class="glass-card p-6">
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">
            {{ $t('accounting.activation.step2_title') }}
          </h2>
          <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            {{ $t('accounting.activation.step2_subtitle') }}
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
            <button type="button" class="btn-secondary mt-2" :disabled="saving" @click="addTvaRate">
              + {{ $t('accounting.settings.add_rate') }}
            </button>
          </div>

          <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <label v-for="row in form.series" :key="row.key" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ t(row.labelKey) }}
              <input v-model="row.prefix" type="text" maxlength="20" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm" />
            </label>
          </div>
        </section>

        <!-- Étape 3 — Mentions légales -->
        <section v-else-if="currentStep === 3" class="glass-card p-6">
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">
            {{ $t('accounting.activation.step3_title') }}
          </h2>
          <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            {{ $t('accounting.activation.step3_subtitle') }}
          </p>
          <textarea
            v-model="form.legal_mentions"
            rows="5"
            maxlength="2000"
            :placeholder="$t('accounting.settings.mentions_placeholder')"
            class="mt-4 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm"
          ></textarea>
        </section>

        <!-- Étape 4 — Contact & premier document -->
        <section v-else class="glass-card p-6">
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">
            {{ $t('accounting.activation.step4_title') }}
          </h2>
          <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            {{ $t('accounting.activation.step4_subtitle') }}
          </p>
          <ul class="mt-4 grid gap-3 md:grid-cols-2" role="list">
            <li class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/40 p-4">
              <div class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
                <UserPlusIcon class="h-5 w-5 text-brand-500" aria-hidden="true" />
                {{ $t('accounting.activation.check_contact') }}
              </div>
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {{ $t('accounting.activation.created_contact') }} — demo@example.invalid
              </p>
            </li>
            <li class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/40 p-4">
              <div class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
                <DocumentTextIcon class="h-5 w-5 text-brand-500" aria-hidden="true" />
                {{ $t('accounting.activation.check_invoice') }}
              </div>
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {{ $t('accounting.activation.done_note') }}
              </p>
            </li>
          </ul>
        </section>

        <!-- Navigation du wizard -->
        <div class="flex flex-wrap items-center justify-between gap-3">
          <button type="button" class="btn-secondary" :disabled="currentStep === 1 || saving" @click="currentStep -= 1">
            {{ $t('accounting.activation.prev') }}
          </button>
          <div class="flex gap-3">
            <button v-if="currentStep < 4" type="button" class="btn-primary" @click="currentStep += 1">
              {{ $t('accounting.activation.next') }}
            </button>
            <button v-else type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? $t('accounting.activation.finishing') : $t('accounting.activation.finish') }}
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { CheckBadgeIcon, DocumentTextIcon, UserPlusIcon } from '@heroicons/vue/24/outline'
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
 * Devises supportées v1 — registre CountryDefaults (miroir de la validation
 * backend UpdateAccountingSettingsRequest, issue #5232).
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
const currentStep = ref(1)
const activation = reactive({
  completed: false,
  steps: { settings: false, contact: false, example_invoice: false },
  contact: null,
  example_invoice: null,
})

const form = reactive({
  currency: 'DZD',
  document_language: 'fr',
  template_style: 'modern',
  payment_terms: '',
  legal_mentions: '',
  tva_rates: [],
  series: [],
})

function applyActivation(data) {
  activation.completed = data?.completed === true
  activation.steps = data?.steps || activation.steps
  activation.contact = data?.contact || null
  activation.example_invoice = data?.example_invoice || null
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

/**
 * Charge l'état d'activation ; pré-remplit le formulaire avec les settings
 * persistés (ou défauts pays) pour que l'utilisateur parte de l'existant.
 */
async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/accounting/activation')
    applyActivation(data?.data || {})
    if (!activation.completed) {
      const { data: settingsData } = await api.get('/accounting/settings')
      applySettings(settingsData?.data || {})
    }
  } catch (err) {
    toast.error(err?.response?.data?.message || t('accounting.activation.load_error'))
  } finally {
    loading.value = false
  }
}

async function finish() {
  saving.value = true
  try {
    const payload = {
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
    }
    const { data } = await api.post('/accounting/activation', payload)
    applyActivation(data?.data || {})
    toast.success(t('accounting.activation.done_title'))
  } catch (err) {
    const serverMessage = err?.response?.data?.message
    const firstFieldError = err?.response?.data?.errors
      ? Object.values(err.response.data.errors)[0]?.[0]
      : null
    toast.error(serverMessage || firstFieldError || t('accounting.activation.complete_error'))
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
