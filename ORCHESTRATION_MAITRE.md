# ORCHESTRATION MAITRE - LEOPARDO RH
# Version 4.1.1 | 04 Avril 2026

---

## ETAT DE LA DOCUMENTATION

| Fichier | Statut au 31 Mars 2026 | Reference d'etat |
|---|---|---|
| `02_API_CONTRATS_COMPLET.md` | `82 endpoints` - VERSION FINALE v2.0 | Source de verite API |
| `03_ERD_COMPLET.md` | Complet | Source de verite donnees |
| `05_REGLES_METIER.md` | Complet | Source de verite metier |
| `16_STRATEGIE_TESTS.md` | Complet | Source de verite qualite |
| `08_MULTITENANCY_STRATEGY.md` | Complet | Source de verite isolation |

Note critique : `AUDIT_COMPLET_MANQUES.md` est obsolete depuis v3.3.0 et reste archive pour tracabilite historique uniquement.

---

## INDEX DES FICHIERS CORRIGE

Tous les chemins ci-dessous sont navigables depuis la racine du monorepo.

| Reference logique | Chemin reel verifie |
|---|---|
| ORCHESTRATION_MAITRE.md | `ORCHESTRATION_MAITRE.md` |
| AUDIT_COMPLET_MANQUES.md | `AUDIT_COMPLET_MANQUES.md` |
| 08_FEUILLE_DE_ROUTE.md | `08_FEUILLE_DE_ROUTE.md` |
| 02_API_CONTRATS_COMPLET.md | `docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md` |
| 03_MODELE_ECONOMIQUE.md | `docs/dossierdeConception/03_modele_economique/03_MODELE_ECONOMIQUE.md` |
| 03_ERD_COMPLET.md | `docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md` |
| 05_REGLES_METIER.md | `docs/dossierdeConception/05_regles_metier/05_REGLES_METIER.md` |
| 08_MULTITENANCY_STRATEGY.md | `docs/dossierdeConception/08_multitenancy/08_MULTITENANCY_STRATEGY.md` |
| TENANT_MIGRATION_SERVICE_SPEC.md | `docs/dossierdeConception/08_multitenancy/TENANT_MIGRATION_SERVICE_SPEC.md` |
| 16_STRATEGIE_TESTS.md | `docs/dossierdeConception/09_tests_qualite/16_STRATEGIE_TESTS.md` |
| CU-01_ET_AGENTS.md | `CU-01_ET_AGENTS.md` |
| CHANGELOG.md | `CHANGELOG.md` |

---

## REPARTITION DES AGENTS

| Type de tache | Responsable | Raison |
|---|---|---|
| Verification fichiers JSON/YAML/MD | Toi (humain) via Claude.ai | Simple a verifier manuellement |
| Generation donnees de test | Claude Code | Dans l'environnement Laravel |
| Validation coherence inter-fichiers | Claude.ai (cette conversation) | Pilotage documentaire transverse |
| W1-3 verification mocks JSON (MA-01) | Toi (humain) via Claude.ai | Tache documentaire, pas technique |

---

## FRONTEND - REGLES DE RESPONSABILITE

Cursor (agent principal frontend) :
- Tout le code Vue.js dans `api/resources/js/Pages/`
- Tout le code Vue.js dans `api/resources/js/Components/`
- Tailwind CSS + PrimeVue
- Inertia.js (donnees depuis controllers Laravel)
- Prerequis : CC-02 backend termine

v0.dev (landing page uniquement) :
- `api/resources/views/landing/` (Blade + Tailwind statique)
- Pas de logique metier
- Livrable : composants Blade prets a integrer

Claude.ai (cette conversation) :
- Conception et revue uniquement
- Pas de generation directe de code frontend
- Validation des livrables Cursor/v0.dev avant merge

Regle de conflit :
- Si Cursor et v0.dev produisent des styles contradictoires, Cursor est prioritaire (application > vitrine).

---

## GOUVERNANCE CANONIQUE (ANTI-CONTRADICTION)

Ordre de priorite documentaire (du plus fort au plus faible) :
1. `ORCHESTRATION_MAITRE.md` (etat global et priorites)
2. `docs/dossierdeConception/*` (specs de reference)
3. `docs/PROMPTS_EXECUTION/v2/*` (execution par agent)
4. `docs/GESTION_PROJET/*` (journal et suivi operationnel)
5. Fichiers archives (`AUDIT_COMPLET_MANQUES.md`, notes historiques)

Regles obligatoires :
- Si deux fichiers se contredisent, appliquer l'ordre de priorite ci-dessus.
- Toute correction de spec doit modifier au minimum :
  - le fichier technique concerne
  - `CHANGELOG.md`
  - `JOURNAL_RACINE.md`
- Interdiction de coder sur une spec non harmonisee.

---

## STRATEGIE PRODUIT PAR PHASE (SCOPE CONTROL)

Phase 1 - Priorite execution (MVP 20-24 semaines) :
- Cible principale : petites structures (persona Murat)
- Valeur livree : pointage fiable + "ce que je dois aujourd'hui" + quick estimate
- Modules prioritaires : Auth, Employees, Attendance, Quick Estimate, PDF recu informatif

Phase 2 - Montee en gamme :
- Cible principale : PME structurees (Karim / Mehmet)
- Valeur livree : paie avancee complete, reporting et gouvernance enterprise
- Modules etendus : payroll complet, analytics avancees, automatisations legales pays

Regle anti-derapage :
- Toute fonctionnalite non reliee au resultat de la phase active est reportee au backlog phase suivante.

---

## QUALITY GATES AVANT MERGE

Un livrable ne peut pas etre merge si un seul point est manquant :
- Tests fonctionnels du module passes
- Isolation tenant verifiee (aucune fuite inter-entreprises)
- RBAC verifie pour les roles impactes
- Contrat API aligne (`02_API_CONTRATS_COMPLET.md` + OpenAPI si endpoint public)
- Changelog et journal mis a jour
- Risques d'exploitation documentes (rollback ou runbook impacte)

---

## EXECUTION IMPERATIVE (ANTI-FAIBLESSES RESIDUELLES)

Ces fichiers sont maintenant obligatoires pour l'execution :
- Index canonique: `docs/GESTION_PROJET/INDEX_CANONIQUE.md`
- Backlog unique Phase 1: `docs/GESTION_PROJET/BACKLOG_PHASE1_UNIQUE.md`
- Blockers et next actions: `docs/GESTION_PROJET/EXECUTION_BLOCKERS_AND_NEXT.md`
- Runbooks ops:
  - `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md`
  - `docs/GESTION_PROJET/RUNBOOK_ROLLBACK.md`
  - `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`
  - `docs/GESTION_PROJET/RUNBOOK_INCIDENT_P1.md`
  - `docs/GESTION_PROJET/RUNBOOK_DRILLS_LOG.md`
- PR discipline:
  - `.github/PULL_REQUEST_TEMPLATE.md`
  - `.github/BRANCH_PROTECTION_REQUIRED.md`

Regle:
- Aucune implementation ne demarre hors backlog unique.
- Aucune release sans runbook applicable et teste.
