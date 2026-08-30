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
