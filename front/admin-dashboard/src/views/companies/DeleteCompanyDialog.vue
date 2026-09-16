<template>
  <Teleport to="body">
    <div
      v-if="open"
      ref="trapRef"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4 outline-none"
      role="dialog"
      aria-modal="true"
      :aria-label="t('companies.deletion.title')"
      @click.self="close"
      @keydown.escape="close"
    >
      <div class="w-full max-w-lg glass-card p-6">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('companies.deletion.title') }}</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">{{ t('companies.deletion.subtitle') }}</p>
        <p v-if="companyName" class="mt-1 text-sm font-semibold text-gray-700 dark:text-slate-200">{{ companyName }}</p>

        <p v-if="isLoading" class="mt-4 text-sm text-gray-500 dark:text-slate-400">{{ t('common.loading') }}</p>

        <p v-else-if="loadError" class="mt-4 text-sm text-red-600" role="alert">
          {{ t('companies.deletion.loadFailed') }}
        </p>

        <template v-else-if="inventory">
          <!-- Critère 1 : aucune suppression sans désactivation préalable. -->
          <div
            v-if="!inventory.deletable"
            class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-300"
            role="alert"
          >
            {{ t('companies.deletion.notDeactivated') }}
          </div>

          <template v-else>
            <!-- Critère 3 : inventaire chiffré plutôt qu'un message générique. -->
            <h4 class="mt-4 text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">
              {{ t('companies.deletion.inventoryTitle') }}
            </h4>
            <ul class="mt-2 divide-y divide-slate-100 dark:divide-slate-800">
              <li
                v-for="row in inventoryRows"
                :key="row.key"
                class="flex items-center justify-between py-1.5 text-sm"
              >
                <span class="text-gray-600 dark:text-slate-300">{{ row.label }}</span>
                <strong class="text-gray-900 dark:text-white">{{ row.value }}</strong>
              </li>
            </ul>

            <!-- Critère 5 : avec de la paie, le mode est un choix explicite. -->
            <fieldset v-if="inventory.has_payroll_data" class="mt-4">
              <legend class="text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">
                {{ t('companies.deletion.modeLegend') }}
              </legend>
              <label
                v-for="option in modeOptions"
                :key="option.value"
                class="mt-2 flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700"
              >
                <input
                  v-model="mode"
                  type="radio"
                  name="deletion-mode"
                  :value="option.value"
                  class="mt-1"
                  :data-testid="`companies-delete-mode-${option.value}`"
                />
                <span>
                  <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ option.label }}</span>
                  <span class="block text-xs text-gray-500 dark:text-slate-400">{{ option.hint }}</span>
                </span>
              </label>
            </fieldset>

            <!-- Critère 2 : confirmation par ressaisie du nom exact. -->
            <div class="mt-4">
              <label
                :id="confirmLabelId"
                :for="confirmFieldId"
                class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400"
              >
                {{ t('companies.deletion.confirmHint') }}
              </label>
              <input
                :id="confirmFieldId"
                v-model="typedName"
                type="text"
                class="form-input mt-1 w-full"
                :placeholder="t('companies.deletion.confirmPlaceholder')"
                autocomplete="off"
                spellcheck="false"
                :aria-labelledby="confirmLabelId"
                data-testid="companies-delete-confirm-name"
              />
            </div>

            <p v-if="errorMessage" class="mt-3 text-sm text-red-600" role="alert">{{ errorMessage }}</p>
          </template>
        </template>

        <div class="mt-5 flex justify-end gap-2">
          <button class="btn-secondary" @click="close">{{ t('common.cancel') }}</button>
          <button
            class="btn-danger"
            :disabled="!canSubmit"
            data-testid="companies-delete-submit"
            @click="submit"
          >
            {{ isSubmitting ? t('common.busy') : t('companies.deletion.confirmAction') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
/**
 * #7475 — Suppression sûre d'un tenant, côté console plateforme.
 *
 * Le parcours est en deux temps et le dialogue le rend visible : on montre
 * d'abord **ce qui sera détruit** (inventaire chiffré lu côté serveur), puis on
 * exige la ressaisie du nom exact, et — si l'espace porte de la paie — le choix
 * explicite entre effacement complet et conservation de la paie.
 *
 * Aucune décision n'est prise ici : le serveur reste seul juge (statut,
 * confirmation, mode requis). Le dialogue ne fait que ne pas *proposer*
 * l'impossible.
 */
import { computed, ref, useId, watch } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale'
import { useFocusTrap } from '@/composables/useFocusTrap'

const props = defineProps({
  open: { type: Boolean, default: false },
  company: { type: Object, default: null },
})

const emit = defineEmits(['close', 'deleted'])

const toast = useToast()
const localeStore = useLocaleStore()

function t(key, fallback = '') {
  return translate(localeStore.current, key, fallback)
}

const inventory = ref(null)
const isLoading = ref(false)
const loadError = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref('')
const typedName = ref('')
const mode = ref('purge')

const confirmFieldId = `delete-confirm-${useId()}`
const confirmLabelId = `delete-confirm-label-${useId()}`

const { containerRef: trapRef } = useFocusTrap(computed(() => props.open))

const companyName = computed(() => props.company?.name || props.company?.company?.name || '')

const modeOptions = computed(() => [
  {
    value: 'purge',
    label: t('companies.deletion.modePurge'),
    hint: t('companies.deletion.modePurgeHint'),
  },
  {
    value: 'anonymize',
    label: t('companies.deletion.modeAnonymize'),
    hint: t('companies.deletion.modeAnonymizeHint'),
  },
])

const COUNTER_LABELS = {
  employees: 'companies.deletion.inventoryEmployees',
  payroll_runs: 'companies.deletion.inventoryPayrollRuns',
  pay_slips: 'companies.deletion.inventoryPaySlips',
  employee_documents: 'companies.deletion.inventoryDocuments',
  attendance_logs: 'companies.deletion.inventoryAttendance',
}

const inventoryRows = computed(() => {
  const counters = inventory.value?.counters || {}
  return Object.entries(COUNTER_LABELS)
    .map(([key, labelKey]) => ({ key, label: t(labelKey), value: counters[key] ?? 0 }))
    .filter((row) => row.value > 0)
})

const typedMatches = computed(
  () => companyName.value !== '' && typedName.value.trim() === companyName.value,
)

const canSubmit = computed(
  () => Boolean(inventory.value?.deletable)
    && typedMatches.value
    && !isSubmitting.value
    && !isLoading.value
    && !loadError.value,
)

async function loadInventory(companyId) {
  isLoading.value = true
  loadError.value = false
  inventory.value = null
  errorMessage.value = ''

  try {
    const response = await api.get(`/platform/companies/${companyId}/deletion-inventory`)
    inventory.value = response.data?.data || null
  } catch {
    loadError.value = true
  } finally {
    isLoading.value = false
  }
}

watch(() => props.open, (isOpen) => {
  typedName.value = ''
  mode.value = 'purge'
  errorMessage.value = ''

  if (!isOpen) return

  const companyId = props.company?.id || props.company?.company?.id
  if (companyId) loadInventory(companyId)
})

function close() {
  if (isSubmitting.value) return
  emit('close')
}

async function submit() {
  if (!canSubmit.value) return

  const companyId = props.company?.id || props.company?.company?.id
  const payload = { confirm_name: typedName.value.trim() }

  if (inventory.value?.has_payroll_data) payload.mode = mode.value

  isSubmitting.value = true
  errorMessage.value = ''

  try {
    await api.delete(`/platform/companies/${companyId}`, { data: payload })
    toast.success(t('companies.deletion.success'))
    emit('deleted', companyId)
    emit('close')
  } catch (error) {
    // Les 409/422 portent un code métier : on l'affiche plutôt que de laisser
    // croire à une panne (jamais de faux succès).
    const message = error.response?.data?.localized_message
      || error.response?.data?.message
      || t('companies.deletion.loadFailed')
    errorMessage.value = typeof message === 'string' ? message : t('companies.deletion.loadFailed')
  } finally {
    isSubmitting.value = false
  }
}
</script>
