# Documentation — Leopardo

Index et **règles d'organisation** de la documentation du projet (Refs #7844, audit externe 2026-09-20).

> Pour la structure globale du monorepo, voir [`ARCHITECTURE.md`](../ARCHITECTURE.md) à la racine.

---

## 1. Convention de nommage `docs/`

Règles applicables à **tout nouveau** dossier ou fichier de `docs/` (les existants migrent par phases, voir §4) :

| Règle | Détail |
|---|---|
| **Casse dossiers** | `kebab-case` minuscule (`go-to-market/`, `gestion-projet/`). Plus de MAJUSCULES ni de camelCase pour les nouveaux dossiers. |
| **Casse fichiers** | `kebab-case.md` pour les nouveaux fichiers (`monitoring-setup.md`). Exceptions tolérées : `README.md`, fichiers datés de session (`qa-YYYY-MM-DD.md`), ADRs (`NNNN-titre.md`). |
| **Langue** | **Public = EN** (guides utilisateurs, API, présentation) · **Interne/gouvernance = FR** (runbooks, protocoles, gestion projet). La langue d'un dossier est celle de son `README.md`. |
| **Fichiers en vrac** | **Interdits** au premier niveau de `docs/` : tout fichier vit dans un dossier-famille. Seules exceptions à la racine : `README.md` (cet index) et `QUICKSTART.md` (doc canonique d'onboarding). |
| **Cycle de vie** | Doc obsolète → `docs/archive/` (ou `docs/notes/archive/` si gouvernance historique), avec bandeau « ⚠️ Archivé » et lien vers le remplaçant. Ne jamais supprimer sans redirection. |
| **1 dossier = 1 README** | Chaque dossier-famille a un `README.md` d'index expliquant son périmètre. |

### Table de correspondance (noms actuels → cible kebab-case)

Renommages **différés** (phases 2-3, voir §4 — beaucoup sont surveillés par des gardes CI) :

| Actuel | Cible | Actuel | Cible |
|---|---|---|---|
| `CONTEXT/` | `context/` | `GESTION_PROJET/` | `gestion-projet/` ⚠️ garde |
| `GOTO_MARKET/` | `business/go-to-market/` | `GOUVERNANCE/` | `governance/` |
| `GTM/` | `business/go-to-market/` | `GUIDES/` | `guides/` |
| `HR/` | `domains/hr/` | `PROMPTS_EXECUTION/` | `archive/prompts-execution/` (ou dépôt privé) |
| `PROTOCOLES/` | `governance/protocoles/` | `REFERENTIEL_PRODUIT/` | `product/referentiel/` ⚠️ garde |
| `STRATEGIE_COMMERCIALE/` | `business/` (ou dépôt privé) | `commercial/` | `business/avant-vente/` |
| `dossierdeConception/` | `architecture/conception/` ⚠️ garde | | |

---

## 2. Taxonomie cible (~11 familles)

Regroupement cible des 53 dossiers actuels :

```
docs/
├── README.md, QUICKSTART.md          # seuls fichiers racine autorisés
├── architecture/    # architecture, dossierdeConception, specifications, api, modules,
│                    # edge-sync, design, infra
├── product/         # REFERENTIEL_PRODUIT, vision, focus
├── domains/         # HR, accounting, payroll, attendance, expense, contracts,
│                    # restaurant, travel, kiosk (docs par domaine métier)
├── platforms/       # mobile, web, desktop, admin, client
├── ops/             # ops, deployment, i18n (exploitation, déploiement, monitoring)
├── security/        # security (menaces, audits sécu, RGPD)
├── quality/         # qa, testing, validation, qualite, audits, external-audits
├── governance/      # GOUVERNANCE, GESTION_PROJET, PROTOCOLES, plan, pilotes
├── contributing/    # contributing, GUIDES, CONTEXT, user-guide, ai
├── business/        # GOTO_MARKET, GTM, STRATEGIE_COMMERCIALE, commercial (→ §3 : publication ?)
├── archive/         # archive, notes, PROMPTS_EXECUTION (historique gelé)
└── assets/          # assets partagés
```

### Consolidation des 4 dossiers commerciaux (cible : 2 max)

| Actuel | Contenu | Cible |
|---|---|---|
| `GOTO_MARKET/` | Stratégie GTM, canaux, rapports mensuels (référencé par workflows — `rapports-mensuels/YYYY-MM.md`) | `business/go-to-market/` (**stratégie**) |
| `GTM/` | Templates prospection, cas clients | fusion dans `business/go-to-market/` |
| `STRATEGIE_COMMERCIALE/` | Scripts de vente, modèles + CSV CRM, PDF | `business/vente/` (**opérationnel**) — candidat n°1 au dépôt privé |
| `commercial/` | Benchmarks concurrents, dossiers appels d'offres | fusion dans `business/vente/` |

---

## 3. Règle public / interne

Ce dépôt est **public (MIT)**. Ne DOIVENT PAS être publiés dans `docs/` :

- **Secrets & accès** : credentials, tokens, URLs internes authentifiées (les docs comme `CI_CD_SECRETS.md` décrivent les *noms* de secrets, jamais les valeurs — à vérifier à chaque PR).
- **Données clients/CRM** : CSV de prospects, coordonnées, pipelines nominatifs (⚠️ `STRATEGIE_COMMERCIALE/LEOPARDO_RH_CRM_*.csv` — voir décision ci-dessous).
- **Scripts de vente & tactiques commerciales** (`STRATEGIE_COMMERCIALE/LEOPARDO_RH_SCRIPTS_COMMERCIAUX.md`) : avantage concurrentiel.
- **Prompts d'orchestration IA internes** (`PROMPTS_EXECUTION/`) : mode opératoire interne.
- **Données personnelles** de pilotes/employés (carnets `pilotes/`, sessions QA nominatives).

**Décision propriétaire requise (hors périmètre de cette PR)** : déplacer `STRATEGIE_COMMERCIALE/` (au minimum les CSV CRM et scripts), `PROMPTS_EXECUTION/` et les carnets nominatifs de `pilotes/` vers un **dépôt privé** (`leopardo-hr-internal`), ou assumer explicitement leur publication. Tracer la décision dans une issue dédiée.

---

## 4. Plan de migration par phases

⚠️ **Gardes CI** : `dev-hub/tools/check-governance.ps1` exige des fichiers précis sous `docs/GESTION_PROJET/`, `docs/notes/archive/`, `docs/archive/PILOTAGE.md`, et impose une entrée `CHANGELOG.md` pour tout changement sous `docs/dossierdeConception/`, `docs/GESTION_PROJET/`, `docs/REFERENTIEL_PRODUIT/`, `docs/notes/archive/`, `docs/archive/PILOTAGE.md`. D'autres scripts `dev-hub/tools/*` et workflows `.github/` référencent des chemins `docs/...` (vérifier avec `rg 'docs/' .github/ dev-hub/tools/`). **Tout déplacement de la phase 2+ doit mettre à jour ces références dans la même PR.**

### Phase 0 — cette PR (fait)

Convention écrite + rangement des fichiers en vrac **non référencés par gardes/workflows/code** (détail en §4.1).

### Phase 1 — consolidation commerciale + décision public/privé

`GTM/` → `GOTO_MARKET/`, `commercial/` + `STRATEGIE_COMMERCIALE/` → dossier vente unique ; extraction du contenu interne vers dépôt privé. Gardes : workflow rapport mensuel GTM (`docs/GOTO_MARKET/rapports-mensuels/`). Risque : moyen.

### Phase 2 — renommages kebab-case non gardés

`CONTEXT`, `GOUVERNANCE`, `GUIDES`, `HR`, `PROTOCOLES` → cibles §1. Chacun est référencé par des scripts `dev-hub/tools/*` (ex. `BC_BATCH_BRANCH_PROTOCOL.md`, `GUIDE_OPEN_CORE_MARKETPLACE.md`, `P01/P04/P05`) → mise à jour scripts + docs dans la même PR. Risque : moyen.

### Phase 3 — chemins critiques de la garde (CHANGELOG requis)

`GESTION_PROJET/`, `REFERENTIEL_PRODUIT/`, `dossierdeConception/`, `notes/archive/`, `archive/PILOTAGE.md` : nécessite de modifier `check-governance.ps1` (listes `$requiredFiles`, `$criticalPattern`, `$historicalFiles`) + tous les workflows + `CHANGELOG.md`. Risque : **élevé** — une PR dédiée par dossier.

### Phase 4 — regroupement en familles

Création de `domains/`, `platforms/`, `quality/`… et déplacement des dossiers minuscules restants (chacun référencé par 1-N scripts : `payroll/`, `security/`, `ops/`, `validation/`, `audits/`, `pilotes/`, `deployment/`, `desktop/`, `accounting/`, `attendance/`, `qualite/`, `testing/`, `specifications/`, `infra/`, `focus/`, `plan/`, `architecture/`, `api/` — mise à jour des gardes à chaque fois). Risque : moyen à élevé ; facultatif si le coût dépasse le bénéfice — la taxonomie peut rester « logique » (cet index) sans déplacement physique.

### 4.1 Fichiers racine — état après cette PR

**Déplacés (aucune référence garde/workflow/code)** :

| Fichier | Destination |
|---|---|
| `ALERTS_CONFIGURATION.md` | `ops/` |
| `MONITORING_SETUP.md` | `ops/` |
| `RELEASE_PROCESS.md` | `ops/` |
| `ARCHITECTURE_CICD.md` | `architecture/` |
| `MONOREPO_TOOLING.md` | `contributing/` |
| `DEMO_PILOTE_CRM.md` | `pilotes/` |
| `JULES_ORIGE_BUG.md` | `qa/` |
| `MOBILE_API_SYNC_CI_CD_FIXES.md` | `mobile/` |
| `qa-expert-audit-360-2026-08-15.md` · `qa-expert11-session-2026-08-15.md` · `qa-expert14-session-2026-08-15.md` | `qa/` |

**NON déplacés (référencés — à traiter en phase dédiée avec mise à jour des références)** :

| Fichier | Référencé par | Cible future |
|---|---|---|
| `CI_CD_SECRETS.md` | `.github/` + `dev-hub/tools/` (11 réfs) | `ops/` |
| `CONTRIBUTING_DDD.md` | `dev-hub/tools/` | `contributing/` |
| `ARCHITECTURE_STATUS.md` | `dev-hub/tools/` | `architecture/` |
| `DEMO_ACCOUNTS.md` | `.github/`/`dev-hub/tools/` | `ops/` |
| `DEMO_KIT_DZ.md` | `api/database/seeders/DemoDzSeeder.php` (api/ = scope critique) | `pilotes/` |
| `DEPLOYMENT_PRODUCTION.md` | `front/web/src/lib/site-url.ts`, `.specify/` | `ops/` (avec `DEPLOYMENT_STAGING.md`) |
| `DEPLOYMENT_STAGING.md` | paire de `DEPLOYMENT_PRODUCTION.md` | `ops/` |
| `RGPD_REGISTRE_TRAITEMENTS.md` | `front/web/src/app/api/forms/solution-survey/route.ts` | `security/` |
| `JOURNAL_RACINE.md` | `docs/GESTION_PROJET/RUNBOOK_ROLLBACK.md`, `docs/notes/archive/*` (scope critique garde) | `archive/` |
| `QUICKSTART.md` | multiples (doc canonique) | **reste à la racine** (exception §1) |

---

## 5. Index

### 🚀 Démarrage rapide
| Doc | Contenu |
|---|---|
| [`QUICKSTART.md`](QUICKSTART.md) | Setup local en 5 minutes (doc canonique) |
| [`archive/DEMARRAGE_RAPIDE.md`](archive/DEMARRAGE_RAPIDE.md) | ⚠️ Obsolète/archivé (#7843) — voir QUICKSTART.md |
| [`../DEVELOPMENT.md`](../DEVELOPMENT.md) | Conventions de développement |
| [`contributing/MONOREPO_TOOLING.md`](contributing/MONOREPO_TOOLING.md) | Commandes melos, npm --prefix, Makefile backend |

### 📋 Protocoles, gardes & référentiels
| Doc | Contenu |
|---|---|
| [`PROTOCOLES/`](PROTOCOLES/) | Corpus de protocoles ratifié P01-P07 |
| [`GOUVERNANCE/REGISTRE_GARDES.md`](GOUVERNANCE/REGISTRE_GARDES.md) | Catalogue des gardes CI (`dev-hub/tools/*` + workflows) |
| [`GOUVERNANCE/PROTOCOLE_LOTS_MULTI_AGENTS.md`](GOUVERNANCE/PROTOCOLE_LOTS_MULTI_AGENTS.md) | Lots d'issues multi-agents & merge sous saturation CI |
| [`GOUVERNANCE/RETEX_FLUX.md`](GOUVERNANCE/RETEX_FLUX.md) | Flux constat → issue [LECON] → leçon (P04) |
| [`GESTION_PROJET/BIBLIOTHEQUE_ERREURS.md`](GESTION_PROJET/BIBLIOTHEQUE_ERREURS.md) | Pièges connus : piège → symptôme → garde |
| [`REFERENTIEL_PRODUIT/TERMES.md`](REFERENTIEL_PRODUIT/TERMES.md) · [`MESSAGE.md`](REFERENTIEL_PRODUIT/MESSAGE.md) · [`METRIQUES_VITRINE.md`](REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md) | Lexique public, message canonique, métriques datées |
| [`desktop/`](desktop/) | Documentation desktop (tranches verticales, P06) |
| [`../dev-hub/tools/`](../dev-hub/tools/) | Outils de garde & vérification (voir REGISTRE_GARDES.md) |
| [`ops/ETAT_DEV_PROD_2026-09-09.md`](ops/ETAT_DEV_PROD_2026-09-09.md) | État des volets dev/prod (2026-09-09) |
| [`ops/HEALTH_ENDPOINTS.md`](ops/HEALTH_ENDPOINTS.md) | Sondes de santé API (#7255) |

### 🧭 Contexte & conception
| Doc | Contenu |
|---|---|
| [`CONTEXT/`](CONTEXT/) | Contexte rapide pour une nouvelle IA / un nouvel intervenant |
| [`architecture/AGENT-START-HERE.md`](architecture/AGENT-START-HERE.md) | Point d'entrée obligatoire pour un nouvel agent |
| [`dossierdeConception/`](dossierdeConception/) | Dossier de conception complet (cahier des charges, UML) |
| [`specifications/`](specifications/) | Spécifications obligatoires avant tout nouveau module |
| [`vision/`](vision/) | Vision produit, architecture produit, design system |

### 🏗️ Architecture, backend, plateformes
| Doc | Contenu |
|---|---|
| [`../ARCHITECTURE.md`](../ARCHITECTURE.md) | Vue d'ensemble monorepo + règles DDD |
| [`architecture/`](architecture/) | ADRs et décisions d'architecture |
| [`architecture/ARCHITECTURE_CICD.md`](architecture/ARCHITECTURE_CICD.md) | Architecture CI/CD |
| [`infra/`](infra/) | État infrastructure et alignement |
| [`api/`](api/) · [`../api/openapi.yaml`](../api/openapi.yaml) | Documentation API REST · spec OpenAPI canonique |
| [`modules/`](modules/) | Documentation par module métier |
| [`mobile/`](mobile/) | Documentation mobile (Flutter) |
| [`web/`](web/) · [`web/vitrine/`](web/vitrine/) | Frontend web · historique vitrine |
| [`admin/`](admin/) · [`client/`](client/) · [`kiosk/`](kiosk/) | Admin-dashboard · portail client · kiosk |
| [`edge-sync/`](edge-sync/) | Synchronisation edge/offline |
| [`ai/`](ai/) | Architecture IA |
| `api-mock-data/` (généré, non versionné — #7654) | Régénérer via `python dev-hub/tools/generate_api_examples.py` |

### 🔐 Sécurité & qualité
| Doc | Contenu |
|---|---|
| [`security/`](security/) | Politiques de sécurité, threat models |
| [`RGPD_REGISTRE_TRAITEMENTS.md`](RGPD_REGISTRE_TRAITEMENTS.md) | Registre RGPD des traitements |
| [`testing/`](testing/) · [`validation/`](validation/) · [`qualite/`](qualite/) | Stratégie de tests · dossiers de validation · dette qualité |
| [`qa/`](qa/) | Sessions QA — source de vérité de l'état courant |
| [`audits/`](audits/) · [`external-audits/`](external-audits/) | Audits internes · audits externes |

### 🚢 Déploiement & opérations
| Doc | Contenu |
|---|---|
| [`DEPLOYMENT_PRODUCTION.md`](DEPLOYMENT_PRODUCTION.md) · [`DEPLOYMENT_STAGING.md`](DEPLOYMENT_STAGING.md) | Déploiement production (Render) · staging |
| [`ops/MONITORING_SETUP.md`](ops/MONITORING_SETUP.md) · [`ops/ALERTS_CONFIGURATION.md`](ops/ALERTS_CONFIGURATION.md) | Monitoring · alertes |
| [`ops/RELEASE_PROCESS.md`](ops/RELEASE_PROCESS.md) | Processus de release |
| [`deployment/`](deployment/) · [`ops/`](ops/) | Docs de déploiement · opérations & domaines |
| [`CI_CD_SECRETS.md`](CI_CD_SECRETS.md) | Noms des secrets CI/CD (jamais les valeurs) |

### 📊 Produit & gestion de projet
| Doc | Contenu |
|---|---|
| [`REFERENTIEL_PRODUIT/`](REFERENTIEL_PRODUIT/) | Référentiel produit |
| [`archive/PILOTAGE.md`](archive/PILOTAGE.md) | ⚠️ Archivé (#6698, #7428) — gestion de projet = GitHub Issues/Projects |
| [`GESTION_PROJET/`](GESTION_PROJET/) | Runbooks, audits d'écarts, supports d'exécution |
| [`GOUVERNANCE/`](GOUVERNANCE/) · [`PROTOCOLES/`](PROTOCOLES/) | Gouvernance · protocoles P01-P07 |
| [`pilotes/`](pilotes/) · [`plan/`](plan/) · [`focus/`](focus/) | Carnets pilotes · plans d'exécution · programme FOCUS 2026 |

### 📈 Commercial & go-to-market (→ consolidation phase 1, §2)
| Doc | Contenu |
|---|---|
| [`GOTO_MARKET/`](GOTO_MARKET/) | Stratégie go-to-market (source de vérité business) |
| [`GTM/`](GTM/) | Outils de prospection (templates, cas clients) |
| [`STRATEGIE_COMMERCIALE/`](STRATEGIE_COMMERCIALE/) | Plans d'action commerciaux, modèles CRM — ⚠️ candidat dépôt privé (§3) |
| [`commercial/`](commercial/) | Benchmarks concurrents, appels d'offres |
| [`archive/LEOPARDO_STRATEGIC_ANALYSIS.md`](archive/LEOPARDO_STRATEGIC_ANALYSIS.md) | Analyse stratégique (archivée) |

### 🧩 Domaines métier & guides
| Doc | Contenu |
|---|---|
| [`HR/`](HR/) · [`accounting/`](accounting/) · [`payroll/`](payroll/) · [`attendance/`](attendance/) · [`expense/`](expense/) | RH · comptabilité · paie · pointage · notes de frais |
| [`restaurant/`](restaurant/) · [`travel/`](travel/) · [`contracts/`](contracts/) | Verticales Restaurant/Travel · contrats inter-BC |
| [`GUIDES/`](GUIDES/) · [`user-guide/`](user-guide/) · [`contributing/`](contributing/) | Guides utilisateurs · guides transverses · contribution |
| [`i18n/`](i18n/) | Suivi des lots d'internationalisation |
| [`design/`](design/) · [`assets/`](assets/) | Design (sources canoniques) · assets partagés |

### 📝 Historique & archive
| Doc | Contenu |
|---|---|
| [`archive/`](archive/) | Archives du projet (phases livrées, plans clos, `PLAN_ACTION*/`) |
| [`notes/`](notes/) | Notes techniques et archives de contexte (non-source-de-vérité) |
| [`PROMPTS_EXECUTION/`](PROMPTS_EXECUTION/) | Archive des prompts d'exécution AI — ⚠️ candidat dépôt privé (§3) |

> ⚠️ Les dossiers `PROMPTS_EXECUTION/` et `archive/PLAN_ACTION*/` sont des archives de référence : ne pas les modifier.
> **Depuis le 2026-07-26, la gestion de projet active se fait exclusivement via GitHub Issues / GitHub Projects**
> (voir `AGENTS.md` — PILOTAGE.md archivé, #6698).
