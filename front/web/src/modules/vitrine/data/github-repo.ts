/**
 * #8067 — signal open-source du héro : chiffres du dépôt public, STATIQUES au
 * build (pas d'appel client à l'API GitHub — exigence de l'issue, pattern
 * Frappe HR). Rafraîchir manuellement (ou via script CI) à chaque campagne :
 *   curl -s https://api.github.com/repos/kitokoh/leopardo-hr | jq '.stargazers_count,.forks_count'
 * Dernière mise à jour : 2026-09-23.
 */
export const GITHUB_REPO_URL = 'https://github.com/kitokoh/leopardo-hr'
export const GITHUB_REPO_SLUG = 'kitokoh/leopardo-hr'
export const GITHUB_STARS = 13
export const GITHUB_FORKS = 4
export const GITHUB_LICENSE = 'MIT'
