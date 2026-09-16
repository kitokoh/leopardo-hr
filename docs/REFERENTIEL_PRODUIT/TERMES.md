# TERMES — lexique produit opposable (protocole P03)

Termes autorisés / interdits sur toutes les surfaces (vitrine, README, docs, pitchs).

| Contexte | À dire | À ne PAS dire |
|---|---|---|
| Nom produit | **Leopardo** | « Leopardo RH » dans une phrase de présentation (c'est un identifiant technique, pas un nom d'usage) |
| Catégorie du produit | **suite métier** (FR) · **business suite** (EN) · **işletme yönetimi paketi** (TR) · **حزمة الأعمال** (AR) | « logiciel RH », « SaaS RH », « HR SaaS », « HR software », « İK yazılımı » |
| Contenu RH de la suite | « RH & paie » (comme périmètre, dans une phrase qui décrit ce que la suite fait) | présenter l'entreprise comme une société « uniquement RH » |
| Site marketing | la vitrine | le site web (ambigu) |
| Espace client | portail client / dashboard | back-office |
| Super-admin | plateforme / admin plateforme | admin client |
| Pointage | pointage mobile/kiosk/biométrie | chronométrage |
| Paie pays | règles pays en cours de validation (statut pilot) | paie 100 % conforme |
| Statut produit | open-source, multi-tenant, mobile-first | « entreprise » tant que non prouvé |
| Apps | Leopardo Employee / Manager / RH / Platform Admin / Accounting / Marketing / Travel Agent | « l'app » (ambigu) |

Règle : toute nouvelle surface de présentation doit reprendre ce lexique — écart = bug de contenu (label `content`).

## Notifications in-app — une seule source de vérité (#7481)

Deux stores coexistaient (`notifications` et `app_notifications`), alimentés par
deux chemins différents — et l'un des deux n'était lu par **aucun** client.

| | Store canonique | Store déprécié |
|---|---|---|
| Table | `notifications` | `app_notifications` |
| Écrit par | `CommunicationService` (canal `app`) **et** `NotificationDispatcher` | plus personne |
| Lu par | `GET /api/v1/notifications` (web, mobile), l'assistant (IntentEngine) | plus personne |
| Politique | préférences par employé, heures calmes (catégorie `security` non muette), quotas mensuels, audit `CommunicationEvent` | aucune |
| Clé | `company_id` + `employee_id` | `user_id` (employé, sans `company_id`) |

**Règle opposable** :

1. Toute notification in-app s'écrit dans `notifications` — jamais ailleurs.
2. Le chemin produit passe par `CommunicationService` (préférences, heures
   calmes, quotas, audit). Le `NotificationDispatcher` est le **transport
   direct** des appelants sans gabarit (validation de taux de paie, port
   transversal `InAppNotifier`) : il publie dans le **même** store, sans
   dupliquer la politique.
3. `app_notifications` n'est plus alimenté — table conservée pour l'historique,
   aucune migration destructive. `AppNotification` est **déprécié** : y écrire
   recrée une notification invisible pour l'utilisateur et hors politique.
4. Garde de non-régression : `tests/Feature/Notification/NotificationSingleStoreTest.php`
   (une notification publiée via `InAppNotifier` est visible sur
   `GET /notifications`, comptée comme non lue, marquable comme lue, et
   `app_notifications` reste vide).

**Temps réel** : le flux SSE (`GET /notifications/stream` +
`POST /notifications/sse-token`) est **retiré** — aucun client ne le consommait
(`0 EventSource` dans `front/web` et `front/mobile_apps` ; le mobile polle
toutes les 30 s). Une surface morte coûte de la maintenance et trompe les audits
de sécurité. S'il revient, ce sera un lot produit à part entière : il faudra un
**client** (reconnexion, backoff, i18n) et pas seulement un contrôleur.
Garde automatique de non-régression (dette gelée, mesurée) : `dev-hub/tools/check-naming-drift.sh` + `dev-hub/tools/naming-baseline.json` (#7428).
Décision de positionnement complète : `POSITIONNEMENT_SUITE_METIER.md`.
