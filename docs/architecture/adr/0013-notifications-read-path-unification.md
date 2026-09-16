# ADR 0013 - Notifications in-app : stratégie de bascule `app_notifications` ↔ `notifications`

## Statut

**Acceptée** (2026-09-15 — arbitrage tranché par l'issue #7481 ; documentation d'origine : issue #2497,
review session 2026-08-15).

**Date** : 2026-08-15 · **Acceptée le** : 2026-09-15

### Pourquoi le statut change (#7481)

L'issue #7481 constatait que « toute nouvelle fonctionnalité doit deviner **lequel** utiliser — et il
n'y a pas de réponse dans le code ». C'était **inexact** : la réponse existait, ici même. Le vrai
défaut était que cet ADR était resté **« Proposée »** depuis le 2026-08-15 — donc non opposable — et
que rien n'empêchait un nouveau lot de partir dans l'autre sens.

**État vérifié le 2026-09-15 (code de `main`) :**

| Point de l'ADR | État réel |
|---|---|
| Étape 1 — read-path unifié | **non faite** : `NotificationController::index/unread/markRead/markAllRead/destroy` lisent **uniquement** `notifications`. Les notifications écrites par `NotificationDispatcher` (IA, validation des taux) restent **invisibles de l'utilisateur** — c'est le défaut d'origine, toujours ouvert. |
| Étape 2 — réécriture des émetteurs | **non faite**, et **contredite par un lot récent** : #7427 (alertes caméras) diffuse via `CommunicationService::notifyEmployee()`, c'est-à-dire **le canal historique** que cette étape demande de migrer. |
| Étape 3 — migration + dépréciation | non faite (pas de garde CI ; elle existe désormais — `notification-emitter-guard.yml`, #7481). |

**Et le read-path n'est pas seulement « pas unifié » : il est fragmenté en trois endroits.** Mesures
indépendantes, concordantes, faites sur `main` (PostgreSQL + Redis réels) :

| Lecteur | Ce qu'il lit réellement | Conséquence |
|---|---|---|
| `GET /notifications` — `NotificationController` | la table historique `notifications` | ne voit **jamais** ce qu'écrit `NotificationDispatcher` |
| `app/AI/IntentEngine.php:650` — outil « mes notifications » de l'assistant | `AppNotification::where('user_id', …)` **en direct** | voit **l'inverse** : le canal moderne, sans passer par le contrat `InAppNotifier` |
| `NotificationRepositoryInterface` | **personne** — aucune implémentation dans le dépôt | une troisième vérité, purement théorique, qui n'arbitre rien |

Les deux premiers points ne sont pas un détail de style : **aujourd'hui, l'assistant et la boîte de
réception peuvent montrer deux contenus différents au même utilisateur, pour la même question.** La
fragmentation est donc visible en production, pas seulement dans l'architecture.

Le troisième point est une dette qui peut se refermer dans deux directions : implémenter le contrat, ou
le retirer. Le laisser tel quel garantit qu'il sera un jour branché « pour de bon » et créera une
quatrième divergence.

**Ce n'est pas un oubli, c'est une dette systémique assumée** : `NotificationRepositoryInterface` est
l'une des **20 interfaces orphelines tolérées** par `dev-hub/tools/check-orphan-interfaces.sh`
(allowlist, issue #1492). La règle du dépôt y est explicite — *« soit l'implémenter, soit assumer via
ADR et ajouter à l'allowlist »*. Cet ADR est l'assomption, et l'étape 1 est l'échéance. Le retirer
maintenant, dans une PR dont le sujet est de rendre la règle opposable, ne traiterait qu'1 cas sur 20
**et** détruirait un contrat typé sur `AppNotification` — c'est-à-dire aligné sur la direction ici
décidée, donc précisément ce dont l'étape 1 pourrait se servir.

### Critères d'acceptation de l'étape 1

Ce que « read-path unifié » doit vouloir dire, pour que ce ne soit pas une intention :

1. `GET /notifications` et l'outil « mes notifications » de l'assistant renvoient **le même contenu
   pour le même utilisateur** — verrouillé par un test, pas par une relecture (« l'assistant voit
   exactement ce que voit `GET /notifications` »).
2. Le mapping des ids est **explicite** : les deux tables ont des `bigint` indépendants, donc les ids
   **collisionnent** — fusionner sans mapping afficherait la notification d'une autre personne.
3. `NotificationRepositoryInterface` est **implémenté ou retiré** (pas laissé mort).
4. La pagination et le compteur « non lus » restent justes — ce sont eux que le mobile consomme.

**Conséquence** : la direction reste celle décidée ici (`app_notifications` est la cible), elle devient
**opposable**, et l'écart de #7427 est **enregistré comme écart** — pas comme un changement de
direction. Si le propriétaire préfère inverser la cible (`notifications`), ce n'est pas un silence à
interpréter : il faut **révoquer explicitement cet ADR**, parce que les deux sens ont des conséquences
incompatibles (mapping des ids, préférences, audit `communication_events`).

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
