/**
 * Résolution de l'URL de base de l'API pour le dashboard admin (#8707).
 *
 * LE PROBLÈME QUI A COÛTÉ UN MOIS DE FRONT FIGÉ
 * --------------------------------------------
 * Avant #8707, `services/api.js` lisait `import.meta.env.VITE_API_URL`, une
 * valeur INLINÉE PAR VITE AU BUILD. Le workflow de déploiement y injectait
 * `vars.DEV_API_BASE_URL` — c'est-à-dire l'URL BRUTE du service Render de la
 * stack active. Résultat : l'URL d'un fournisseur était gravée dans le bundle
 * JavaScript.
 *
 * Le 2026-09-20, Render a suspendu la stack dev A
 * (`gestionemployerbackend.onrender.com`, `x-render-routing: suspend-by-user`).
 * Le bundle admin a continué de l'appeler jusqu'au redéploiement suivant. Et
 * chaque bascule de stack (A → B → C) imposait un rebuild complet du front.
 *
 * LA RÈGLE MAINTENANT : UNE SEULE URL PUBLIQUE, STABLE
 * ----------------------------------------------------
 * Le dashboard admin ne connaît plus AUCUNE URL de fournisseur. Il appelle le
 * **proxy same-origin du front web**
 * (`front/web/src/app/api/v1/[...path]/route.ts`), dont la cible est lue au
 * RUNTIME (`API_PROXY_TARGET` > `BACKEND_API_URL` > `NEXT_PUBLIC_API_URL`).
 *
 *   admin (Cloudflare Pages)  ──►  proxy web (Vercel)  ──►  API Render active
 *                                  ^ cible lue au runtime
 *
 * Le proxy web est un domaine STABLE (jamais re-provisionné lors d'une
 * bascule). Basculer de stack = changer une variable d'environnement Vercel et
 * redéployer le web ; le dashboard admin, lui, ne bouge plus jamais.
 *
 * Résolution, par ordre de priorité :
 *   1. `VITE_API_PROXY_BASE_URL` — URL du proxy stable. Voie normale, et la
 *      seule considérée comme sûre en production.
 *   2. `VITE_API_URL` — appel direct à un fournisseur. Conservé pour les
 *      postes de dev et les environnements sans front web, mais refusé en
 *      production sauf dérogation explicite.
 *   3. Aucune des deux en production → erreur explicite au chargement.
 *   4. Aucune des deux en dev/test → backend local, avec `console.warn`.
 */

/** Repli dev/test uniquement — `php artisan serve` sur le poste (jamais partagé). */
export const DEV_FALLBACK_API_BASE_URL = 'http://localhost:8000/api/v1'

/**
 * @typedef {{ baseURL: string, mode: 'proxy'|'direct'|'dev-fallback' }} ResolvedApiBaseUrl
 */

/**
 * @returns {ResolvedApiBaseUrl}
 */
export function resolveApiBaseUrl() {
  const proxy = import.meta.env.VITE_API_PROXY_BASE_URL
  if (proxy) {
    // Une URL de proxy est stable PAR CONSTRUCTION : c'est elle qui décide de
    // la cible, au runtime, côté serveur. On ne l'analyse donc pas comme un
    // appel direct.
    return { baseURL: proxy, mode: 'proxy' }
  }

  const direct = import.meta.env.VITE_API_URL
  if (direct) {
    return { baseURL: direct, mode: 'direct' }
  }

  if (import.meta.env.PROD) {
    // Diagnostic technique destiné au développeur, PAS de la copie d'UI :
    // volontairement hors catalogues i18n, préfixé `[admin-dashboard]` comme
    // le reconnaît la garde PA2-I18N-014 (cf. #7842).
    throw new Error(
      "[admin-dashboard] Aucune URL d'API configurée dans ce build de production. " +
        'Définissez VITE_API_PROXY_BASE_URL (recommandé : URL du proxy du front web, ' +
        'ex. https://<web>/api/v1) pour que la cible reste lue au runtime, ou ' +
        "VITE_API_URL pour un appel direct. Le repli silencieux vers l'API dev " +
        'onrender.com a été retiré (#7842).',
    )
  }

  console.warn(
    '[admin-dashboard] Ni VITE_API_PROXY_BASE_URL ni VITE_API_URL ne sont définies — ' +
      `repli sur le backend local ${DEV_FALLBACK_API_BASE_URL} (dev/test uniquement, ` +
      'interdit en production, #7842/#7963/#8707).',
  )
  return { baseURL: DEV_FALLBACK_API_BASE_URL, mode: 'dev-fallback' }
}

/**
 * Garde-fou de production : refuser un build qui appelle un fournisseur en
 * direct. C'est exactement la régression qui a figé le front un mois durant —
 * on échoue bruyamment plutôt que de la laisser repasser.
 *
 * Dérogation explicite : `VITE_ALLOW_DIRECT_API_IN_PROD=true`.
 */
export function assertProductionInvariant(resolved) {
  if (!import.meta.env.PROD) return
  if (resolved.mode !== 'direct') return
  if (import.meta.env.VITE_ALLOW_DIRECT_API_IN_PROD === 'true') return

  throw new Error(
    "[admin-dashboard] Build de production avec un appel DIRECT à l'API " +
      `(${resolved.baseURL}). Cela fige l'URL d'un fournisseur dans le bundle : ` +
      'une bascule de stack exigera un rebuild. Utilisez VITE_API_PROXY_BASE_URL ' +
      "(URL du proxy du front web) pour que la cible reste lue au runtime, ou " +
      "posez VITE_ALLOW_DIRECT_API_IN_PROD=true pour l'assumer explicitement (#8707).",
  )
}
