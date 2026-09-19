# Tasks — espace gérant Travel (épic #7642)

## A1 — #7633 : hub + navigation
- [ ] 1. Remplacer le redirect `(dashboard)/travel/page.tsx` par le hub gérant
      (KPIs `GET /travel/reports/dashboard` typés, états loading/empty/error,
      tuiles network/trips/bookings/reports, lien portail voyageur).
- [ ] 2. `client-features.ts` : entrée `travel` → `/travel`, sous-entrée `travel_portal`
      → `/travel/portal`, `ROUTE_TO_MODULE` des sous-routes ; libellés `i18n.ts`.
- [ ] 3. Clés i18n manquantes dans les 4 locales de `shared/i18n/locales/` + sync miroir.
- [ ] 4. `npm run lint` + `npx tsc --noEmit` verts ; commit `Closes #7633`.

## A2 — #7634 : /travel/network
- [ ] 5. CRUD gares, bureaux, lignes ; arrêts ordonnés par ligne ; référentiels en lecture.
- [ ] 6. Vérifs + commit `Closes #7634`.

## A3 — #7635 : /travel/trips
- [ ] 7. Liste + recherche, création (ligne, horaires, véhicule, transporteur),
      tarifs par classe, publier/annuler, manifeste.
- [ ] 8. Vérifs + commit `Closes #7635`.

## A4 — #7636 : /travel/bookings
- [ ] 9. Liste, détail, confirm/cancel/refund/refund-passenger, émission billets,
      PDF, check-in.
- [ ] 10. Dé-skipper et adapter `front/web/e2e/travel-admin-smoke.spec.ts`.
- [ ] 11. Vérifs + commit `Closes #7636`.

## A5 — #7637 : /travel/reports
- [ ] 12. 4 rapports (sales/occupancy/revenue/cancellations) + période + export async.
- [ ] 13. CHANGELOG racine `## [Unreleased]` (dernier commit du lot).
- [ ] 14. Vérifs + commit `Closes #7637`.
