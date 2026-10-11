<template>
  <div class="space-y-8 animate-fade-in max-w-6xl">
    <div>
      <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
        {{ $t('fundraising.settings.title') }}
      </h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
        {{ $t('fundraising.settings.subtitle') }}
      </p>
    </div>

    <!-- Verticale non activée (flag `fundraising`) ou accès refusé -->
    <div
      v-if="moduleDisabled"
      class="flex items-center gap-3 rounded-2xl border border-amber-300/70 dark:border-amber-700/60 bg-amber-50/80 dark:bg-amber-950/40 p-4"
      role="status"
    >
      <SparklesIcon class="h-6 w-6 text-amber-500" aria-hidden="true" />
      <div>
        <p class="text-sm font-bold text-amber-800 dark:text-amber-300">
          {{ $t('fundraising.settings.disabled_title') }}
        </p>
        <p class="text-sm text-amber-700/90 dark:text-amber-400/80">
          {{ $t('fundraising.settings.disabled_body') }}
        </p>
      </div>
    </div>

    <div v-if="loading" class="glass-card p-6 text-slate-500 dark:text-slate-400">
      {{ $t('common.busy', 'Chargement…') }}
    </div>

    <form v-else-if="!moduleDisabled" class="space-y-6" @submit.prevent="save">
      <!-- Moyens de paiement offerts -->
      <section class="glass-card p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">
          {{ $t('fundraising.settings.payments_title') }}
        </h2>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('fundraising.settings.payments_subtitle') }}
        </p>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
          <label
            v-for="method in paymentMethods"
            :key="method.key"
            class="flex items-center gap-3 rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-3 cursor-pointer"
          >
            <input
              v-model="form.enabled_methods"
              type="checkbox"
              :value="method.key"
              class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
            />
            <span class="text-sm font-bold text-slate-800 dark:text-slate-200">
              {{ $t(`fundraising.settings.method_${method.key}`) }}
            </span>
          </label>
        </div>
        <p v-if="form.enabled_methods.length === 0" class="mt-2 text-xs font-bold text-red-500">
          {{ $t('fundraising.settings.methods_required') }}
        </p>
      </section>

      <!-- Garde-fous montants -->
      <section class="glass-card p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">
          {{ $t('fundraising.settings.limits_title') }}
        </h2>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('fundraising.settings.limits_subtitle') }}
        </p>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $t('fundraising.settings.default_min_amount') }}
            <input
              v-model="form.default_min_amount"
              type="number"
              min="0"
              step="any"
              :placeholder="String(configDefaults.min)"
              class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm"
            />
          </label>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $t('fundraising.settings.default_max_amount') }}
            <input
              v-model="form.default_max_amount"
              type="number"
              min="0"
              step="any"
              :placeholder="String(configDefaults.max)"
              class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm"
            />
          </label>
        </div>
      </section>

      <!-- Montants suggérés -->
      <section class="glass-card p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">
          {{ $t('fundraising.settings.suggested_title') }}
        </h2>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('fundraising.settings.suggested_subtitle') }}
        </p>
        <input
          v-model="form.suggested_amounts_text"
          type="text"
          class="mt-4 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm"
          placeholder="500, 1000, 5000"
        />
        <p class="mt-1 text-xs text-slate-400">{{ $t('fundraising.settings.suggested_hint') }}</p>
      </section>

      <!-- Surface publique -->
      <section class="glass-card p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">
          {{ $t('fundraising.settings.public_title') }}
        </h2>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('fundraising.settings.public_subtitle') }}
        </p>
        <div class="mt-4 space-y-3">
          <label
            v-for="toggle in publicToggles"
            :key="toggle.key"
            class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-3 cursor-pointer"
          >
            <span>
              <span class="block text-sm font-bold text-slate-800 dark:text-slate-200">
                {{ $t(`fundraising.settings.${toggle.key}`) }}
              </span>
              <span class="block text-xs text-slate-500 dark:text-slate-400">
                {{ $t(`fundraising.settings.${toggle.key}_hint`) }}
              </span>
            </span>
            <input
              v-model="form[toggle.key]"
              type="checkbox"
              class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
            />
          </label>
        </div>
      </section>

      <div class="flex items-center gap-3">
        <button
          type="submit"
          class="btn-primary"
          :disabled="saving || form.enabled_methods.length === 0"
        >
          {{ saving ? $t('common.busy', 'Chargement…') : $t('fundraising.settings.save') }}
        </button>
        <span v-if="saved" class="text-sm font-bold text-emerald-600 dark:text-emerald-400">
          {{ $t('fundraising.settings.saved') }}
        </span>
      </div>
    </form>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { SparklesIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { useToast } from 'vue-toastification'

const toast = useToast()
const localeStore = useLocaleStore()

function t(key, fallback = '') {
  return translate(localeStore.current, key, fallback)
}

const paymentMethods = [
  { key: 'card' },
  { key: 'mobile_money' },
  { key: 'bank_transfer' },
  { key: 'cash' },
]

const publicToggles = [
  { key: 'allow_anonymous' },
  { key: 'allow_messages' },
  { key: 'show_supporters' },
]

// Rappel des défauts serveur (config/fundraising.php) pour les placeholders.
const configDefaults = { min: 100, max: 10000000 }

const loading = ref(true)
const saving = ref(false)
const saved = ref(false)
const moduleDisabled = ref(false)

const form = reactive({
  enabled_methods: ['card', 'mobile_money', 'bank_transfer', 'cash'],
  default_min_amount: '',
  default_max_amount: '',
  suggested_amounts_text: '',
  allow_anonymous: true,
  allow_messages: true,
  show_supporters: true,
})

function applySettings(settings) {
  form.enabled_methods = Array.isArray(settings.enabled_methods) ? [...settings.enabled_methods] : []
  form.default_min_amount = settings.default_min_amount ?? ''
  form.default_max_amount = settings.default_max_amount ?? ''
  form.suggested_amounts_text = Array.isArray(settings.default_suggested_amounts)
    ? settings.default_suggested_amounts.join(', ')
    : ''
  form.allow_anonymous = settings.allow_anonymous !== false
  form.allow_messages = settings.allow_messages !== false
  form.show_supporters = settings.show_supporters !== false
}

function parseSuggestedAmounts() {
  const amounts = form.suggested_amounts_text
    .split(',')
    .map((chunk) => Number(chunk.trim()))
    .filter((value) => Number.isFinite(value) && value > 0)

  return amounts.length > 0 ? amounts : null
}

async function load() {
  loading.value = true
  saved.value = false
  try {
    const { data } = await api.get('/fundraising/settings')
    applySettings(data.data || {})
  } catch (err) {
    if (err?.response?.status === 403) {
      // Flag `fundraising` inactif (ou rôle insuffisant) — bannière dédiée.
      moduleDisabled.value = true
    } else {
      toast.error(err?.response?.data?.message || t('fundraising.settings.load_error', 'Chargement impossible.'))
    }
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  saved.value = false
  try {
    const { data } = await api.put('/fundraising/settings', {
      enabled_methods: form.enabled_methods,
      default_min_amount: form.default_min_amount === '' ? null : Number(form.default_min_amount),
      default_max_amount: form.default_max_amount === '' ? null : Number(form.default_max_amount),
      default_suggested_amounts: parseSuggestedAmounts(),
      allow_anonymous: form.allow_anonymous,
      allow_messages: form.allow_messages,
      show_supporters: form.show_supporters,
    })
    applySettings(data.data || {})
    saved.value = true
    toast.success(t('fundraising.settings.saved', 'Paramétrage enregistré.'))
  } catch (err) {
    const serverMessage = err?.response?.data?.message
    const firstFieldError = err?.response?.data?.errors
      ? Object.values(err.response.data.errors).flat()[0]
      : null
    toast.error(serverMessage || firstFieldError || t('fundraising.settings.save_error', 'Enregistrement impossible.'))
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<style scoped>
@reference '../../style.css';
</style>
