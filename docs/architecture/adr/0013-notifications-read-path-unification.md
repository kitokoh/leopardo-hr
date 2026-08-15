# ADR 0013 - Notifications in-app : stratégie de bascule `app_notifications` ↔ `notifications`

## Statut

Proposée (documentation d'arbitrage — issue #2497, review session 2026-08-15).

**Date** : 2026-08-15

## Contexte

Deux tables coexistent pour les notifications in-app :

| Canal | Table | Modèle | Écrit | Lit |
|---|---|---|---|---|
| **Historique** | `notifications` | `App\Modules\Notification\Domain\Models\Notification` | `CommunicationService` (communication_events → in-app), `SalaryAdvanceService`/`SalaryAdvanceController` (notifications avances), commandes edge (`DetectSilentEdgeNodes`, `MonitorEdgeNodesCommand`) | `NotificationController` (`GET /api/v1/notifications`, `unread`, read, delete) — **le read-path public** |
| **Moderne** | `app_notifications` | `App\Modules\Notification\Domain\Models\AppNotification` | `NotificationDispatcher::dispatch()` — via `SendNotification` (action) ← `NotifyTaxRateValidation` (listener) | `MarkNotificationsRead` (action) — **aucun read-path API public** ; `AppNotification::user()` pointe désormais vers l'employé tenant (#2436/#2447) |

**Problème** : les notifications émises via le chemin moderne (`app_notifications`, ex. validation de taux #1813/#2498) sont persistées mais **jamais servies** par `GET /api/v1/notifications` → l'utilisateur ne les voit pas. Les correctifs mergés (#2391, #2395, #2446, #2447) ont réparé la persistance et la relation, pas le read-path unifié.

## Décision

**Fusionner les read-paths sur le canal moderne `app_notifications`, avec migration des données du canal historique, en 3 étapes.** Ne pas créer de troisième canal.

### Étape 1 — Read-path unifié (court terme, P2)

`NotificationController` lit **les deux tables** et fusionne les résultats (`app_notifications` en priorité, `notifications` en complément), ou — plus simple et plus sûr — lit uniquement `app_notifications` **après** avoir réécrit les émetteurs historiques (étape 2) vers ce canal. L'action `MarkNotificationsRead` devient le chemin canonique de lecture/marquage.

Contrainte : `app_notifications.user_id` stocke des ids d'employés tenant (décision #2436/#2447) ; `notifications` est scopée par `company_id`/`employee_id`. La fusion doit normaliser sur l'employé tenant (`employee_id`), pas sur `public.users`.

### Étape 2 — Réécriture des émetteurs (moyen terme)

Tous les émetteurs historiques passent par `NotificationDispatcher::dispatch()` / `SendNotification` :
- `CommunicationService` (branche in-app de `communication_events`)
- `SalaryAdvanceService` / `SalaryAdvanceController`
- commandes edge (`DetectSilentEdgeNodes`, `MonitorEdgeNodesCommand`)
- tout futur émetteur : **obligation** de passer par `NotificationDispatcher` (garde `FrontendApiContractTest` + revue)

Cela garantit : un seul point d'écriture, respect des préférences (`notification_preferences`), audit `communication_events`, push mobile déclenché (#2391).

### Étape 3 — Migration + dépréciation (long terme)

- Migration de données `notifications` → `app_notifications` (une passe idempotente, par tenant, avec mapping `employee_id`).
- `notifications` passe en lecture seule puis est dépréciée ; le modèle `Notification` et ses émetteurs directs sont supprimés une fois la couverture vérifiée (tests `NotificationControllerTest` réalignés).
- Garde CI : aucun nouvel import de `App\Modules\Notification\Domain\Models\Notification` hors migration/read legacy.

## Conséquences

- **Visibilité produit** : les notifications taux de validation (#1813/#2498) et toutes les futures notifications modernes deviennent visibles dans l'app.
- **API** : `GET /api/v1/notifications` garde son contrat (pagination `data`, `unread`), le read-path interne change.
- **Mobile** : les apps employee/manager consomment déjà `GET /api/v1/notifications` (contrat v4.16.185) — aucune modification client requise.
- **Risque** : la fusion (étape 1) est la partie sensible (double lecture, mapping des ids) — elle doit être couverte par des tests d'isolation tenant et de non-régression `NotificationControllerTest`.

## Alternatives écartées

- **Alias simple** (`app_notifications` vue sur `notifications`) : ne résout pas la double écriture ni le mapping d'ids.
- **Déprécier `app_notifications` au profit de `notifications`** : inverse la direction des correctifs récents (#2436/#2447 ont consolidé `AppNotification` sur l'employé tenant) et conserve le couplage `communication_events` historique.
- **Troisième canal** : rejeté (plus de surface à maintenir).

## Références

- Issue #2497 (cette ADR), #2436 (relation AppNotification→employee), #2398/#2446 (migration `app_notifications`), #2391 (push FCM depuis le dispatcher), #1813 (notifications taux de validation), #2498 (observabilité du dispatch).
- Contrat API mobile : AGENTS.md v4.16.185 (`GET /api/v1/notifications?unread=true`).
