/**
 * Construction de la Content-Security-Policy du build (#7695).
 *
 * La CSP n'est plus figée dans `public/_headers` : les origines backend sont
 * connues au build (VITE_API_URL, VITE_WEBSOCKET_URL) — `connect-src` est
 * donc généré EXPLICITE (fin du wildcard `https:`/`wss:` nu), et
 * `script-src` ne porte plus `'unsafe-inline'` (le bundle Vite n'émet que
 * des scripts externes ; index.html n'a aucun script inline).
 *
 * Module partagé par le plugin de build (vite.config.js) et le test de
 * garde post-build (scripts/check-csp-guard.mjs).
 */

// Doit rester aligné sur le fallback de src/services/api.js (#2659).
export const DEFAULT_API_URL = 'https://gestionemployerbackend.onrender.com/api/v1'

export const CSP_PLACEHOLDER = '__CSP_BUILD_PLACEHOLDER__'

/** Origine (scheme://host[:port]) d'une URL, ou null si invalide/absente. */
function originOf(url) {
  if (!url) return null
  try {
    return new URL(url).origin
  } catch {
    return null
  }
}

/**
 * Construit la valeur du header Content-Security-Policy.
 *
 * @param {object} env - variables d'environnement du build (process.env).
 * @returns {string} valeur du header CSP.
 */
export function buildCsp(env = {}) {
  const apiOrigin = originOf(env.VITE_API_URL) || originOf(DEFAULT_API_URL)
  // Serveur push Socket.IO optionnel (#7303) : sans VITE_WEBSOCKET_URL le
  // client ne tente AUCUNE connexion websocket — pas d'origine à autoriser.
  const wsOrigin = originOf(env.VITE_WEBSOCKET_URL)

  const connectSrc = ["'self'", apiOrigin]
  if (wsOrigin && !connectSrc.includes(wsOrigin)) {
    connectSrc.push(wsOrigin)
  }

  const directives = [
    "default-src 'self'",
    // #7695 : plus d'`unsafe-inline` — Vite n'émet que des scripts externes.
    "script-src 'self'",
    // Styles inline requis par Vue (bindings :style) et vue-toastification.
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
    "font-src 'self' data: https://fonts.gstatic.com",
    "img-src 'self' data: https://ui-avatars.com https://*.tile.openstreetmap.org",
    `connect-src ${connectSrc.join(' ')}`,
    "object-src 'none'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'none'",
  ]

  return directives.join('; ')
}

/**
 * Test de garde (#7695) : lève si la CSP régresse.
 *  - `script-src` ne doit plus contenir `'unsafe-inline'` ;
 *  - `connect-src` ne doit plus contenir de scheme nu (`https:`, `wss:`,
 *    `http:`, `ws:`) ni de wildcard `*`.
 *
 * @param {string} csp - valeur du header Content-Security-Policy.
 */
export function assertCspHardened(csp) {
  if (!csp || typeof csp !== 'string') {
    throw new Error('CSP absente ou invalide (#7695).')
  }
  if (csp.includes(CSP_PLACEHOLDER)) {
    throw new Error(
      `CSP non générée : le placeholder ${CSP_PLACEHOLDER} est encore présent (#7695).`,
    )
  }

  const directive = (name) =>
    csp
      .split(';')
      .map((d) => d.trim())
      .find((d) => d === name || d.startsWith(`${name} `))

  const scriptSrc = directive('script-src')
  if (!scriptSrc) {
    throw new Error('CSP sans directive script-src (#7695).')
  }
  if (scriptSrc.includes("'unsafe-inline'")) {
    throw new Error(`script-src contient 'unsafe-inline' — interdit (#7695) : ${scriptSrc}`)
  }

  const connectSrc = directive('connect-src')
  if (!connectSrc) {
    throw new Error('CSP sans directive connect-src (#7695).')
  }
  const sources = connectSrc.split(/\s+/).slice(1)
  const bare = sources.filter((s) => /^(https?|wss?):$/.test(s) || s === '*')
  if (bare.length > 0) {
    throw new Error(
      `connect-src contient des sources wildcard (${bare.join(', ')}) — interdit (#7695) : ${connectSrc}`,
    )
  }
}
