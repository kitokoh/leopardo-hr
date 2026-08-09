/**
 * Stockage du token d'authentification admin.
 *
 * Source unique de vérité pour la clé ET le mécanisme de stockage du token.
 *
 * ⚠️ Histoire (bug #1575) : le token a été migré de localStorage vers
 * sessionStorage par la PR #1299 (durcissement sécurité — pas de token
 * persistant exposé au XSS), mais les lecteurs secondaires (intercepteur
 * axios, store realtime) lisaient encore localStorage : le fallback polling
 * de notifications ne démarrait donc jamais. Tout accès au token doit passer
 * par ce module.
 */
const ADMIN_TOKEN_STORAGE_KEY = 'admin_token'

export function getAuthToken() {
  return sessionStorage.getItem(ADMIN_TOKEN_STORAGE_KEY)
}

export function setAuthToken(token) {
  sessionStorage.setItem(ADMIN_TOKEN_STORAGE_KEY, token)
}

export function removeAuthToken() {
  sessionStorage.removeItem(ADMIN_TOKEN_STORAGE_KEY)
}
