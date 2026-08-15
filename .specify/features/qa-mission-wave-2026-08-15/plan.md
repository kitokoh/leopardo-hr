# Plan: Vague Mission QA 2026-08-15

**Input**: spec.md (vague mission QA 2026-08-15)

## Objectif

Documenter et corriger les manquements NOUVEAUX détectés par 4 audits parallèles
(web, admin, API, mobile) + tests prod live, sur `main` @ d30b52da. Fermer les
issues stale (corrigées mais ouvertes). Merger les branches ouvertes restantes.

## Périmètre technique

| Bloc | Dossier | Outillage de validation |
|------|---------|------------------------|
| Vitrine | `front/web` | `npm run lint`, `npm run build`, mojibake, Playwright |
| Admin | `front/admin-dashboard` | `npm run lint`, `vite build` |
| API | `api/` | PHPStan strict, Pint, PHPUnit ciblé |
| Mobile | `front/mobile_apps` | `flutter analyze`, tests widget |
| GitHub | issues/PRs | `gh`, scripts `dev-hub/tools/` |

## Stratégie

1. **Documentation Spec Kit** : `.specify/features/qa-mission-wave-2026-08-15/{spec,plan,tasks}.md` ✓
2. **Issues GitHub** : création des issues T135-T172 (label `qa-mission-2026-08-15`, P1/P2/P3), auto-assignation + marker branch (protocole #2400).
3. **Fermeture stale** : vérification code sur main → commentaire + closed (script `check-issues-left-open-by-merged-prs.sh`).
4. **Implémentation** : par domaine, P1 d'abord, branches `fix/<issue>-<slug>` depuis `origin/main` frais.
5. **Merges** : rebase des branches/PRs ouvertes (évaluer contenu vs main), CI verte requise, merge + suppression branche.

## Risques & mitigations

- **Agents parallèles actifs** (PRs #2967-#2972 créées pendant la session) : re-vérifier
  branches/PRs avant chaque implémentation ; ne pas merger une PR qui duplique un travail en cours.
- **Durée d'essai 14 vs 30** : arbitrage par PlanSeeder (14) ; #2972 défend 30 → commentaire argumenté, pas de merge aveugle.
- **Prod stale** (API v4.23.5) : corrigé par le merge de PRs backend → deploiement auto (`deploy-main.yml`).
- **Sans docker** : validation locale backend via PostgreSQL/Redis natifs installés ; CI GitHub comme porte finale.

## Jalons

1. Spec Kit + issues créées
2. Issues stale fermées avec preuve
3. PRs P1 mergées (CI verte)
4. Vague P2/P3 par domaine mergée
5. Branches restantes évaluées/mergées/nettoyées
