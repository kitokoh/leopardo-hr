<template>
  <div class="space-y-8 animate-fade-in max-w-5xl">
    <div>
      <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">{{ $t('holidays.page.title', 'Jours fériés par pays') }}</h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
        {{ $t('holidays.page.subtitle', "Calendrier des jours fériés utilisés par le moteur de paie pour calculer les jours ouvrés réels (issue #1811). Les fériés nationaux sont partagés ; les fériés d'entreprise restent propres à votre société.") }}
      </p>
    </div>

    <div class="glass-card p-6">
      <div class="flex flex-wrap items-end gap-4">
        <div>
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="holiday-country">{{ $t('holidays.country.label', 'Pays') }}</label>
          <select id="holiday-country" v-model="countryCode" class="form-input min-w-40" @change="loadHolidays">
            <option v-for="cc in supportedCountries" :key="cc.code" :value="cc.code">{{ $t('holidays.country.' + cc.code, cc.code) }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="holiday-year">{{ $t('holidays.year.label', 'Année') }}</label>
          <select id="holiday-year" v-model="year" class="form-input min-w-32" @change="loadHolidays">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div class="ml-auto">
          <button class="btn-primary" :disabled="saving" @click="openCreate">
            {{ $t('holidays.add', 'Ajouter un jour férié') }}
          </button>
        </div>
      </div>

      <div class="mt-6 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-4 font-semibold">{{ $t('holidays.th.date', 'Date') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('holidays.th.name', 'Nom') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('holidays.th.type', 'Type') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('holidays.th.scope', 'Portée') }}</th>
              <th class="py-2 font-semibold text-right">{{ $t('holidays.th.actions', 'Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="h in holidays" :key="h.id" class="border-b border-slate-100 dark:border-slate-800">
              <td class="py-2.5 pr-4 font-medium text-slate-800 dark:text-slate-200">{{ h.date }}</td>
              <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ h.name }}</td>
              <td class="py-2.5 pr-4">
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="typeBadge(h.holiday_type)">
                  {{ $t('holidays.type.' + h.holiday_type, h.holiday_type) }}
                </span>
              </td>
              <td class="py-2.5 pr-4">
                <span v-if="h.company_id === null" class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                  {{ $t('holidays.scope.national', 'National') }}
                </span>
                <span v-else class="px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300">
                  {{ $t('holidays.scope.company', 'Entreprise') }}
                </span>
              </td>
              <td class="py-2.5 text-right">
                <button class="btn-secondary py-1 px-2.5 mr-2" :disabled="h.company_id === null || saving" @click="openEdit(h)">
                  {{ $t('holidays.edit', 'Modifier') }}
                </button>
                <button class="btn-danger py-1 px-2.5" :disabled="h.company_id === null || saving" @click="removeHoliday(h)">
                  {{ $t('holidays.delete', 'Supprimer') }}
                </button>
              </td>
            </tr>
            <tr v-if="!loading && holidays.length === 0">
              <td colspan="5" class="py-8 text-center text-slate-400">
                {{ $t('holidays.empty', 'Aucun jour férié pour :country / :year.').replace(':country', countryCode).replace(':year', String(year)) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal création/édition -->
    <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="modalOpen = false">
      <div class="glass-card w-full max-w-md p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4">
          {{ editing ? $t('holidays.modal.editTitle', 'Modifier le jour férié') : $t('holidays.modal.newTitle', 'Nouveau jour férié') }}
        </h2>
        <form class="space-y-4" @submit.prevent="saveHoliday">
          <div>
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="holiday-name">{{ $t('holidays.name.label', 'Nom') }}</label>
            <input id="holiday-name" v-model="form.name" type="text" class="form-input" maxlength="120" required>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="holiday-date">{{ $t('holidays.date.label', 'Date') }}</label>
              <input id="holiday-date" v-model="form.date" type="date" class="form-input" required>
            </div>
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="holiday-type">{{ $t('holidays.type.label', 'Type') }}</label>
              <select id="holiday-type" v-model="form.holiday_type" class="form-input">
                <option value="fixed">{{ $t('holidays.type.fixed', 'Fixe') }}</option>
                <option value="islamic">{{ $t('holidays.type.islamic', 'Islamique') }}</option>
                <option value="christian">{{ $t('holidays.type.christian', 'Chrétien') }}</option>
                <option value="custom">{{ $t('holidays.type.custom', 'Personnalisé') }}</option>
              </select>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <input id="holiday-recurring" v-model="form.is_recurring" type="checkbox" class="h-4 w-4">
            <label for="holiday-recurring" class="text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ $t('holidays.recurring.label', 'Récurent chaque année') }}
            </label>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="btn-secondary" @click="modalOpen = false">{{ $t('holidays.cancel', 'Annuler') }}</button>
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? $t('holidays.saving', 'Enregistrement…') : $t('holidays.save', 'Enregistrer') }}
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

const toast = useToast()
const localeStore = useLocaleStore()
/** Traduction avec fallback sur la clé pour faciliter le débogage. */
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const supportedCountries = ['DZ', 'CM', 'CI', 'SN', 'MA', 'TN'].map((code) => ({ code }))

const years = Array.from({ length: 8 }, (_, i) => 2024 + i)

const countryCode = ref('DZ')
const year = ref(new Date().getFullYear())
const holidays = ref([])
const loading = ref(false)
const saving = ref(false)
const modalOpen = ref(false)
const editing = ref(null)
const form = reactive({ name: '', date: '', holiday_type: 'fixed', is_recurring: false })

async function loadHolidays() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/public-holidays', {
      params: { country_code: countryCode.value, year: year.value },
    })
    holidays.value = data.data || []
  } catch (err) {
    toast.error(err?.response?.data?.message || t('holidays.errors.load', 'Impossible de charger les jours fériés.'))
  } finally {
    loading.value = false
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
    toast.success(t('holidays.success.saved', 'Jour férié enregistré.'))
    await loadHolidays()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('holidays.errors.save', "Impossible d'enregistrer le jour férié."))
  } finally {
    saving.value = false
  }
}

async function removeHoliday(h) {
  const message = t('holidays.confirm.delete', 'Supprimer « :name » ?').replace(':name', h.name)
  if (!window.confirm(message)) return
  saving.value = true
  try {
    await api.delete(`/admin/public-holidays/${h.id}`)
    toast.success(t('holidays.success.deleted', 'Jour férié supprimé.'))
    await loadHolidays()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('holidays.errors.delete', 'Impossible de supprimer.'))
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

onMounted(loadHolidays)
</script>
