<template>
  <div class="space-y-8 animate-fade-in max-w-6xl">
    <div>
      <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
        {{ $t('tax_rates.title') }}
      </h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
        {{ $t('tax_rates.subtitle') }}
      </p>
    </div>

    <!-- En attente de validation (platform admin) -->
    <div v-if="isPlatformAdmin" class="glass-card p-6">
      <h2 class="text-xl font-bold text-slate-900 dark:text-white">
        {{ $t('tax_rates.pending_title') }}
      </h2>
      <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-4 font-semibold">{{ $t('tax_rates.th_type') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('tax_rates.th_name') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('tax_rates.th_country') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('tax_rates.th_rate') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('tax_rates.th_effective') }}</th>
              <th class="py-2 font-semibold text-right">{{ $t('tax_rates.th_actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in pendingItems" :key="`${p.table}-${p.id}`" class="border-b border-slate-100 dark:border-slate-800">
              <td class="py-2.5 pr-4">
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-violet-100 dark:bg-violet-900 text-violet-700 dark:text-violet-300">
                  {{ p.table === 'tax_slabs' ? $t('tax_rates.type_slab') : $t('tax_rates.type_contribution') }}
                </span>
              </td>
              <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ p.name }}</td>
              <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ p.country_code }}</td>
              <td class="py-2.5 pr-4 font-medium text-slate-800 dark:text-slate-200">{{ p.rate }} %</td>
              <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ p.effective_from }}</td>
              <td class="py-2.5 text-right whitespace-nowrap">
                <button class="btn-primary py-1 px-2.5 mr-2" :disabled="acting" @click="approve(p)">
                  {{ $t('tax_rates.approve') }}
                </button>
                <button class="btn-danger py-1 px-2.5" :disabled="acting" @click="openReject(p)">
                  {{ $t('tax_rates.reject') }}
                </button>
              </td>
            </tr>
            <tr v-if="pendingItems.length === 0">
              <td colspan="6" class="py-8 text-center text-slate-400">
                {{ $t('tax_rates.pending_empty') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Barèmes fiscaux actifs + brouillons -->
    <div class="glass-card p-6">
      <div class="flex flex-wrap items-center gap-4">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $t('tax_rates.rates_title') }}</h2>
          <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $t('tax_rates.rates_subtitle') }}</p>
        </div>
        <div class="ml-auto">
          <button class="btn-primary" :disabled="saving" @click="openCreate">
            {{ $t('tax_rates.propose') }}
          </button>
        </div>
      </div>

      <div class="mt-5 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-slate-500 dark:text-slate-400">
              <th class="py-2 pr-4 font-semibold">{{ $t('tax_rates.th_type') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('tax_rates.th_name') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('tax_rates.th_country') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('tax_rates.th_rate') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('tax_rates.th_status') }}</th>
              <th class="py-2 pr-4 font-semibold">{{ $t('tax_rates.th_effective') }}</th>
              <th class="py-2 font-semibold text-right">{{ $t('tax_rates.th_actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in rates" :key="`${s.table}-${s.id}`" class="border-b border-slate-100 dark:border-slate-800">
              <td class="py-2.5 pr-4">
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-violet-100 dark:bg-violet-900 text-violet-700 dark:text-violet-300">
                  {{ s.table === 'tax_slabs' ? $t('tax_rates.type_slab') : $t('tax_rates.type_contribution') }}
                </span>
              </td>
              <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ s.name }}</td>
              <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ s.country_code }}</td>
              <td class="py-2.5 pr-4 font-medium text-slate-800 dark:text-slate-200">{{ s.rate }} %</td>
              <td class="py-2.5 pr-4">
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="statusBadge(s.status)">
                  {{ statusLabel(s.status) }}
                </span>
              </td>
              <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ s.effective_from }}</td>
              <td class="py-2.5 text-right whitespace-nowrap">
                <template v-if="s.status === 'draft'">
                  <button class="btn-secondary py-1 px-2.5 mr-2" :disabled="saving" @click="submitRate(s)">
                    {{ $t('tax_rates.submit') }}
                  </button>
                </template>
                <button class="btn-secondary py-1 px-2.5" :disabled="saving" @click="showHistory(s)">
                  {{ $t('tax_rates.history') }}
                </button>
              </td>
            </tr>
            <tr v-if="rates.length === 0">
              <td colspan="7" class="py-8 text-center text-slate-400">{{ $t('tax_rates.rates_empty') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal : proposer une modification -->
    <div v-if="createOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeCreate">
      <div class="glass-card w-full max-w-lg p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4">{{ $t('tax_rates.modal_title') }}</h2>
        <form class="space-y-4" @submit.prevent="createRate">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="rate-type">{{ $t('tax_rates.th_type') }}</label>
              <select id="rate-type" v-model="form.table" class="form-input" required>
                <option value="tax_slabs">{{ $t('tax_rates.type_slab') }}</option>
                <option value="social_contributions">{{ $t('tax_rates.type_contribution') }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="rate-country">{{ $t('tax_rates.th_country') }}</label>
              <select id="rate-country" v-model="form.country_code" class="form-input" required>
                <option v-for="cc in supportedCountries" :key="cc.code" :value="cc.code">{{ cc.label }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="rate-name">{{ $t('tax_rates.th_name') }}</label>
            <input id="rate-name" v-model="form.name" type="text" class="form-input" maxlength="150" required>
          </div>
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="rate-rate">{{ $t('tax_rates.th_rate') }}</label>
              <input id="rate-rate" v-model.number="form.rate" type="number" min="0" max="100" step="0.01" class="form-input" required>
            </div>
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="rate-from">{{ $t('tax_rates.th_effective') }}</label>
              <input id="rate-from" v-model="form.effective_from" type="date" class="form-input" required>
            </div>
            <div>
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="rate-legal">{{ $t('tax_rates.legal_ref') }}</label>
              <input id="rate-legal" v-model="form.legal_reference" type="text" class="form-input" maxlength="200" required>
            </div>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="btn-secondary" @click="closeCreate">{{ $t('tax_rates.cancel') }}</button>
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? $t('tax_rates.saving') : $t('tax_rates.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal : motif de rejet -->
    <div v-if="rejectOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeReject">
      <div class="glass-card w-full max-w-md p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4">{{ $t('tax_rates.reject_modal_title') }}</h2>
        <form class="space-y-4" @submit.prevent="rejectItem">
          <div>
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="reject-reason">{{ $t('tax_rates.reject_reason') }}</label>
            <textarea id="reject-reason" v-model="rejectReason" rows="3" class="form-input" required></textarea>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="btn-secondary" @click="closeReject">{{ $t('tax_rates.cancel') }}</button>
            <button type="submit" class="btn-danger" :disabled="acting">
              {{ acting ? $t('tax_rates.saving') : $t('tax_rates.reject') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal : historique -->
    <div v-if="historyOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeHistory">
      <div class="glass-card w-full max-w-2xl p-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4">{{ $t('tax_rates.history_title') }}</h2>
        <div class="max-h-96 overflow-y-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 dark:border-slate-700 text-left text-slate-500 dark:text-slate-400">
                <th class="py-2 pr-4 font-semibold">{{ $t('tax_rates.th_action') }}</th>
                <th class="py-2 pr-4 font-semibold">{{ $t('tax_rates.th_actor') }}</th>
                <th class="py-2 pr-4 font-semibold">{{ $t('tax_rates.th_reason') }}</th>
                <th class="py-2 font-semibold">{{ $t('tax_rates.th_date') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="h in historyItems" :key="h.id" class="border-b border-slate-100 dark:border-slate-800">
                <td class="py-2.5 pr-4">
                  <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="historyBadge(h.action)">
                    {{ historyLabel(h.action) }}
                  </span>
                </td>
                <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ h.actor_role }} #{{ h.actor_id }}</td>
                <td class="py-2.5 pr-4 text-slate-700 dark:text-slate-300">{{ h.reason || '—' }}</td>
                <td class="py-2.5 text-slate-700 dark:text-slate-300">{{ formatDate(h.created_at) }}</td>
              </tr>
              <tr v-if="historyItems.length === 0">
                <td colspan="4" class="py-8 text-center text-slate-400">{{ $t('tax_rates.history_empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex justify-end pt-3">
          <button type="button" class="btn-secondary" @click="closeHistory">{{ $t('tax_rates.close') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import api from '@/services/api'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { useToast } from 'vue-toastification'

const toast = useToast()
const localeStore = useLocaleStore()

function t(key, fallback = '') {
  return translate(localeStore.current, key, fallback)
}

const supportedCountries = [
  { code: 'DZ', label: 'Algérie' },
  { code: 'CM', label: 'Cameroun' },
  { code: 'CI', label: "Côte d'Ivoire" },
  { code: 'SN', label: 'Sénégal' },
  { code: 'MA', label: 'Maroc' },
  { code: 'TN', label: 'Tunisie' },
  { code: 'FR', label: 'France' },
]

const isPlatformAdmin = ref(false)
const rates = ref([])
const pendingItems = ref([])
const historyItems = ref([])
const loading = ref(false)
const saving = ref(false)
const acting = ref(false)
const createOpen = ref(false)
const rejectOpen = ref(false)
const historyOpen = ref(false)
const rejectTarget = ref(null)
const rejectReason = ref('')
const form = reactive({
  table: 'tax_slabs',
  country_code: 'DZ',
  name: '',
  rate: null,
  effective_from: '',
  legal_reference: '',
})

async function loadRates() {
  loading.value = true
  try {
    const [slabs, contributions] = await Promise.all([
      api.get('/tax-slabs'),
      api.get('/social-contributions'),
    ])
    rates.value = [
      ...(slabs.data?.data || []).map((r) => ({ ...r, table: 'tax_slabs' })),
      ...(contributions.data?.data || []).map((r) => ({ ...r, table: 'social_contributions' })),
    ]
  } catch (err) {
    toast.error(err?.response?.data?.message || t('tax_rates.load_error'))
  } finally {
    loading.value = false
  }
}

async function loadPending() {
  try {
    const { data } = await api.get('/admin/rate-validation/pending')
    pendingItems.value = data.data || []
  } catch {
    // Route admin : indisponible pour un manager non plateforme — silencieux.
    pendingItems.value = []
  }
}

function openCreate() {
  Object.assign(form, { table: 'tax_slabs', country_code: 'DZ', name: '', rate: null, effective_from: '', legal_reference: '' })
  createOpen.value = true
}

function closeCreate() {
  createOpen.value = false
}

async function createRate() {
  if (form.legal_reference.trim() === '') {
    toast.error(t('tax_rates.legal_ref_required'))
    return
  }
  saving.value = true
  try {
    const payload = {
      country_code: form.country_code,
      name: form.name,
      rate: form.rate,
      effective_from: form.effective_from,
      // La référence légale est tracée dans l'historique (nom de la ligne).
      // Le nom du brouillon porte la référence légale : « Nom (réf. légale) ».
      ...(form.legal_reference ? {} : {}),
    }
    if (form.table === 'tax_slabs') {
      payload.min_amount = 0
      payload.max_amount = null
      payload.fixed_deduction = 0
    } else {
      payload.code = `PROPOSAL_${Date.now()}`
      payload.type = 'employee'
      payload.cap = null
    }
    await api.post(`/${form.table === 'tax_slabs' ? 'tax-slabs' : 'social-contributions'}`, payload)
    createOpen.value = false
    toast.success(t('tax_rates.saved'))
    await loadRates()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('tax_rates.save_error'))
  } finally {
    saving.value = false
  }
}

async function submitRate(item) {
  saving.value = true
  try {
    const base = item.table === 'tax_slabs' ? 'tax-slabs' : 'social-contributions'
    await api.put(`/${base}/${item.id}/submit`)
    toast.success(t('tax_rates.submitted'))
    await Promise.all([loadRates(), loadPending()])
  } catch (err) {
    toast.error(err?.response?.data?.message || t('tax_rates.submit_error'))
  } finally {
    saving.value = false
  }
}

async function approve(item) {
  acting.value = true
  try {
    await api.put(`/admin/rate-validation/${item.table}/${item.id}/approve`)
    toast.success(t('tax_rates.approved'))
    await Promise.all([loadRates(), loadPending()])
  } catch (err) {
    toast.error(err?.response?.data?.message || t('tax_rates.approve_error'))
  } finally {
    acting.value = false
  }
}

function openReject(item) {
  rejectTarget.value = item
  rejectReason.value = ''
  rejectOpen.value = true
}

function closeReject() {
  rejectOpen.value = false
  rejectTarget.value = null
}

async function rejectItem() {
  if (!rejectTarget.value || rejectReason.value.trim() === '') return
  acting.value = true
  try {
    await api.put(`/admin/rate-validation/${rejectTarget.value.table}/${rejectTarget.value.id}/reject`, {
      reason: rejectReason.value.trim(),
    })
    rejectOpen.value = false
    toast.success(t('tax_rates.rejected'))
    await Promise.all([loadRates(), loadPending()])
  } catch (err) {
    toast.error(err?.response?.data?.message || t('tax_rates.reject_error'))
  } finally {
    acting.value = false
  }
}

async function showHistory(item) {
  const base = item.table === 'tax_slabs' ? 'tax-slabs' : 'social-contributions'
  try {
    const { data } = await api.get(`/${base}/${item.id}/history`)
    historyItems.value = data.data || []
    historyOpen.value = true
  } catch (err) {
    toast.error(err?.response?.data?.message || t('tax_rates.history_error'))
  }
}

function closeHistory() {
  historyOpen.value = false
}

function statusBadge(status) {
  const map = {
    active: 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300',
    pending_validation: 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300',
    draft: 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
    superseded: 'bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300',
  }
  return map[status] || map.draft
}

function statusLabel(status) {
  const map = {
    active: t('tax_rates.status_active'),
    pending_validation: t('tax_rates.status_pending'),
    draft: t('tax_rates.status_draft'),
    superseded: t('tax_rates.status_superseded'),
  }
  return map[status] || status
}

function historyBadge(action) {
  const map = {
    created: 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
    submitted: 'bg-sky-100 dark:bg-sky-900 text-sky-700 dark:text-sky-300',
    approved: 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300',
    rejected: 'bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300',
    superseded: 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300',
  }
  return map[action] || map.created
}

function historyLabel(action) {
  const map = {
    created: t('tax_rates.history_created'),
    submitted: t('tax_rates.history_submitted'),
    approved: t('tax_rates.history_approved'),
    rejected: t('tax_rates.history_rejected'),
    superseded: t('tax_rates.history_superseded'),
  }
  return map[action] || action
}

function formatDate(iso) {
  if (!iso) return '—'
  try {
    return new Date(iso).toLocaleString(localeStore.current === 'fr' ? 'fr-FR' : localeStore.current)
  } catch {
    return iso
  }
}

onMounted(async () => {
  // Détection du rôle plateforme : le cockpit expose un flag via /auth/me.
  try {
    const { data } = await api.get('/auth/me')
    isPlatformAdmin.value = Boolean(data?.data?.role === 'super_admin' || data?.data?.is_platform_admin)
  } catch {
    isPlatformAdmin.value = false
  }
  await loadRates()
  if (isPlatformAdmin.value) {
    await loadPending()
  }
})
</script>
