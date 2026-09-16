# Plan — #7555 / #7556

## #7555 — rôles des collaborateurs (web client)

1. `shared/i18n/locales/{fr,en,ar,tr}.json` — namespace `teamRoles.*` (libellés de rôle,
   statuts d'invitation, messages d'erreur API) ; régénération des catalogues web par
   `node shared/i18n/sync/sync-web.js` (jamais édités à la main).
2. `src/lib/i18n/team-roles.ts` — options de rôle, libellés, parsing `manager:<type>`,
   mapping des codes d'erreur API, repli FR.
3. `src/app/(dashboard)/settings/team/page.tsx` — la page (liste, invitation, changement de
   rôle, renvoi, archivage, états, pagination).
4. `src/app/(dashboard)/employees/page.tsx` — sélecteur de rôle au formulaire + affichage
   du rôle réel (fin du `role: 'employee'` en dur).
5. `src/app/(dashboard)/layout.tsx` — 1 entrée de menu (modification minimale, 6 lignes) :
   un autre agent modifie ce fichier sur la branche du lot BC-01, la fusion doit rester
   triviale.
6. Tests : `settings/team/__tests__/team-page.test.tsx`, `employees/__tests__/employees-page.test.tsx`.

## #7556 — menu mobile (web client)

7. `src/app/(dashboard)/layout.tsx` — tiroir unique : rail métier + modules entreprise +
   section Compte ; hook `usePanelDismiss` (Échap + verrou de défilement compté) ; voile
   commun ; `max-h-[70vh] overflow-y-auto` sur les 5 panneaux ; un seul panneau ouvert.
8. `src/lib/i18n.ts` — 2 clés de shell (CopyTree) × 4 locales, le reste réutilisant
   l'existant.
9. `e2e/dashboard-mobile-drawer.spec.ts` — nouveaux contrats (fermeture, bornes, hamburger
   unique, liens de compte) ; `e2e/dashboard-mobile-nav.spec.ts` conservé tel quel.

## Vérification

- `npx tsc --noEmit`, `npx eslint src --max-warnings 0` (+ `e2e`), `npm run check:mojibake`.
- `npx jest` (suite complète) et les specs du parcours rôles.
- `npx playwright test e2e/dashboard-mobile-nav.spec.ts e2e/dashboard-mobile-drawer.spec.ts`
  (5 navigateurs).
- `node dev-hub/tools/check-i18n-diff.js origin/main HEAD`, `check-i18n-catalog-parity.sh`,
  `sync-web.js` (idempotent) puis `validate.js`.
