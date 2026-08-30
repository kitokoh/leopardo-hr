<template>
  <div class="space-y-6">
    <div class="flex flex-wrap gap-2" role="tablist" :aria-label="$t('travel.adverts.tabsLabel', 'Sous-sections annonces')">
      <button
        v-for="sub in subTabs"
        :key="sub.key"
        type="button"
        role="tab"
        :aria-selected="activeSub === sub.key"
        :class="[
          'rounded-md px-3.5 py-1.5 text-sm font-medium transition-colors',
          activeSub === sub.key
            ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
        ]"
        @click="selectSub(sub)"
      >
        {{ sub.label }}
      </button>
    </div>

    <TravelCrudSection
      v-if="activeSub === 'types'"
      :config="typeConfig"
    />
    <TravelCrudSection
      v-else-if="activeSub === 'positions'"
      :config="positionConfig"
    />
    <TravelCrudSection
      v-else-if="activeSub === 'prices'"
      :config="priceConfig"
      :lookups="{ types: typeOptions, positions: positionOptions }"
      :column-display="priceDisplay"
      @saved="loadReferenceLookups"
    />

    <!-- Annonces : soumission + annonces visibles (cycle complet : pay/validate/reject
         via endpoint admin dédié — #6428) -->
    <div v-else class="space-y-4">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">
            {{ t('travel.adverts.title', 'Annonces payantes') }}
          </h2>
          <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            {{ t('travel.adverts.subtitle', 'Soumettre une annonce et suivre les annonces visibles.') }}
          </p>
        </div>
        <button class="btn-primary" type="button" @click="openCreate">
          <PlusIcon class="mr-2 h-4 w-4" />
          {{ $t('travel.action.create', 'Créer') }}
        </button>
      </div>

      <DataTable
        :columns="advertColumns"
        :rows="adverts"
        :loading="loading"
        :error="listError"
        :search-keys="['title', 'content']"
        :search-placeholder="t('travel.search.advert', 'Rechercher une annonce…')"
        :caption="t('travel.adverts.title', 'Annonces payantes')"
      >
        <template #cell-price_minor="{ row, value }">
          {{ formatMoney(value, row.currency || 'XAF') }}
        </template>
        <template #row-actions="{ row }">
          <button
            class="rounded-md px-2 py-1.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
            type="button"
            :aria-label="t('travel.adverts.action.renew', 'Renouveler')"
            :title="t('travel.adverts.action.renew', 'Renouveler')"
            @click="renew(row)"
          >
            {{ t('travel.adverts.action.renew', 'Renouveler') }}
          </button>
        </template>
      </DataTable>
    </div>

    <!-- Formulaire de soumission d'annonce -->
    <TravelModal
      :open="createOpen"
      :title="t('travel.adverts.createTitle', 'Soumettre une annonce')"
      wide
      @close="closeCreate"
    >
      <form class="grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="submitAdvert">
        <FormField
          :id="'travel-advert-type'"
          :label="t('travel.adverts.field.type', 'Type')"
          :error="formErrors.advert_type_id"
          required
        >
          <template #default="{ id, ariaInvalid, describedBy }">
            <select :id="id" v-model="form.advert_type_id" class="form-input" required :aria-invalid="ariaInvalid" :aria-describedby="describedBy">
              <option value="">{{ t('travel.form.selectPlaceholder', '— Sélectionner —') }}</option>
              <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </template>
        </FormField>
        <FormField
          :id="'travel-advert-position'"
          :label="t('travel.adverts.field.position', 'Emplacement')"
          :error="formErrors.advert_position_id"
          required
        >
          <template #default="{ id, ariaInvalid, describedBy }">
            <select :id="id" v-model="form.advert_position_id" class="form-input" required :aria-invalid="ariaInvalid" :aria-describedby="describedBy">
              <option value="">{{ t('travel.form.selectPlaceholder', '— Sélectionner —') }}</option>
              <option v-for="opt in positionOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </template>
        </FormField>
        <FormField
          :id="'travel-advert-title'"
          :label="t('travel.field.title', 'Titre')"
          :error="formErrors.title"
          required
        >
          <template #default="{ id, ariaInvalid, describedBy }">
            <input :id="id" v-model.trim="form.title" type="text" class="form-input" required maxlength="160" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" />
          </template>
        </FormField>
        <FormField
          :id="'travel-advert-validity'"
          :label="t('travel.adverts.field.validityDays', 'Durée de validité (jours)')"
          :error="formErrors.validity_days"
        >
          <template #default="{ id, ariaInvalid, describedBy }">
            <input :id="id" v-model.number="form.validity_days" type="number" class="form-input" min="1" max="365" :aria-invalid="ariaInvalid" :aria-describedby="describedBy" />
          </template>
        </FormField>
        <FormField
          :id="'travel-advert-content'"
          :label="t('travel.adverts.field.content', 'Contenu')"
          :error="formErrors.content"
          class="col-span-full"
          required
        >
          <template #default="{ id, ariaInvalid, describedBy }">
            <textarea :id="id" v-model.trim="form.content" class="form-input" rows="4" required maxlength="2000" :aria-invalid="ariaInvalid" :aria-describedby="describedBy"></textarea>
          </template>
        </FormField>

        <div v-if="globalError" class="col-span-full rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
          {{ globalError }}
        </div>

        <div class="col-span-full flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="closeCreate">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button type="submit" class="btn-primary" :disabled="saving">
            {{ saving ? $t('common.busy', 'En cours…') : t('travel.adverts.action.submit', 'Soumettre') }}
          </button>
        </div>
      </form>
    </TravelModal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { PlusIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import FormField from '@/components/common/FormField.vue'
import TravelCrudSection from '@/components/travel/TravelCrudSection.vue'
import TravelModal from '@/components/travel/TravelModal.vue'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)
const toast = useToast()

const activeSub = ref('types')

function selectSub(sub) {
  activeSub.value = sub.key
}

const subTabs = computed(() => [
  { key: 'types', label: t('travel.adverts.tabTypes', 'Types') },
  { key: 'positions', label: t('travel.adverts.tabPositions', 'Emplacements') },
  { key: 'prices', label: t('travel.adverts.tabPrices', 'Grille tarifaire') },
  { key: 'adverts', label: t('travel.adverts.tabAdverts', 'Annonces') }
])

/* ── lookups types / positions ─────────────────────────────── */
const advertTypes = ref([])
const advertPositions = ref([])

const typeOptions = computed(() => advertTypes.value.map((x) => ({ value: x.id, label: x.label || x.code })))
const positionOptions = computed(() => advertPositions.value.map((x) => ({ value: x.id, label: x.label || x.code })))

async function loadReferenceLookups() {
  try {
    const [types, positions] = await Promise.all([
      api.get('/travel/advert-types', { params: { per_page: 100 }, _skipAuthRedirect: true }),
      api.get('/travel/advert-positions', { params: { per_page: 100 }, _skipAuthRedirect: true })
    ])
    advertTypes.value = types.data?.data || []
    advertPositions.value = positions.data?.data || []
  } catch {
    advertTypes.value = []
    advertPositions.value = []
  }
}

function lookupLabel(list, id) {
  const item = list.find((x) => String(x.id) === String(id))
  return item ? item.label || item.code : (id ?? '-')
}

/* ── types d'annonces ──────────────────────────────────────── */
const referenceConfig = (resource, titleKey, titleFallback) => ({
  resource,
  titleKey,
  titleFallback,
  searchPlaceholderKey: 'travel.search.advert',
  searchKeys: ['code', 'label'],
  defaultSort: 'code',
  canEdit: false,
  columns: [
    { key: 'code', label: 'travel.field.code', sortable: true },
    { key: 'label', label: 'travel.field.label', sortable: true }
  ],
  fields: [
    { key: 'code', label: 'travel.field.code', type: 'text', required: true, max: 40 },
    { key: 'label', label: 'travel.field.label', type: 'text', required: true, max: 120 }
  ],
  defaults: {}
})

const typeConfig = computed(() => referenceConfig('advert-types', 'travel.adverts.types', 'Types d’annonces'))
const positionConfig = computed(() => referenceConfig('advert-positions', 'travel.adverts.positions', 'Emplacements publicitaires'))

/* ── grille tarifaire ──────────────────────────────────────── */
const priceConfig = computed(() => ({
  resource: 'advert-prices',
  titleKey: 'travel.adverts.prices',
  titleFallback: 'Grille tarifaire',
  searchPlaceholderKey: 'travel.search.advert',
  searchKeys: ['currency'],
  defaultSort: 'id',
  canEdit: false,
  columns: [
    { key: 'advert_type_id', label: 'travel.adverts.field.type', sortable: true },
    { key: 'advert_position_id', label: 'travel.adverts.field.position', sortable: true },
    { key: 'price_per_image_minor', label: 'travel.adverts.field.pricePerImage', type: 'money', sortable: true },
    { key: 'price_per_character_minor', label: 'travel.adverts.field.pricePerCharacter', type: 'money', sortable: true },
    { key: 'currency', label: 'travel.field.currency', sortable: true }
  ],
  fields: [
    { key: 'advert_type_id', label: 'travel.adverts.field.type', type: 'select', source: 'types', required: true },
    { key: 'advert_position_id', label: 'travel.adverts.field.position', type: 'select', source: 'positions', required: true },
    { key: 'price_per_image_minor', label: 'travel.adverts.field.pricePerImage', type: 'money', required: true, min: 1 },
    { key: 'price_per_character_minor', label: 'travel.adverts.field.pricePerCharacter', type: 'money', required: true, min: 1 },
    { key: 'currency', label: 'travel.field.currency', type: 'text', required: true, min: 3, max: 3 }
  ],
  defaults: { currency: 'XAF' }
}))

const priceDisplay = computed(() => ({
  advert_type_id: (row, value) => lookupLabel(advertTypes.value, value),
  advert_position_id: (row, value) => lookupLabel(advertPositions.value, value)
}))

/* ── annonces ──────────────────────────────────────────────── */
const adverts = ref([])
const loading = ref(false)
const listError = ref('')
const createOpen = ref(false)
const saving = ref(false)
const form = ref({})
const formErrors = ref({})
const globalError = ref('')

const advertColumns = computed(() => [
  { key: 'title', label: t('travel.field.title', 'Titre'), sortable: true },
  { key: 'price_minor', label: t('travel.adverts.field.price', 'Prix'), sortable: true },
  { key: 'expires_at', label: t('travel.adverts.field.expiresAt', 'Expire le'), sortable: true }
])

function formatMoney(minor, currency) {
  try {
    return new Intl.NumberFormat(localeStore.current, { style: 'currency', currency }).format(Number(minor) / 100)
  } catch {
    return `${Number(minor) / 100} ${currency}`
  }
}

async function loadAdverts() {
  loading.value = true
  listError.value = ''
  try {
    const res = await api.get('/travel/adverts', { params: { per_page: 100 }, _skipAuthRedirect: true })
    adverts.value = res.data?.data || []
  } catch (err) {
    listError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  form.value = { validity_days: 30 }
  formErrors.value = {}
  globalError.value = ''
  createOpen.value = true
}

function closeCreate() {
  createOpen.value = false
}

async function submitAdvert() {
  saving.value = true
  formErrors.value = {}
  globalError.value = ''
  try {
    await api.post('/travel/adverts', form.value, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    createOpen.value = false
    await loadAdverts()
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

async function renew(row) {
  try {
    await api.post(`/travel/adverts/${row.id}/renew`, {}, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    await loadAdverts()
  } catch (err) {
    toast.error(err.response?.data?.message || t('travel.error.saveFailed', "Échec de l'enregistrement."))
  }
}

onMounted(() => {
  loadReferenceLookups()
  loadAdverts()
})
</script>
