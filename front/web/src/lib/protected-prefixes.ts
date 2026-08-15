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
  '/smart-attendance',
  '/social',
  '/social-marketing',
] as const;
