# Frontend API Contract Matrix

Derniere mise a jour : 2026-05-25

Objectif : garder les frontends admin, mobile et kiosk alignes avec le backend Laravel. Toute nouvelle route critique doit avoir un endpoint backend, un role attendu et un test associe.

## Contrats critiques

| Surface | Parcours | Endpoint | Role attendu | Test de garde |
|---|---|---|---|---|
| Mobile | Connexion | `POST /api/v1/auth/login` | public | `FrontendApiContractTest` |
| Mobile | Session courante | `GET /api/v1/auth/me` | authentifie | `FrontendApiContractTest` |
| Mobile | Langue utilisateur | `PATCH /api/v1/auth/language` | authentifie | `FrontendApiContractTest` |
| Mobile | Changement mot de passe | `POST /api/v1/auth/change-password` | authentifie | `FrontendApiContractTest` |
| Mobile | Deconnexion | `POST /api/v1/auth/logout` | authentifie | `FrontendApiContractTest` |
| Mobile | Pointage entree | `POST /api/v1/attendance/check-in` | employe | `Attendance\CheckInTest`, `FrontendApiContractTest` |
| Mobile | Pointage sortie | `POST /api/v1/attendance/check-out` | employe | `FrontendApiContractTest` |
| Mobile | Demande correction pointage | `POST /api/v1/attendance/corrections` | employe | `FrontendApiContractTest` |
| Mobile | Modification pointage directe | `PUT /api/v1/attendance/{attendanceLog}` | manager principal/RH | `FrontendApiContractTest` |
| Mobile | Pointage du jour | `GET /api/v1/attendance/today` | employe/manager | `Attendance\TodayAndHistoryTest`, `FrontendApiContractTest` |
| Mobile | Historique pointage | `GET /api/v1/attendance` | employe/manager | `Attendance\TodayAndHistoryTest`, `ApiListQueryContractTest`, `FrontendApiContractTest` |
| Mobile | Liste equipe RH | `GET /api/v1/employees` | manager principal/RH | `ApiListQueryContractTest`, `FrontendApiContractTest`, `repository_contract_test.dart` |
| Mobile | Creation employe RH | `POST /api/v1/employees` | manager principal/RH | `repository_contract_test.dart` |
| Mobile | Liste absences | `GET /api/v1/absences` | employe/manager | `Absences\AbsenceIndexTest`, `ApiListQueryContractTest`, `FrontendApiContractTest` |
| Mobile | Demande absence | `POST /api/v1/absences` | employe | `Absences\AbsenceStoreTest`, `FrontendApiContractTest` |
| Mobile | Detail absence | `GET /api/v1/absences/{absence}` | employe/manager | `Absences\AbsenceShowTest`, `FrontendApiContractTest` |
| Mobile | Annulation absence | `DELETE /api/v1/absences/{absence}` | employe/manager | `FrontendApiContractTest` |
| Mobile | Approbation absence | `PUT /api/v1/absences/{absence}/approve` | manager principal/RH | `repository_contract_test.dart`, `mobile_marketing_readiness_test.dart` |
| Mobile | Refus absence | `PUT /api/v1/absences/{absence}/reject` | manager principal/RH | `repository_contract_test.dart`, `mobile_marketing_readiness_test.dart` |
| Mobile | Soldes conges | `GET /api/v1/me/leave-balances` | employe | `FrontendApiContractTest` |
| Mobile | Bulletins employe | `GET /api/v1/me/pay-slips` | employe | `ApiListQueryContractTest`, `FrontendApiContractTest` |
| Mobile | Detail bulletin | `GET /api/v1/me/pay-slips/{paySlip}` | employe | `ApiListQueryContractTest`, `FrontendApiContractTest` |
| Mobile | PDF bulletin | `GET /api/v1/me/pay-slips/{paySlip}/pdf` | employe | `FrontendApiContractTest` |
| Mobile | Liste avances salaire | `GET /api/v1/salary-advances` | employe/manager | `repository_contract_test.dart` |
| Mobile | Demande avance salaire | `POST /api/v1/salary-advances` | employe | `repository_contract_test.dart` |
| Mobile | Approbation avance salaire | `PUT /api/v1/salary-advances/{salaryAdvance}/approve` | manager principal/RH | `repository_contract_test.dart`, `mobile_marketing_readiness_test.dart` |
| Mobile | Refus avance salaire | `PUT /api/v1/salary-advances/{salaryAdvance}/reject` | manager principal/RH | `repository_contract_test.dart`, `mobile_marketing_readiness_test.dart` |
| Mobile / web client | Notifications | `GET /api/v1/notifications` | authentifie | `ApiListQueryContractTest`, `FrontendApiContractTest`, `NotificationControllerTest` |
| Mobile | Marquer notification lue | `PUT /api/v1/notifications/{notification}/read` | authentifie | `FrontendApiContractTest` |
| Mobile | Tout marquer lu | `PUT /api/v1/notifications/read-all` | authentifie | `FrontendApiContractTest` |
| Mobile / web client | Preferences notifications | `GET/PATCH /api/v1/notification-preferences` | authentifie | `NotificationPreferenceControllerTest` |
| Mobile | Enregistrer push token | `POST /api/v1/device-tokens` | authentifie | `FrontendApiContractTest` |
| Mobile | Supprimer push token | `DELETE /api/v1/device-tokens` | authentifie | `FrontendApiContractTest` |
| Admin client | Resume dashboard | `GET /api/v1/dashboard/summary` | manager | `DashboardControllerTest`, `FrontendApiContractTest` |
| Admin client | Activite recente dashboard | `GET /api/v1/dashboard/recent-activity` | manager | `DashboardControllerTest`, `FrontendApiContractTest` |
| Admin client | Readiness lancement | `GET /api/v1/launch-readiness` | manager principal/RH | `LaunchReadinessControllerTest` |
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
| Kiosk | Synchronisation offline | `POST /api/v1/kiosks/{deviceCode}/sync` | device | `FrontendApiContractTest` |
| Kiosk | Roster local | `GET /api/v1/kiosks/{deviceCode}/roster` | device | `FrontendApiContractTest` |
| Kiosk | Infos employe | `POST /api/v1/kiosks/{deviceCode}/employee-info` | device | `FrontendJsonContractTest`, `FrontendApiContractTest` |
| Kiosk | Solde conges employe | `POST /api/v1/kiosks/{deviceCode}/leave-balance` | device | `FrontendJsonContractTest`, `FrontendApiContractTest` |
| Kiosk | Annonces | `GET /api/v1/kiosks/{deviceCode}/announcements` | device | `FrontendApiContractTest` |

## Regles

- `front/admin-dashboard` utilise `VITE_API_URL` pointe sur `.../api/v1`; les anciens appels `/v1/*` sont normalises par le client Axios.
- `front/zkteco-kiosk` accepte une base API avec ou sans `/api/v1`.
- Les exports admin passent par Axios authentifie afin de conserver le bearer token sur Cloudflare Pages.
- Une route critique supprimee ou renommee doit mettre a jour cette matrice, les appels frontend, la spec OpenAPI si exposee et le test contractuel.
