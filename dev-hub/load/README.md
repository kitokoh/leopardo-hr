# Load testing Leopardo RH

Ce dossier contient les scripts de charge k6 utilises pour mesurer les parcours API critiques sans modifier les donnees par defaut.

## Script disponible

| Script | Objectif | Mutations |
|---|---|---|
| `k6/api-core-smoke.js` | Health, dashboard manager, employees, attendance, payroll et self-service employe | Non |

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

## Profil par defaut

- `HEALTH_VUS=5` pendant `1m`
- `MANAGER_VUS=5` pendant `1m`
- `EMPLOYEE_VUS=5` pendant `1m`
- seuil global : moins de 2% d'erreurs HTTP ;
- p95 global sous 1200 ms ;
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

## Garde-fous

- Le script ne cree pas de pointage, run de paie, export bancaire ou document.
- Les tests destructifs ou batch doivent vivre dans un script separe avec un flag explicite.
- Ne jamais commiter de token dans ce dossier.
