# Benchmarks Performance — Leopardo RH

Derniere mise a jour : 2026-05-12

## Methodologie

Tests executes avec **k6** (load testing) sur l'API backend deployee sur Render.
Scripts source : `dev-hub/load/k6/`

## 1. API Core Smoke (baseline)

| Endpoint | Methode | P50 | P95 | P99 | Statut |
|----------|---------|-----|-----|-----|--------|
| `/api/v1/health/live` | GET | 15ms | 45ms | 90ms | 200 OK |
| `/api/v1/health/ready` | GET | 25ms | 80ms | 150ms | 200 OK |
| `/api/v1/auth/login` | POST | 120ms | 280ms | 450ms | 200 OK |
| `/api/v1/auth/me` | GET | 35ms | 90ms | 160ms | 200 OK |
| `/docs` (OpenAPI) | GET | 80ms | 200ms | 350ms | 200 OK |

**Source :** `dev-hub/load/k6/api-core-smoke.js`

## 2. Pointage simultane — 100 employes

Simulation de 100 employes pointant simultanement (check-in + check-out).

| Metrique | Valeur |
|----------|--------|
| Utilisateurs virtuels | 100 |
| Duree | 5 min |
| Requetes totales | 12 000 |
| Taux de succes | 99.8% |
| P95 check-in | 180ms |
| P95 check-out | 190ms |
| Debit | 40 req/s |

**Source :** `dev-hub/load/k6/employee-100-attendance-payroll.js`

## 3. Calcul paie batch — 500 employes

Execution d'un cycle de paie complet pour 500 employes.

| Metrique | Valeur |
|----------|--------|
| Employes | 500 |
| Temps calcul total | 18.4s |
| Temps par employe | 36ms |
| Generation bulletins PDF | 22.1s (async queue) |
| Export bancaire SEPA | 0.8s |
| SLA cible (< 30s) | **PASSE** |

**Source :** `dev-hub/load/k6/payroll-500-batch.js`

## 4. Dashboard admin — 10K employes

Navigation du dashboard avec une base de 10 000 employes.

| Endpoint | P50 | P95 | Pagine |
|----------|-----|-----|--------|
| `/api/v1/employees?page=1&per_page=25` | 85ms | 220ms | Oui |
| `/api/v1/employees?search=ali` | 120ms | 310ms | Oui |
| `/api/v1/dashboard/kpi` | 95ms | 250ms | Cache |
| `/api/v1/analytics/headcount` | 110ms | 280ms | Cache |
| `/api/v1/payroll-runs` | 70ms | 180ms | Oui |

**Source :** `dev-hub/load/k6/admin-dashboard-10k.js`

## 5. Synthese SLA

| Metrique | Objectif | Mesure actuelle | Statut |
|----------|----------|-----------------|--------|
| Disponibilite | 99.9% | 99.95% | PASSE |
| P95 endpoints read | < 500ms | 310ms | PASSE |
| P95 endpoints write | < 800ms | 450ms | PASSE |
| Calcul paie 500 emp | < 30s | 18.4s | PASSE |
| Pointage simultane 100 | < 300ms P95 | 190ms | PASSE |
| Recherche 10K employes | < 500ms P95 | 310ms | PASSE |

## 6. Optimisations appliquees

| Optimisation | Impact |
|-------------|--------|
| Cache Redis (dashboard, analytics) | -60% latence endpoints read-heavy |
| Queue async (paie batch, PDF) | Pas de timeout sur gros calculs |
| Indexation PostgreSQL (company_id, status, employee_id) | -70% temps requetes filtrees |
| Compression gzip/brotli | -65% taille reponses |
| Code splitting Vue.js (lazy loading) | -40% bundle initial admin |
| Eager loading (N+1 corrections) | -80% requetes rapport mensuel |

## 7. Infrastructure

| Composant | Config | Cout |
|-----------|--------|------|
| API (Render) | 1 instance, 512MB RAM | 7 USD/mois |
| PostgreSQL (Render) | Plan starter, 1GB | 7 USD/mois |
| Redis (Render) | Plan starter, 25MB | 0 USD/mois |
| Admin (Cloudflare Pages) | CDN global | Gratuit |
| Vitrine (Vercel) | Hobby | Gratuit |

**Total infra production : ~14 USD/mois** (scalable verticalement sur Render Pro pour les clients enterprise).
