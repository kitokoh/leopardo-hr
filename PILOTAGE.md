# 📑 PILOTAGE — LEOPARDO RH
# PROGRAM_VERSION = 4.16.250 | 2026-06-13
# CE FICHIER EST LA SOURCE DE VÉRITÉ OPÉRATIONNELLE DU PROJET

---

## LE PROJET EN 1 PHRASE

**Leopardo RH** = Mobile-First Company OS pour PME terrain (5-250 employés).
8 surfaces frontend, 87 modèles, 93 contrôleurs API, déployé en production.

---

## ÉTAT ACTUEL

```
Date MAJ       : 2026-06-13
Version        : 4.16.250
Phase active   : LOT P0 — Conversion commerciale (premier client payant)
Dernière release: 2026-06-06 (Plans 01-72 tous livrés)
Prochaine action: Self-service trial provisioning + intégration Stripe
Objectif 30j   : 3-5 clients payants, MRR 150-250€
```

---

## SURFACES DU PRODUIT

| Surface | Stack | Déploiement | Statut |
|---------|-------|-------------|--------|
| API Backend | Laravel 11 / PHP 8.4 / PostgreSQL 16 | Render | ✅ Production |
| Admin Dashboard | Vue 3 + Vite + Tailwind | Cloudflare Pages | ✅ Production |
| Vitrine Web | Next.js + TypeScript + Tailwind | Vercel | ✅ Production |
| App Employee | Flutter/Dart | Firebase App Distribution | ✅ Distribution |
| App Manager | Flutter/Dart | Firebase App Distribution | ✅ Distribution |
| App Platform Admin | Flutter/Dart | Firebase App Distribution | ✅ Distribution |
| Core Mobile Partagé | Flutter package (`leopardo_core`) | — | ✅ Actif |
| Kiosque ZKTeco | HTML/JS | — | 🚧 Prototype |

---

## ARCHITECTURE PRODUCTION

```
API           : gestionemployerbackend.onrender.com (Render Web Service)
Vitrine       : leopardo-hr.vercel.app (Vercel)
Admin         : Cloudflare Pages
BDD           : PostgreSQL 16 (Render managed)
Cache/Queues  : Upstash Redis (TLS) — queues: default, pdf, notifications, payroll, webhooks
Push          : Firebase Cloud Messaging (HTTP v1)
Distribution  : Firebase App Distribution (3 apps)
CI/CD         : GitHub Actions (25 workflows)
Auth          : Sanctum (tokens opaques) + 2FA super-admin
Multitenancy  : Shared schema PostgreSQL (shared_tenants)
```

---

## INVENTAIRE TECHNIQUE

| Composant | Quantité |
|-----------|----------|
| Modèles Eloquent | 87 |
| Contrôleurs API V1 | 93 |
| Services métier | 43 |
| Jobs asynchrones | 9 |
| Migrations (tenant + public) | 59 |
| Tests Feature | 99+ |
| OpenAPI spec | 332 KB |
| Workflows CI/CD | 25 |
| Plans d'action livrés | 72 |
| Scripts de validation | 27 |
| Couverture backend | 60%+ |

---

## PLANS D'ACTION — HISTORIQUE

| Plage | Thème | Statut |
|-------|-------|--------|
| Plans 01-12 | Architecture, API, paie, IA, véhicules, CI/CD, GTM | ✅ Livré |
| Plans 13-17 | Consolidation, solidification, couverture | ✅ Livré |
| Plans 18-24 | Expérience client, communication, readiness, i18n | ✅ Livré |
| Plans 25-29 | Mobile multi-app, release, excellence, platform admin | ✅ Livré |
| Plans 30-56 | Attendance, tâches, QR, dashboard, schedules, UX | ✅ Livré |
| Plans 57-65 | API docs, branding, double validation, PDF async | ✅ Livré |
| Plans 66-72 | Cartographie, audit final, lancement, market launch | ✅ Livré |
| **LOT P0** | **Conversion commerciale** | **🚧 En cours** |

---

## PRIORITÉS COURANTES

### 🔴 P0 — Conversion Commerciale (immédiat)
- Self-service trial provisioning (signup → tenant en < 30s)
- Intégration Stripe Checkout (plans Starter/Business)
- Page pricing fonctionnelle avec CTA paiement
- Pipeline CRM dans admin dashboard
- Email de bienvenue avec credentials

### 🟡 P1 — Solidification Produit
- Device QA sur appareils physiques (Samsung, Xiaomi, Huawei)
- Templates sectoriels BTP / Sécurité privée
- Exports comptables CSV/Excel
- Réduction phpstan-baseline (-500 erreurs)

### 🟢 P2 — Expérience Premier Client
- Wizard onboarding guidé in-app
- Email drip séquence trial → payant
- Stress test k6 endpoints critiques

### 🔵 P3 — Scale Technique
- Webhooks signés production
- Offline-first avancé (pointage sans réseau)
- Monitoring Sentry/Datadog

---

## CIBLES COMMERCIALES

| Métrique | Actuel | Cible 30j | Cible 90j | Cible 6 mois |
|----------|--------|-----------|-----------|--------------|
| MRR | 0€ | 150-250€ | 1 000-1 500€ | 5 000-7 000€ |
| Clients payants | 0 | 3-5 | 20-30 | 100-150 |
| Churn mensuel | N/A | < 10% | < 5% | < 4% |
| Conversion trial→payant | N/A | 15-25% | 20-30% | 25%+ |

---

## MARCHÉ CIBLE

**Positionnement :** Mobile-First Company OS for Field Teams in Emerging Markets

**Marchés prioritaires :**
1. Maghreb (DZ, MA, TN) — #1
2. Afrique de l'Ouest (SN, CI) — #2
3. Turquie — #3

**Secteurs prioritaires :**
1. Sécurité privée (pointage multi-sites, conformité)
2. BTP / Construction (suivi chantier, temps réel)

**Pricing (Maghreb) :**
- Gratuit : 0€ (≤5 employés)
- Starter : 39€/mois (5-50 employés)
- Business : 119€/mois (50-250 employés)
- Enterprise : Sur devis (250+ employés)

---

## RÈGLES ABSOLUES

```
1.  SCOPE       → Pas de nouveau module tant que P0 (premier client payant) n'est pas atteint.
2.  HORODATAGE  → now() côté serveur. JAMAIS le timestamp du client.
3.  TENANT      → Global Scope BelongsToCompany. JAMAIS de WHERE company_id dans les controllers.
4.  TESTS       → Écrire les tests AVANT le code. Couverture ≥ 60%.
5.  CI          → GitHub Actions est la source de vérité. Ne pas insister sur les checks locaux.
6.  DEPLOY      → Render deploy hook + healthcheck 30×20s + rollback automatique.
7.  MOBILE      → StartupGate obligatoire. runApp() avant tout await. Pas de page noire.
8.  PARSING     → requestWithRetry + extractDataList/extractDataMap sur tous les appels API mobiles.
9.  CHANGELOG   → Chaque changement = entrée CHANGELOG.md.
10. AGENTS.md   → Chaque leçon opérationnelle = mise à jour AGENTS.md.
```

---

## DOCUMENTS DE RÉFÉRENCE

| # | Document | Usage |
|---|----------|-------|
| 1 | `PILOTAGE.md` (ce fichier) | État projet, priorités, règles |
| 2 | `AGENTS.md` | Règles opérationnelles agents (123 KB) |
| 3 | `CHANGELOG.md` | Historique des changements (121 KB) |
| 4 | `LEOPARDO_STRATEGIC_ANALYSIS.md` | Analyse stratégique complète |
| 5 | `docs/CONTEXT/` | Contexte produit/technique/opérationnel |
| 6 | `docs/PLAN_ACTION/` | 72 plans d'action livrés |
| 7 | `docs/GOTO_MARKET/` | Stratégie commerciale |
| 8 | `api/openapi.yaml` | Spécification API (332 KB) |
