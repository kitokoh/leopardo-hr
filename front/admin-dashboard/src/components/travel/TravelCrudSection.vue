<template>
  <section class="space-y-4">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ title }}</h2>
        <p v-if="subtitle" class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ subtitle }}</p>
      </div>
      <button
        v-if="config.canCreate !== false"
        class="btn-primary"
        type="button"
        @click="openCreate"
      >
        <PlusIcon class="mr-2 h-4 w-4" />
        {{ $t('travel.action.create', 'Créer') }}
      </button>
    </div>

    <div v-if="globalError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
      {{ globalError }}
    </div>

    <DataTable
      :columns="columns"
      :rows="rows"
      :loading="loading"
      :error="listError"
      :search-keys="config.searchKeys"
      :search-placeholder="searchPlaceholder"
      :default-sort="config.defaultSort"
      :default-sort-dir="config.defaultSortDir"
      :caption="title"
    >
      <template v-if="config.statusField && !hasDisplay(config.statusField)" #[`cell-${config.statusField}`]="{ value }">
        <StatusBadge :status="value" :map="statusMap" />
      </template>

      <template v-for="mcol in moneyColumns" :key="mcol" #[`cell-${mcol}`]="{ row, value }">
        {{ formatMoney(value, row.currency || 'XAF') }}
      </template>

      <template v-for="colKey in displayColumns" :key="colKey" #[`cell-${colKey}`]="{ row, value }">
        {{ columnDisplay[colKey](row, value) }}
      </template>

      <template #row-actions="{ row }">
        <div class="flex items-center justify-end gap-1">
          <button
            v-for="action in rowActionsFor(row)"
            :key="action.key"
            class="rounded-md px-2 py-1.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
            type="button"
            :aria-label="actionLabel(action)"
            :title="actionLabel(action)"
            @click="$emit('action', { key: action.key, row })"
          >
            {{ actionLabel(action) }}
          </button>
          <button
            class="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
            type="button"
            :aria-label="$t('travel.action.edit', 'Modifier')"
            :title="$t('travel.action.edit', 'Modifier')"
            @click="openEdit(row)"
          >
            <PencilSquareIcon class="h-4 w-4" />
          </button>
          <button
            v-if="config.canDelete !== false"
            class="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600"
            type="button"
            :aria-label="$t('travel.action.delete', 'Supprimer')"
            :title="$t('travel.action.delete', 'Supprimer')"
            @click="askDelete(row)"
          >
            <TrashIcon class="h-4 w-4" />
          </button>
        </div>
      </template>
    </DataTable>

    <!-- Formulaire création / édition -->
    <TravelModal
      :open="formOpen"
      :title="editing ? $t('travel.action.editTitle', 'Modifier') : $t('travel.action.createTitle', 'Créer')"
      wide
      @close="closeForm"
    >
      <form class="grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="save">
        <FormField
          v-for="field in config.fields"
          :key="field.key"
          :id="`travel-${config.resource}-${field.key}`"
          :label="fieldLabel(field)"
          :error="formErrors[field.key]"
          :required="field.required"
          :hint="field.hint"
        >
          <template #default="{ ariaInvalid, describedBy }">
            <select
              v-if="field.type === 'select'"
              v-model="form[field.key]"
              class="form-input"
              :aria-invalid="ariaInvalid"
              :aria-describedby="describedBy"
              :required="field.required"
            >
              <option value="">{{ $t('travel.form.selectPlaceholder', '— Sélectionner —') }}</option>
              <option v-for="opt in fieldOptions(field)" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
            <textarea
              v-else-if="field.type === 'textarea'"
              v-model="form[field.key]"
              class="form-input"
              rows="3"
              :aria-invalid="ariaInvalid"
              :aria-describedby="describedBy"
            ></textarea>
            <input
              v-else
              :type="field.type === 'money' ? 'number' : field.type"
              v-model="form[field.key]"
              :min="field.min"
              :max="field.max"
              :step="field.type === 'money' ? '0.01' : field.step"
              :placeholder="field.placeholder"
              class="form-input"
              :aria-invalid="ariaInvalid"
              :aria-describedby="describedBy"
              :required="field.required"
            />
          </template>
        </FormField>

        <div class="col-span-full flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="closeForm">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button type="submit" class="btn-primary" :disabled="saving">
            {{ saving ? $t('common.busy', 'En cours…') : (editing ? $t('travel.action.save', 'Enregistrer') : $t('travel.action.create', 'Créer')) }}
          </button>
        </div>
      </form>

      <!-- Éléments imbriqués (étapes d'une route, tarifs d'un trajet, chambres d'un hôtel…) -->
      <div v-if="nested && editing" class="mt-6 border-t border-slate-200/50 pt-6 dark:border-slate-800/50">
        <h4 class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ nestedTitle }}</h4>
        <div v-if="nestedError" class="mt-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert">
          {{ nestedError }}
        </div>
        <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200/50 dark:border-slate-800/50">
          <table class="min-w-full divide-y divide-slate-200/50 text-sm dark:divide-slate-800/50">
            <thead class="bg-slate-50/50 dark:bg-slate-800/50">
              <tr>
                <th
                  v-for="col in nestedColumns"
                  :key="col.key"
                  class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400"
                >
                  {{ col.label }}
                </th>
                <th class="px-4 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                  {{ $t('travel.table.actions', 'Actions') }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <tr v-for="nrow in nestedRows" :key="nrow.id" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/70">
                <td
                  v-for="col in nestedColumns"
                  :key="col.key"
                  class="whitespace-nowrap px-4 py-2 text-slate-700 dark:text-slate-300"
                >
                  {{ nestedCell(nrow, col) }}
                </td>
                <td class="whitespace-nowrap px-4 py-2 text-right">
                  <button
                    class="rounded-md p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    type="button"
                    :aria-label="$t('travel.action.edit', 'Modifier')"
                    @click="openNestedEdit(nrow)"
                  >
                    <PencilSquareIcon class="h-4 w-4" />
                  </button>
                  <button
                    class="rounded-md p-1 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600"
                    type="button"
                    :aria-label="$t('travel.action.delete', 'Supprimer')"
                    @click="deleteNested(nrow)"
                  >
                    <TrashIcon class="h-4 w-4" />
                  </button>
                </td>
              </tr>
              <tr v-if="nestedRows.length === 0 && !nestedLoading">
                <td :colspan="nestedColumns.length + 1" class="px-4 py-4 text-center text-xs text-slate-400">
                  {{ $t('travel.table.emptyNested', 'Aucun élément.') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <button type="button" class="btn-secondary mt-3 text-sm" @click="openNestedCreate">
          <PlusIcon class="mr-1.5 h-4 w-4" />
          {{ $t('travel.action.addNested', 'Ajouter') }}
        </button>
      </div>
    </TravelModal>

    <!-- Mini-formulaire imbriqué -->
    <TravelModal
      :open="nestedFormOpen"
      :title="nestedEditing ? $t('travel.action.editTitle', 'Modifier') : $t('travel.action.addNested', 'Ajouter')"
      @close="closenestedFormOpen"
    >
      <form class="grid grid-cols-1 gap-3 sm:grid-cols-2" @submit.prevent="saveNested">
        <FormField
          v-for="field in nested.fields"
          :key="field.key"
          :id="`travel-nested-${field.key}`"
          :label="fieldLabel(field)"
          :error="nestedFormErrors[field.key]"
          :required="field.required"
        >
          <template #default="{ ariaInvalid, describedBy }">
            <select
              v-if="field.type === 'select'"
              v-model="nestedForm[field.key]"
              class="form-input"
              :aria-invalid="ariaInvalid"
              :aria-describedby="describedBy"
              :required="field.required"
            >
              <option value="">{{ $t('travel.form.selectPlaceholder', '— Sélectionner —') }}</option>
              <option v-for="opt in fieldOptions(field)" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
            <input
              v-else
              :type="field.type === 'money' ? 'number' : field.type"
              v-model="nestedForm[field.key]"
              :min="field.min"
              :step="field.type === 'money' ? '0.01' : field.step"
              class="form-input"
              :aria-invalid="ariaInvalid"
              :aria-describedby="describedBy"
              :required="field.required"
            />
          </template>
        </FormField>
        <div class="col-span-full flex justify-end gap-2 pt-1">
          <button type="button" class="btn-secondary" @click="nestedFormOpen = false">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button type="submit" class="btn-primary" :disabled="nestedSaving">
            {{ nestedSaving ? $t('common.busy', 'En cours…') : $t('travel.action.save', 'Enregistrer') }}
          </button>
        </div>
      </form>
    </TravelModal>

    <ConfirmDialog
      :open="deleteOpen"
      :title="$t('travel.action.deleteTitle', 'Confirmer la suppression')"
      :message="deleteMessage"
      :busy="deleting"
      @confirm="confirmDelete"
      @cancel="closedeleteOpen"
    />
  </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { PlusIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import FormField from '@/components/common/FormField.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import TravelModal from '@/components/travel/TravelModal.vue'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const props = defineProps({
  /** Config du CRUD : resource, columns, fields, defaults, searchKeys, statusField, statusMap, nested… */
  config: { type: Object, required: true },
  /** Lookups pour les selects : { cities: [{ value, label }], … } */
  lookups: { type: Object, default: () => ({}) },
  /** Params de liste supplémentaires (objet ou fonction) */
  extraParams: { type: [Object, Function], default: () => ({}) },
  /** Formatage de cellules : { [columnKey]: (row, value) => string } */
  columnDisplay: { type: Object, default: () => ({}) }
})

const emit = defineEmits(['saved', 'deleted', 'action'])

const toast = useToast()
const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const rows = ref([])
const loading = ref(false)
const listError = ref('')
const globalError = ref('')
const formOpen = ref(false)
const editing = ref(false)
const saving = ref(false)
const form = ref({})
const formErrors = ref({})
const deleteOpen = ref(false)
const deleting = ref(false)
const rowToDelete = ref(null)

const nestedRows = ref([])
const nestedLoading = ref(false)
const nestedError = ref('')
const nestedFormOpen = ref(false)
const nestedEditing = ref(false)
const nestedSaving = ref(false)
const nestedForm = ref({})
const nestedFormErrors = ref({})

const title = computed(() => t(props.config.titleKey, props.config.titleFallback || ''))
const subtitle = computed(() => (props.config.subtitleKey ? t(props.config.subtitleKey, '') : ''))
const searchPlaceholder = computed(() => t(props.config.searchPlaceholderKey || 'travel.search.placeholder', 'Rechercher…'))

const columns = computed(() =>
  (props.config.columns || []).map((col) => ({ ...col, label: t(col.label, col.labelFallback || '') }))
)

const moneyColumns = computed(() =>
  (props.config.columns || [])
    .filter((col) => col.type === 'money' && !props.columnDisplay[col.key])
    .map((col) => col.key)
)

const displayColumns = computed(() => Object.keys(props.columnDisplay || {}))

function hasDisplay(key) {
  return Boolean(props.columnDisplay[key])
}

const columnDisplay = computed(() => props.columnDisplay)

function rowActionsFor(row) {
  return (props.config.rowActions || []).filter((action) => !action.condition || action.condition(row))
}

function actionLabel(action) {
  return t(action.label, action.labelFallback || action.key)
}

const statusMap = computed(() => {
  const map = props.config.statusMap
  if (!map) return undefined
  return Object.fromEntries(
    Object.entries(map).map(([status, cfg]) => [
      status,
      { ...cfg, label: t(cfg.labelKey || cfg.label, cfg.label || status) }
    ])
  )
})

const nested = computed(() => props.config.nested)
const nestedTitle = computed(() => (nested.value ? t(nested.value.titleKey, '') : ''))
const nestedColumns = computed(() =>
  (nested.value?.columns || []).map((col) => ({ ...col, label: t(col.label, col.labelFallback || '') }))
)

function fieldLabel(field) {
  return t(field.label, field.labelFallback || field.key)
}

function fieldOptions(field) {
  if (field.options) return field.options
  if (field.source) {
    const source = props.lookups[field.source] || []
    return source.map((item) => ({
      value: item.value ?? item.id,
      label: item.label ?? item.name ?? String(item.value ?? item.id)
    }))
  }
  return []
}

function toMinor(value) {
  if (value === '' || value === null || value === undefined) return null
  return Math.round(Number(value) * 100)
}

function fromMinor(value) {
  if (value === null || value === undefined || value === '') return ''
  return String(Number(value) / 100)
}

function normalizePayload(payload) {
  const out = {}
  for (const field of props.config.fields) {
    let value = payload[field.key]
    if (field.type === 'money' && value !== '' && value !== null && value !== undefined) {
      value = toMinor(value)
    }
    if (value === '' && !field.required) value = null
    out[field.key] = value
  }
  return out
}

function populateForm(payload) {
  const out = {}
  for (const field of props.config.fields) {
    let value = payload[field.key]
    if (field.type === 'money') value = fromMinor(value)
    out[field.key] = value ?? ''
  }
  return out
}

function paramsFor() {
  const base = { per_page: 100 }
  const extra = typeof props.extraParams === 'function' ? props.extraParams() : props.extraParams
  return { ...base, ...extra }
}

async function load() {
  loading.value = true
  listError.value = ''
  try {
    const res = await api.get(`/travel/${props.config.resource}`, { params: paramsFor(), _skipAuthRedirect: true })
    rows.value = res.data?.data || []
  } catch (err) {
    listError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = false
  form.value = { ...(props.config.defaults || {}) }
  formErrors.value = {}
  nestedRows.value = []
  nestedError.value = ''
  formOpen.value = true
}

function openEdit(row) {
  editing.value = true
  form.value = populateForm(row)
  formErrors.value = {}
  nestedRows.value = []
  nestedError.value = ''
  formOpen.value = true
  if (nested.value) loadNested(row.id)
}

async function save() {
  saving.value = true
  globalError.value = ''
  formErrors.value = {}
  try {
    const payload = normalizePayload(form.value)
    let res
    if (editing.value) {
      res = await api.put(`/travel/${props.config.resource}/${form.value.id}`, payload, { _skipAuthRedirect: true })
    } else {
      res = await api.post(`/travel/${props.config.resource}`, payload, { _skipAuthRedirect: true })
    }
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    formOpen.value = false
    emit('saved', res.data?.data)
    await load()
  } catch (err) {
    const data = err.response?.data || {}
    if (data.errors) {
      formErrors.value = Object.fromEntries(
        Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
      )
    }
    globalError.value = data.message || data.localized_message || t('travel.error.saveFailed', "Échec de l'enregistrement.")
  } finally {
    saving.value = false
  }
}

function askDelete(row) {
  rowToDelete.value = row
  deleteOpen.value = true
}

const deleteMessage = computed(() =>
  t('travel.confirm.deleteMessage', 'Supprimer définitivement cet élément ?')
)

async function confirmDelete() {
  deleting.value = true
  try {
    await api.delete(`/travel/${props.config.resource}/${rowToDelete.value.id}`, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.deleted', 'Supprimé.'))
    deleteOpen.value = false
    emit('deleted', rowToDelete.value)
    await load()
  } catch (err) {
    toast.error(err.response?.data?.message || t('travel.error.deleteFailed', 'Échec de la suppression.'))
  } finally {
    deleting.value = false
  }
}

/* ── éléments imbriqués ─────────────────────────────────────── */

function nestedResource(parentId) {
  return nested.value.resource.replace('{id}', String(parentId))
}

async function loadNested(parentId) {
  nestedLoading.value = true
  nestedError.value = ''
  try {
    const res = await api.get(`/travel/${nestedResource(parentId)}`, { params: { per_page: 100 }, _skipAuthRedirect: true })
    nestedRows.value = res.data?.data || []
  } catch (err) {
    nestedError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    nestedLoading.value = false
  }
}

function openNestedCreate() {
  nestedEditing.value = false
  nestedForm.value = { ...(nested.value.defaults || {}) }
  nestedFormErrors.value = {}
  nestedFormOpen.value = true
}

function openNestedEdit(row) {
  nestedEditing.value = true
  nestedForm.value = { ...row }
  for (const field of nested.value.fields) {
    if (field.type === 'money' && nestedForm.value[field.key] != null) {
      nestedForm.value[field.key] = fromMinor(nestedForm.value[field.key])
    }
  }
  nestedFormErrors.value = {}
  nestedFormOpen.value = true
}

async function saveNested() {
  nestedSaving.value = true
  nestedFormErrors.value = {}
  try {
    const payload = normalizeNestedPayload(nestedForm.value)
    const resource = nestedResource(form.value.id)
    if (nestedEditing.value) {
      await api.put(`/travel/${resource}/${nestedForm.value.id}`, payload, { _skipAuthRedirect: true })
    } else {
      await api.post(`/travel/${resource}`, payload, { _skipAuthRedirect: true })
    }
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    nestedFormOpen.value = false
    await loadNested(form.value.id)
  } catch (err) {
    const data = err.response?.data || {}
    if (data.errors) {
      nestedFormErrors.value = Object.fromEntries(
        Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
      )
    }
    toast.error(data.message || t('travel.error.saveFailed', "Échec de l'enregistrement."))
  } finally {
    nestedSaving.value = false
  }
}

function normalizeNestedPayload(payload) {
  const out = {}
  for (const field of nested.value.fields) {
    let value = payload[field.key]
    if (field.type === 'money' && value !== '' && value !== null && value !== undefined) {
      value = toMinor(value)
    }
    if (value === '' && !field.required) value = null
    out[field.key] = value
  }
  return out
}

async function deleteNested(row) {
  if (!window.confirm(t('travel.confirm.deleteMessage', 'Supprimer définitivement cet élément ?'))) return
  try {
    await api.delete(`/travel/${nestedResource(form.value.id)}/${row.id}`, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.deleted', 'Supprimé.'))
    await loadNested(form.value.id)
  } catch (err) {
    toast.error(err.response?.data?.message || t('travel.error.deleteFailed', 'Échec de la suppression.'))
  }
}

function nestedCell(row, col) {
  const raw = col.key.split('.').reduce((acc, k) => acc?.[k], row)
  if (raw === null || raw === undefined) return '-'
  if (col.type === 'money') return formatMoney(raw, row.currency || 'XAF')
  return String(raw)
}

function formatMoney(minor, currency) {
  try {
    return new Intl.NumberFormat(localeStore.current, { style: 'currency', currency }).format(Number(minor) / 100)
  } catch {
    return `${Number(minor) / 100} ${currency}`
  }
}

function closeForm() {
  formOpen.value = false
}

function closeNestedForm() {
  nestedFormOpen.value = false
}

function closeDelete() {
  deleteOpen.value = false
}

defineExpose({ load })

watch(
  () => props.config.resource,
  () => load()
)

onMounted(load)
</script>
