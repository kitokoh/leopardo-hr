# Journal Racine — Qui a fait quoi, quand

Ce journal trace les opérations transverses du repo (structure, documentation, nettoyage).

| Date | Acteur | Action | Fichiers/Portée | Détails |
|---|---|---|---|---|
| 2026-04-04 | Codex | Corrections post-audit | Orchestration, audit, pricing, roadmap, multitenancy, changelog | Harmonisation documentaire v4.0.1 |
| 2026-04-04 | Codex | Nettoyage structure | `docs/PROMPTS_EXECUTION/v2/*`, `docs/notes/*`, racine | Références cassées corrigées, fichiers index ajoutés, notes déplacées |
| 2026-04-04 | Codex | Suppression parasite | `bon-fixed/*` | Fichiers supprimés du suivi Git, dossier racine vide encore verrouillé par Windows |
| 2026-04-04 | Codex | Normalisation petites structures | Personas, API, règles métier, onboarding, mobile, PDF, SQL spec, changelog | Ajout Persona Murat + endpoints daily-summary/quick-estimate + quickstart + reçu de période (v4.1.0) |
| 2026-04-04 | Codex | Renforcement gouvernance canonique | Orchestration, feuille de route, contexte session, changelog | Priorite documentaire, quality gates, anti-scope-creep, cadrage Phase 1/Phase 2 (v4.1.1) |
| 2026-04-04 | Codex | Harmonisation versionning | Docs pilotage + changelog | Alignement des entetes vers baseline programme v4.1.1 et correction compteur API de reference |
| 2026-04-04 | Codex | Suppression des 4 faiblesses residuelles | Index canonique, backlog unique, runbooks, CI gates | Verrouillage execution (anti-confusion, anti-derapage, anti-regression) en v4.1.3 |
## [12 Avril 2026 - Session 3] Déploiement Cloud (Render/Neon)
- **Acteur** : Antigravity (Codex)
- **Objectif** : Basculer l'infrastructure de o2switch vers Render + Neon.
- **Réalisations** : 
  - Création de `Dockerfile.prod` (FrankenPHP).
  - Suppression du workflow o2switch.
  - Mise à jour de la documentation (Arborescence, PILOTAGE, CI/CD).
  - Guide `RENDER_SETUP.md` finalisé.
- **Statut** : En cours de validation CI.
| 2026-04-04 | Codex | Durcissement execution | PR template, branch protection spec, checker local, drills log | Passage de la gouvernance documentaire a un controle operationnel concret (v4.1.4) |
| 2026-04-04 | Codex | Normalisation finale coherence | Pilotage/OpenAPI/Multitenancy | PROGRAM_VERSION fixe a 4.1.4, retrait propre de /auth/refresh OpenAPI, correction doc trait BelongsToCompany |
| 2026-04-04 | Antigravity | Restructuration globale (v4.1.4) | `PILOTAGE.md`, `GARDE_FOUS.md`, `v3/prompts` | Source de vérité unique activée, prompts MVP v3, scope verrouillé |
| 2026-04-21 | Codex | Decision GO MVP | `docs/GESTION_PROJET/GO_NO_GO_MVP.md`, `CHANGELOG.md`, tag `v0.1.0-mvp` | Render, Neon/PostgreSQL, Firebase mobile et tests de connexion reels valides; passage en pilote client encadre |
| 2026-04-23 | Devin | Mobile landing page | `front/mobile/lib/features/auth/screens/welcome_screen.dart`, `front/mobile/lib/features/auth/screens/register_screen.dart`, `front/mobile/lib/app.dart`, `front/mobile/lib/features/auth/screens/login_screen.dart`, `front/mobile/test/features/auth/welcome_screen_test.dart`, `CHANGELOG.md` | Nouvelle page `/welcome` (hero + carrousel de 4 benefices employe + CTA Se connecter / Creer un compte), nouvelle page `/register` explicatrice du flow d'invitation, back-button sur `/login`, redirection non-auth desormais vers `/welcome` au lieu de `/login` |
| 2026-04-25 | Devin | Corrections CRITIQUES Pointage | `api/app/Exceptions/AlreadyCheckedInException.php`, `api/app/Exceptions/MissingCheckInException.php`, `api/app/Services/AttendanceService.php`, `api/app/Policies/AttendancePolicy.php`, `api/app/Http/Controllers/Api/V1/AttendanceController.php`, `api/app/Http/Middleware/TenantMiddleware.php`, `api/openapi.yaml`, `api/tests/...`, `CHANGELOG.md` | Mise en conformite avec le rapport Leopardo_RH_Pointage_Validation_Finale : HTTP 422 (au lieu de 409) pour ALREADY_CHECKED_IN et MISSING_CHECK_IN, soustraction de `break_minutes` dans `hours_worked`, `late_minutes` integrant la tolerance, blocage des managers sur check-in/check-out (PT-10), filtrage des employes inactifs dans `today()` (PT-29/PT-43), blocage des employes suspendus dans le middleware tenant (PT-68) |

| 2026-07-29 | KiloClaw | Audit doc P2 : chemin mobile obsolete corrige | `docs/GESTION_PROJET/DOSSIER_REPONSE_AU_CAHIER_DES_CHARGES.md`, `CHANGELOG.md` | Audit lecture-seule (token GitHub temporaire) : le document (date 2026-05-10) referencait `front/mobile/`, chemin supprime le 2026-06-13 (PR #754) et remplace par 5 apps Flutter dans `front/mobile_apps/`. Ajout d'un bandeau de fraicheur date en tete (meme convention que `GARDE_FOUS.md`) et correction des 2 occurrences vers `front/mobile_apps/` |
| 2026-07-29 | KiloClaw | Audit doc P3 : index GESTION_PROJET + canonicite dossierSonnet | `docs/GESTION_PROJET/README.md`, `docs/dossierdeConception/00_docs/dossierSonnet/README.md` (nouveau), `docs/dossierdeConception/README.md`, `CHANGELOG.md` | Audit lecture-seule (token GitHub temporaire) : `GESTION_PROJET/README.md` ne listait que 7/33 fichiers reels, section "Index complet" ajoutee classant les 32 fichiers restants (runbooks, scenarios de test, rapports/audits ponctuels, deploiement) ; nouveau README dans `dossierSonnet/` clarifiant que `docs/vision/README.md` fait foi en cas de divergence sur les 4 PDF partages, et documentant la difference de taille non significative de `Leopardo_RH_Finance_Complet.pdf` (texte identique, verifie via `pdftotext`) |
| 2026-07-29 | KiloClaw | Audit doc P1 : propagation bascule PLAN_ACTION2 -> GitHub Issues | `PILOTAGE.md`, `docs/README.md`, `CHANGELOG.md` | Audit lecture-seule (token GitHub temporaire) : `AGENTS.md` (2026-07-26) declare `docs/PLAN_ACTION2/` obsolete et bascule la gestion de projet vers GitHub Issues/Projects ; `docs/PLAN_ACTION2/README.md` lui-meme confirme (UTF-16, deja mis a jour le meme jour), mais `PILOTAGE.md` (2026-07-19/21) et `docs/README.md` (2026-07-20) n'avaient pas ete mis a jour et disaient encore explicitement le contraire ("Plan d'action actif"). Un agent lisant ces 2 fichiers avant AGENTS.md recevait l'instruction inverse. Table de gouvernance de PILOTAGE.md corrigee + bandeau date ajoute ; section Historique & Archive de docs/README.md corrigee pour refleter la fermeture |
| 2026-07-29 | KiloClaw | Audit doc P1 : spec retroactive MODULE_RECRUTEMENT.md | `docs/specifications/MODULE_RECRUTEMENT.md` (nouveau), `CHANGELOG.md` | Audit lecture-seule (token GitHub temporaire) : la regle d'or d'AGENTS.md exige une spec dans docs/specifications/ avant tout nouveau module, citant MODULE_RECRUTEMENT.md en exemple litteral, mais le module Recrutement/ATS (issues #1324/#1326, merge 2026-07-27/28) n'en avait jamais eu (confirme via git log --diff-filter=A). Spec redigee a posteriori a partir du code reel (schema DB, endpoints, RBAC, surfaces front, tests) |

## Template d'entrée

`| YYYY-MM-DD | Auteur | Action | Fichiers/Portée | Détails |`
