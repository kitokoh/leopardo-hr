# Mobile pompiste FuelStation (FUEL-013, issue #5807)

> **Statut : contrats backend livrés (surface `/fuel-station/me/*` sur
> `main`) + documentation.** L'écran mobile pompiste (UI Flutter) est
> **gelé par le freeze 60 jours** (`FREEZE_SCOPE_60J.md` : « Apps mobiles
> non-employee → builds verts garantis, fixes de régression ; convergence
> F-27 gelée »). Le contrat ci-dessous est stable et testé ; l'app sera
> intégrée à `leopardo_manager` (ou app dédiée) au gate J60 ou via
> exception fondateur (`[FREEZE-EXCEPTION]`).

## Contexte

Le pompiste (opérateur de station) travaille sur le terrain (pompes,
cuves) avec un terminal mobile. Il doit : pointer sa présence, consulter
son shift, enregistrer les relevés de compteurs, saisir les ventes,
ouvrir/suivre/clôturer sa caisse de session — le tout sans bureau.

## Surface API pompiste (déjà en production sur main)

| Besoin | Endpoint | Note |
|---|---|---|
| Mes shifts | `GET /api/v1/fuel-station/me/shifts` | shifts assignés au pompiste connecté |
| Ma présence | `GET /api/v1/fuel-station/me/presence` | pointer (entrée/sortie) |
| Mes caisses | `GET /api/v1/fuel-station/me/cash-sessions` | sessions de caisse du pompiste |
| Ouvrir une caisse | `POST /api/v1/fuel-station/cash-sessions` | fonds d'ouverture |
| Mouvement de caisse | `POST /api/v1/fuel-station/cash-sessions/{session}/movements` | dépôts/versements |
| Clôturer la caisse | `POST /api/v1/fuel-station/cash-sessions/{session}/close` | écart calculé serveur |
| Mes ventes | `GET /api/v1/fuel-station/me/sales` | ventes du pompiste |
| Saisir une vente | `POST /api/v1/fuel-station/sales` | prix serveur (jamais client) |
| Relevé compteur | `POST /api/v1/fuel-station/stations/{station}/pumps/{pump}/meters/{meter}/readings` | relevé + intervalle |
| Corriger un relevé | `POST /api/v1/fuel-station/meter-readings/{reading}/corrections` | correction tracée |

Toutes les routes sont authentifiées Sanctum, tenant-scope, avec résolution
du pompiste par `employee_id` (référence RH par valeur) — un pompiste ne
voit que SES shifts/caisses/ventes (isolation, 404 sûr).

## Hors-ligne (recommandation)

Pour les zones à réseau faible, réutiliser le pattern de file idempotente
(RESTO-804/TRAVEL-704) : opérations `sale.create`, `reading.create`,
`cash_session.close` rejouées avec `idempotency_key` — un rejeu ne crée
jamais de doublon. À implémenter avec l'app (gate J60).

## Critère d'acceptation FUEL-013

- [x] Parcours pompiste opérationnel en backend (surface `/fuel-station/me/*` + saisies)
- [ ] Écran mobile pompiste (Flutter) — **gelé** (FREEZE_SCOPE_60J.md, gate J60 ou exception fondateur)

## Preuves

- Controllers sur main : `FuelShiftController`, `FuelPresenceController`,
  `FuelCashSessionController`, `FuelSaleController`, `FuelMeterReadingController`
- Routes : `api/routes/modules/fuel_station.php` (`/fuel-station/me/*`)
- Tests Feature existants de la verticale (session, écart, relevés)
