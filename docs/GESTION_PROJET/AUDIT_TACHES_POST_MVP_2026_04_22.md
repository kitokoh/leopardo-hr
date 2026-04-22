# Audit taches post-MVP — 2026-04-22

Statut: audit rapide base sur `main` au 2026-04-22, commit `4d9ca29`.

Ce document classe les taches T01-T18 signalees apres les premiers retours clients.
Il ne remplace pas les tests; il sert a choisir l ordre d execution.

## Vue globale

| Tache | Statut observe | Priorite | Note |
|---|---|---|---|
| T01 Kiosque authentifie | Fait partiellement/OK | Critique | `X-Kiosk-Token` + hash existe sur roster/punch/sync |
| T02 Restauration `search_path` | A faire | Critique | Plusieurs services font `SET search_path` sans `try/finally` |
| T03 Unique double check-in | Partiellement couvert | Critique | Unique global `(employee_id,date,session_number)` existe, pas l index partiel demande |
| T04 `company_id` check-in/out mobile | Partiellement couvert | Securite | Trait `BelongsToCompany` injecte `company_id`; service ne le renseigne pas explicitement |
| T05 Filtrer champs sensibles `fill()` | Partiellement fait | Securite | Non-manager filtre; managers/self-update ont des protections, tests a renforcer |
| T06 Whitelist FormRequests champs sensibles | A verifier/renforcer | Securite | Champs sensibles absents des rules, test explicite manque |
| T07 `down()` migrations 000100-000108 | Partiel | DevOps | Certains `down()` existent; audit complet necessaire |
| T13 Intercepteur 401 Flutter | Fait partiellement/OK | Mobile | `ApiClient` supprime token et callback `onUnauthorized`; UX message a verifier |
| T14 Bloquer mock en release | A faire | Mobile/Securite | `API_BASE_URL=mock` active `MockInterceptor`; pas de garde `kDebugMode` observe |
| T15 Biom. locale avant check-in | A faire | Mobile/Securite | `local_auth` existe pour demande biometrie, pas avant check-in |
| T16 Smoke test Render | Fait | DevOps | `deploy-main.yml` appelle `/api/v1/health` |
| T17 Secrets Firebase README | Fait minimal | DevOps | README liste `FIREBASE_APP_ID`, `FIREBASE_TOKEN`; peut etre enrichi |
| T18 Pagination dashboard Blade | A faire | Performance | `DashboardController` charge tous les employes avec `get()` |

## Prochaine sequence conseillee

1. P0: T02 `search_path` avec tests.
2. P1: T18 pagination dashboard + libelles francais pro.
3. P1: T14 mock interdit en release.
4. P1: T04 company_id explicite + test.
5. P1: recu/export heures sup + cotisations.
6. P1: light mode mobile.
