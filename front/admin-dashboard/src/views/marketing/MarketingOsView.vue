<script setup>
/**
 * Marketing OS — espace Super Admin de pilotage marketing (issue #8528).
 *
 * Centre de commande de la présence en ligne : stratégie, médiathèque,
 * publications. Les données appartiennent à UNE entreprise cliente : l'écran
 * demande donc explicitement laquelle, puis travaille dans son contexte.
 *
 * Ce que l'écran ne fait JAMAIS :
 *  - présenter une publication assistée comme automatique (le `mode` affiché
 *    est celui calculé par le serveur) ;
 *  - marquer un contenu publié parce qu'une page de réseau social a été
 *    ouverte (la confirmation exige l'URL réelle) ;
 *  - afficher une statistique non mesurée comme un fait (source « indisponible »).
 *
 * Garde PA2-I18N-014 : aucun texte utilisateur en dur. Les libellés du gabarit
 * passent par `$t()` (propriété globale, cf. `main.js`) ; côté script, la
 * fonction `t()` de `@/i18n` est utilisée pour les MESSAGES de retour d'action.
 */
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import { translate as t } from '@/i18n/index.js'
import { useAuthStore } from '@/stores/auth'
import MarketingOsDashboardPanel from '@/components/marketing/MarketingOsDashboardPanel.vue'
import MarketingOsStrategyPanel from '@/components/marketing/MarketingOsStrategyPanel.vue'
import MarketingOsCompanyPicker from '@/components/marketing/MarketingOsCompanyPicker.vue'
import MarketingOsStatusBadge from '@/components/marketing/MarketingOsStatusBadge.vue'
import {
  PUBLICATION_MODE_LABELS,
  PUBLICATION_STATUS_LABELS,
  VALIDATION_STATUS_LABELS,
  ASSET_KIND_LABELS,
  confirmPublicationPublished,
  createContentIdea,
  createMarketingPublication,
  fetchAssetPublications,
  fetchMarketingOsOverview,
  listContentIdeas,
  listMarketingAssets,
  listMarketingCampaigns,
  listMarketingPublications,
  marketingOsErrorMessage,
  promoteContentIdea,
  uploadMarketingAsset,
  labelFrom,
} from '@/services/marketingOs'

const auth = useAuthStore()
const canManage = computed(() => (auth.permissions || []).includes('marketing_os.manage'))

// Onglets : identifiant technique + CLÉ de traduction (jamais de libellé).
const tabs = [
  { id: 'dashboard', labelKey: 'marketingOs.tab.dashboard' },
  { id: 'strategy', labelKey: 'marketingOs.tab.strategy' },
  { id: 'library', labelKey: 'marketingOs.tab.library' },
  { id: 'publications', labelKey: 'marketingOs.tab.publications' },
]
const activeTab = ref('dashboard')

const companies = ref([])
const companyId = ref('')
const loading = ref(false)
const loadError = ref('')

const overview = ref(null)
const campaigns = ref([])
const ideas = ref([])
const assets = ref([])
const publications = ref([])

const actionMessage = ref('')
const actionError = ref('')

// ── Filtres médiathèque (section C : recherche puissante) ────────────────
const emptyFilters = () => ({
  search: '',
  kind: '',
  locale: '',
  sector: '',
  product: '',
  validation_status: '',
  platform: '',
  tag: '',
  from: '',
  to: '',
})
const assetFilters = ref(emptyFilters())

const emptyAssetForm = () => ({
  name: '',
  kind: 'video',
  description: '',
  locale: 'fr',
  sector: '',
  product: '',
  tags: '',
})
const assetForm = ref(emptyAssetForm())
const assetFile = ref(null)
const showAssetForm = ref(false)

// ── Ressource ↔ publications (section G) ────────────────────────────────
const assetHistory = ref(null)

// ── Confirmation de publication (section F) ─────────────────────────────
const confirming = ref(null)
const confirmForm = ref({ published_url: '', platform_account: '' })
const emptyPublicationForm = () => ({
  platform: 'linkedin',
  title: '',
  body: '',
  hashtags: '',
  marketing_asset_id: '',
})
const publicationForm = ref(emptyPublicationForm())

async function loadCompanies() {
  try {
    // Liste des entreprises clientes : endpoint plateforme existant
    // `GET /platform/companies` (`companies.view`), même contrat que l'écran
    // Entreprises — aucun endpoint inventé pour l'occasion.
    const { data } = await api.get('/platform/companies', { params: { per_page: 100 } })
    const rows = data?.data || []
    companies.value = rows.map((c) => ({ id: c.id, name: c.name }))
  } catch (error) {
    loadError.value = marketingOsErrorMessage(error, t('marketingOs.error.loadCompanies'))
  }
}

function resetData() {
  overview.value = null
  campaigns.value = []
  ideas.value = []
  assets.value = []
  publications.value = []
  assetHistory.value = null
}

async function loadAll() {
  if (!companyId.value) {
    resetData()
    return
  }
  loading.value = true
  loadError.value = ''
  actionError.value = ''
  try {
    const [overviewRes, campaignsRes, ideasRes, assetsRes, publicationsRes] = await Promise.all([
      fetchMarketingOsOverview(companyId.value),
      listMarketingCampaigns(companyId.value, { per_page: 100 }),
      listContentIdeas(companyId.value, { per_page: 100 }),
      listMarketingAssets(companyId.value, buildAssetParams()),
      listMarketingPublications(companyId.value, { per_page: 100 }),
    ])
    overview.value = overviewRes.data?.data || null
    campaigns.value = campaignsRes.data?.data || []
    ideas.value = ideasRes.data?.data || []
    assets.value = assetsRes.data?.data || []
    publications.value = publicationsRes.data?.data || []
  } catch (error) {
    loadError.value = marketingOsErrorMessage(error, t('marketingOs.error.load'))
  } finally {
    loading.value = false
  }
}

function buildAssetParams() {
  const params = { per_page: 100 }
  Object.entries(assetFilters.value).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) params[key] = value
  })
  return params
}

async function reloadAssets() {
  if (!companyId.value) return
  try {
    const { data } = await listMarketingAssets(companyId.value, buildAssetParams())
    assets.value = data?.data || []
  } catch (error) {
    actionError.value = marketingOsErrorMessage(error, t('marketingOs.error.filter'))
  }
}

async function submitIdea(payload) {
  actionMessage.value = ''
  actionError.value = ''
  try {
    await createContentIdea(companyId.value, payload)
    actionMessage.value = t('marketingOs.toast.ideaCreated')
    await loadAll()
  } catch (error) {
    actionError.value = marketingOsErrorMessage(error, t('marketingOs.error.create'))
  }
}

async function promote(idea) {
  actionMessage.value = ''
  actionError.value = ''
  try {
    const { data } = await promoteContentIdea(companyId.value, idea.id)
    // Le serveur dit si la tâche a réellement été créée : on ne l'invente pas.
    const created = data?.meta?.production_task_created === true
    actionMessage.value = created
      ? t('marketingOs.toast.promotedWithTask')
      : t('marketingOs.toast.promotedWithoutTask')
    await loadAll()
  } catch (error) {
    actionError.value = marketingOsErrorMessage(error, t('marketingOs.error.promote'))
  }
}

async function submitAsset() {
  actionMessage.value = ''
  actionError.value = ''
  try {
    await uploadMarketingAsset(companyId.value, {
      file: assetFile.value,
      payload: {
        name: assetForm.value.name,
        kind: assetForm.value.kind,
        description: assetForm.value.description || null,
        locale: assetForm.value.locale || 'fr',
        sector: assetForm.value.sector || null,
        product: assetForm.value.product || null,
        tags: assetForm.value.tags
          ? assetForm.value.tags.split(',').map((tag) => tag.trim()).filter(Boolean)
          : [],
      },
    })
    actionMessage.value = t('marketingOs.toast.assetAdded')
    showAssetForm.value = false
    assetForm.value = emptyAssetForm()
    assetFile.value = null
    await loadAll()
  } catch (error) {
    actionError.value = marketingOsErrorMessage(error, t('marketingOs.error.upload'))
  }
}

async function showHistory(asset) {
  actionError.value = ''
  try {
    const { data } = await fetchAssetPublications(companyId.value, asset.id)
    assetHistory.value = data?.data || null
  } catch (error) {
    actionError.value = marketingOsErrorMessage(error, t('marketingOs.error.history'))
  }
}

async function submitPublication() {
  actionMessage.value = ''
  actionError.value = ''
  try {
    const { data } = await createMarketingPublication(companyId.value, {
      platform: publicationForm.value.platform,
      title: publicationForm.value.title || null,
      body: publicationForm.value.body || null,
      hashtags: publicationForm.value.hashtags || null,
      marketing_asset_id: publicationForm.value.marketing_asset_id
        ? Number(publicationForm.value.marketing_asset_id)
        : null,
    })
    // Le MODE affiché est celui calculé par le serveur, jamais deviné ici.
    const mode = data?.data?.mode
    actionMessage.value =
      mode === 'direct' ? t('marketingOs.toast.preparedDirect') : t('marketingOs.toast.preparedAssisted')
    await loadAll()
  } catch (error) {
    actionError.value = marketingOsErrorMessage(error, t('marketingOs.error.prepare'))
  }
}

function startConfirm(publication) {
  confirming.value = publication
  confirmForm.value = {
    published_url: publication.published_url || '',
    platform_account: publication.platform_account || '',
  }
}

async function submitConfirm() {
  actionMessage.value = ''
  actionError.value = ''
  try {
    await confirmPublicationPublished(companyId.value, confirming.value.id, {
      publishedUrl: confirmForm.value.published_url,
      platformAccount: confirmForm.value.platform_account || null,
    })
    actionMessage.value = t('marketingOs.toast.confirmed')
    confirming.value = null
    await loadAll()
  } catch (error) {
    actionError.value = marketingOsErrorMessage(error, t('marketingOs.error.confirm'))
  }
}

function formatDate(value) {
  if (!value) return null
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date.toLocaleString()
}

watch(companyId, loadAll)
onMounted(loadCompanies)
</script>

<template>
  <div class="space-y-6">
    <header class="space-y-1">
      <h1 class="text-xl font-semibold text-gray-900">{{ $t('marketingOs.title') }}</h1>
      <p class="text-sm text-gray-600">{{ $t('marketingOs.subtitle') }}</p>
    </header>

    <MarketingOsCompanyPicker v-model="companyId" :companies="companies" :loading="loading" @reload="loadAll" />

    <p v-if="loadError" class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ loadError }}</p>
    <p v-if="actionMessage" class="rounded-md bg-emerald-50 p-3 text-sm text-emerald-800">{{ actionMessage }}</p>
    <p v-if="actionError" class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ actionError }}</p>

    <div v-if="companyId" class="inline-flex flex-wrap gap-1 rounded-md border border-gray-200 bg-white p-0.5">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        class="rounded px-3 py-1.5 text-sm"
        :class="activeTab === tab.id ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
        @click="activeTab = tab.id"
      >
        {{ $t(tab.labelKey) }}
      </button>
    </div>

    <!-- A. Tableau de bord -->
    <MarketingOsDashboardPanel
      v-if="companyId && activeTab === 'dashboard'"
      :overview="overview"
      :loading="loading"
    />

    <!-- B. Stratégie & calendrier -->
    <MarketingOsStrategyPanel
      v-else-if="companyId && activeTab === 'strategy'"
      :ideas="ideas"
      :campaigns="campaigns"
      :loading="loading"
      :can-manage="canManage"
      :action-message="actionMessage"
      :action-error="actionError"
      @create-idea="submitIdea"
      @promote-idea="promote"
    />

    <!-- C. Médiathèque -->
    <section v-else-if="companyId && activeTab === 'library'" class="space-y-4">
      <div class="grid grid-cols-1 gap-2 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-3 lg:grid-cols-5">
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.filter.search') }}</span>
          <input v-model="assetFilters.search" :placeholder="$t('marketingOs.filter.searchHint')" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.filter.kind') }}</span>
          <select v-model="assetFilters.kind" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">{{ $t('marketingOs.filter.all') }}</option>
            <option v-for="(labelKey, value) in ASSET_KIND_LABELS" :key="value" :value="value">
              {{ $t(labelKey) }}
            </option>
          </select>
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.filter.locale') }}</span>
          <input v-model="assetFilters.locale" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.filter.sector') }}</span>
          <input v-model="assetFilters.sector" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.filter.product') }}</span>
          <input v-model="assetFilters.product" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.filter.validation') }}</span>
          <select v-model="assetFilters.validation_status" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">{{ $t('marketingOs.filter.all') }}</option>
            <option v-for="(labelKey, value) in VALIDATION_STATUS_LABELS" :key="value" :value="value">
              {{ $t(labelKey) }}
            </option>
          </select>
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.filter.platform') }}</span>
          <input v-model="assetFilters.platform" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.filter.tag') }}</span>
          <input v-model="assetFilters.tag" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.filter.from') }}</span>
          <input v-model="assetFilters.from" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.filter.to') }}</span>
          <input v-model="assetFilters.to" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <div class="flex items-end gap-2">
          <button type="button" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white" @click="reloadAssets">
            {{ $t('marketingOs.filter.apply') }}
          </button>
          <button v-if="canManage" type="button" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm" @click="showAssetForm = !showAssetForm">
            {{ $t('marketingOs.library.add') }}
          </button>
        </div>
      </div>

      <form
        v-if="showAssetForm"
        class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-3"
        @submit.prevent="submitAsset"
      >
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.library.name') }}</span>
          <input v-model="assetForm.name" required maxlength="200" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.library.kind') }}</span>
          <select v-model="assetForm.kind" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option v-for="(labelKey, value) in ASSET_KIND_LABELS" :key="value" :value="value">
              {{ $t(labelKey) }}
            </option>
          </select>
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.library.file') }}</span>
          <input type="file" class="mt-1 block w-full text-sm" @change="assetFile = $event.target.files[0] || null" />
          <span class="mt-1 block text-xs text-gray-500">{{ $t('marketingOs.library.fileHint') }}</span>
        </label>
        <label class="text-sm sm:col-span-3">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.library.description') }}</span>
          <textarea v-model="assetForm.description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.library.locale') }}</span>
          <input v-model="assetForm.locale" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.library.sector') }}</span>
          <input v-model="assetForm.sector" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.library.product') }}</span>
          <input v-model="assetForm.product" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm sm:col-span-2">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.library.tags') }}</span>
          <input v-model="assetForm.tags" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <div class="flex items-end">
          <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">
            {{ $t('marketingOs.library.save') }}
          </button>
        </div>
      </form>

      <p v-if="!assets.length" class="rounded-lg border border-dashed border-gray-300 p-6 text-sm text-gray-500">
        {{ $t('marketingOs.library.empty') }}
      </p>
      <div v-else class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
            <tr>
              <th class="px-3 py-2">{{ $t('marketingOs.library.colName') }}</th>
              <th class="px-3 py-2">{{ $t('marketingOs.library.colKind') }}</th>
              <th class="px-3 py-2">{{ $t('marketingOs.library.colVersion') }}</th>
              <th class="px-3 py-2">{{ $t('marketingOs.library.colLocale') }}</th>
              <th class="px-3 py-2">{{ $t('marketingOs.library.colSector') }}</th>
              <th class="px-3 py-2">{{ $t('marketingOs.library.colValidation') }}</th>
              <th class="px-3 py-2">{{ $t('marketingOs.library.colFile') }}</th>
              <th class="px-3 py-2">{{ $t('marketingOs.library.colAdded') }}</th>
              <th class="px-3 py-2 text-right">{{ $t('marketingOs.library.colPublications') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="asset in assets" :key="asset.id">
              <td class="px-3 py-2 font-medium text-gray-900">{{ asset.name }}</td>
              <td class="px-3 py-2 text-gray-600">{{ $t(labelFrom(ASSET_KIND_LABELS, asset.kind)) }}</td>
              <td class="px-3 py-2 text-gray-600">{{ $t('marketingOs.library.versionValue', { version: asset.version }) }}</td>
              <td class="px-3 py-2 text-gray-600">{{ asset.locale }}</td>
              <td class="px-3 py-2 text-gray-600">{{ asset.sector || $t('marketingOs.common.dash') }}</td>
              <td class="px-3 py-2">
                <MarketingOsStatusBadge
                  :label="$t(labelFrom(VALIDATION_STATUS_LABELS, asset.validation_status))"
                  :tone="asset.validation_status === 'approved' ? 'success' : asset.validation_status === 'rejected' ? 'danger' : 'neutral'"
                />
              </td>
              <td class="px-3 py-2 text-gray-600">
                {{ asset.has_file ? $t('marketingOs.library.fileYes') : $t('marketingOs.library.fileText') }}
              </td>
              <td class="px-3 py-2 text-gray-600">
                {{ formatDate(asset.created_at) || $t('marketingOs.common.dash') }}
              </td>
              <td class="px-3 py-2 text-right">
                <button type="button" class="rounded border border-gray-300 px-2 py-1 text-xs" @click="showHistory(asset)">
                  {{ $t('marketingOs.library.wherePublished') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Historique d'une ressource (section G) -->
      <div v-if="assetHistory" class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-900">
            {{
              $t('marketingOs.history.title', {
                name: assetHistory.asset.name,
                count: assetHistory.published_count,
              })
            }}
          </h3>
          <button type="button" class="text-xs text-gray-500" @click="assetHistory = null">
            {{ $t('marketingOs.history.close') }}
          </button>
        </div>
        <p v-if="!assetHistory.publications.length" class="mt-2 text-sm text-gray-500">
          {{ $t('marketingOs.history.neverPublished') }}
        </p>
        <ul v-else class="mt-3 divide-y divide-gray-100">
          <li v-for="pub in assetHistory.publications" :key="pub.id" class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
            <span class="font-medium text-gray-900">
              {{ pub.platform }} — {{ pub.title || $t('marketingOs.common.untitled') }}
            </span>
            <span class="flex flex-wrap items-center gap-2">
              <MarketingOsStatusBadge
                :label="$t(labelFrom(PUBLICATION_STATUS_LABELS, pub.status))"
                :tone="pub.status === 'published' ? 'success' : pub.status === 'failed' ? 'danger' : 'neutral'"
              />
              <MarketingOsStatusBadge
                :label="$t(labelFrom(PUBLICATION_MODE_LABELS, pub.mode))"
                :tone="pub.mode === 'direct' ? 'info' : 'muted'"
              />
              <span v-if="pub.platform_account" class="text-xs text-gray-500">
                {{ $t('marketingOs.history.account', { account: pub.platform_account }) }}
              </span>
              <span class="text-xs text-gray-500">
                {{ formatDate(pub.published_at) || $t('marketingOs.common.dash') }}
              </span>
              <a
                v-if="pub.published_url"
                :href="pub.published_url"
                target="_blank"
                rel="noopener noreferrer"
                class="text-xs text-indigo-600 hover:underline"
              >
                {{ $t('marketingOs.history.realLink') }}
              </a>
              <span v-if="pub.stats_source" class="text-xs text-gray-400">
                {{ $t('marketingOs.history.statsSource', { source: pub.stats_source }) }}
              </span>
            </span>
          </li>
        </ul>
      </div>
    </section>

    <!-- E / F. Publications -->
    <section v-else-if="companyId && activeTab === 'publications'" class="space-y-4">
      <form
        v-if="canManage"
        class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-3"
        @submit.prevent="submitPublication"
      >
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.publication.platform') }}</span>
          <input v-model="publicationForm.platform" required maxlength="40" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.publication.asset') }}</span>
          <select v-model="publicationForm.marketing_asset_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">{{ $t('marketingOs.publication.noAsset') }}</option>
            <option v-for="asset in assets" :key="asset.id" :value="asset.id">
              {{ $t('marketingOs.publication.assetOption', { name: asset.name, version: asset.version }) }}
            </option>
          </select>
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.publication.title') }}</span>
          <input v-model="publicationForm.title" maxlength="200" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm sm:col-span-2">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.publication.body') }}</span>
          <textarea v-model="publicationForm.body" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">{{ $t('marketingOs.publication.hashtags') }}</span>
          <input v-model="publicationForm.hashtags" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <div class="flex items-end">
          <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">
            {{ $t('marketingOs.publication.prepare') }}
          </button>
        </div>
      </form>

      <p v-if="!publications.length" class="rounded-lg border border-dashed border-gray-300 p-6 text-sm text-gray-500">
        {{ $t('marketingOs.publication.empty') }}
      </p>
      <div v-else class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
            <tr>
              <th class="px-3 py-2">{{ $t('marketingOs.publication.colPlatform') }}</th>
              <th class="px-3 py-2">{{ $t('marketingOs.publication.colTitle') }}</th>
              <th class="px-3 py-2">{{ $t('marketingOs.publication.colMode') }}</th>
              <th class="px-3 py-2">{{ $t('marketingOs.publication.colStatus') }}</th>
              <th class="px-3 py-2">{{ $t('marketingOs.publication.colScheduled') }}</th>
              <th class="px-3 py-2">{{ $t('marketingOs.publication.colPublished') }}</th>
              <th class="px-3 py-2">{{ $t('marketingOs.publication.colLink') }}</th>
              <th class="px-3 py-2">{{ $t('marketingOs.publication.colAttempts') }}</th>
              <th v-if="canManage" class="px-3 py-2 text-right">{{ $t('marketingOs.publication.colAction') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="publication in publications" :key="publication.id">
              <td class="px-3 py-2 font-medium text-gray-900">{{ publication.platform }}</td>
              <td class="px-3 py-2 text-gray-600">{{ publication.title || $t('marketingOs.common.dash') }}</td>
              <td class="px-3 py-2">
                <MarketingOsStatusBadge
                  :label="$t(labelFrom(PUBLICATION_MODE_LABELS, publication.mode))"
                  :tone="publication.mode === 'direct' ? 'info' : 'muted'"
                />
              </td>
              <td class="px-3 py-2">
                <MarketingOsStatusBadge
                  :label="$t(labelFrom(PUBLICATION_STATUS_LABELS, publication.status))"
                  :tone="publication.status === 'published' ? 'success' : publication.status === 'failed' ? 'danger' : 'neutral'"
                />
              </td>
              <td class="px-3 py-2 text-gray-600">
                {{ formatDate(publication.scheduled_at) || $t('marketingOs.common.dash') }}
              </td>
              <td class="px-3 py-2 text-gray-600">
                {{ formatDate(publication.published_at) || $t('marketingOs.common.dash') }}
              </td>
              <td class="px-3 py-2">
                <a
                  v-if="publication.published_url"
                  :href="publication.published_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-xs text-indigo-600 hover:underline"
                >
                  {{ $t('marketingOs.publication.open') }}
                </a>
                <span v-else class="text-xs text-gray-400">{{ $t('marketingOs.common.dash') }}</span>
              </td>
              <td class="px-3 py-2 text-gray-600">
                {{ publication.attempts }}
                <span v-if="publication.error_message" class="block text-xs text-red-600">{{ publication.error_message }}</span>
              </td>
              <td v-if="canManage" class="px-3 py-2 text-right">
                <button
                  v-if="publication.status !== 'published'"
                  type="button"
                  class="rounded border border-indigo-200 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-50"
                  @click="startConfirm(publication)"
                >
                  {{ $t('marketingOs.publication.confirm') }}
                </button>
                <span v-else class="text-xs text-emerald-700">{{ $t('marketingOs.publication.online') }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Confirmation : URL réelle obligatoire -->
      <div v-if="confirming" class="rounded-lg border border-indigo-200 bg-indigo-50 p-4">
        <h3 class="text-sm font-semibold text-gray-900">
          {{ $t('marketingOs.confirm.title', { platform: confirming.platform }) }}
        </h3>
        <p class="mt-1 text-xs text-gray-600">{{ $t('marketingOs.confirm.hint') }}</p>
        <form class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3" @submit.prevent="submitConfirm">
          <label class="text-sm sm:col-span-2">
            <span class="font-medium text-gray-700">{{ $t('marketingOs.confirm.url') }}</span>
            <input v-model="confirmForm.published_url" type="url" required class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
          </label>
          <label class="text-sm">
            <span class="font-medium text-gray-700">{{ $t('marketingOs.confirm.account') }}</span>
            <input v-model="confirmForm.platform_account" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
          </label>
          <div class="flex items-end gap-2 sm:col-span-3">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">
              {{ $t('marketingOs.confirm.submit') }}
            </button>
            <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm" @click="confirming = null">
              {{ $t('marketingOs.confirm.cancel') }}
            </button>
          </div>
        </form>
      </div>
    </section>

    <p v-if="!companyId" class="rounded-lg border border-dashed border-gray-300 p-6 text-sm text-gray-500">
      {{ $t('marketingOs.selectCompany') }}
    </p>
  </div>
</template>
