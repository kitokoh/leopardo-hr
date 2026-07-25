# Load testing Leopardo RH

Ce dossier contient les scripts de charge k6 utilises pour mesurer les parcours API critiques sans modifier les donnees par defaut.

## Script disponible

| Script | Objectif | Mutations |
|---|---|---|
| `k6/api-core-smoke.js` | Health, auth session, dashboard manager, employees, attendance, payroll et self-service employe | Non |
| `k6/employee-100-attendance-payroll.js` | Benchmark 100 employes simultanes sur pointage, historique et consultation paie | Non par defaut ; check-in optionnel avec `ALLOW_ATTENDANCE_MUTATIONS=true` |
| `k6/attendance-punch-scale.js` | Charge progressive pointage (10/20/50/100 VUs) manuel (`check-in`/`check-out`) ou path-based (`smart-attendance/geo-events`) | Oui, punchs reels crees a chaque iteration (voir Garde-fous) |
| `k6/payroll-500-batch.js` | Benchmark calcul paie 500 employes avec seuil < 30 s | Lecture par defaut ; calcul optionnel avec `ALLOW_PAYROLL_MUTATIONS=true` |
| `k6/payroll-progressive-scale.js` | Charge progressive paie (10/20/50/100 VUs) : preview cycle, paiement en masse et sante de la queue de notifications async | Lecture par defaut (preview + observability) ; paiement en masse reel optionnel avec `ALLOW_PAYROLL_MUTATIONS=true` |
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

## Execution GitHub Actions

Le workflow manuel `k6 Load Smoke - Leopardo RH` lance `k6/api-core-smoke.js` via Docker et publie `api-core-smoke-summary.json` en artefact.

Secrets optionnels :

- `K6_MANAGER_TOKEN` : token Sanctum manager/RH du tenant staging.
- `K6_EMPLOYEE_TOKEN` : token Sanctum employe du meme tenant.

Sans token, le workflow reste utile en smoke health-only : les scenarios manager/employe retombent sur les sondes health au lieu de muter des donnees.

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

### Pointage progressif 10/20/50/100 (PA2-QA-004)

Montee en charge par palier sur le pointage, en mode manuel (`check-in`/`check-out` classiques) ou path-based (evenements geofence `zone_enter`/`zone_exit` de SmartAttendance). Chaque palier dure `STAGE_DURATION` (30s par defaut) et le VU cible peut etre ajuste par palier.

Mode manuel (par defaut) :

```bash
BASE_URL="https://api-staging.leopardo-rh.com" \
EMPLOYEE_TOKENS="token1,token2,token3" \
PUNCH_MODE=manual \
k6 run dev-hub/load/k6/attendance-punch-scale.js
```

Mode path-based (geofence) :

```bash
BASE_URL="https://api-staging.leopardo-rh.com" \
EMPLOYEE_TOKENS="token1,token2,token3" \
PUNCH_MODE=path \
GEO_LAT=33.5731 GEO_LNG=-7.5898 \
k6 run dev-hub/load/k6/attendance-punch-scale.js
```

Paliers par defaut : 10 -> 20 -> 50 -> 100 VUs, 30s chacun (`STAGE_1_VUS`..`STAGE_4_VUS`, `STAGE_DURATION`). Seuils : moins de 2% d'erreurs, p95 punch < 1500 ms, zero echec non attendu (statuts hors 200/201/409/422 comptes comme echec).

**Attention** : ce script cree reellement des pointages (check-in/check-out ou sessions geofence) a chaque iteration, contrairement aux autres scripts read-only de ce dossier. Executer uniquement sur un tenant de staging dedie aux tests de charge, jamais en production. Utiliser `ALLOW_CHECKOUT=false` pour ne garder que le check-in si l'on souhaite limiter le volume de mutations en mode manuel.

### Paie progressif 10/20/50/100 (PA2-QA-005)

Miroir paie de `attendance-punch-scale.js` (PA2-QA-004) : memes 4 paliers 10/20/50/100 VUs, memes variables `STAGE_1_VUS`..`STAGE_4_VUS`/`STAGE_DURATION`/`STAGE_GRACEFUL_RAMPDOWN`. Couvre les 3 criteres d'acceptation du ticket a chaque iteration de chaque VU :

1. **Preview paie** — `GET /api/v1/payroll/cycles/preview` (PA2-PAY-003), lecture pure, toujours actif.
2. **Batch paiement** — `POST /api/v1/payroll-runs/{id}/bulk-pay` (PA2-PAY-013/`ProcessBulkPaymentJob`) puis poll de `GET .../bulk-pay/status` ; **desactive par defaut**, necessite `ALLOW_PAYROLL_MUTATIONS=true` et un run reel par `PAYROLL_RUN_IDS`.
3. **Notification async** — poll de `GET /api/v1/platform/observability/queues` (PA2-QA-006) pour verifier que la queue de notifications post-paiement absorbe la charge (depth/failed jobs) sous pression, plutot que d'appeler un canal de notification qui n'a pas de contrat HTTP synchrone observable depuis k6.

Mode lecture uniquement (preview + observability), sans risque de double-paiement :

```bash
BASE_URL="https://api-staging.leopardo-rh.com" \
MANAGER_TOKENS="token1,token2,token3" \
k6 run dev-hub/load/k6/payroll-progressive-scale.js
```

Avec paiement en masse reel (staging uniquement, un `PAYROLL_RUN_ID` validable/calculable par token) :

```bash
BASE_URL="https://api-staging.leopardo-rh.com" \
MANAGER_TOKENS="token1,token2,token3" \
PAYROLL_RUN_IDS="101,102,103" \
ALLOW_PAYROLL_MUTATIONS=true \
k6 run dev-hub/load/k6/payroll-progressive-scale.js
```

Seuils : moins de 2% d'erreurs, p95 preview < 2000 ms, p95 dispatch bulk-pay < 3000 ms, p95 poll observability < 1500 ms, zero echec non attendu par groupe.

**Attention** : avec `ALLOW_PAYROLL_MUTATIONS=true`, ce script declenche reellement `ProcessBulkPaymentJob` sur chaque `PAYROLL_RUN_ID` fourni a chaque iteration du VU correspondant. Executer uniquement sur un tenant de staging dedie, avec des runs de paie jetables, jamais sur un run reel destine a payer de vrais employes.

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

Les valeurs `*_VUS` inferieures a `1` sont normalisees a leur defaut pour respecter la configuration k6.

### Variables `attendance-punch-scale.js`

| Variable | Defaut | Usage |
|---|---|---|
| `PUNCH_MODE` | `manual` | `manual` (check-in/check-out) ou `path` (geofence zone_enter/zone_exit) |
| `EMPLOYEE_TOKENS` | vide | Tokens Sanctum employe, un par VU en round-robin (fallback health si vide) |
| `STAGE_1_VUS`..`STAGE_4_VUS` | `10`/`20`/`50`/`100` | VUs cibles par palier |
| `STAGE_DURATION` | `30s` | Duree de chaque palier |
| `STAGE_GRACEFUL_RAMPDOWN` | `10s` | Temps de descente en fin de test |
| `ALLOW_CHECKOUT` | `true` | Desactiver pour ne faire que le check-in en mode manuel |
| `GEO_LAT` / `GEO_LNG` / `GEO_ACCURACY_METERS` | `33.5731` / `-7.5898` / `15` | Coordonnees utilisees pour les evenements geofence en mode path |
| `GEO_DWELL_SECONDS` | `1` | Delai entre `zone_enter` et `zone_exit` |
| `PUNCH_SLEEP` | `1` | Pause entre iterations d'un VU |

### Variables `payroll-progressive-scale.js`

| Variable | Defaut | Usage |
|---|---|---|
| `MANAGER_TOKENS` | vide | Tokens Sanctum manager/RH, un par VU en round-robin (fallback health si vide) |
| `PAYROLL_RUN_IDS` | vide | IDs de payroll run valides/calculables, un par VU en round-robin (bulk-pay ignore si vide) |
| `ALLOW_PAYROLL_MUTATIONS` | `false` | Active le dispatch reel de `bulk-pay` (sinon preview + observability uniquement) |
| `PREVIEW_FREQUENCY` | `monthly` | Frequence candidate envoyee a `payroll/cycles/preview` |
| `STAGE_1_VUS`..`STAGE_4_VUS` | `10`/`20`/`50`/`100` | VUs cibles par palier |
| `STAGE_DURATION` | `30s` | Duree de chaque palier |
| `STAGE_GRACEFUL_RAMPDOWN` | `10s` | Temps de descente en fin de test |
| `BULK_PAY_POLL_DELAY_SECONDS` | `1` | Delai avant le premier poll de statut apres un dispatch bulk-pay accepte |
| `PAYROLL_SLEEP` | `1` | Pause entre iterations d'un VU |

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
