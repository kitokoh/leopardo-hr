import api from '@/services/api'

/**
 * Client API de la verticale TravelAgency (BC-24 TRAVEL).
 *
 * Tous les appels passent par l'instance axios partagée (`/api/v1`),
 * endpoints réels `/travel/*` — aucun mock (convention TRAVEL-041).
 * L'enveloppe Laravel `{data, meta}` est normalisée par les helpers
 * `travelList` / `travelItem`.
 */

/** Extrait la liste paginée Laravel (data.items ou data). */
export function travelList(response) {
  const payload = response?.data ?? response ?? {}
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  if (payload.data && Array.isArray(payload.data.data)) return payload.data.data
  if (payload.data && Array.isArray(payload.data.items)) return payload.data.items
  return []
}

/** Extrait la méta de pagination Laravel, si présente. */
export function travelMeta(response) {
  const payload = response?.data ?? response ?? {}
  const inner = payload.data && !Array.isArray(payload.data) ? payload.data : payload
  return inner?.meta ?? inner?.pagination ?? {}
}

/** Extrait un objet unique (enveloppe {data: {...}}). */
export function travelItem(response) {
  const payload = response?.data ?? response ?? {}
  return payload.data ?? payload
}

/** GET /travel/{resource} — liste (pagination + filtres). */
export function listTravel(resource, params = {}) {
  return api.get(`/travel/${resource}`, { params })
}

/** GET /travel/{resource}/{id}. */
export function getTravel(resource, id) {
  return api.get(`/travel/${resource}/${id}`)
}

/** POST /travel/{resource}. */
export function createTravel(resource, payload) {
  return api.post(`/travel/${resource}`, payload)
}

/** PUT /travel/{resource}/{id}. */
export function updateTravel(resource, id, payload) {
  return api.put(`/travel/${resource}/${id}`, payload)
}

/** DELETE /travel/{resource}/{id}. */
export function deleteTravel(resource, id) {
  return api.delete(`/travel/${resource}/${id}`)
}

/** POST /travel/{resource}/{id}/{action} (publish, cancel, confirm, ...). */
export function travelAction(resource, id, action, payload = {}) {
  return api.post(`/travel/${resource}/${id}/${action}`, payload)
}

/** GET /travel/{resource}/{id}/{action} (ex. billet PDF → URL signée). */
export function travelGetAction(resource, id, action, params = {}) {
  return api.get(`/travel/${resource}/${id}/${action}`, { params })
}

/** POST sous-ressource : /travel/{resource}/{id}/{sub}. */
export function createTravelSub(resource, id, sub, payload = {}) {
  return api.post(`/travel/${resource}/${id}/${sub}`, payload)
}

/** GET sous-ressource : /travel/{resource}/{id}/{sub}. */
export function listTravelSub(resource, id, sub, params = {}) {
  return api.get(`/travel/${resource}/${id}/${sub}`, { params })
}

/** PUT sous-ressource : /travel/{resource}/{id}/{sub}/{subId}. */
export function updateTravelSub(resource, id, sub, subId, payload) {
  return api.put(`/travel/${resource}/${id}/${sub}/${subId}`, payload)
}

/** DELETE sous-ressource : /travel/{resource}/{id}/{sub}/{subId}. */
export function deleteTravelSub(resource, id, sub, subId) {
  return api.delete(`/travel/${resource}/${id}/${sub}/${subId}`)
}

/** Formate un montant en unités mineures (ex. 1500 → « 15,00 »). */
export function formatMinor(amount, currency = 'XOF', locale = 'fr') {
  const minor = Number(amount ?? 0)
  const value = minor / 100
  try {
    return `${new Intl.NumberFormat(locale === 'en' ? 'en-GB' : 'fr-FR', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(value)} ${currency}`
  } catch {
    return `${value.toFixed(2)} ${currency}`
  }
}

// ── Contenu & monétisation (TRAVEL-911/#6416) ──────────────────────────────
// Quiz, annonces payantes (référentiels + cycle de vie), sites touristiques.
// Les endpoints existent côté backend (TRAVEL-904..909) — aucun mock.

/** POST /travel/quizzes/{id}/questions — ajout d'une question. */
export function createQuizQuestion(quizId, payload) {
  return api.post(`/travel/quizzes/${quizId}/questions`, payload)
}

/** PUT /travel/quizzes/{quizId}/questions/{questionId}. */
export function updateQuizQuestion(quizId, questionId, payload) {
  return api.put(`/travel/quizzes/${quizId}/questions/${questionId}`, payload)
}

/** DELETE /travel/quizzes/{quizId}/questions/{questionId}. */
export function deleteQuizQuestion(quizId, questionId) {
  return api.delete(`/travel/quizzes/${quizId}/questions/${questionId}`)
}

/** POST /travel/quizzes/{id}/participate — participation (score serveur). */
export function participateQuiz(quizId, answers) {
  return api.post(`/travel/quizzes/${quizId}/participate`, { answers })
}

/** GET /travel/quizzes/{id}/participations — résultats (travel.manage). */
export function quizParticipations(quizId) {
  return api.get(`/travel/quizzes/${quizId}/participations`)
}

/** POST /travel/adverts/{id}/pay — paiement d'une annonce. */
export function payAdvert(id, payload) {
  return api.post(`/travel/adverts/${id}/pay`, payload)
}

/** POST /travel/adverts/{id}/validate — validation/rejet (travel.manage). */
export function validateAdvert(id, payload) {
  return api.post(`/travel/adverts/${id}/validate`, payload)
}

/** POST /travel/adverts/{id}/renew — renouvellement payé. */
export function renewAdvert(id, payload) {
  return api.post(`/travel/adverts/${id}/renew`, payload)
}

/** GET /travel/advert-types|advert-positions|advert-prices (listes nues). */
export function listAdvertCatalog(resource) {
  return api.get(`/travel/${resource}`)
}

/** POST /travel/advert-types|advert-positions (création). */
export function createAdvertCatalog(resource, payload) {
  return api.post(`/travel/${resource}`, payload)
}

/** PUT /travel/advert-types/{id} (mise à jour catalogue). */
export function updateAdvertCatalog(resource, id, payload) {
  return api.put(`/travel/${resource}/${id}`, payload)
}

/** DELETE /travel/advert-types/{id}. */
export function deleteAdvertCatalog(resource, id) {
  return api.delete(`/travel/${resource}/${id}`)
}

/** GET /travel/tourist-sites (filtres city_id/search/status). */
export function listTouristSites(params = {}) {
  return api.get('/travel/tourist-sites', { params })
}

/** POST /travel/contacts/{id}/notify — notification manuelle (TRAVEL-912). */
export function notifyTravelContact(id, payload) {
  return api.post(`/travel/contacts/${id}/notify`, payload)
}
