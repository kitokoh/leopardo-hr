# Gestion projet - Leopardo RH

Ce dossier centralise les documents de pilotage, les runbooks, les audits d'ecarts et les supports d'execution du projet.

## Ordre de lecture recommande

1. `../../PILOTAGE.md` - source de verite operationnelle
2. `ALIGNEMENT_DOCUMENTATION_MAIN_2026-04-26.md` - ecart entre documentation cible et etat reel de `main`
3. `GARDE_FOUS.md` - regles anti-derive — ⚠️ ARCHIVE/OBSOLETE depuis l'audit doc du 2026-07-19, sa "liste noire MVP" contredit le scope reel livre documente dans `PILOTAGE.md`. Lire pour la discipline anti scope-creep uniquement, pas pour le perimetre produit reel.
4. `RUNBOOK_DEPLOY.md`, `RUNBOOK_ROLLBACK.md`, `RUNBOOK_BACKUP_RESTORE.md`, `RUNBOOK_INCIDENT_P1.md` - socle d'exploitation
5. `REGISTRE_SCENARIOS_TESTS.md` + `SCENARIOS_TEST_*` - base canonique de couverture fonctionnelle et CI
6. ARCHITECTURE_I18N_ENTERPRISE_2026-05-07.md - cible multilingue partagee backend/web/mobile
7. PLAN_ACTION_AMELIORATION.md - plan d'amelioration backend, conserve comme archive d'execution et feuille de detail

## Sous-ensembles logiques

- `RUNBOOK_*` : exploitation et incidents
- `RAPPORT_*` : constats et comptes rendus
- `SCENARIOS_TEST_*` : verification manuelle ou CI
- `REGISTRE_SCENARIOS_TESTS.md` : source de verite transversale pour savoir quels scenarios, workflows et artefacts conditionnent le deploiement
- `PLAN_ACTION_AMELIORATION.md` : backlog d'amelioration technique structure
- `TICKETS_ALIGNEMENT_DOC_INFRA_2026-04-25.md` : derive et rattrapage de la documentation infra

## Index complet (33 fichiers)

Cette section liste explicitement tous les fichiers du dossier, classes par sous-ensemble logique, pour eviter qu'un fichier reste invisible depuis ce README (voir audit doc 2026-07-29).

### Pilotage et audits ponctuels

| Fichier | Statut / usage |
|---|---|
| `ALIGNEMENT_DOCUMENTATION_MAIN_2026-04-26.md` | Ecart documentation cible vs etat reel de `main` |
| `AUDIT_TACHES_POST_MVP_2026_04_22.md` | Audit ponctuel des taches T01-T18 post-retours clients (2026-04-22) |
| `CORRECTIONS.md` | Corrections documentaires Sprint 0 - les 7 items sont deja tous appliques (voir statut en bas du fichier) |
| `DOSSIER_REPONSE_AU_CAHIER_DES_CHARGES.md` | Reponse operationnelle au cahier des charges - date du 2026-05-10, contient une reference obsolete a `front/mobile/` (chemin supprime par la PR #754 du 2026-06-13 ; le chemin actuel est `front/mobile_apps/`), a corriger |
| `GARDE_FOUS.md` | ARCHIVE/OBSOLETE depuis l'audit doc du 2026-07-19 (voir ligne 3 ci-dessus) |
| `GO_NO_GO_MVP.md` | Decision officielle de passage MVP (2026-04-21) |
| `GOOD_FIRST_ISSUES.md` | Liste de 10 tickets d'entree pour contributeurs debutants |
| `PLAN_ACTION_AMELIORATION.md` | Backlog d'amelioration technique structure, conserve comme archive d'execution |
| `RETOURS_CLIENTS_PILOTE_2026_04_22.md` | Synthese des retours clients pilote post-GO MVP |
| `TICKETS_ALIGNEMENT_DOC_INFRA_2026-04-25.md` | Tickets de rattrapage doc/infra |

### Deploiement et releases

| Fichier | Statut / usage |
|---|---|
| `RAPPORT_DEPLOIEMENT_RENDER.md` | Rapport de deploiement backend sur Render (2026-04-13) |
| `RAPPORT_QA_CI_2026-04-18.md` | Renforcement des validations backend/mobile en CI et du reset Super Admin (2026-04-18) |
| `RELEASE_v0.1.0.md` | Notes de la premiere release publique |
| `RENDER_SETUP.md` | Guide pas-a-pas de deploiement gratuit Neon + Render |
| `SCHEMA_DEPLOIEMENT_ZKTECO_CLIENT.md` | Schema d'integration borne ZKTeco / PC local / mobile / API |
| `SUPPORT_COMMERCIAL_ZKTECO_LEOPARDO_RH.md` | Argumentaire commercial pointage ZKTeco + Leopardo RH |

### Runbooks d'exploitation (`RUNBOOK_*`)

| Fichier | Objet |
|---|---|
| `RUNBOOK_OPERATIONS.md` | Point d'entree unique : indique quel runbook specialise utiliser selon l'incident |
| `RUNBOOK_DEPLOY.md` | Deploiement production / staging |
| `RUNBOOK_ROLLBACK.md` | Rollback production |
| `RUNBOOK_MARKETING_ROLLBACK.md` | Rollback specifique au site marketing/vitrine |
| `RUNBOOK_BACKUP_RESTORE.md` | Sauvegarde et restauration |
| `RUNBOOK_INCIDENT_P1.md` | Procedure incident critique P1 |
| `RUNBOOK_DRILLS_LOG.md` | Journal des exercices (drills) reels executes |
| `RUNBOOK_ALERTING.md` | Sources et configuration des alertes |
| `RUNBOOK_OBSERVABILITY.md` | Observabilite via Sentry |
| `RUNBOOK_UPTIME_MONITORING.md` | Mise en place du monitoring de disponibilite (setup, pas une mesure live - voir `PILOTAGE.md`) |
| `RUNBOOK_LOCAL_TESTS.md` | Validation locale Docker avant push (regle d'equipe backend) |
| `RUNBOOK_BETA_ENV_SETUP.md` | Preparation d'un environnement Beta sur Render + Neon |
| `RUNBOOK_BETA_ACCEPTANCE.md` | Validation du MVP complet avant exposition a des prospects |
| `RUNBOOK_ZKTECO_CLIENT.md` | Installation et exploitation terrain d'une borne ZKTeco |

### Scenarios de test et couverture CI (`SCENARIOS_TEST_*`)

| Fichier | Perimetre |
|---|---|
| `REGISTRE_SCENARIOS_TESTS.md` | Source de verite transversale : quels scenarios/workflows/artefacts conditionnent le deploiement |
| `SCENARIOS_TEST_API_GITHUB_ACTIONS.md` | Couverture backend API en CI |
| `SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md` | Couverture `front/admin-dashboard/` (Playwright) en CI |
| `SCENARIOS_TEST_MOBILE_FLUTTER.md` | Couverture complete des apps mobiles Flutter |
| `BETA_VALIDATION_REPORT_TEMPLATE.md` | Gabarit vierge de rapport de validation beta |

### Architecture transverse

| Fichier | Objet |
|---|---|
| `ARCHITECTURE_I18N_ENTERPRISE_2026-05-07.md` | Cible multilingue partagee backend/web/mobile |

## Regle

Les fichiers de ce dossier doivent toujours expliciter s'ils sont :

- un document canonique de pilotage,
- un runbook executable,
- un audit ponctuel,
- ou une archive de contexte.

