import api from '@/services/api'

/**
 * Client API des médias de vitrine (BC-27 SHOWCASE, V-MEDIA #6872).
 *
 * Endpoints réels `/showcase/media` (privé, multipart) et service public
 * `/public/vitrine/{slug}/media/{id}` (vitrine publiée uniquement) —
 * aucun mock, aucun chemin absolu fabriqué côté client : l'URL publique est
 * toujours celle renvoyée par le serveur (`media.url`).
 */

/** GET /showcase/media — liste des médias (filtres optionnels). */
export function listShowcaseMedia(params = {}) {
  return api.get('/showcase/media', { params })
}

/**
 * POST /showcase/media — upload multipart.
 *
 * @param {{ file: File, kind: 'logo'|'section', sectionId?: number|null }} payload
 */
export function uploadShowcaseMedia({ file, kind, sectionId = null }) {
  const form = new FormData()
  form.append('file', file)
  form.append('kind', kind)
  if (sectionId !== null && sectionId !== undefined) {
    form.append('section_id', String(sectionId))
  }
  return api.post('/showcase/media', form)
}

/** DELETE /showcase/media/{id} — suppression par id stable. */
export function deleteShowcaseMedia(id) {
  return api.delete(`/showcase/media/${id}`)
}

/**
 * Résout l'URL publique d'un média renvoyé par l'API.
 *
 * `media.url` est un chemin relatif au service API (`/public/vitrine/...`) ;
 * on le préfixe par la base axios (déjà `/api/v1`). Le jeton d'aperçu est
 * ajouté pour un brouillon (même contrat que GET /public/vitrine/{slug}).
 */
export function showcaseMediaSrc(media, { token = null } = {}) {
  const url = media?.url
  if (!url) return ''
  const base = (api.defaults?.baseURL || '').replace(/\/$/, '')
  const absolute = url.startsWith('http') ? url : `${base}${url}`
  if (!token) return absolute
  return `${absolute}${absolute.includes('?') ? '&' : '?'}token=${encodeURIComponent(token)}`
}

/**
 * Extrait un message d'erreur lisible d'une réponse API (422 : première
 * erreur de champ ; sinon `message` serveur).
 */
export function showcaseMediaErrorMessage(error, fallback = '') {
  const data = error?.response?.data
  const firstError = data?.errors ? Object.values(data.errors).flat()[0] : null
  return firstFieldError(firstError) || data?.message || fallback
}

function firstFieldError(value) {
  if (Array.isArray(value)) return value[0] || null
  return typeof value === 'string' ? value : null
}
