# Load testing Leopardo RH

Ce dossier contient les scripts de charge k6 utilises pour mesurer les parcours API critiques sans modifier les donnees par defaut.

## Script disponible

| Script | Objectif | Mutations |
|---|---|---|
| `k6/api-core-smoke.js` | Health, auth session, dashboard manager, employees, attendance, payroll et self-service employe | Non |
| `k6/employee-100-attendance-payroll.js` | Benchmark 100 employes simultanes sur pointage, historique et consultation paie | Non par defaut ; check-in optionnel avec `ALLOW_ATTENDANCE_MUTATIONS=true` |
| `k6/payroll-500-batch.js` | Benchmark calcul paie 500 employes avec seuil < 30 s | Lecture par defaut ; calcul optionnel avec `ALLOW_PAYROLL_MUTATIONS=true` |
| `k6/admin-dashboard-10k.js` | Benchmark dashboard admin + pagination/search sur tenant 10k employes | Non |

## Prerequis

- Installer k6 : https://grafana.com/docs/k6/latest/set-up/install-k6/
- Utiliser une base de staging ou de preproduction, jamais la production client sans fenetre de test.
- Generer deux tokens Sanctum de test :
  - `MANAGER_TOKEN` pour un manager/RH du tenant cible ;
  - `EMPLOYEE_TOKEN` pour un employe du meme tenant.

## Execution locale

```bash
BASE_URL="https://api-staging.leopardo-rh.com" \
MANAGER_TOKEN="..." \
EMPLOYEE_TOKEN="..." \
k6 run dev-hub/load/k6/api-core-smoke.js
```

## Benchmarks cibles Plan 14

### 100 employes simultanes

```bash
BASE_URL="https://api-staging.leopardo-rh.com" \
EMPLOYEE_TOKENS="token1,token2,token3" \
k6 run dev-hub/load/k6/employee-100-attendance-payroll.js
```

Seuils : moins de 2% d'erreurs, p95 attendance < 1500 ms, p95 paie self-service < 1800 ms.

### Paie 500 employes

Preparer un tenant staging avec 500 employes actifs et un `PAYROLL_RUN_ID` en brouillon/calculable.

```bash
BASE_URL="https://api-staging.leopardo-rh.com" \
MANAGER_TOKEN="..." \
PAYROLL_RUN_ID="123" \
ALLOW_PAYROLL_MUTATIONS=true \
k6 run dev-hub/load/k6/payroll-500-batch.js
```

Seuil : `POST /api/v1/payroll-runs/{id}/calculate` p95 < 30000 ms.

### Dashboard admin 10k employes

```bash
BASE_URL="https://api-staging.leopardo-rh.com" \
MANAGER_TOKEN="..." \
k6 run dev-hub/load/k6/admin-dashboard-10k.js
```

Seuils : p95 dashboard < 1500 ms, liste/search employes < 1800 ms.

## Profil par defaut

- `HEALTH_VUS=5` pendant `1m`
- `MANAGER_VUS=5` pendant `1m`
- `EMPLOYEE_VUS=5` pendant `1m`
- seuil global : moins de 2% d'erreurs HTTP ;
- p95 global sous 1200 ms ;
- p95 dashboard sous 1000 ms sur `auth/me`, `dashboard/summary`, `dashboard/recent-activity` et `dashboard/kpi` ;
- p95 paie sous 1500 ms.

## Variables utiles

| Variable | Defaut | Usage |
|---|---|---|
| `BASE_URL` | `http://localhost:8000` | API cible |
| `MANAGER_TOKEN` | vide | Active les parcours manager/RH |
| `EMPLOYEE_TOKEN` | `MANAGER_TOKEN` | Active les parcours self-service |
| `HEALTH_VUS` | `5` | Utilisateurs virtuels health |
| `MANAGER_VUS` | `5` | Utilisateurs virtuels manager |
| `EMPLOYEE_VUS` | `5` | Utilisateurs virtuels employe |
| `HEALTH_DURATION` | `1m` | Duree health |
| `MANAGER_DURATION` | `1m` | Duree manager |
| `EMPLOYEE_DURATION` | `1m` | Duree employe |

## Procedure de benchmark Plan 14

1. Creer un tenant staging avec au moins 100 employes.
2. Generer un token manager et un token employe.
3. Lancer le smoke de base pour valider les seuils.
4. Augmenter progressivement `MANAGER_VUS` et `EMPLOYEE_VUS`.
5. Consigner les resultats dans un rapport date : p50, p95, taux d'erreur, endpoints les plus lents.
6. Ouvrir un ticket par goulot : N+1, index absent, payload trop lourd, cache manquant.

## Corrections N+1 / scalabilite livrees

- `AttendanceMonthlyReportService` groupe les logs par `employee_id` avant de produire les lignes employes, afin d'eviter un scan complet des logs pour chaque employe du rapport.
- `OrgChartController` construit l'arbre depuis une collection groupee par `manager_id` et scope explicitement les lectures sur `company_id`, afin d'eviter le rescanning O(n^2) et de garder l'isolation tenant lisible.

## Garde-fous

- Le script ne cree pas de pointage, run de paie, export bancaire ou document.
- Les tests destructifs ou batch doivent vivre dans un script separe avec un flag explicite.
- Ne jamais commiter de token dans ce dossier.
