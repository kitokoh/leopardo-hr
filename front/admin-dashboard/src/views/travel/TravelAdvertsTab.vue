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

    <!-- ── Annonces ─────────────────────────────────────────── -->
    <div v-if="activeSub === 'adverts'" class="space-y-4">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">
            {{ $t('travel.adverts.title', 'Annonces payantes') }}
          </h2>
          <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            {{ $t('travel.adverts.subtitle', 'Soumission, paiement, validation et renouvellement des annonces.') }}
          </p>
        </div>
        <button class="btn-primary" type="button" @click="openCreate">
          <PlusIcon class="mr-2 h-4 w-4" />
          {{ $t('travel.action.create', 'Créer') }}
        </button>
      </div>

      <div v-if="globalError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
        {{ globalError }}
      </div>

      <DataTable
        :columns="columns"
        :rows="adverts"
        :loading="loading"
        :error="listError"
        :search-keys="['title', 'content', 'status']"
        :search-placeholder="$t('travel.search.advert', 'Rechercher une annonce…')"
        default-sort="id"
        default-sort-dir="desc"
        :caption="$t('travel.adverts.title', 'Annonces payantes')"
      >
        <template #cell-status="{ value }">
          <StatusBadge :status="value" :map="statusMap" />
        </template>
        <template #cell-price_minor="{ row, value }">
          {{ formatMoney(value, row.currency || 'XAF') }}
        </template>
        <template #row-actions="{ row }">
          <div class="flex items-center justify-end gap-1">
            <button
              v-for="action in actionsFor(row)"
              :key="action.key"
              class="rounded-md px-2 py-1.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
              type="button"
              :aria-label="action.label"
              :title="action.label"
              @click="runAction(action, row)"
            >
              {{ action.label }}
            </button>
            <button
              class="rounded-md px-2 py-1.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
              type="button"
              :aria-label="$t('travel.action.view', 'Voir')"
              @click="openDetail(row)"
            >
              {{ $t('travel.action.view', 'Voir') }}
            </button>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- ── Référentiels (types / positions / tarifs) ────────── -->
    <TravelCrudSection
      v-else-if="activeSub === 'types'"
      :config="typeConfig"
    />
    <TravelCrudSection
      v-else-if="activeSub === 'positions'"
      :config="positionConfig"
    />
    <TravelCrudSection
      v-else-if="activeSub === 'prices'"
      :config="priceConfig"
      :lookups="{ advertTypes: advertTypeOptions, advertPositions: advertPositionOptions }"
    />

    <!-- Création d'une annonce -->
    <TravelModal :open="createOpen" :title="$t('travel.adverts.createTitle', 'Créer une annonce')" wide @close="closeCreate">
      <form class="grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="saveAdvert">
        <FormField
          id="travel-advert-type"
          :label="$t('travel.field.advertType', 'Type')"
          :error="formErrors.advert_type_id"
          required
        >
          <select v-model="form.advert_type_id" class="form-input" required>
            <option value="">{{ $t('travel.form.selectPlaceholder', '— Sélectionner —') }}</option>
            <option v-for="opt in advertTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </FormField>
        <FormField
          id="travel-advert-position"
          :label="$t('travel.field.advertPosition', 'Position')"
          :error="formErrors.advert_position_id"
          required
        >
          <select v-model="form.advert_position_id" class="form-input" required>
            <option value="">{{ $t('travel.form.selectPlaceholder', '— Sélectionner —') }}</option>
            <option v-for="opt in advertPositionOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </FormField>
        <FormField
          id="travel-advert-title"
          :label="$t('travel.field.title', 'Intitulé')"
          :error="formErrors.title"
          required
        >
          <input v-model="form.title" type="text" class="form-input" required :maxlength="160" />
        </FormField>
        <FormField
          id="travel-advert-validity"
          :label="$t('travel.adverts.validityDays', 'Durée de validité (jours)')"
          :error="formErrors.validity_days"
        >
          <input v-model.number="form.validity_days" type="number" min="1" max="365" step="1" class="form-input" />
        </FormField>
        <FormField
          id="travel-advert-content"
          :label="$t('travel.field.content', 'Contenu')"
          class="sm:col-span-2"
          :error="formErrors.content"
          required
        >
          <textarea v-model="form.content" class="form-input" rows="4" required :maxlength="2000"></textarea>
        </FormField>
        <FormField
          id="travel-advert-image"
          :label="$t('travel.field.imageAsset', 'Image (asset id)')"
          :error="formErrors.image_asset_id"
        >
          <input v-model="form.image_asset_id" type="number" min="1" step="1" class="form-input" />
        </FormField>
        <div class="col-span-full flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="closeCreate">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button type="submit" class="btn-primary" :disabled="saving">
            {{ saving ? $t('common.busy', 'En cours…') : $t('travel.action.create', 'Créer') }}
          </button>
        </div>
      </form>
    </TravelModal>

    <!-- Détail d'une annonce -->
    <TravelModal
      :open="detailOpen"
      :title="detail?.title || $t('travel.adverts.detailTitle', 'Détail de l\u2019annonce')"
      wide
      @close="closeDetail"
    >
      <div v-if="detailLoading" class="py-8 text-center text-sm text-slate-400">
        {{ $t('travel.loading', 'Chargement…') }}
      </div>
      <div v-else-if="detailError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
        {{ detailError }}
      </div>
      <dl v-else-if="detail" class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
        <div class="sm:col-span-2">
          <dt class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            {{ $t('travel.field.content', 'Contenu') }}
          </dt>
          <dd class="mt-1 text-slate-700 dark:text-slate-300">{{ detail.content }}</dd>
        </div>
        <div>
          <dt class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $t('travel.field.status', 'Statut') }}</dt>
          <dd class="mt-1"><StatusBadge :status="detail.status" :map="statusMap" /></dd>
        </div>
        <div>
          <dt class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $t('travel.field.amount', 'Montant') }}</dt>
          <dd class="mt-1 text-slate-700 dark:text-slate-300">{{ formatMoney(detail.price_minor, detail.currency || 'XAF') }}</dd>
        </div>
        <div>
          <dt class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $t('travel.adverts.paidAt', 'Payé le') }}</dt>
          <dd class="mt-1 text-slate-700 dark:text-slate-300">{{ detail.paid_at || '—' }}</dd>
        </div>
        <div>
          <dt class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $t('travel.adverts.validatedAt', 'Validée le') }}</dt>
          <dd class="mt-1 text-slate-700 dark:text-slate-300">{{ detail.validated_at || '—' }}</dd>
        </div>
        <div>
          <dt class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $t('travel.field.endDate', 'Expire le') }}</dt>
          <dd class="mt-1 text-slate-700 dark:text-slate-300">{{ detail.expires_at || '—' }}</dd>
        </div>
        <div>
          <dt class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $t('travel.adverts.visible', 'Visible') }}</dt>
          <dd class="mt-1 text-slate-700 dark:text-slate-300">{{ detail.visible ? $t('common.yes', 'Oui') : $t('common.no', 'Non') }}</dd>
        </div>
      </dl>
    </TravelModal>

    <!-- Rejet avec motif -->
    <TravelModal
      :open="rejectOpen"
      :title="$t('travel.adverts.rejectTitle', 'Rejeter l\u2019annonce')"
      @close="closeReject"
    >
      <form class="grid grid-cols-1 gap-4" @submit.prevent="confirmReject">
        <FormField id="travel-advert-reject-reason" :label="$t('travel.bookings.reason', 'Motif')" :error="rejectErrors.reason" required>
          <textarea v-model="rejectReason" class="form-input" rows="3" required :maxlength="500"></textarea>
        </FormField>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="closeReject">
            {{ $t('common.cancel', 'Annuler') }}
          </button>
          <button type="submit" class="btn-primary" :disabled="actionBusy">
            {{ actionBusy ? $t('common.busy', 'En cours…') : $t('travel.adverts.reject', 'Rejeter') }}
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
import StatusBadge from '@/components/common/StatusBadge.vue'
import TravelModal from '@/components/travel/TravelModal.vue'
import TravelCrudSection from '@/components/travel/TravelCrudSection.vue'
import { useToast } from 'vue-toastification'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const toast = useToast()
const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const activeSub = ref('adverts')

function selectSub(sub) {
  activeSub.value = sub.key
}

const subTabs = computed(() => [
  { key: 'adverts', label: t('travel.adverts.tabAdverts', 'Annonces') },
  { key: 'types', label: t('travel.adverts.tabTypes', 'Types') },
  { key: 'positions', label: t('travel.adverts.tabPositions', 'Positions') },
  { key: 'prices', label: t('travel.adverts.tabPrices', 'Tarifs') },
])

const adverts = ref([])
const loading = ref(false)
const listError = ref('')
const globalError = ref('')

const createOpen = ref(false)
const saving = ref(false)
const form = ref({})
const formErrors = ref({})

const detailOpen = ref(false)
const detailLoading = ref(false)
const detailError = ref('')
const detail = ref(null)

const rejectOpen = ref(false)
const rejectReason = ref('')
const rejectErrors = ref({})
const actionBusy = ref(false)
const targetRow = ref(null)

const advertTypes = ref([])
const advertPositions = ref([])

const statusMap = computed(() => ({
  draft: { label: t('travel.advertStatus.draft', 'Brouillon'), color: 'gray' },
  submitted: { label: t('travel.advertStatus.submitted', 'Soumise'), color: 'blue' },
  paid: { label: t('travel.advertStatus.paid', 'Payée'), color: 'purple' },
  validated: { label: t('travel.advertStatus.validated', 'Validée'), color: 'green' },
  rejected: { label: t('travel.advertStatus.rejected', 'Rejetée'), color: 'red' },
  expired: { label: t('travel.advertStatus.expired', 'Expirée'), color: 'amber' },
  archived: { label: t('travel.advertStatus.archived', 'Archivée'), color: 'gray' },
}))

const columns = computed(() => [
  { key: 'id', label: t('travel.field.id', 'ID'), sortable: true },
  { key: 'title', label: t('travel.field.title', 'Intitulé'), sortable: true },
  { key: 'status', label: t('travel.field.status', 'Statut'), sortable: true },
  { key: 'price_minor', label: t('travel.field.amount', 'Montant'), type: 'money' },
  { key: 'currency', label: t('travel.field.currency', 'Devise') },
  { key: 'expires_at', label: t('travel.field.endDate', 'Expire le'), sortable: true },
])

const advertTypeOptions = computed(() =>
  advertTypes.value.map((item) => ({ value: item.id, label: item.label || item.code }))
)
const advertPositionOptions = computed(() =>
  advertPositions.value.map((item) => ({ value: item.id, label: item.label || item.code }))
)

const typeConfig = computed(() => ({
  resource: 'advert-types',
  titleKey: 'travel.adverts.types',
  searchPlaceholderKey: 'travel.search.advertRef',
  searchKeys: ['code', 'label'],
  defaultSort: 'code',
  canEdit: false,
  columns: [
    { key: 'code', label: 'travel.field.code', sortable: true },
    { key: 'label', label: 'travel.field.label', sortable: true },
  ],
  fields: [
    { key: 'code', type: 'text', required: true, label: 'travel.field.code' },
    { key: 'label', type: 'text', required: true, label: 'travel.field.label' },
  ],
  defaults: { code: '', label: '' },
}))

const positionConfig = computed(() => ({
  resource: 'advert-positions',
  titleKey: 'travel.adverts.positions',
  searchPlaceholderKey: 'travel.search.advertRef',
  searchKeys: ['code', 'label'],
  defaultSort: 'code',
  canEdit: false,
  columns: [
    { key: 'code', label: 'travel.field.code', sortable: true },
    { key: 'label', label: 'travel.field.label', sortable: true },
  ],
  fields: [
    { key: 'code', type: 'text', required: true, label: 'travel.field.code' },
    { key: 'label', type: 'text', required: true, label: 'travel.field.label' },
  ],
  defaults: { code: '', label: '' },
}))

const priceConfig = computed(() => ({
  resource: 'advert-prices',
  titleKey: 'travel.adverts.prices',
  searchPlaceholderKey: 'travel.search.advertRef',
  searchKeys: ['advert_type', 'advert_position', 'currency'],
  defaultSort: 'id',
  canEdit: false,
  columns: [
    { key: 'advert_type', label: 'travel.field.advertType', sortable: true },
    { key: 'advert_position', label: 'travel.field.advertPosition', sortable: true },
    { key: 'price_per_image_minor', label: 'travel.adverts.pricePerImage', type: 'money' },
    { key: 'price_per_character_minor', label: 'travel.adverts.pricePerCharacter', type: 'money' },
    { key: 'currency', label: 'travel.field.currency', sortable: true },
  ],
  fields: [
    { key: 'advert_type_id', type: 'select', source: 'advertTypes', required: true, label: 'travel.field.advertType' },
    { key: 'advert_position_id', type: 'select', source: 'advertPositions', required: true, label: 'travel.field.advertPosition' },
    { key: 'price_per_image_minor', type: 'money', required: true, label: 'travel.adverts.pricePerImage' },
    { key: 'price_per_character_minor', type: 'money', required: true, label: 'travel.adverts.pricePerCharacter' },
    { key: 'currency', type: 'text', required: true, maxlength: 3, label: 'travel.field.currency' },
  ],
  defaults: { advert_type_id: '', advert_position_id: '', price_per_image_minor: '', price_per_character_minor: '', currency: 'XAF' },
}))

function actionsFor(row) {
  const actions = []
  if (row.status === 'submitted' || row.status === 'draft') {
    actions.push({ key: 'pay', label: t('travel.adverts.pay', 'Payer'), endpoint: 'pay' })
  }
  if (row.status === 'paid') {
    actions.push({ key: 'validate', label: t('travel.adverts.validate', 'Valider'), endpoint: 'validate' })
    actions.push({ key: 'reject', label: t('travel.adverts.reject', 'Rejeter') })
  }
  if (row.status === 'validated') {
    actions.push({ key: 'renew', label: t('travel.adverts.renew', 'Renouveler'), endpoint: 'renew' })
  }
  return actions
}

function formatMoney(minor, currency) {
  try {
    return new Intl.NumberFormat(localeStore.current, { style: 'currency', currency }).format(Number(minor) / 100)
  } catch {
    return `${Number(minor) / 100} ${currency}`
  }
}

async function load() {
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

async function loadReferentials() {
  try {
    const [types, positions] = await Promise.all([
      api.get('/travel/advert-types', { params: { per_page: 100 }, _skipAuthRedirect: true }),
      api.get('/travel/advert-positions', { params: { per_page: 100 }, _skipAuthRedirect: true }),
    ])
    advertTypes.value = types.data?.data || []
    advertPositions.value = positions.data?.data || []
  } catch {
    /* silencieux : les selects afficheront un état vide, l'erreur remonte via les CRUD */
  }
}

function openCreate() {
  form.value = {
    advert_type_id: '',
    advert_position_id: '',
    title: '',
    content: '',
    image_asset_id: '',
    validity_days: 30,
  }
  formErrors.value = {}
  globalError.value = ''
  createOpen.value = true
}

function closeCreate() {
  createOpen.value = false
}

async function saveAdvert() {
  saving.value = true
  globalError.value = ''
  formErrors.value = {}
  try {
    const payload = { ...form.value }
    if (payload.image_asset_id === '') delete payload.image_asset_id
    await api.post('/travel/adverts', payload, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    createOpen.value = false
    await load()
  } catch (err) {
    const data = err.response?.data || {}
    if (data.errors) {
      formErrors.value = Object.fromEntries(Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]))
    }
    globalError.value = data.message || data.localized_message || t('travel.error.saveFailed', "Échec de l'enregistrement.")
  } finally {
    saving.value = false
  }
}

async function openDetail(row) {
  detail.value = null
  detailError.value = ''
  detailOpen.value = true
  detailLoading.value = true
  try {
    const res = await api.get(`/travel/adverts/${row.id}`, { _skipAuthRedirect: true })
    detail.value = res.data?.data
  } catch (err) {
    detailError.value = err.response?.data?.message || t('travel.error.loadFailed', 'Impossible de charger les données.')
  } finally {
    detailLoading.value = false
  }
}

function closeDetail() {
  detailOpen.value = false
}

async function runAction(action, row) {
  if (action.key === 'reject') {
    targetRow.value = row
    rejectReason.value = ''
    rejectErrors.value = {}
    rejectOpen.value = true
    return
  }
  actionBusy.value = true
  try {
    await api.post(`/travel/adverts/${row.id}/${action.endpoint}`, {}, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    await load()
  } catch (err) {
    toast.error(err.response?.data?.message || t('travel.error.actionFailed', "L'action a échoué."))
  } finally {
    actionBusy.value = false
  }
}

function closeReject() {
  rejectOpen.value = false
}

async function confirmReject() {
  if (!targetRow.value) return
  actionBusy.value = true
  rejectErrors.value = {}
  try {
    await api.post(`/travel/adverts/${targetRow.value.id}/reject`, { reason: rejectReason.value }, { _skipAuthRedirect: true })
    toast.success(t('travel.toast.saved', 'Enregistré.'))
    rejectOpen.value = false
    await load()
  } catch (err) {
    const data = err.response?.data || {}
    if (data.errors) {
      rejectErrors.value = Object.fromEntries(Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]))
    }
    toast.error(data.message || t('travel.error.actionFailed', "L'action a échoué."))
  } finally {
    actionBusy.value = false
  }
}

onMounted(() => {
  load()
  loadReferentials()
})
</script>
