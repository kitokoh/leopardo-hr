<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
          {{ $t('plans.title') }}
        </h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          {{ $t('plans.subtitle') }}
        </p>
      </div>
      <button class="btn-primary" @click="openCreate">
        <PlusIcon class="h-4 w-4 mr-2" />
        {{ $t('plans.new') }}
      </button>
    </div>

    <DataTable
      :columns="columns"
      :rows="plans"
      :loading="loading"
      :error="error"
      :search-keys="['name']"
      :empty-message="$t('plans.empty')"
      key-field="id"
    >
      <template #cell-price_monthly="{ value }">
        <span class="font-medium">{{ formatMoney(value) }}</span>
      </template>
      <template #cell-price_yearly="{ value }">
        <span class="text-slate-500 dark:text-slate-400">{{ formatMoney(value) }}</span>
      </template>
      <template #cell-max_employees="{ row }">
        <span v-if="row.max_employees === null">{{ $t('plans.unlimited') }}</span>
        <span v-else>{{ row.max_employees }}</span>
      </template>
      <template #cell-features="{ row }">
        <span class="text-slate-500 dark:text-slate-400">
          {{ activeFeatureCount(row) }} / {{ FEATURE_KEYS.length }}
        </span>
      </template>
      <template #cell-is_active="{ row }">
        <span
          class="px-2 py-0.5 rounded-full text-xs font-semibold"
          :class="
            row.is_active
              ? 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300'
              : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'
          "
        >
          {{ row.is_active ? $t('plans.active') : $t('plans.archived') }}
        </span>
      </template>
      <template #row-actions="{ row }">
        <div class="flex justify-end gap-2">
          <RowActionButton
            :icon="PencilSquareIcon"
            :label="$t('plans.edit')"
            @click="openEdit(row)"
          />
          <RowActionButton
            :icon="DocumentDuplicateIcon"
            :label="$t('plans.duplicate')"
            @click="duplicate(row)"
          />
          <RowActionButton
            v-if="row.is_active"
            :icon="ArchiveBoxIcon"
            tone="warning"
            :label="$t('plans.archive')"
            @click="askArchive(row)"
          />
          <RowActionButton
            :icon="TrashIcon"
            tone="danger"
            :label="$t('plans.delete')"
            @click="askDelete(row)"
          />
        </div>
      </template>
    </DataTable>

    <!-- Création / édition d'une offre -->
    <div v-if="formOpen" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4 sm:p-8">
      <div class="glass-card w-full max-w-2xl p-6">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
          {{ editing ? $t('plans.edit') : $t('plans.new') }}
        </h2>

        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <label class="block sm:col-span-2">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('plans.field.name') }}</span>
            <input v-model="form.name" type="text" maxlength="50" class="input mt-1 w-full" />
          </label>
          <label class="block">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('plans.field.priceMonthly') }}</span>
            <input v-model.number="form.price_monthly" type="number" min="0" step="0.01" class="input mt-1 w-full" />
          </label>
          <label class="block">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('plans.field.priceYearly') }}</span>
            <input v-model.number="form.price_yearly" type="number" min="0" step="0.01" class="input mt-1 w-full" />
          </label>
          <label class="block">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('plans.field.maxEmployees') }}</span>
            <input
              v-model="maxEmployeesInput"
              type="number"
              min="1"
              step="1"
              :placeholder="$t('plans.unlimited')"
              class="input mt-1 w-full"
            />
          </label>
          <label class="block">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('plans.field.trialDays') }}</span>
            <input v-model.number="form.trial_days" type="number" min="0" max="365" step="1" class="input mt-1 w-full" />
          </label>

          <div class="sm:col-span-2">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ $t('plans.field.features') }}
            </span>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('plans.featuresHint') }}</p>
            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
              <label
                v-for="key in FEATURE_KEYS"
                :key="key"
                class="flex items-center gap-2 rounded-lg border border-slate-200/60 px-3 py-2 text-sm dark:border-slate-700/60"
              >
                <input v-model="form.features[key]" type="checkbox" class="h-4 w-4 rounded" />
                <span class="text-slate-700 dark:text-slate-300">{{ $t(`subscriptions.features.${key}`) }}</span>
              </label>
            </div>
          </div>

          <label class="flex items-center gap-2 sm:col-span-2">
            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded" />
            <span class="text-sm text-slate-700 dark:text-slate-300">{{ $t('plans.field.isActive') }}</span>
          </label>
        </div>

        <p v-if="formError" class="mt-4 text-sm text-red-600 dark:text-red-400">{{ formError }}</p>

        <div class="mt-6 flex justify-end gap-3">
          <button class="btn-secondary" :disabled="saving" @click="formOpen = false">
            {{ $t('common.cancel') }}
          </button>
          <button class="btn-primary" :disabled="saving" @click="save">
            <CloudArrowUpIcon v-if="saving" class="h-4 w-4 mr-2 animate-pulse" />
            {{ editing ? $t('plans.save') : $t('plans.create') }}
          </button>
        </div>
      </div>
    </div>

    <ConfirmDialog
      :open="confirmOpen"
      :title="confirmTitle"
      :message="confirmMessage"
      :confirm-label="confirmLabel"
      @confirm="runConfirmed"
      @cancel="confirmOpen = false"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useToast } from 'vue-toastification'
import {
  PlusIcon,
  PencilSquareIcon,
  TrashIcon,
  DocumentDuplicateIcon,
  ArchiveBoxIcon,
  CloudArrowUpIcon,
} from '@heroicons/vue/24/outline'
import api from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import RowActionButton from '@/components/common/RowActionButton.vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

/**
 * #7430 (BC-21 BILLING) — « Offres & tarifs » : le paramétrage des offres.
 *
 * La table `plans` n'était alimentée que par un seeder et l'admin n'exposait
 * qu'une lecture : changer un prix, une limite d'employés ou la matrice de
 * features exigeait un déploiement. Cet écran branche le CRUD réel
 * (`POST/PATCH/DELETE /platform/plans`, `…/duplicate`, `…/archive`).
 *
 * Deux garde-fous produit sont rappelés à l'écran :
 *  - une offre **utilisée** ne se supprime pas (l'API répond 409) : le message
 *    de l'API est remonté tel quel et oriente vers l'archivage ;
 *  - une **copie** naît archivée (jamais publiée par accident).
 */

/** Matrice offre × features : les capacités facturables connues du catalogue. */
const FEATURE_KEYS = [
  'biometric',
  'tasks',
  'advanced_reports',
  'excel_export',
  'bank_export',
  'billing_auto',
  'multi_managers',
  'photo_attendance',
  'api_public',
  'evaluations',
  'schema_isolation',
]

const columns = [
  { key: 'name', label: 'Offre', sortable: true },
  { key: 'price_monthly', label: 'Mensuel', sortable: true },
  { key: 'price_yearly', label: 'Annuel', sortable: true },
  { key: 'max_employees', label: 'Employés', sortable: true },
  { key: 'features', label: 'Features' },
  { key: 'is_active', label: 'État', sortable: true },
]

const localeStore = useLocaleStore()
const toast = useToast()

const plans = ref([])
const loading = ref(true)
const error = ref('')
const saving = ref(false)
const formOpen = ref(false)
const formError = ref('')
const editing = ref(null)
const maxEmployeesInput = ref('')
const confirmOpen = ref(false)
const confirmTitle = ref('')
const confirmMessage = ref('')
const confirmLabel = ref('')
let confirmedAction = null

const form = reactive({
  name: '',
  price_monthly: 0,
  price_yearly: 0,
  trial_days: 14,
  is_active: true,
  features: {},
})

function t(key) {
  return translate(localeStore.current, key, key)
}

function formatMoney(value) {
  return `${Number(value ?? 0).toFixed(2)} €`
}

function activeFeatureCount(row) {
  return Object.values(row.features || {}).filter(Boolean).length
}

function emptyFeatures() {
  return Object.fromEntries(FEATURE_KEYS.map((key) => [key, false]))
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await api.get('/platform/plans')
    plans.value = response.data?.data?.items || []
  } catch (err) {
    error.value = err?.response?.data?.message || t('plans.loadError')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  formError.value = ''
  Object.assign(form, {
    name: '',
    price_monthly: 0,
    price_yearly: 0,
    trial_days: 14,
    is_active: true,
    features: emptyFeatures(),
  })
  Object.keys(form.features).forEach((key) => {
    form.features[key] = false
  })
  maxEmployeesInput.value = ''
  formOpen.value = true
}

function openEdit(row) {
  editing.value = row
  formError.value = ''
  Object.assign(form, {
    name: row.name,
    price_monthly: row.price_monthly,
    price_yearly: row.price_yearly,
    trial_days: row.trial_days,
    is_active: row.is_active,
    features: { ...emptyFeatures(), ...(row.features || {}) },
  })
  maxEmployeesInput.value = row.max_employees === null || row.max_employees === undefined ? '' : String(row.max_employees)
  formOpen.value = true
}

async function save() {
  saving.value = true
  formError.value = ''
  try {
    const payload = {
      name: form.name,
      price_monthly: Number(form.price_monthly) || 0,
      price_yearly: Number(form.price_yearly) || 0,
      trial_days: Number(form.trial_days) || 0,
      is_active: Boolean(form.is_active),
      max_employees: maxEmployeesInput.value === '' ? null : Number(maxEmployeesInput.value),
      features: form.features,
    }

    if (editing.value) {
      await api.patch(`/platform/plans/${editing.value.id}`, payload)
      toast.success(t('plans.updated'))
    } else {
      await api.post('/platform/plans', payload)
      toast.success(t('plans.created'))
    }

    formOpen.value = false
    await load()
  } catch (err) {
    // 422 : le détail de validation est plus utile que le message générique.
    const validation = err?.response?.data?.errors
    formError.value = validation
      ? Object.values(validation).flat().join(' ')
      : err?.response?.data?.message || t('plans.saveError')
  } finally {
    saving.value = false
  }
}

async function duplicate(row) {
  try {
    await api.post(`/platform/plans/${row.id}/duplicate`)
    toast.success(t('plans.duplicated'))
    await load()
  } catch (err) {
    toast.error(err?.response?.data?.message || t('plans.saveError'))
  }
}

function askArchive(row) {
  confirmTitle.value = t('plans.archive')
  confirmMessage.value = t('plans.archiveConfirm')
  confirmLabel.value = t('plans.archive')
  confirmedAction = async () => {
    try {
      await api.post(`/platform/plans/${row.id}/archive`)
      toast.success(t('plans.archivedDone'))
      await load()
    } catch (err) {
      toast.error(err?.response?.data?.message || t('plans.saveError'))
    }
  }
  confirmOpen.value = true
}

function askDelete(row) {
  confirmTitle.value = t('plans.delete')
  confirmMessage.value = t('plans.deleteConfirm')
  confirmLabel.value = t('plans.delete')
  confirmedAction = async () => {
    try {
      await api.delete(`/platform/plans/${row.id}`)
      toast.success(t('plans.deleted'))
      await load()
    } catch (err) {
      // 409 = offre utilisée : on relaie le message de l'API (« archivez-la »)
      // au lieu d'un échec muet.
      toast.error(err?.response?.data?.message || t('plans.deleteError'))
    }
  }
  confirmOpen.value = true
}

async function runConfirmed() {
  confirmOpen.value = false
  if (confirmedAction) {
    await confirmedAction()
    confirmedAction = null
  }
}

const hasConfirmedAction = computed(() => typeof confirmedAction === 'function')

onMounted(load)

// `hasConfirmedAction` est exposé pour la garde de revue : une action
// destructive ne doit jamais partir sans confirmation armée (#7433).
defineExpose({ hasConfirmedAction })
</script>
