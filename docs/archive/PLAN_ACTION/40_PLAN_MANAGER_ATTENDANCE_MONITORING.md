# Plan 40 - Monitoring presence manager mobile

## Objectif

Rendre les routes manager mobiles deja prevues réellement exploitables avant lancement marketing :

- `/manager/attendance` : presence equipe du jour, sessions ouvertes, retards.
- `/manager/anomalies` : triage des anomalies issues de l'API.
- `/manager/corrections` : file des corrections employees avec decision RH.

## Realisation

- Backend : ajout de `GET /api/v1/attendance/corrections`.
- Backend : ajout de `PUT /api/v1/attendance/corrections/{id}/approve` et `PUT /api/v1/attendance/corrections/{id}/reject`.
- Backend : approbation tenant-scope qui applique ou cree le pointage manuel et passe par `AttendanceService::recalculateLog`.
- Mobile manager : remplacement des placeholders presence/anomalies/corrections par des ecrans `MobileSurface`.
- Mobile manager : actions d'approbation/refus avec rafraichissement de la file.
- Contrats : OpenAPI, `FrontendApiContractTest`, garde mobile workflow et scenarios API mis a jour.

## Points de vigilance

- Les decisions de correction restent reservees aux managers `principal` et `rh` via `AttendancePolicy::update`.
- Les employees peuvent toujours demander une correction, mais ne peuvent pas lire ni traiter la file.
- Les prochaines iterations doivent enrichir les filtres manager par equipe directe lorsque les sous-perimetres manager seront finalises.
