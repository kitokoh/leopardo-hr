# Validation - statut documentaire

Ce dossier contient les referentiels de validation fonctionnelle et QA utilises pour verifier qu'un module est reellement terminable et testable.

## Documents vivants vs rapports historiques figes

La majorite des fichiers de ce dossier portent une date dans leur nom
(ex: `*_2026_06_01.md`, `RELEASE_READINESS_REPORT_2026-05-19.md`). Ce sont des
**rapports d'audit/smoke-test figes au moment de leur redaction** — ils ne sont
jamais mis a jour retroactivement et servent de preuve/tracabilite pour les
plans `docs/PLAN_ACTION/` qui les ont produits. Ne pas les considerer comme
l'etat actuel du systeme ; se referer au code sur `main` et a `PILOTAGE.md`
pour l'etat present.

Seuls les documents **sans date dans le nom** sont vivants et doivent rester a
jour : `RELEASE_READINESS_GATE.md`, `FRONTEND_API_CONTRACT_MATRIX.md`,
`CLIENT_LOGIN_READINESS.md`, `CLIENT_UX_OBSERVABILITY.md`,
`LAUNCH_OBSERVABILITY_DASHBOARD.md`, `MOBILE_FIREBASE_DISTRIBUTION.md`,
`MOBILE_MARKETING_READINESS.md`, `MOBILE_STORE_READINESS.md`, et ce fichier.

## Statut des documents

| Fichier | Statut | Usage autorise | Source canonique qui prime |
|---|---|---|---|
| `01_pointage/Leopardo_RH_Pointage_Validation_Finale.pdf` | Referentiel QA canonique du module pointage | Executer les scenarios de test, verifier la readiness du module, documenter PASS/FAIL/BLOCK | Le code sur `main` pour le comportement final, puis `PILOTAGE.md` pour le statut programme |

## Regle

Ce PDF peut faire foi pour la validation du **module pointage** tant qu'il reste aligne avec :

- les routes/API reelles,
- les roles effectivement supportes,
- et les tests backend/mobile executes en CI.

Il ne constitue pas une source de verite globale pour :

- l'infrastructure,
- la gouvernance programme,
- ou les autres modules hors pointage.
