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
        <div>
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="sim-compare">{{ $t('tax_slabs.sim_compare') }}</label>
          <input id="sim-compare" v-model.number="compareSalary" type="number" min="0" step="1000" class="form-input" @input="debouncedSimulate">
        </div>
        <div class="flex items-end">
          <button class="btn-primary w-full" :disabled="simulating" @click="runSimulate">
            {{ simulating ? $t('tax_slabs.sim_running') : $t('tax_slabs.sim_run') }}
          </button>
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
import { onMounted, ref } from 'vue'
import api from '@/services/api'
import TaxSlabEditor from '@/components/payroll/TaxSlabEditor.vue'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

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

const supportedCountries = [
  { code: 'DZ', flag: '🇩🇿', labelKey: 'common.countries.DZ' },
  { code: 'CM', flag: '🇨🇲', labelKey: 'common.countries.CM' },
  { code: 'CI', flag: '🇨🇮', labelKey: 'common.countries.CI' },
  { code: 'SN', flag: '🇸🇳', labelKey: 'common.countries.SN' },
  { code: 'MA', flag: '🇲🇦', labelKey: 'common.countries.MA' },
  { code: 'TN', flag: '🇹🇳', labelKey: 'common.countries.TN' },
  { code: 'TR', flag: '🇹🇷', labelKey: 'common.countries.TR' },
  { code: 'FR', flag: '🇫🇷', labelKey: 'common.countries.FR' },
  { code: 'CG', flag: '🇨🇬', labelKey: 'common.countries.CG' },
  { code: 'GA', flag: '🇬🇦', labelKey: 'common.countries.GA' },
  { code: 'BF', flag: '🇧🇫', labelKey: 'common.countries.BF' },
  { code: 'ML', flag: '🇲🇱', labelKey: 'common.countries.ML' },
]

const countryCode = ref('DZ')
const scope = ref('national')
const editorRef = ref(null)

const grossSalary = ref(60000)
const compareSalary = ref(0)
const simulating = ref(false)
const simResult = ref(null)

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
  return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Number(value || 0))
}

onMounted(runSimulate)
</script>
