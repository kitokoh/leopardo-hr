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
 *
 * 🔒 #7695 (audit sécurité) : le bearer SUPER-ADMIN ne vit plus en
 * sessionStorage pendant la vie de la page — il est tenu dans une variable
 * de module (mémoire volatile), hors de portée d'un `sessionStorage.getItem`
 * injecté par XSS. Le SPA étant statique (Cloudflare Pages, pas de BFF pour
 * poser un cookie httpOnly — suite complète trackée côté #1299), la
 * continuité de session au rechargement est assurée par un hand-off
 * éphémère :
 *   - au boot du module, le token éventuellement présent en sessionStorage
 *     est CONSOMMÉ (lu puis supprimé immédiatement) ;
 *   - il n'est réécrit en sessionStorage qu'à `pagehide` (rechargement /
 *     navigation de la page), puis re-consommé au retour (`pageshow`,
 *     couvre aussi le bfcache).
 * Fenêtre d'exposition résiduelle : la (dé)charge de la page uniquement —
 * pendant toute la session active, `sessionStorage` ne contient pas le
 * token. Combiné à la CSP durcie (#7695 : plus d'`unsafe-inline` dans
 * script-src, connect-src explicite), l'exfiltration par XSS est
 * significativement plus coûteuse.
 *
 * Les tests E2E restent compatibles : ils sèment `admin_token` via
 * `addInitScript` (avant le boot de l'app), le module le consomme au
 * chargement comme un rechargement normal.
 */
const ADMIN_TOKEN_STORAGE_KEY = 'admin_token'

// Mémoire volatile — jamais lisible via les APIs de stockage du DOM.
let inMemoryToken = null

/** Lit puis SUPPRIME le token du sessionStorage (hand-off one-shot). */
function consumePersistedToken() {
  try {
    const persisted = sessionStorage.getItem(ADMIN_TOKEN_STORAGE_KEY)
    if (persisted !== null) {
      sessionStorage.removeItem(ADMIN_TOKEN_STORAGE_KEY)
      inMemoryToken = persisted
    }
  } catch {
    // sessionStorage indisponible (sandbox/SSR) : mémoire volatile seule.
  }
}

/** Réécrit le token en sessionStorage juste avant décharge de la page. */
function persistTokenForReload() {
  try {
    if (inMemoryToken !== null) {
      sessionStorage.setItem(ADMIN_TOKEN_STORAGE_KEY, inMemoryToken)
    }
  } catch {
    // sessionStorage indisponible : l'admin devra se reconnecter au reload.
  }
}

consumePersistedToken()

if (typeof window !== 'undefined') {
  // `pagehide` est plus fiable que `beforeunload` (mobile, bfcache) ; le
  // second reste en filet pour les navigateurs qui ne tirent que lui.
  window.addEventListener('pagehide', persistTokenForReload)
  window.addEventListener('beforeunload', persistTokenForReload)
  // Retour depuis le bfcache : la page revit sans re-exécuter le module —
  // on re-consomme le token pour refermer la fenêtre d'exposition.
  window.addEventListener('pageshow', consumePersistedToken)
}

export function getAuthToken() {
  return inMemoryToken
}

export function setAuthToken(token) {
  inMemoryToken = token
}

export function removeAuthToken() {
  inMemoryToken = null
  // Défensif : purge tout résidu persisté (ex. logout juste après un boot).
  try {
    sessionStorage.removeItem(ADMIN_TOKEN_STORAGE_KEY)
  } catch {
    // sessionStorage indisponible — rien à purger.
  }
}
