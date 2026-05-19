# Frontend API Contract Matrix

Derniere mise a jour : 2026-05-19

Objectif : garder les frontends admin, mobile et kiosk alignes avec le backend Laravel. Toute nouvelle route critique doit avoir un endpoint backend, un role attendu et un test associe.

## Contrats critiques

| Surface | Parcours | Endpoint | Role attendu | Test de garde |
|---|---|---|---|---|
| Mobile | Connexion | `POST /api/v1/auth/login` | public | `FrontendApiContractTest` |
| Mobile | Session courante | `GET /api/v1/auth/me` | authentifie | `FrontendApiContractTest` |
| Mobile | Deconnexion | `POST /api/v1/auth/logout` | authentifie | `FrontendApiContractTest` |
| Admin client | Resume dashboard | `GET /api/v1/dashboard/summary` | manager | `FrontendApiContractTest` |
| Admin client | Export audit | `GET /api/v1/audit-logs/export-csv` | manager/admin | `FrontendApiContractTest` |
| Admin client | Export employes | `GET /api/v1/export/employees` | manager | `ExportControllerTest`, `FrontendApiContractTest` |
| Admin client | Export contrats | `GET /api/v1/export/contracts` | manager | `ExportControllerTest`, `FrontendApiContractTest` |
| Admin client | Export vehicules | `GET /api/v1/export/vehicles` | manager | `ExportControllerTest`, `FrontendApiContractTest` |
| Admin client | Export bulletins | `GET /api/v1/export/pay-slips` | manager | `ExportControllerTest`, `FrontendApiContractTest` |
| Admin client | Export absences | `GET /api/v1/export/absences` | manager | `ExportControllerTest`, `FrontendApiContractTest` |
| Admin client | Export formations | `GET /api/v1/export/training` | manager | `ExportControllerTest`, `FrontendApiContractTest` |
| Kiosk | Enregistrement appareil | `POST /api/v1/kiosks` | device/public controle | `FrontendApiContractTest` |
| Kiosk | Pointage badge | `POST /api/v1/kiosks/{deviceCode}/punch` | device | `FrontendApiContractTest` |
| Kiosk | Pointage QR | `POST /api/v1/kiosks/{deviceCode}/qr-punch` | device | `FrontendApiContractTest` |
| Kiosk | Roster local | `GET /api/v1/kiosks/{deviceCode}/roster` | device | `FrontendApiContractTest` |
| Kiosk | Annonces | `GET /api/v1/kiosks/{deviceCode}/announcements` | device | `FrontendApiContractTest` |

## Regles

- `front/admin-dashboard` utilise `VITE_API_URL` pointe sur `.../api/v1`; les anciens appels `/v1/*` sont normalises par le client Axios.
- `front/zkteco-kiosk` accepte une base API avec ou sans `/api/v1`.
- Les exports admin passent par Axios authentifie afin de conserver le bearer token sur Cloudflare Pages.
- Une route critique supprimee ou renommee doit mettre a jour cette matrice, les appels frontend, la spec OpenAPI si exposee et le test contractuel.
