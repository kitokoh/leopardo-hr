# Documentation — Leopardo HR

Index de la documentation technique et stratégique du projet.

> Pour la structure globale du monorepo, voir [`ARCHITECTURE.md`](../ARCHITECTURE.md) à la racine.

---

## 🚀 Démarrage rapide

| Doc | Contenu |
|---|---|
| [`QUICKSTART.md`](QUICKSTART.md) | Setup local en 5 minutes |
| [`DEMARRAGE_RAPIDE.md`](DEMARRAGE_RAPIDE.md) | Guide de démarrage détaillé |
| [`../DEVELOPMENT.md`](../DEVELOPMENT.md) | Conventions de développement |
| [`../MONOREPO_TOOLING.md`](MONOREPO_TOOLING.md) | Commandes melos, npm --prefix, Makefile backend |

---

## 🧭 Contexte & conception

| Doc | Contenu |
|---|---|
| [`CONTEXT/`](CONTEXT/) | Contexte rapide pour une nouvelle IA / un nouvel intervenant |
| [`dossierdeConception/`](dossierdeConception/) | Dossier de conception complet (cahier des charges source, diagrammes UML, dossierSonnet) |
| [`specifications/`](specifications/) | Spécifications fonctionnelles/techniques obligatoires avant tout nouveau module (règle d'or `AGENTS.md`) |
| [`vision/`](vision/) | Vision produit, architecture produit, design system, phases 2 |

---

## 🏗️ Architecture

| Doc | Contenu |
|---|---|
| [`../ARCHITECTURE.md`](../ARCHITECTURE.md) | Vue d'ensemble monorepo + règles DDD |
| [`architecture/`](architecture/) | ADRs et décisions d'architecture |
| [`infra/`](infra/) | État infrastructure et alignement |

---

## 🔧 Backend

| Doc | Contenu |
|---|---|
| [`api/`](api/) | Documentation API REST |
| [`../api/openapi.yaml`](../api/openapi.yaml) | Spec OpenAPI 3.x (source canonique) |
| [`modules/`](modules/) | Documentation par module métier |
| [`../api/MULTILANG.md`](../api/MULTILANG.md) | Multilinguisme backend |

---

## 📱 Mobile

| Doc | Contenu |
|---|---|
| [`mobile/`](mobile/) | Documentation mobile (Flutter) |
| [`../MONOREPO_TOOLING.md`](MONOREPO_TOOLING.md) | Usage Melos |

---

## 🌐 Web

| Doc | Contenu |
|---|---|
| [`web/`](web/) | Documentation frontend web |
| [`web/vitrine/`](web/vitrine/) | Historique d'implémentation vitrine (phases 1-7) |

---

## 🔐 Sécurité & Qualité

| Doc | Contenu |
|---|---|
| [`security/`](security/) | Politiques de sécurité |
| [`testing/`](testing/) | Stratégie de tests |
| [`validation/`](validation/) | Dossiers de validation fonctionnelle par domaine |
| [`audits/`](audits/) | Audits internes (doc, CI/CD, sécurité, global) |
| [`external-audits/`](external-audits/) | Audits externes (ex. ORION) |
| [`../api/README.md`](../api/README.md) | Setup backend et tests |

---

## 🚢 Déploiement

| Doc | Contenu |
|---|---|
| [`DEPLOYMENT_PRODUCTION.md`](DEPLOYMENT_PRODUCTION.md) | Déploiement production (Render) |
| [`DEPLOYMENT_STAGING.md`](DEPLOYMENT_STAGING.md) | Déploiement staging |
| [`MONITORING_SETUP.md`](MONITORING_SETUP.md) | Setup monitoring et alertes |
| [`ALERTS_CONFIGURATION.md`](ALERTS_CONFIGURATION.md) | Configuration des alertes |
| [`RELEASE_PROCESS.md`](RELEASE_PROCESS.md) | Processus de release |
| [`deployment/`](deployment/) | Docs de déploiement détaillées |

---

## 📊 Produit & Gestion de projet

| Doc | Contenu |
|---|---|
| [`REFERENTIEL_PRODUIT/`](REFERENTIEL_PRODUIT/) | Référentiel produit |
| [`../PILOTAGE.md`](../PILOTAGE.md) | Pilotage projet (filières actives, source de vérité programme) |
| [`GESTION_PROJET/`](GESTION_PROJET/) | Runbooks, audits d'écarts, supports d'exécution, réponse au cahier des charges |
| [`api/README.md`](api/README.md) | Documentation API |

---

## 📈 Commercial & Go-to-market

| Doc | Contenu |
|---|---|
| [`GOTO_MARKET/`](GOTO_MARKET/) | Stratégie go-to-market (source de vérité business) |
| [`GOTO_MARKET/LEOPARDO_STRATEGIC_ANALYSIS.md`](GOTO_MARKET/LEOPARDO_STRATEGIC_ANALYSIS.md) | Analyse stratégique |
| [`GTM/`](GTM/) | Outils opérationnels de prospection (templates, cas clients, good first issues) |
| [`STRATEGIE_COMMERCIALE/`](STRATEGIE_COMMERCIALE/) | Plans d'action commerciaux, modèles CRM |
| [`commercial/`](commercial/) | Benchmarks concurrents, dossiers techniques appels d'offres |
| [`GUIDES/`](GUIDES/) | Guides utilisateurs (employé, manager, intégration partenaires, traduction) |

---

## 🤖 IA & données

| Doc | Contenu |
|---|---|
| [`ai/`](ai/) | Architecture IA (`AI_ARCHITECTURE.md`) |
| [`api-mock-data/`](api-mock-data/) | Jeux de données mock pour l'API |
| [`edge-sync/`](edge-sync/) | Architecture de synchronisation edge/offline |
| [`kiosk/`](kiosk/) | Documentation kiosk (pointage biométrique/QR) |
| [`admin/`](admin/) | Documentation admin-dashboard |
| [`assets/`](assets/) | Assets partagés (branding mobile, etc.) |

---

## 📝 Historique & Archive

| Doc | Contenu |
|---|---|
| [`PROMPTS_EXECUTION/`](PROMPTS_EXECUTION/) | Archive des prompts d'exécution AI (v2 legacy, v3 actif) |
| [`archive/PLAN_ACTION/`](archive/PLAN_ACTION/) | Plans d'action historiques (01-72), tous livrés |
| [`archive/PLAN_ACTION2/`](archive/PLAN_ACTION2/) | Backlog atomique PA2 historique (00-27), remplacé par les GitHub Issues |
| [`PLAN_ACTION2/`](PLAN_ACTION2/) | Plans d'action post-audit 2026 (130 tickets PA2, actif du 2026-06-13 au 2026-07-26) — **clos/obsolète depuis le 2026-07-26** ; ne contient plus qu'un `README.md` de redirection vers les GitHub Issues |
| [`notes/`](notes/) | Notes et archives de contexte (non-source-de-vérité) |

> ⚠️ Les dossiers `PROMPTS_EXECUTION/` et `PLAN_ACTION*/`/`archive/PLAN_ACTION*/` sont des archives de référence.
> Ne pas les modifier. **Depuis le 2026-07-26, la gestion de projet active se fait exclusivement
> via GitHub Issues et GitHub Projects** (voir `AGENTS.md`, section « NOUVELLE MÉTHODE DE GESTION
> DE PROJET ») — ne pas chercher de travail dans `PLAN_ACTION2/` ni y créer de nouveaux tickets.
> Consulter `PILOTAGE.md` pour les filières actives.

---

## 🤝 Contribution

| Doc | Contenu |
|---|---|
| [`../CONTRIBUTING.md`](../CONTRIBUTING.md) | Guide de contribution |
| [`../CONVENTIONS.md`](../CONVENTIONS.md) | Conventions de code |
| [`../CODEOWNERS`](../CODEOWNERS) | Owners par zone du code |
| [`contributing/`](contributing/) | Guides de contribution détaillés |
