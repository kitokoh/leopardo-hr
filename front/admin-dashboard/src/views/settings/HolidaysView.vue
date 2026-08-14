<template>
  <div class="space-y-8 animate-fade-in max-w-5xl">
    <div>
      <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">{{ $t('holidays.page_title') }}</h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
        {{ $t('holidays.page_subtitle') }}
      </p>
    </div>

    <!-- ── Jours fériés fixes ─────────────────────────────────────────── -->
    <div class="glass-card p-6">
      <div class="flex flex-wrap items-end gap-4">
        <div>
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="holiday-country">{{ $t('holidays.country') }}</label>
          <select id="holiday-country" v-model="countryCode" class="form-input min-w-40" @change="loadHolidays">
            <option v-for="cc in supportedCountries" :key="cc.code" :value="cc.code">{{ $t(cc.labelKey) }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="holiday-year">{{ $t('holidays.year') }}</label>
          <select id="holiday-year" v-model="year" class="form-input min-w-32" @change="loadHolidays">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div class="ml-auto">
          <button class="btn-primary" :disabled="saving" @click="openCreate">
            {{ $t('holidays.add') }}
          </button>
        </div>
      </div>

      <div class="mt-6 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-4 font-semibold">{{ $t('holidays.th_date') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('holidays.th_name') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('holidays.th_type') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('holidays.th_scope') }}</th>
              <th class="py-2 font-semibold text-right">{{ $t('holidays.th_actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="h in holidays" :key="h.id" class="border-b border-slate-100 dark:border-slate-800">
              <td class="py-2.5 pr-4 font-medium text-slate-800 dark:text-slate-200">{{ h.date }}</td>
              <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ h.name }}</td>
              <td class="py-2.5 pr-4">
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="typeBadge(h.holiday_type)">
                  {{ h.holiday_type }}
                </span>
              </td>
              <td class="py-2.5 pr-4">
                <span v-if="h.company_id === null" class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                  {{ $t('holidays.scope_national') }}
                </span>
                <span v-else class="px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300">
                  {{ $t('holidays.scope_company') }}
                </span>
              </td>
              <td class="py-2.5 text-right">
                <button class="btn-secondary py-1 px-2.5 mr-2" :disabled="h.company_id === null || saving" @click="openEdit(h)">
                  {{ $t('holidays.edit') }}
                </button>
                <button class="btn-danger py-1 px-2.5" :disabled="h.company_id === null || saving" @click="removeHoliday(h)">
                  {{ $t('holidays.delete') }}
                </button>
              </td>
            </tr>
            <tr v-if="!loading && holidays.length === 0">
              <td colspan="5" class="py-8 text-center text-slate-400">
                {{ $t('holidays.empty', { country: countryCode, year: String(year) }) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Fêtes islamiques (issue #1812) ────────────────────────────── -->
    <div class="glass-card p-6">
      <div class="flex flex-wrap items-center gap-4">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">Fêtes islamiques</h2>
          <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Dates mobiles du calendrier hégirien (Aïd, Maouloud, Tamkharit…) saisies par année.
            Elles s'appliquent automatiquement aux pays CEMAC/CEDEAO + DZ/MA/TN.
          </p>
        </div>
        <div class="ml-auto flex items-center gap-3">
          <select v-model="islamicYear" class="form-input min-w-28" @change="loadIslamicCalendar">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
          <button class="btn-secondary" :disabled="saving || unconfirmedCount === 0" @click="confirmYear">
            ✅ Confirmer {{ islamicYear }}
          </button>
        </div>
      </div>

      <!-- Banner d'alerte -->
      <div v-if="hasUnconfirmed" class="mt-4 rounded-lg border border-amber-300/60 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-800 dark:text-amber-200">
        ⚠️ {{ unconfirmedCount }} fête(s) islamique(s) non confirmée(s) pour {{ islamicYear }} —
        vérifiez les dates avant la clôture de paie.
      </div>

      <div class="mt-5 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-4 font-semibold">Fête</th>
              <th class="py-2 pr-4 font-semibold">Date grégorienne</th>
              <th class="py-2 pr-4 font-semibold">Durée</th>
              <th class="py-2 pr-4 font-semibold">Pays</th>
              <th class="py-2 pr-4 font-semibold">Statut</th>
              <th class="py-2 font-semibold text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="f in islamicEntries" :key="f.id" class="border-b border-slate-100 dark:border-slate-800">
              <td class="py-2.5 pr-4 font-medium text-slate-800 dark:text-slate-200">{{ f.name }}</td>
              <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ f.gregorian_date }}</td>
              <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ f.duration_days }} jour(s)</td>
              <td class="py-2.5 pr-4">
                <span v-for="cc in f.countries" :key="cc" class="mr-1 inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-sky-100 dark:bg-sky-900 text-sky-700 dark:text-sky-300">
                  {{ cc }}
                </span>
              </td>
              <td class="py-2.5 pr-4">
                <span v-if="f.confirmed" class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300">
                  ✅ Confirmé
                </span>
                <span v-else class="px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300">
                  🟡 Approximatif
                </span>
              </td>
              <td class="py-2.5 text-right">
                <button class="btn-secondary py-1 px-2.5" :disabled="saving" @click="openIslamicEdit(f)">
                  Modifier
                </button>
              </td>
            </tr>
            <tr v-if="!islamicLoading && islamicEntries.length === 0">
              <td colspan="6" class="py-8 text-center text-slate-400">
                Aucune fête islamique enregistrée pour {{ islamicYear }}.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal création/édition férié fixe -->
    <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeFixedModal">
      <div class="glass-card w-full max-w-md p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4">
          {{ editing ? $t('holidays.edit_title') : $t('holidays.add_title') }}
        </h2>
        <form class="space-y-4" @submit.prevent="saveHoliday">
          <div>
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="holiday-name">{{ $t('holidays.th_name') }}</label>
            <input id="holiday-name" v-model="form.name" type="text" class="form-input" maxlength="120" required>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="holiday-date">{{ $t('holidays.th_date') }}</label>
              <input id="holiday-date" v-model="form.date" type="date" class="form-input" required>
            </div>
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="holiday-type">{{ $t('holidays.th_type') }}</label>
              <select id="holiday-type" v-model="form.holiday_type" class="form-input">
                <option value="fixed">{{ $t('holidays.type_fixed') }}</option>
                <option value="islamic">{{ $t('holidays.type_islamic') }}</option>
                <option value="christian">{{ $t('holidays.type_christian') }}</option>
                <option value="custom">{{ $t('holidays.type_custom') }}</option>
              </select>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <input id="holiday-recurring" v-model="form.is_recurring" type="checkbox" class="h-4 w-4">
            <label for="holiday-recurring" class="text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ $t('holidays.recurring') }}
            </label>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="btn-secondary" @click="closeFixedModal">{{ $t('holidays.cancel') }}</button>
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? $t('holidays.saving') : $t('holidays.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal édition fête islamique -->
    <div v-if="islamicModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeIslamicModal">
      <div class="glass-card w-full max-w-md p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4">Modifier {{ islamicForm.name }}</h2>
        <form class="space-y-4" @submit.prevent="saveIslamicEntry">
          <div>
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="islamic-date">Date grégorienne</label>
            <input id="islamic-date" v-model="islamicForm.gregorian_date" type="date" class="form-input" required>
          </div>
          <div>
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="islamic-duration">Durée (jours)</label>
            <input id="islamic-duration" v-model.number="islamicForm.duration_days" type="number" min="1" max="5" class="form-input" required>
          </div>
          <div class="flex items-center gap-2">
            <input id="islamic-confirmed" v-model="islamicForm.confirmed" type="checkbox" class="h-4 w-4">
            <label for="islamic-confirmed" class="text-sm font-medium text-slate-700 dark:text-slate-300">
              Date confirmée (officielle)
            </label>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="btn-secondary" @click="closeIslamicModal">{{ $t('holidays.cancel') }}</button>
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? $t('holidays.saving') : $t('holidays.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import api from '@/services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

const supportedCountries = [
  { code: 'DZ', labelKey: 'holidays.countries.DZ' },
  { code: 'CM', labelKey: 'holidays.countries.CM' },
  { code: 'CI', labelKey: 'holidays.countries.CI' },
  { code: 'SN', labelKey: 'holidays.countries.SN' },
  { code: 'MA', labelKey: 'holidays.countries.MA' },
  { code: 'TN', labelKey: 'holidays.countries.TN' },
]

const years = Array.from({ length: 8 }, (_, i) => 2024 + i)

const countryCode = ref('DZ')
const year = ref(new Date().getFullYear())
const holidays = ref([])
const loading = ref(false)
const saving = ref(false)
const modalOpen = ref(false)
const editing = ref(null)
const form = reactive({ name: '', date: '', holiday_type: 'fixed', is_recurring: false })

// ── Fêtes islamiques (issue #1812) ─────────────────────────────────────
const islamicYear = ref(new Date().getFullYear())
const islamicEntries = ref([])
const islamicLoading = ref(false)
const islamicModalOpen = ref(false)
const islamicEditing = ref(null)
const islamicForm = reactive({ holiday_key: '', name: '', gregorian_date: '', duration_days: 1, confirmed: false })

const unconfirmedCount = computed(() => {
  return islamicEntries.value.filter((f) => !f.confirmed).length
})

// PA2-I18N-014 : expression déportée en computed.
const hasUnconfirmed = computed(() => unconfirmedCount.value > 0)

function closeIslamicModal() {
  islamicModalOpen.value = false
}

function closeFixedModal() {
  modalOpen.value = false
}

async function loadHolidays() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/public-holidays', {
      params: { country_code: countryCode.value, year: year.value },
    })
    holidays.value = data.data || []
  } catch (err) {
    toast.error(err?.response?.data?.message || t('holidays.load_error'))
  } finally {
    loading.value = false
  }
}

async function loadIslamicCalendar() {
  islamicLoading.value = true
  try {
    const { data } = await api.get('/admin/islamic-calendar', {
      params: { year: islamicYear.value },
    })
    islamicEntries.value = data.data || []
  } catch (err) {
    toast.error(err?.response?.data?.message || t('holidays.islamic.load_error'))
  } finally {
    islamicLoading.value = false
  }
}

function openCreate() {
  editing.value = null
  Object.assign(form, { name: '', date: `${year.value}-01-01`, holiday_type: 'fixed', is_recurring: false })
  modalOpen.value = true
}

function openEdit(h) {
  editing.value = h
  Object.assign(form, {
    name: h.name,
    date: h.date,
    holiday_type: h.holiday_type,
    is_recurring: Boolean(h.is_recurring),
  })
  modalOpen.value = true
}

async function saveHoliday() {
  saving.value = true
  try {
    const payload = {
      country_code: countryCode.value,
      name: form.name,
      date: form.date,
      year: year.value,
      holiday_type: form.holiday_type,
      is_recurring: form.is_recurring,
    }
    if (editing.value) {
      await api.put(`/admin/public-holidays/${editing.value.id}`, payload)
    } else {
      await api.post('/admin/public-holidays', payload)
    }
    modalOpen.value = false
    toast.success(t('holidays.saved'))
    await loadHolidays()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('holidays.save_error'))
  } finally {
    saving.value = false
  }
}

async function removeHoliday(h) {
  if (!window.confirm(`Supprimer « ${h.name} » ?`)) return
  saving.value = true
  try {
    await api.delete(`/admin/public-holidays/${h.id}`)
    toast.success(t('holidays.deleted'))
    await loadHolidays()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('holidays.delete_error'))
  } finally {
    saving.value = false
  }
}

function openIslamicEdit(f) {
  islamicEditing.value = f
  Object.assign(islamicForm, {
    holiday_key: f.holiday_key,
    name: f.name,
    gregorian_date: f.gregorian_date,
    duration_days: f.duration_days,
    confirmed: Boolean(f.confirmed),
  })
  islamicModalOpen.value = true
}

async function saveIslamicEntry() {
  saving.value = true
  try {
    await api.put(`/admin/islamic-calendar/${islamicForm.holiday_key}/${islamicYear.value}`, {
      gregorian_date: islamicForm.gregorian_date,
      duration_days: islamicForm.duration_days,
      confirmed: islamicForm.confirmed,
    })
    islamicModalOpen.value = false
    toast.success(t('holidays.islamic.saved'))
    await loadIslamicCalendar()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('holidays.islamic.save_error'))
  } finally {
    saving.value = false
  }
}

async function confirmYear() {
  if (!window.confirm(`Confirmer toutes les dates islamiques de ${islamicYear.value} ?`)) return
  saving.value = true
  try {
    const { data } = await api.post(`/admin/islamic-calendar/confirm-year/${islamicYear.value}`)
    toast.success(`${data.data?.confirmed_count ?? 0} date(s) confirmée(s).`)
    await loadIslamicCalendar()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('holidays.islamic.confirm_error'))
  } finally {
    saving.value = false
  }
}

function typeBadge(type) {
  const map = {
    fixed: 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300',
    islamic: 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300',
    christian: 'bg-sky-100 dark:bg-sky-900 text-sky-700 dark:text-sky-300',
    custom: 'bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300',
  }
  return map[type] || map.custom
}

onMounted(() => {
  loadHolidays()
  loadIslamicCalendar()
})
</script>
