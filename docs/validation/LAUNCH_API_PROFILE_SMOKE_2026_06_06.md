# Launch API Profile Smoke - 2026-06-06

## Objectif

Ajouter une recette API exploitable avant lancement marketing pour verifier les parcours critiques par profil sans exposer de secrets dans le depot.

Script canonique :

```powershell
powershell -ExecutionPolicy Bypass -File dev-hub\tools\launch-api-profile-smoke.ps1
```

Workflow GitHub manuel :

```text
Launch API Profile Smoke
```

Ce workflow lit les memes variables via secrets GitHub et publie l'artefact `launch-api-profile-smoke`.

## Variables d'environnement

- `LEOPARDO_API_BASE_URL` : base API, par defaut `https://gestionemployerbackend.onrender.com/api/v1`.
- `LEOPARDO_MANAGER_TOKEN` : token Bearer manager/RH.
- `LEOPARDO_EMPLOYEE_TOKEN` : token Bearer employe.
- `LEOPARDO_PLATFORM_ADMIN_TOKEN` : token Bearer super-admin plateforme.
- `LEOPARDO_KIOSK_DEVICE_CODE` : code borne kiosk.
- `LEOPARDO_KIOSK_TOKEN` : token `X-Kiosk-Token`.

Les profils sans token sont marques `SKIP`. Le script echoue uniquement si un endpoint configure retourne une erreur.

## Couverture

### Public

- `GET /health/live`
- `GET /health/ready`
- `GET /demo-users`

### Employee

- `GET /auth/me`
- `GET /attendance/today`
- `GET /me/monthly-summary`
- `GET /me/leave-balances`
- `GET /salary-advances?per_page=5`
- `GET /me/pay-slips`
- `GET /me/balance`
- `GET /notifications?unread=true`

### Manager / RH

- `GET /auth/me`
- `GET /dashboard/summary`
- `GET /employees?per_page=5`
- `GET /attendance/anomalies`
- `GET /payroll/mobile-summary`
- `GET /notifications?unread=true`

### Platform admin

- `GET /platform/auth/me`
- `GET /platform/companies?per_page=5`
- `GET /platform/plans`
- `GET /platform/country-defaults`
- `GET /platform/metrics/overview`
- `GET /platform/companies/health?limit=5`

### Kiosk

- `GET /kiosks/{deviceCode}/roster`
- `GET /kiosks/{deviceCode}/announcements`

## Ecriture controlee

La creation d'entreprise de test est volontairement desactivee par defaut. Elle ne s'execute que si l'option suivante est fournie :

```powershell
powershell -ExecutionPolicy Bypass -File dev-hub\tools\launch-api-profile-smoke.ps1 -IncludePlatformProvisioning
```

Cette option cree une entreprise `Plan72 Smoke <timestamp>` en statut `trial`. Elle doit etre reservee aux environnements de staging/demo ou aux fenetres de recette controlee.

## Statut

- Garde ajoute : `dev-hub/tools/launch-api-profile-smoke.ps1`.
- Workflow manuel ajoute : `.github/workflows/launch-api-profile-smoke.yml`.
- Gate release mis a jour pour verifier la presence du script, du workflow et de ce rapport.
- Correction outillage : les tokens absents doivent toujours produire `SKIP`, jamais decaler les arguments positionnels du smoke.
- Execution complete avec tokens a realiser par ops/CI protegee avant ouverture marketing large.
