# Frontend/API contract governance report - 2026-06-01

## Decision

Le Plan 68.2 est livre au niveau gouvernance : les contrats critiques sont controles par une chaine unique plutot que par des notes dispersees.

## Chaine de controle

| Niveau | Fichier / workflow | Role |
|---|---|---|
| Matrice fonctionnelle | `docs/validation/FRONTEND_API_CONTRACT_MATRIX.md` | Lie surface, parcours, endpoint, role et test |
| Test backend route existence | `api/tests/Feature/FrontendApiContractTest.php` | Echoue si une route critique consommee par frontend disparait |
| Contrat mobile actionnable | `dev-hub/tools/mobile-workflow-contracts.json` | Lie routes mobiles, endpoints, fichiers d'ecran et tokens d'action |
| CI mobile | `.github/workflows/mobile-apps-ci.yml` | Execute les gardes multi-app et workflow contracts |
| CI OpenAPI | `.github/workflows/openapi-ci.yml` | Valide `api/openapi.yaml` avec Redocly |
| Garde Plan 68.2 | `dev-hub/tools/validate-frontend-api-contract-governance.ps1` | Verifie que les preuves ci-dessus restent reliees |

## Routes critiques verifiees

- Auth : `POST /api/v1/auth/login`
- Pointage : `POST /api/v1/attendance/check-in`, `GET /api/v1/attendance/today`
- Taches : `GET /api/v1/tasks/today`
- Branding tenant : `GET /api/v1/company/branding`
- Notifications push : `POST /api/v1/device-tokens`
- Platform admin : `GET/POST /api/v1/platform/companies`
- Kiosk : `POST /api/v1/kiosks/{deviceCode}/punch`

## Validation executee

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File dev-hub/tools/validate-frontend-api-contract-governance.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File dev-hub/tools/validate-mobile-workflow-contracts.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File dev-hub/tools/release-readiness.ps1 -Strict
```

## Risques restants

- La couverture OpenAPI de toutes les routes non critiques reste un chantier plus large.
- Le test PHPUnit complet reste source de verite CI pour confirmer la resolution Laravel reelle.
- Toute nouvelle route frontend doit ajouter la ligne matrice et le test avant merge.
