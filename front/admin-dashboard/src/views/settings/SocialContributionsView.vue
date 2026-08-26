<template>
  <div class="space-y-8 animate-fade-in max-w-6xl">
    <div>
      <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
        {{ $t('social_contrib.title') }}
      </h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
        {{ $t('social_contrib.subtitle') }}
      </p>
    </div>

    <!-- Liste des cotisations nationales -->
    <div class="glass-card p-6">
      <div class="flex flex-wrap items-end gap-4">
        <div>
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="sc-country">{{ $t('social_contrib.th_country') }}</label>
          <select id="sc-country" v-model="countryCode" class="form-input min-w-40" @change="load">
            <option v-for="cc in supportedCountries" :key="cc.code" :value="cc.code">{{ cc.flag }} {{ $t(cc.labelKey) }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="sc-type">{{ $t('social_contrib.th_type') }}</label>
          <select id="sc-type" v-model="typeFilter" class="form-input min-w-40" @change="load">
            <option value="">{{ $t('social_contrib.type_all') }}</option>
            <option value="employee">{{ $t('social_contrib.type_employee') }}</option>
            <option value="employer">{{ $t('social_contrib.type_employer') }}</option>
          </select>
        </div>
        <div class="ml-auto">
          <button class="btn-primary" :disabled="busy" @click="openCreate">{{ $t('social_contrib.add') }}</button>
        </div>
      </div>

      <div class="mt-5 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-4 font-semibold">{{ $t('social_contrib.th_org') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('social_contrib.th_code') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('social_contrib.th_type') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('social_contrib.th_rate') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('social_contrib.th_cap') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('social_contrib.th_effective') }}</th>
              <th class="py-2 font-semibold text-right">{{ $t('social_contrib.th_actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in items" :key="c.id" class="border-b border-slate-100 dark:border-slate-800">
              <td class="py-2.5 pr-4 font-medium text-slate-800 dark:text-slate-200">{{ c.name }}</td>
              <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ c.code }}</td>
              <td class="py-2.5 pr-4">
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="c.type === 'employer' ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300' : 'bg-teal-100 dark:bg-teal-900 text-teal-700 dark:text-teal-300'">
                  {{ c.type === 'employer' ? $t('social_contrib.type_employer') : $t('social_contrib.type_employee') }}
                </span>
              </td>
              <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ c.rate }} %</td>
              <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ c.cap === null ? '∞' : money(c.cap) }}</td>
              <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ c.effective_from }}</td>
              <td class="py-2.5 text-right whitespace-nowrap">
                <button class="btn-secondary py-1 px-2.5 mr-2" :disabled="busy" @click="openEdit(c)">{{ $t('social_contrib.edit') }}</button>
                <button class="btn-danger py-1 px-2.5" :disabled="busy" @click="askRemoveItem(c)">{{ $t('social_contrib.delete') }}</button>
              </td>
            </tr>
            <tr v-if="items.length === 0">
              <td colspan="7" class="py-8 text-center text-slate-400">{{ $t('social_contrib.empty') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Simulateur + comparateur -->
    <div class="glass-card p-6">
      <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $t('social_contrib.sim_title') }}</h2>
      <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $t('social_contrib.sim_subtitle') }}</p>

      <div class="mt-4 grid grid-cols-1 lg:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="simc-gross">{{ $t('social_contrib.sim_gross') }}</label>
          <input id="simc-gross" v-model.number="grossSalary" type="number" min="0" step="1000" class="form-input" @input="debouncedSimulate">
        </div>
        <div>
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="simc-country">{{ $t('social_contrib.th_country') }}</label>
          <select id="simc-country" v-model="simCountry" class="form-input" @change="runSimulate">
            <option v-for="cc in supportedCountries" :key="cc.code" :value="cc.code">{{ cc.flag }} {{ $t(cc.labelKey) }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="simc-compare">{{ $t('social_contrib.compare_country') }}</label>
          <select id="simc-compare" v-model="compareCountry" class="form-input" @change="runSimulate">
            <option v-for="cc in supportedCountries" :key="cc.code" :value="cc.code">{{ cc.flag }} {{ $t(cc.labelKey) }}</option>
          </select>
        </div>
        <div class="flex items-end">
          <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
            <input v-model="ignoreCaps" type="checkbox" class="h-4 w-4" @change="runSimulate">
            {{ $t('social_contrib.ignore_caps') }}
          </label>
        </div>
      </div>

      <div v-if="simA && simB" class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div v-for="(sim, label) in { a: simA, b: simB }" :key="label" class="rounded-lg border border-slate-200 dark:border-slate-700 p-4">
          <div class="flex items-center justify-between">
            <h3 class="font-bold text-slate-900 dark:text-white">{{ sim.country_code }}</h3>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wide">{{ $t('social_contrib.total_cost') }}</span>
          </div>
          <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
            <div class="rounded bg-slate-50 dark:bg-slate-800 p-3">
              <div class="text-xs font-bold text-slate-400">{{ $t('social_contrib.sim_employee') }}</div>
              <div class="mt-1 text-lg font-black text-slate-800 dark:text-slate-100">{{ money(sim.social_employee) }}</div>
            </div>
            <div class="rounded bg-slate-50 dark:bg-slate-800 p-3">
              <div class="text-xs font-bold text-slate-400">{{ $t('social_contrib.sim_employer') }}</div>
              <div class="mt-1 text-lg font-black text-slate-800 dark:text-slate-100">{{ money(sim.social_employer) }}</div>
            </div>
            <div class="rounded bg-slate-50 dark:bg-slate-800 p-3">
              <div class="text-xs font-bold text-slate-400">{{ $t('social_contrib.sim_tax') }}</div>
              <div class="mt-1 text-lg font-black text-rose-600">{{ money(sim.income_tax) }}</div>
            </div>
            <div class="rounded bg-emerald-50 dark:bg-emerald-900/40 p-3">
              <div class="text-xs font-bold text-emerald-600">{{ $t('social_contrib.total_cost') }}</div>
              <div class="mt-1 text-lg font-black text-emerald-600">{{ money(sim.total_cost) }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal formulaire -->
    <div v-if="formOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeForm">
      <div class="glass-card w-full max-w-lg p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4">
          {{ editing ? $t('social_contrib.edit_title') : $t('social_contrib.add_title') }}
        </h2>
        <form class="space-y-4" @submit.prevent="save">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="scf-org">{{ $t('social_contrib.th_org') }}</label>
              <input id="scf-org" v-model="form.name" type="text" class="form-input" maxlength="150" required>
            </div>
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="scf-code">{{ $t('social_contrib.th_code') }}</label>
              <input id="scf-code" v-model="form.code" type="text" class="form-input" maxlength="50" required>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="scf-type">{{ $t('social_contrib.th_type') }}</label>
              <select id="scf-type" v-model="form.type" class="form-input" required>
                <option value="employee">{{ $t('social_contrib.type_employee') }}</option>
                <option value="employer">{{ $t('social_contrib.type_employer') }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="scf-rate">{{ $t('social_contrib.th_rate') }}</label>
              <input id="scf-rate" v-model.number="form.rate" type="number" min="0" max="100" step="0.01" class="form-input" required>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="scf-cap">{{ $t('social_contrib.th_cap') }}</label>
              <input id="scf-cap" v-model.number="form.cap" type="number" min="0" step="1000" class="form-input">
            </div>
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="scf-eff">{{ $t('social_contrib.th_effective') }}</label>
              <input id="scf-eff" v-model="form.effective_from" type="date" class="form-input" required>
            </div>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="btn-secondary" @click="closeForm">{{ $t('social_contrib.cancel') }}</button>
            <button type="submit" class="btn-primary" :disabled="busy">
              {{ busy ? $t('social_contrib.saving') : $t('social_contrib.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <ConfirmDialog
    :open="deleteOpen"
    :title="t('social_contrib.delete_confirm_title', 'Supprimer cette cotisation ?')"
    :message="deleteTarget ? t('social_contrib.delete_confirm', { name: deleteTarget.name }) : ''"
    :confirm-label="t('common.delete', 'Supprimer')"
    @confirm="removeItem"
    @cancel="deleteOpen = false"
  />
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import api from '@/services/api'
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
const typeFilter = ref('')
const items = ref([])
const busy = ref(false)
const formOpen = ref(false)
const editing = ref(null)
const form = reactive({ name: '', code: '', type: 'employee', rate: null, cap: null, effective_from: '' })

const grossSalary = ref(60000)
const simCountry = ref('CM')
const compareCountry = ref('SN')
const ignoreCaps = ref(false)
const simA = ref(null)
const simulating = ref(false)
const simB = ref(null)
let debounceTimer = null

async function load() {
  busy.value = true
  try {
    const { data } = await api.get('/admin/social-contributions', {
      params: { country_code: countryCode.value, type: typeFilter.value || undefined },
    })
    items.value = data.data || []
  } catch (err) {
    toast.error(err?.response?.data?.message || t('social_contrib.load_error'))
  } finally {
    busy.value = false
  }
}

function openCreate() {
  editing.value = null
  Object.assign(form, { name: '', code: '', type: 'employee', rate: null, cap: null, effective_from: `${new Date().getFullYear()}-01-01` })
  formOpen.value = true
}

function openEdit(item) {
  editing.value = item
  Object.assign(form, {
    name: item.name,
    code: item.code,
    type: item.type,
    rate: item.rate,
    cap: item.cap,
    effective_from: item.effective_from,
  })
  formOpen.value = true
}

function closeForm() {
  formOpen.value = false
}

async function save() {
  busy.value = true
  try {
    const payload = {
      country_code: countryCode.value,
      name: form.name,
      code: form.code,
      type: form.type,
      rate: form.rate,
      cap: form.cap ?? null,
      effective_from: form.effective_from,
    }
    if (editing.value) {
      await api.put(`/admin/social-contributions/${editing.value.id}`, payload)
      toast.success(t('social_contrib.saved'))
    } else {
      await api.post('/admin/social-contributions', payload)
      toast.success(t('social_contrib.created'))
    }
    formOpen.value = false
    await load()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('social_contrib.save_error'))
  } finally {
    busy.value = false
  }
}

const deleteTarget = ref(null)
const deleteOpen = ref(false)

function askRemoveItem(item) {
  deleteTarget.value = item
  deleteOpen.value = true
}

async function removeItem() {
  const item = deleteTarget.value
  if (!item) return
  deleteOpen.value = false
  busy.value = true
  try {
    await api.delete(`/admin/social-contributions/${item.id}`)
    toast.success(t('social_contrib.deleted'))
    await load()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('social_contrib.delete_error'))
  } finally {
    busy.value = false
  }
}

function debouncedSimulate() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(runSimulate, 400)
}

async function runSimulate() {
  if (!grossSalary.value || grossSalary.value <= 0) return
  // Issue #2712 — état de chargement + try/catch (rejet non géré auparavant).
  simulating.value = true
  try {
    const [a, b] = await Promise.all([
      api.post('/admin/payroll/simulate', {
        country_code: simCountry.value,
        gross_salary: grossSalary.value,
        ignore_caps: ignoreCaps.value,
      }),
      api.post('/admin/payroll/simulate', {
        country_code: compareCountry.value,
        gross_salary: grossSalary.value,
        ignore_caps: ignoreCaps.value,
      }),
    ])
    simA.value = a.data.data
    simB.value = b.data.data
  } catch (e) {
    console.error('Simulation failed:', e)
    toast.error('Erreur lors de la simulation : ' + (e?.response?.data?.message || e.message))
  } finally {
    simulating.value = false
  }
}

function money(value) {
  // Issue #2715 — formatage selon la locale active (plus de fr-FR codé).
  return new Intl.NumberFormat(toIntlLocale(localeStore.current), { maximumFractionDigits: 0 }).format(Number(value || 0))
}

onMounted(() => {
  load()
  runSimulate()
})
</script>
