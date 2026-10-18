import api from '@/services/api'

/**
 * Client API du suivi plateforme de la verticale Trade (BC-36 TRADE,
 * TRADE-010 #8485).
 *
 * Endpoints RÉELS `/platform/trade/*` (console superadmin existante) — aucune
 * donnée mock. Ces endpoints ne renvoient que des COMPTEURS et l'état des
 * adaptateurs : jamais le contenu commercial privé d'un tenant.
 */

/** GET /platform/trade/overview — compteurs agrégés de la verticale. */
export function getTradeOverview(params = {}) {
  return api.get('/platform/trade/overview', { params })
}

/** GET /platform/trade/integrations — état réel des adaptateurs externes. */
export function getTradeIntegrations() {
  return api.get('/platform/trade/integrations')
}

/** Extrait l'enveloppe `{data: {...}}` de Laravel. */
export function tradePayload(response) {
  const payload = response?.data ?? response ?? {}
  return payload.data ?? payload
}

/**
 * Vrai si l'intégration est réellement opérationnelle.
 *
 * Une intégration non configurée ne doit JAMAIS être présentée comme active :
 * c'est la règle produit portée par `TradeIntegrationRegistry` côté serveur.
 */
export function isIntegrationOperational(integration) {
  return integration?.configured === true && integration?.available === true
}
