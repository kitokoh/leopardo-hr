<template>
  <div class="space-y-8 animate-fade-in max-w-6xl">
    <div>
      <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
        {{ $t('tax_slabs.title') }}
      </h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
        {{ $t('tax_slabs.subtitle') }}
      </p>
    </div>

    <div class="glass-card p-6">
      <div class="flex flex-wrap items-end gap-4">
        <div>
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="slab-country">{{ $t('tax_slabs.th_country') }}</label>
          <select id="slab-country" v-model="countryCode" class="form-input min-w-40" @change="onCountryChange">
            <option v-for="cc in supportedCountries" :key="cc.code" :value="cc.code">{{ cc.flag }} {{ $t(cc.labelKey) }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="slab-scope">{{ $t('tax_slabs.scope') }}</label>
          <select id="slab-scope" v-model="scope" class="form-input min-w-40" disabled>
            <option value="national">{{ $t('tax_slabs.scope_national') }}</option>
            <option value="company">{{ $t('tax_slabs.scope_company') }}</option>
          </select>
        </div>
        <p class="text-sm text-slate-400 ml-1">({{ $t('tax_slabs.national_note') }})</p>
      </div>

      <div class="mt-6">
        <TaxSlabEditor
          ref="editorRef"
          :key="countryCode"
          :country-code="countryCode"
          @changed="onSlabsChanged"
        />
      </div>
    </div>

    <!-- Simulateur d'impact -->
    <div class="glass-card p-6">
      <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $t('tax_slabs.simulator_title') }}</h2>
      <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $t('tax_slabs.simulator_subtitle') }}</p>

      <div class="mt-4 grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div>
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="sim-gross">{{ $t('tax_slabs.sim_gross') }}</label>
          <input id="sim-gross" v-model.number="grossSalary" type="number" min="0" step="1000" class="form-input" @input="debouncedSimulate">
        </div>
        <div class="flex items-end">
          <button class="btn-primary w-full" :disabled="simulating" @click="runSimulate">
            {{ simulating ? $t('tax_slabs.sim_running') : $t('tax_slabs.sim_run') }}
          </button>
        </div>
      </div>

      <div v-if="confidenceLevel" class="mt-6 rounded-2xl border p-4" :class="confidenceBannerClass" role="alert">
        <div class="flex items-start gap-3">
          <span class="mt-0.5 text-xl leading-none" aria-hidden="true">{{ confidenceIcon }}</span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-bold text-slate-900 dark:text-white">
              {{ t('payroll.confidence.label') }} — {{ t(confidenceLevelLabelKey) }}
            </p>
            <p class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ confidenceMessage }}</p>
            <p
              v-if="simResult.compliance && simResult.compliance.warning && confidenceMessage !== simResult.compliance.warning"
              class="mt-1 text-xs text-slate-500 dark:text-slate-400"
            >
              {{ simResult.compliance.warning }}
            </p>
          </div>
        </div>
      </div>

      <div v-if="simResult" class="mt-6 grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-4">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wide">{{ $t('tax_slabs.sim_gross') }}</div>
          <div class="mt-1 text-xl font-black text-slate-900 dark:text-white">{{ money(simResult.gross) }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-4">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wide">{{ $t('tax_slabs.sim_social') }}</div>
          <div class="mt-1 text-xl font-black text-slate-900 dark:text-white">{{ money(simResult.social_employee) }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-4">
          <div class="text-xs font-bold text-slate-400 uppercase tracking-wide">{{ $t('tax_slabs.sim_tax') }}</div>
          <div class="mt-1 text-xl font-black text-rose-600 dark:text-rose-400">{{ money(simResult.income_tax) }}</div>
        </div>
        <div class="rounded-lg border border-emerald-200 dark:border-emerald-800 p-4">
          <div class="text-xs font-bold text-emerald-600 uppercase tracking-wide">{{ $t('tax_slabs.sim_net') }}</div>
          <div class="mt-1 text-xl font-black text-emerald-600 dark:text-emerald-400">{{ money(simResult.net) }}</div>
        </div>
      </div>

      <div v-if="simResult && simResult.income_tax_by_slab.length" class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-4 font-semibold">{{ $t('tax_slabs.th_min') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('tax_slabs.th_max') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('tax_slabs.th_rate') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('tax_slabs.sim_base') }}</th>
              <th class="py-2 font-semibold">{{ $t('tax_slabs.sim_slab_tax') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(slab, i) in simResult.income_tax_by_slab" :key="i" class="border-b border-slate-100 dark:border-slate-800">
              <td class="py-2 pr-4 text-slate-700 dark:text-slate-300">{{ money(slab.min) }}</td>
              <td class="py-2 pr-4 text-slate-700 dark:text-slate-300">{{ slab.max === null ? '∞' : money(slab.max) }}</td>
              <td class="py-2 pr-4 text-slate-700 dark:text-slate-300">{{ slab.rate }} %</td>
              <td class="py-2 pr-4 text-slate-700 dark:text-slate-300">{{ money(slab.taxable_amount) }}</td>
              <td class="py-2 text-slate-700 dark:text-slate-300">{{ money(slab.tax) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import TaxSlabEditor from '@/components/payroll/TaxSlabEditor.vue'
import { useToast } from 'vue-toastification'
import { translate, toIntlLocale } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { useSupportedCountries } from '@/composables/useSupportedCountries'
const supportedCountries = useSupportedCountries()

const toast = useToast()
const localeStore = useLocaleStore()

/** Traduction avec interpolation {var} — convention catalogue i18n (#1916). */
function t(key, vars = {}) {
  let msg = translate(localeStore.current, key, key)
  for (const [k, v] of Object.entries(vars)) {
    msg = msg.replace(`{${k}}`, String(v))
  }
  return msg
}

const countryCode = ref('DZ')
const scope = ref('national')
const editorRef = ref(null)

const grossSalary = ref(60000)
const simulating = ref(false)
const simResult = ref(null)

// Issue #1872/#2112 — bandeau de conformité : niveau de confiance des règles
// pays renvoyé par POST /payroll/simulate (bloc `compliance` du contrat).
// Un niveau pilote/placeholder ne doit jamais être présenté comme une paie
// légalement certifiée ; messages localisés via le catalogue payroll.confidence.*.
const confidenceLevel = computed(() => simResult.value?.compliance?.level || null)

const confidenceLevelLabelKey = computed(() => {
  const level = confidenceLevel.value
  return level ? `payroll.confidence.level_${level}` : 'payroll.confidence.level_unknown'
})

const confidenceMessage = computed(() => {
  const level = confidenceLevel.value
  const key = level ? `payroll.confidence.${level}.message` : 'payroll.confidence.unknown.message'
  return t(key, { country: countryCode.value })
})

const confidenceBannerClass = computed(() => {
  switch (confidenceLevel.value) {
    case 'production':
      return 'border-green-300 bg-green-50 dark:border-green-800 dark:bg-green-900/20'
    case 'placeholder':
      return 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-900/20'
    case 'pilot':
      return 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20'
    default:
      return 'border-slate-300 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/50'
  }
})

const confidenceIcon = computed(() => {
  switch (confidenceLevel.value) {
    case 'production':
      return '✅'
    case 'placeholder':
      return '⛔'
    case 'pilot':
      return '⚠️'
    default:
      return 'ℹ️'
  }
})

let debounceTimer = null

function onCountryChange() {
  simResult.value = null
}

function onSlabsChanged() {
  runSimulate()
}

function debouncedSimulate() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(runSimulate, 400)
}

async function runSimulate() {
  if (!grossSalary.value || grossSalary.value <= 0) return
  simulating.value = true
  try {
    const { data } = await api.post('/admin/payroll/simulate', {
      country_code: countryCode.value,
      gross_salary: grossSalary.value,
    })
    simResult.value = data.data
  } catch (err) {
    toast.error(err?.response?.data?.message || t('tax_slabs.sim_error'))
  } finally {
    simulating.value = false
  }
}

function money(value) {
  // Issue #2715 — formatage selon la locale active (plus de fr-FR codé).
  return new Intl.NumberFormat(toIntlLocale(localeStore.current), { maximumFractionDigits: 0 }).format(Number(value || 0))
}

onMounted(runSimulate)
</script>
