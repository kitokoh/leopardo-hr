# Guide utilisateur — Pointage (Présences)

> Issue #5269 · Module Attendance — modes unifiés (mobile, kiosque, géo),
> corrections, rapports par période. S'applique aux rôles employé, manager
> (principal/RH) et superviseur.

## 1. Pointer (employé)

- **Mobile** : `POST /api/v1/attendance/check-in` / `check-out` (heure serveur ;
  GPS optionnel — hors zone ≠ bloquant, contexte géofence « doux »).
- **Kiosque / QR** : badge ou QR au kiosque (`/kiosks/{deviceCode}/punch`,
  `qr-punch`) — synchronisation offline-first via le bridge local.
- **Géo (SmartAttendance)** : événements `zone_enter` / `zone_exit` (session
  ouverte/fermée automatiquement).

Règles : tolérance de retard (horaire type), heures supplémentaires comptées
(`overtime_hours`), sessions multiples par jour (`session_number`).

## 2. Corriger un pointage

- **Employé** : demande de correction (`POST /attendance/corrections` — motif,
  justificatif) → approbation manager (workflow tracé).
- **Manager/RH** : liste des demandes (`GET /attendance/corrections`),
  approbation/rejet (`POST .../approve|reject`) ; modification directe
  (`PUT /attendance/{log}`) réservée principal/RH.

## 3. Rapports par période (manager)

`GET /api/v1/attendance/monthly-report` avec :

| Paramètre | Valeurs | Rôle |
|---|---|---|
| `period` | `day` · `week` · `month` (défaut month) | Fenêtre |
| `date` / `week` / `month` | ancre (Y-m-d / Y-m-d / Y-m) | Période |
| `employee_id` / `department_id` | filtres | Équipe/employé |
| `format` | `json` · `csv` · `pdf` | Export |

Totaux : jours travaillés, heures, heures sup, retards, masse salariale
estimée — par employé et par équipe. Synthèse paie : heures/HS par employé.

## 4. Bonnes pratiques

- Pointage = source de vérité paie (jours travaillés, HS, congés → run de
  paie, cf. #5245) : corriger les anomalies avant la clôture de période.
- Après clôture, le run est verrouillé : les corrections passent par la
  régularisation (type `regularization`).

---
*Contrat API détaillé : `api/openapi.yaml` (routes `/attendance/*`,
`/kiosks/*`, SmartAttendance) — lint et couverture gardés en CI.*
