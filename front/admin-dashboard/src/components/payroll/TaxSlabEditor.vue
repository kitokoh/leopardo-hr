<template>
  <div class="space-y-4">
    <!-- Barème courant -->
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-slate-500 dark:text-slate-400">
            <th class="py-2 pr-4 font-semibold">{{ $t('tax_slabs.th_min') }}</th>
            <th class="py-2 pr-4 font-semibold">{{ $t('tax_slabs.th_max') }}</th>
            <th class="py-2 pr-4 font-semibold">{{ $t('tax_slabs.th_rate') }}</th>
            <th class="py-2 pr-4 font-semibold">{{ $t('tax_slabs.th_deduction') }}</th>
            <th class="py-2 pr-4 font-semibold">{{ $t('tax_slabs.th_effective') }}</th>
            <th class="py-2 font-semibold text-right">{{ $t('tax_slabs.th_actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="slab in slabs" :key="slab.id" class="border-b border-slate-100 dark:border-slate-800">
            <td class="py-2.5 pr-4 font-medium text-slate-800 dark:text-slate-200">{{ fmt(slab.min_amount) }}</td>
            <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ slab.max_amount === null ? '∞' : fmt(slab.max_amount) }}</td>
            <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ slab.rate }} %</td>
            <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ fmt(slab.fixed_deduction) }}</td>
            <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ slab.effective_from }}</td>
            <td class="py-2.5 text-right whitespace-nowrap">
              <button class="btn-secondary py-1 px-2.5 mr-2" :disabled="busy" @click="openEdit(slab)">{{ $t('tax_slabs.edit') }}</button>
              <button class="btn-danger py-1 px-2.5" :disabled="busy" @click="removeSlab(slab)">{{ $t('tax_slabs.delete') }}</button>
            </td>
          </tr>
          <tr v-if="slabs.length === 0">
            <td colspan="6" class="py-6 text-center text-slate-400">{{ $t('tax_slabs.empty') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex flex-wrap gap-3 pt-1">
      <button class="btn-primary" :disabled="busy" @click="openCreate">
        {{ $t('tax_slabs.add') }}
      </button>
      <button class="btn-secondary" :disabled="busy" @click="confirmReset">
        {{ $t('tax_slabs.reset') }}
      </button>
    </div>

    <!-- Modal tranche -->
    <div v-if="formOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeForm">
      <div class="glass-card w-full max-w-md p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4">
          {{ editing ? $t('tax_slabs.edit_title') : $t('tax_slabs.add_title') }}
        </h2>
        <form class="space-y-4" @submit.prevent="saveSlab">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" :for="uid('min')">{{ $t('tax_slabs.th_min') }}</label>
              <input :id="uid('min')" v-model.number="form.min_amount" type="number" min="0" step="0.01" class="form-input" required>
            </div>
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" :for="uid('max')">{{ $t('tax_slabs.th_max') }}</label>
              <input :id="uid('max')" v-model.number="form.max_amount" type="number" min="0" step="0.01" class="form-input">
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" :for="uid('rate')">{{ $t('tax_slabs.th_rate') }}</label>
              <input :id="uid('rate')" v-model.number="form.rate" type="number" min="0" max="100" step="0.01" class="form-input" required>
            </div>
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" :for="uid('ded')">{{ $t('tax_slabs.th_deduction') }}</label>
              <input :id="uid('ded')" v-model.number="form.fixed_deduction" type="number" min="0" step="0.01" class="form-input">
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" :for="uid('eff')">{{ $t('tax_slabs.th_effective') }}</label>
              <input :id="uid('eff')" v-model="form.effective_from" type="date" class="form-input" required>
            </div>
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" :for="uid('legal')">{{ $t('tax_slabs.legal_ref') }}</label>
              <input :id="uid('legal')" v-model="form.name" type="text" class="form-input" maxlength="150" required>
            </div>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="btn-secondary" @click="closeForm">{{ $t('tax_slabs.cancel') }}</button>
            <button type="submit" class="btn-primary" :disabled="busy">
              {{ busy ? $t('tax_slabs.saving') : $t('tax_slabs.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import api from '@/services/api'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const props = defineProps({
  countryCode: { type: String, required: true },
})

const emit = defineEmits(['saved', 'changed'])

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

const slabs = ref([])
const busy = ref(false)
const formOpen = ref(false)
const editing = ref(null)
const form = reactive({
  name: '',
  min_amount: null,
  max_amount: null,
  rate: null,
  fixed_deduction: 0,
  effective_from: '',
})

let uidCounter = 0
function uid(suffix) {
  uidCounter += 1
  return `slab-${props.countryCode}-${suffix}-${uidCounter}`
}

async function load() {
  busy.value = true
  try {
    const { data } = await api.get('/admin/tax-slabs', {
      params: { country_code: props.countryCode },
    })
    slabs.value = data.data || []
  } catch (err) {
    toast.error(err?.response?.data?.message || t('tax_slabs.load_error'))
  } finally {
    busy.value = false
  }
}

function openCreate() {
  editing.value = null
  Object.assign(form, {
    name: t('tax_slabs.default_name', { country: props.countryCode }),
    min_amount: null,
    max_amount: null,
    rate: null,
    fixed_deduction: 0,
    effective_from: `${new Date().getFullYear()}-01-01`,
  })
  formOpen.value = true
}

function openEdit(slab) {
  editing.value = slab
  Object.assign(form, {
    name: slab.name,
    min_amount: slab.min_amount,
    max_amount: slab.max_amount,
    rate: slab.rate,
    fixed_deduction: slab.fixed_deduction,
    effective_from: slab.effective_from,
  })
  formOpen.value = true
}

function closeForm() {
  formOpen.value = false
}

async function saveSlab() {
  busy.value = true
  try {
    const payload = {
      country_code: props.countryCode,
      name: form.name,
      min_amount: form.min_amount,
      max_amount: form.max_amount ?? null,
      rate: form.rate,
      fixed_deduction: form.fixed_deduction ?? 0,
      effective_from: form.effective_from,
    }
    if (editing.value) {
      await api.put(`/admin/tax-slabs/${editing.value.id}`, payload)
      toast.success(t('tax_slabs.saved'))
    } else {
      await api.post('/admin/tax-slabs', payload)
      toast.success(t('tax_slabs.created'))
    }
    formOpen.value = false
    await load()
    emit('changed', slabs.value)
  } catch (err) {
    toast.error(err?.response?.data?.message || t('tax_slabs.save_error'))
  } finally {
    busy.value = false
  }
}

async function removeSlab(slab) {
  if (!window.confirm(t('tax_slabs.delete_confirm', { name: slab.name }))) return
  busy.value = true
  try {
    await api.delete(`/admin/tax-slabs/${slab.id}`)
    toast.success(t('tax_slabs.deleted'))
    await load()
    emit('changed', slabs.value)
  } catch (err) {
    toast.error(err?.response?.data?.message || t('tax_slabs.delete_error'))
  } finally {
    busy.value = false
  }
}

async function confirmReset() {
  if (!window.confirm(t('tax_slabs.reset_confirm'))) return
  busy.value = true
  try {
    await api.post('/admin/tax-slabs/reset-defaults', { country_code: props.countryCode })
    toast.success(t('tax_slabs.reset_done'))
    await load()
    emit('changed', slabs.value)
  } catch (err) {
    toast.error(err?.response?.data?.message || t('tax_slabs.reset_error'))
  } finally {
    busy.value = false
  }
}

function fmt(value) {
  return new Intl.NumberFormat('fr-FR').format(Number(value || 0))
}

defineExpose({ load })

onMounted(load)
</script>
