/**
 * Source de vérité unique des préfixes de routes session-protégées (issue #3377).
 *
 * Consommateurs :
 * - `src/middleware.ts` — matcher Edge (redirection login si cookie absent/invalide).
 *   ⚠️ Next.js exige des littéraux statiquement analysables dans `config.matcher` :
 *   le middleware garde sa liste littérale, le test `protected-prefixes.test.ts`
 *   garantit qu'elle ne dérive pas de cette source.
 * - `src/app/robots.ts` — disallow pour tous les bots (y compris Googlebot/Bingbot,
 *   dont le groupe dédié ÉCRASE le groupe `*` dans la spec robots.txt).
 *
 * Toute nouvelle zone protégée ajoutée au middleware DOIT être ajoutée ici —
 * le test de régression casse sinon.
 */
export const PROTECTED_PREFIXES = [
  '/dashboard',
  '/absences',
  '/attendance',
  '/billing',
  '/contracts',
  '/employees',
  '/partner',
  '/payroll',
  '/reports',
  '/training',
  '/settings',
  '/social',
  '/social-marketing',
] as const;

/**
 * Préfixes vitrine dont le middleware normalise `?lang=` en en-tête
 * `x-vitrine-lang` pour les layouts (issue #4004). Source unique distincte de
 * PROTECTED_PREFIXES : ces routes sont PUBLIQUES (aucun gate de session, pas
 * de robots.txt disallow) — seuls les chemins dynamiques (sous-routes) ont
 * besoin du wildcard.
 */
export const VITRINE_LANG_PREFIXES = [
  '/blog',
  '/guides',
  '/case-studies',
  '/checkout',
  // Portail client des documents partagés (issue #5233) : route PUBLIQUE
  // (le token de partage est la credential, pattern CabinetShare #1817) —
  // le middleware normalise `?lang=` / Accept-Language pour un SSR localisé.
  '/documents',
] as const;
