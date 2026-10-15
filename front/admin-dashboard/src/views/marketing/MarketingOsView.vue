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
 */
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/services/api'
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

const tabs = [
  { id: 'dashboard', label: 'Tableau de bord' },
  { id: 'strategy', label: 'Stratégie & calendrier' },
  { id: 'library', label: 'Médiathèque' },
  { id: 'publications', label: 'Publications' },
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
const assetFilters = ref({
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

const assetForm = ref({
  name: '',
  kind: 'video',
  description: '',
  locale: 'fr',
  sector: '',
  product: '',
  tags: '',
})
const assetFile = ref(null)
const showAssetForm = ref(false)

// ── Ressource ↔ publications (section G) ────────────────────────────────
const assetHistory = ref(null)

// ── Confirmation de publication (section F) ─────────────────────────────
const confirming = ref(null)
const confirmForm = ref({ published_url: '', platform_account: '' })
const publicationForm = ref({ platform: 'linkedin', title: '', body: '', hashtags: '', marketing_asset_id: '' })

async function loadCompanies() {
  try {
    // Liste des entreprises clientes : endpoint plateforme existant
    // `GET /platform/companies` (`companies.view`), même contrat que l'écran
    // Entreprises — aucun endpoint inventé pour l'occasion.
    const { data } = await api.get('/platform/companies', { params: { per_page: 100 } })
    const rows = data?.data || []
    companies.value = rows.map((c) => ({ id: c.id, name: c.name }))
  } catch (error) {
    loadError.value = marketingOsErrorMessage(error, 'Impossible de charger la liste des entreprises.')
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
    loadError.value = marketingOsErrorMessage(error, 'Chargement impossible.')
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
    actionError.value = marketingOsErrorMessage(error, 'Filtre impossible.')
  }
}

async function submitIdea(payload) {
  actionMessage.value = ''
  actionError.value = ''
  try {
    await createContentIdea(companyId.value, payload)
    actionMessage.value = 'Idée créée.'
    await loadAll()
  } catch (error) {
    actionError.value = marketingOsErrorMessage(error, 'Création impossible.')
  }
}

async function promote(idea) {
  actionMessage.value = ''
  actionError.value = ''
  try {
    const { data } = await promoteContentIdea(companyId.value, idea.id)
    const created = data?.meta?.production_task_created === true
    actionMessage.value = created
      ? 'Idée passée en production : la tâche a été créée avec les informations déjà saisies.'
      : "Idée passée en production, mais la tâche n'a pas pu être créée — l'idée conserve toutes ses informations."
    await loadAll()
  } catch (error) {
    actionError.value = marketingOsErrorMessage(error, 'Passage en production impossible.')
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
        tags: assetForm.value.tags ? assetForm.value.tags.split(',').map((t) => t.trim()).filter(Boolean) : [],
      },
    })
    actionMessage.value = 'Ressource ajoutée à la médiathèque.'
    showAssetForm.value = false
    assetForm.value = { name: '', kind: 'video', description: '', locale: 'fr', sector: '', product: '', tags: '' }
    assetFile.value = null
    await loadAll()
  } catch (error) {
    actionError.value = marketingOsErrorMessage(error, 'Ajout impossible.')
  }
}

async function showHistory(asset) {
  actionError.value = ''
  try {
    const { data } = await fetchAssetPublications(companyId.value, asset.id)
    assetHistory.value = data?.data || null
  } catch (error) {
    actionError.value = marketingOsErrorMessage(error, 'Historique indisponible.')
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
    const mode = data?.data?.mode
    actionMessage.value =
      mode === 'direct'
        ? 'Publication préparée en mode direct (API) : un connecteur est configuré.'
        : 'Publication préparée en mode assisté : préparez, copiez et ouvrez la plateforme, puis confirmez la mise en ligne avec son URL réelle.'
    await loadAll()
  } catch (error) {
    actionError.value = marketingOsErrorMessage(error, 'Préparation impossible.')
  }
}

function startConfirm(publication) {
  confirming.value = publication
  confirmForm.value = { published_url: publication.published_url || '', platform_account: publication.platform_account || '' }
}

async function submitConfirm() {
  actionMessage.value = ''
  actionError.value = ''
  try {
    await confirmPublicationPublished(companyId.value, confirming.value.id, {
      publishedUrl: confirmForm.value.published_url,
      platformAccount: confirmForm.value.platform_account || null,
    })
    actionMessage.value = 'Publication confirmée : URL et date réelles enregistrées.'
    confirming.value = null
    await loadAll()
  } catch (error) {
    actionError.value = marketingOsErrorMessage(
      error,
      "Confirmation refusée : l'URL réelle de la publication est obligatoire.",
    )
  }
}

function formatDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString()
}

watch(companyId, loadAll)
onMounted(loadCompanies)
</script>

<template>
  <div class="space-y-6">
    <header class="space-y-1">
      <h1 class="text-xl font-semibold text-gray-900">Marketing OS</h1>
      <p class="text-sm text-gray-600">
        Centre de commande marketing : idées, stratégie, production, médiathèque,
        publications et réutilisation des contenus.
      </p>
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
        {{ tab.label }}
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
          <span class="font-medium text-gray-700">Recherche</span>
          <input v-model="assetFilters.search" placeholder="publicité restaurant…" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">Type</span>
          <select v-model="assetFilters.kind" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">Tous</option>
            <option v-for="(label, value) in ASSET_KIND_LABELS" :key="value" :value="value">{{ label }}</option>
          </select>
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">Langue</span>
          <input v-model="assetFilters.locale" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">Secteur</span>
          <input v-model="assetFilters.sector" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">Produit</span>
          <input v-model="assetFilters.product" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">Statut de validation</span>
          <select v-model="assetFilters.validation_status" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">Tous</option>
            <option v-for="(label, value) in VALIDATION_STATUS_LABELS" :key="value" :value="value">{{ label }}</option>
          </select>
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">Plateforme</span>
          <input v-model="assetFilters.platform" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">Tag</span>
          <input v-model="assetFilters.tag" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">Ajouté après</span>
          <input v-model="assetFilters.from" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">Ajouté avant</span>
          <input v-model="assetFilters.to" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <div class="flex items-end gap-2">
          <button type="button" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white" @click="reloadAssets">
            Filtrer
          </button>
          <button v-if="canManage" type="button" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm" @click="showAssetForm = !showAssetForm">
            Ajouter
          </button>
        </div>
      </div>

      <form
        v-if="showAssetForm"
        class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-3"
        @submit.prevent="submitAsset"
      >
        <label class="text-sm">
          <span class="font-medium text-gray-700">Nom *</span>
          <input v-model="assetForm.name" required maxlength="200" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">Type</span>
          <select v-model="assetForm.kind" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option v-for="(label, value) in ASSET_KIND_LABELS" :key="value" :value="value">{{ label }}</option>
          </select>
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">Fichier</span>
          <input type="file" class="mt-1 block w-full text-sm" @change="assetFile = $event.target.files[0] || null" />
          <span class="mt-1 block text-xs text-gray-500">
            Optionnel pour un script, un prompt ou une légende ; obligatoire pour une vidéo, une image, un logo, un audio ou un document.
          </span>
        </label>
        <label class="text-sm sm:col-span-3">
          <span class="font-medium text-gray-700">Description</span>
          <textarea v-model="assetForm.description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">Langue</span>
          <input v-model="assetForm.locale" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">Secteur</span>
          <input v-model="assetForm.sector" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">Produit</span>
          <input v-model="assetForm.product" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm sm:col-span-2">
          <span class="font-medium text-gray-700">Tags (séparés par des virgules)</span>
          <input v-model="assetForm.tags" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <div class="flex items-end">
          <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Enregistrer</button>
        </div>
      </form>

      <p v-if="!assets.length" class="rounded-lg border border-dashed border-gray-300 p-6 text-sm text-gray-500">
        Aucune ressource ne correspond à ces filtres.
      </p>
      <div v-else class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
            <tr>
              <th class="px-3 py-2">Nom</th>
              <th class="px-3 py-2">Type</th>
              <th class="px-3 py-2">Version</th>
              <th class="px-3 py-2">Langue</th>
              <th class="px-3 py-2">Secteur</th>
              <th class="px-3 py-2">Validation</th>
              <th class="px-3 py-2">Fichier</th>
              <th class="px-3 py-2">Ajoutée</th>
              <th class="px-3 py-2 text-right">Publications</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="asset in assets" :key="asset.id">
              <td class="px-3 py-2 font-medium text-gray-900">{{ asset.name }}</td>
              <td class="px-3 py-2 text-gray-600">{{ labelFrom(ASSET_KIND_LABELS, asset.kind) }}</td>
              <td class="px-3 py-2 text-gray-600">v{{ asset.version }}</td>
              <td class="px-3 py-2 text-gray-600">{{ asset.locale }}</td>
              <td class="px-3 py-2 text-gray-600">{{ asset.sector || '—' }}</td>
              <td class="px-3 py-2">
                <MarketingOsStatusBadge
                  :label="labelFrom(VALIDATION_STATUS_LABELS, asset.validation_status)"
                  :tone="asset.validation_status === 'approved' ? 'success' : asset.validation_status === 'rejected' ? 'danger' : 'neutral'"
                />
              </td>
              <td class="px-3 py-2 text-gray-600">{{ asset.has_file ? 'Oui' : 'Texte' }}</td>
              <td class="px-3 py-2 text-gray-600">{{ formatDate(asset.created_at) }}</td>
              <td class="px-3 py-2 text-right">
                <button type="button" class="rounded border border-gray-300 px-2 py-1 text-xs" @click="showHistory(asset)">
                  Où publiée ?
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
            « {{ assetHistory.asset.name }} » — {{ assetHistory.published_count }} publication(s) en ligne
          </h3>
          <button type="button" class="text-xs text-gray-500" @click="assetHistory = null">Fermer</button>
        </div>
        <p v-if="!assetHistory.publications.length" class="mt-2 text-sm text-gray-500">
          Cette ressource n'a encore jamais été publiée. Elle peut être réutilisée sans écraser d'historique.
        </p>
        <ul v-else class="mt-3 divide-y divide-gray-100">
          <li v-for="pub in assetHistory.publications" :key="pub.id" class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
            <span class="font-medium text-gray-900">{{ pub.platform }} — {{ pub.title || 'Sans titre' }}</span>
            <span class="flex flex-wrap items-center gap-2">
              <MarketingOsStatusBadge :label="labelFrom(PUBLICATION_STATUS_LABELS, pub.status)" :tone="pub.status === 'published' ? 'success' : pub.status === 'failed' ? 'danger' : 'neutral'" />
              <MarketingOsStatusBadge :label="labelFrom(PUBLICATION_MODE_LABELS, pub.mode)" :tone="pub.mode === 'direct' ? 'info' : 'muted'" />
              <span v-if="pub.platform_account" class="text-xs text-gray-500">compte : {{ pub.platform_account }}</span>
              <span class="text-xs text-gray-500">{{ formatDate(pub.published_at) }}</span>
              <a v-if="pub.published_url" :href="pub.published_url" target="_blank" rel="noopener noreferrer" class="text-xs text-indigo-600 hover:underline">Lien réel</a>
              <span v-if="pub.stats_source" class="text-xs text-gray-400">stats : {{ pub.stats_source }}</span>
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
          <span class="font-medium text-gray-700">Plateforme *</span>
          <input v-model="publicationForm.platform" required maxlength="40" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">Ressource de la médiathèque</span>
          <select v-model="publicationForm.marketing_asset_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="">— Aucune —</option>
            <option v-for="asset in assets" :key="asset.id" :value="asset.id">{{ asset.name }} (v{{ asset.version }})</option>
          </select>
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">Titre</span>
          <input v-model="publicationForm.title" maxlength="200" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm sm:col-span-2">
          <span class="font-medium text-gray-700">Texte adapté à la plateforme</span>
          <textarea v-model="publicationForm.body" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <label class="text-sm">
          <span class="font-medium text-gray-700">Hashtags</span>
          <input v-model="publicationForm.hashtags" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        </label>
        <div class="flex items-end">
          <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Préparer</button>
        </div>
      </form>

      <p v-if="!publications.length" class="rounded-lg border border-dashed border-gray-300 p-6 text-sm text-gray-500">
        Aucune publication pour cette entreprise.
      </p>
      <div v-else class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
            <tr>
              <th class="px-3 py-2">Plateforme</th>
              <th class="px-3 py-2">Titre</th>
              <th class="px-3 py-2">Mode</th>
              <th class="px-3 py-2">Statut</th>
              <th class="px-3 py-2">Planifiée</th>
              <th class="px-3 py-2">Publiée</th>
              <th class="px-3 py-2">Lien réel</th>
              <th class="px-3 py-2">Tentatives</th>
              <th v-if="canManage" class="px-3 py-2 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="publication in publications" :key="publication.id">
              <td class="px-3 py-2 font-medium text-gray-900">{{ publication.platform }}</td>
              <td class="px-3 py-2 text-gray-600">{{ publication.title || '—' }}</td>
              <td class="px-3 py-2">
                <MarketingOsStatusBadge
                  :label="labelFrom(PUBLICATION_MODE_LABELS, publication.mode)"
                  :tone="publication.mode === 'direct' ? 'info' : 'muted'"
                />
              </td>
              <td class="px-3 py-2">
                <MarketingOsStatusBadge
                  :label="labelFrom(PUBLICATION_STATUS_LABELS, publication.status)"
                  :tone="publication.status === 'published' ? 'success' : publication.status === 'failed' ? 'danger' : 'neutral'"
                />
              </td>
              <td class="px-3 py-2 text-gray-600">{{ formatDate(publication.scheduled_at) }}</td>
              <td class="px-3 py-2 text-gray-600">{{ formatDate(publication.published_at) }}</td>
              <td class="px-3 py-2">
                <a v-if="publication.published_url" :href="publication.published_url" target="_blank" rel="noopener noreferrer" class="text-xs text-indigo-600 hover:underline">Ouvrir</a>
                <span v-else class="text-xs text-gray-400">—</span>
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
                  Confirmer la mise en ligne
                </button>
                <span v-else class="text-xs text-emerald-700">En ligne</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Confirmation : URL réelle obligatoire -->
      <div v-if="confirming" class="rounded-lg border border-indigo-200 bg-indigo-50 p-4">
        <h3 class="text-sm font-semibold text-gray-900">
          Confirmer la mise en ligne — {{ confirming.platform }}
        </h3>
        <p class="mt-1 text-xs text-gray-600">
          Ouvrir la plateforme ne publie rien. Renseignez l'URL réelle de la publication
          après l'avoir effectivement mise en ligne.
        </p>
        <form class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3" @submit.prevent="submitConfirm">
          <label class="text-sm sm:col-span-2">
            <span class="font-medium text-gray-700">URL réelle de la publication *</span>
            <input v-model="confirmForm.published_url" type="url" required class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
          </label>
          <label class="text-sm">
            <span class="font-medium text-gray-700">Compte utilisé</span>
            <input v-model="confirmForm.platform_account" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
          </label>
          <div class="flex items-end gap-2 sm:col-span-3">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Confirmer</button>
            <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm" @click="confirming = null">Annuler</button>
          </div>
        </form>
      </div>
    </section>

    <p v-if="!companyId" class="rounded-lg border border-dashed border-gray-300 p-6 text-sm text-gray-500">
      Sélectionnez une entreprise cliente pour ouvrir son Marketing OS.
    </p>
  </div>
</template>
