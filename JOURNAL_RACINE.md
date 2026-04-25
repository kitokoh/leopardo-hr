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
| 2026-04-23 | Devin | Mobile landing page | `mobile/lib/features/auth/screens/welcome_screen.dart`, `mobile/lib/features/auth/screens/register_screen.dart`, `mobile/lib/app.dart`, `mobile/lib/features/auth/screens/login_screen.dart`, `mobile/test/features/auth/welcome_screen_test.dart`, `CHANGELOG.md` | Nouvelle page `/welcome` (hero + carrousel de 4 benefices employe + CTA Se connecter / Creer un compte), nouvelle page `/register` explicatrice du flow d'invitation, back-button sur `/login`, redirection non-auth desormais vers `/welcome` au lieu de `/login` |

## Template d'entrée

`| YYYY-MM-DD | Auteur | Action | Fichiers/Portée | Détails |`
