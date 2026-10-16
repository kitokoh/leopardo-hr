import api from '@/services/api'

/**
 * Client API du **Marketing OS** (espace Super Admin).
 *
 * Backend : `PlatformMarketingOsController` — routes du cockpit SPA
 * `/admin/companies/{company}/marketing-os/*` (alias sous le groupe
 * `prefix('admin')` de `routes/api.php`, même contrat que les autres écrans du
 * `front/admin-dashboard`), protégées par
 * `platform.permission:marketing_os.view|manage` (cf. issue #8528).
 *
 * Règles de ce client :
 *  - aucun mock, aucun chemin absolu fabriqué côté client ;
 *  - le `mode` de publication n'est jamais forcé ici : il est **calculé par
 *    le serveur** selon la présence réelle d'un connecteur, et le serveur le
 *    renvoie (`meta.direct_available`). L'écran ne fait donc que l'afficher ;
 *  - `published_url` est transmis tel quel : sans lui, le serveur refuse de
 *    marquer une publication comme publiée (garde structurelle).
 */

const base = (companyId) => `/admin/companies/${companyId}/marketing-os`

// ── A. Tableau de bord ──────────────────────────────────────────────────
export function fetchMarketingOsOverview(companyId) {
  return api.get(`${base(companyId)}/overview`)
}

// ── B. Stratégie : campagnes et idées ───────────────────────────────────
export function listMarketingCampaigns(companyId, params = {}) {
  return api.get(`${base(companyId)}/campaigns`, { params })
}

export function createMarketingCampaign(companyId, payload) {
  return api.post(`${base(companyId)}/campaigns`, payload)
}

export function listContentIdeas(companyId, params = {}) {
  return api.get(`${base(companyId)}/ideas`, { params })
}

export function createContentIdea(companyId, payload) {
  return api.post(`${base(companyId)}/ideas`, payload)
}

export function updateContentIdea(companyId, ideaId, payload) {
  return api.patch(`${base(companyId)}/ideas/${ideaId}`, payload)
}

/** Transforme une idée en tâche de production (sans perdre la saisie). */
export function promoteContentIdea(companyId, ideaId) {
  return api.post(`${base(companyId)}/ideas/${ideaId}/promote`)
}

// ── C. Médiathèque ──────────────────────────────────────────────────────
export function listMarketingAssets(companyId, params = {}) {
  return api.get(`${base(companyId)}/assets`, { params })
}

/**
 * POST /assets — multipart.
 *
 * Un contenu TEXTE (script, prompt, légende) se crée sans fichier : le type
 * décide (`kind`), pas le client. Les types binaires sans fichier sont
 * refusés par le serveur avec un message explicite.
 */
export function uploadMarketingAsset(companyId, { file = null, payload = {} }) {
  const form = new FormData()
  Object.entries(payload).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '') return
    if (Array.isArray(value)) {
      value.forEach((entry, index) => form.append(`${key}[${index}]`, String(entry)))
      return
    }
    if (typeof value === 'object') {
      form.append(key, JSON.stringify(value))
      return
    }
    form.append(key, String(value))
  })
  if (file) form.append('file', file)
  return api.post(`${base(companyId)}/assets`, form)
}

/** « Où cette ressource a-t-elle été publiée ? » (section G du brief). */
export function fetchAssetPublications(companyId, assetId) {
  return api.get(`${base(companyId)}/assets/${assetId}/publications`)
}

// ── E/F. Publications ───────────────────────────────────────────────────
export function listMarketingPublications(companyId, params = {}) {
  return api.get(`${base(companyId)}/publications`, { params })
}

export function createMarketingPublication(companyId, payload) {
  return api.post(`${base(companyId)}/publications`, payload)
}

/**
 * Transition de statut. `confirmPublished` exige l'URL réelle de la
 * publication : le serveur répond 422 sinon.
 */
export function updatePublicationStatus(companyId, publicationId, payload) {
  return api.patch(`${base(companyId)}/publications/${publicationId}/status`, payload)
}

export function confirmPublicationPublished(companyId, publicationId, { publishedUrl, platformAccount = null, stats = null }) {
  return updatePublicationStatus(companyId, publicationId, {
    action: 'confirm_published',
    published_url: publishedUrl,
    platform_account: platformAccount,
    stats,
  })
}

/**
 * Extrait un message d'erreur lisible d'une réponse API
 * (422 : première erreur de champ ; sinon `message` serveur).
 */
export function marketingOsErrorMessage(error, fallback = '') {
  const data = error?.response?.data
  const firstError = data?.errors ? Object.values(data.errors).flat()[0] : null
  if (Array.isArray(firstError) && firstError.length) return firstError[0]
  if (typeof firstError === 'string') return firstError
  return data?.message || fallback
}

// ── Libellés (miroir d'affichage des énumérations serveur) ──────────────
// Garde PA2-I18N-014 : AUCUN texte utilisateur en dur. Ces tables ne portent
// donc pas de libellés mais des CLÉS de traduction ; les composants font
// `$t(labelFrom(MAP, value))`. Les valeurs proviennent des énumérations PHP.
/** @param {string} group @param {string} value */
const key = (group, value) => `marketingOs.${group}.${value}`

export const CONTENT_IDEA_STATUS_LABELS = {
  idea: key('status', 'idea'),
  briefing: key('status', 'briefing'),
  in_production: key('status', 'inProduction'),
  in_review: key('status', 'inReview'),
  approved: key('status', 'approved'),
  scheduled: key('status', 'scheduled'),
  published: key('status', 'published'),
  cancelled: key('status', 'cancelled'),
}

export const PUBLICATION_STATUS_LABELS = {
  idea: key('status', 'idea'),
  in_preparation: key('pubStatus', 'inPreparation'),
  in_review: key('status', 'inReview'),
  approved: key('status', 'approved'),
  scheduled: key('status', 'scheduled'),
  published: key('status', 'published'),
  failed: key('pubStatus', 'failed'),
  cancelled: key('status', 'cancelled'),
}

export const PUBLICATION_MODE_LABELS = {
  direct: key('mode', 'direct'),
  assisted: key('mode', 'assisted'),
}

export const VALIDATION_STATUS_LABELS = {
  draft: key('validation', 'draft'),
  pending: key('validation', 'pending'),
  approved: key('validation', 'approved'),
  rejected: key('validation', 'rejected'),
}

export const CONTENT_FORMAT_LABELS = {
  video_short: key('format', 'videoShort'),
  video_long: key('format', 'videoLong'),
  image: key('format', 'image'),
  carousel: key('format', 'carousel'),
  article: key('format', 'article'),
  ad: key('format', 'ad'),
  testimonial: key('format', 'testimonial'),
  other: key('format', 'other'),
}

export const OBJECTIVE_LABELS = {
  notoriete: key('objective', 'awareness'),
  acquisition: key('objective', 'acquisition'),
  conversion: key('objective', 'conversion'),
  fidelisation: key('objective', 'retention'),
  lancement: key('objective', 'launch'),
}

export const ASSET_KIND_LABELS = {
  video: key('assetKind', 'video'),
  image: key('assetKind', 'image'),
  logo: key('assetKind', 'logo'),
  illustration: key('assetKind', 'illustration'),
  script: key('assetKind', 'script'),
  prompt: key('assetKind', 'prompt'),
  copy: key('assetKind', 'copy'),
  document: key('assetKind', 'document'),
  audio: key('assetKind', 'audio'),
  other: key('assetKind', 'other'),
}

export const CAMPAIGN_STATUS_LABELS = {
  draft: key('campaignStatus', 'draft'),
  active: key('campaignStatus', 'active'),
  paused: key('campaignStatus', 'paused'),
  finished: key('campaignStatus', 'finished'),
  cancelled: key('campaignStatus', 'cancelled'),
}

/**
 * Résout une clé de libellé depuis une table ; renvoie la VALEUR brute si elle
 * n'est pas connue (jamais masquée), et la clé de repli i18n sinon.
 */
export function labelFrom(map, value, fallback = key('common', 'dash')) {
  if (value === null || value === undefined || value === '') return fallback
  return map[value] || value
}

/** Clés de repli partagées par les composants (jamais de texte en dur). */
export const EMPTY_LABEL_KEY = key('common', 'dash')
export const UNTITLED_LABEL_KEY = key('common', 'untitled')
