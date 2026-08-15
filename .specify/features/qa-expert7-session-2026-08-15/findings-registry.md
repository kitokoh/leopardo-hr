# Registre des manquements — Session QA Expert 7 2026-08-15

> Session de test exhaustive (vitrine, web, admin, mobiles, workflows, APIs, logiques,
> onboarding, cohérence) — méthode Spec Kit. Anti-doublon (#2400) respecté.

## A. Vérifications runtime effectuées (prod live)

- [x] **Vitrine `leopardo-rh.com` → DOWN (NXDOMAIN)** : Google DNS Status 3 pour
      `leopardo-rh.com` + `www` (A/NS vides). → **Issue #3452** (P1 ops).
- [x] API Render live v4.23.5 (stale vs main) : /health 200 · /i18n/catalog/fr 500 ·
      /supported-countries 404 · /trial/status 404 · /api-explorer 404 · login demo 401.
      (tracé #2627/#2632 — non dupliqué).
- [x] Admin `leo-admin.pages.dev` : 200.

## B. Findings

| ID | Sév | Constat | Statut |
|----|-----|---------|--------|
| E7-01 | P1 | Vitrine DOWN — leopardo-rh.com NXDOMAIN | Issue #3452 |
| E7-02 | P1 | Régression MERGE #3561 : `getIllustrativeExampleLabel` écrasé de vitrine-locale.ts (crash runtime + test RTL rouge) | Fix #3630 |
| E7-03 | P2 | `pageMetadata.integrations` manquant dans seo.ts (4 erreurs tsc sur main) | Fix #3630 |
| E7-04 | P3 | SignupForm FR residues — déjà corrigé sur main | Fermée avec preuve (#3330) |
| E7-05 | P3 | read-all admin POST→405 — déjà corrigé | Fermée (#3391) |
| E7-06 | P3 | CSV PayrollView injection — déjà corrigé (PR #3441) | Fermée (#3436) |
| E7-07 | P3 | TrackingSync dedup — corrigé (commit ac01b9c8) | Fermée (#3369) |
| E7-08 | P3 | Edge licence forgeable + garde rôle — corrigés (PR #3444) | Déjà fermées (#3317/#3319) |
| E7-09 | P3 | Kiosk search_path try/finally — corrigé | Déjà fermée (#3368) |
| E7-10 | P3 | Onboarding guard — corrigé (ac01b9c8) | Déjà fermée (#3430) |

## C. Actions session (PRs — toutes mergées sauf #3630)

- #3449 docs session (mergée) · #3456 #3388 (mergée) · #3460 #3401 (fermée — direction PUT
  anti-canonique, contrat = POST, voir mémoire) · #3466 #3393/#3394/#3437 (mergée) ·
  #3471 #3434 (mergée) · #3480 #3414/#3416 (mergée) · #3514 #3363/#3367/#3370 (mergée) ·
  #3539 #3409 (mergée) · #3561 #3372/#3410 (mergée) · #3609 #3378 (mergée) · **#3630
  régression illustrative-label + pageMetadata.integrations (ouverte)**.

## D. Décisions & constats post-session

- Contrat notifications read-all verrouillé = **POST** (+ alias legacy mark-all-read),
  jamais PUT (#3524) — voir `memory/wiki/github-notifications-read-all-contract.md`.
- Le merge #3561 a écrasé le bloc #3246 : leçon = re-vérifier le diff complet d'un PR qui
  touche un fichier partagé (vitrine-locale.ts) avant merge, et lancer jest localement.
