# Chat horizontal Leopardo Business OS — Audit de capacité et cartographie

> **Mission** : auditer la capacité de la stack actuelle, puis concevoir et implémenter le
> chat horizontal de Leopardo Business OS sans dupliquer l'existant.
>
> **Branche de mission** : `mission/leopardo-business-os`
> **Base d'audit** : `main` @ `6e18bc72a` (2026-10-19), 15 654 commits, tag courant `v4.34.0`.
> **Statut des mesures** : toute affirmation de capacité est explicitement marquée
> `[MESURÉ]` (preuve exécutée dans cette mission) ou `[HYPOTHÈSE]` (raisonnement sur
> configuration/code, non testé). Aucune capacité n'est présentée comme mesurée sans
> sortie de commande à l'appui.

---

## 0. Résumé exécutif

1. **Un système de conversation existe déjà** — il ne s'appelle pas « chat » mais
   `ConversationThread` / `ConversationMessage`, et il vit dans le **BC-13 COMMS**
   (`api/app/Modules/Notification`), exposé sous `/api/v1/conversations`. Il est
   **fonctionnel, testé et multi-tenant** : 5 endpoints, 1 migration, 9 tests Feature.
2. **Ce système est strictement binaire** : un employé et **son** manager, deux
   participants et deux seulement. Il n'y a **ni groupe, ni rôle, ni modération, ni
   temps réel, ni pagination de messages, ni édition/suppression, ni anti-doublon.**
3. **Aucun WebSocket n'est configuré** (pas de Reverb, pas de Pusher, pas de
   `config/broadcasting.php`). Le flux SSE notifications a été **volontairement retiré**
   (#7481, ADR-0013 « Remplacée ») avec la règle opposable : *« si le temps réel revient,
   il faudra un client, pas seulement un contrôleur »*.
4. **L'hébergement actuel n'est pas dimensionné pour du temps réel fiable** : Render
   free tier (spin-down ~15 min), workers de queue non provisionnés (402 billing), queue
   et scheduler drainés **dans le conteneur web**. C'est documenté dans `render.yaml`
   lui-même comme « intérim assumé » (#7845/#7649).
5. **Recommandation** : étendre le module `Notification` existant (ne rien créer de
   parallèle), avec **polling adaptatif + reprise sur curseur** comme socle temps réel
   (compatible avec l'hébergement actuel, zéro fournisseur payant nouveau), et un seul
   ajout de modèle : `conversation_participants` (groupes + rôles + accusés de lecture
   par participant).

---

## 1. Méthode d'audit

| Étape | Commande / preuve |
|---|---|
| Clonage du dépôt réel | `git clone … kitokoh/leopardo-hr` |
| Branches, commits, tags | `git branch -a`, `git log --oneline -30`, `git tag` |
| Travail en cours des autres agents | `git log -1 --format='%ci %s'` par branche distante |
| PR / issues ouvertes | GitHub REST API (`/pulls`, `/issues?state=open`, `/search/issues`) |
| Inventaire BC | `dev-hub/governance/bounded-context-registry.json` |
| Recherche de tout module de communication | `grep -rilE "messag\|chat\|conversation\|thread"` sur `api/app`, `api/routes` |
| Recherche temps réel | `grep -rn "reverb\|websocket\|pusher\|broadcast"`, lecture `api/config/broadcasting.php` |
| Lecture du code | modèles, service, contrôleur, requests, resources, migration, tests |
| Frontends | `grep -rn "conversations"` sur `front/web`, `front/admin-dashboard`, `front/mobile_apps` |
| Configuration réelle | `render.yaml`, `render.prod.yaml`, `docker-compose.yml`, `api/.env.example` |

Aucune conclusion de cet audit ne repose sur la documentation seule : chaque constat a
été vérifié dans le code ou dans le fichier de configuration cité.

---

## 2. État du dépôt au moment de l'audit

### 2.1 Branches distantes et travail en cours

| Branche | Dernier commit | Nature |
|---|---|---|
| `main` | 2026-10-19 `feat(vitrine): modernisation visuelle…(#8745)` | base saine |
| `mission/leopardo-business-os` | 2026-10-17 `docs(governance): registre des scénarios — surface API Lot 5/6 (#8668)` | **branche de mission réutilisée** |
| `design/etablissements-pages` | 2026-10-18 `docs: note existing moderate dependency alert` | **agent de l'expérience publique / distribution** |
| `bc/bc-37-farm-os` | 2026-10-19 | verticale Farm OS |
| `chore/openapi-remediation-bounded-contexts` | 2026-10-18 | PR #8732 |
| `feat/restaurant-os-policy-fail-closed` | 2026-10-17 | PR #8642 |
| `dependabot/…` | — | dépendances |

### 2.2 Travail de l'agent « expérience publique / distribution »

C'est la branche **`design/etablissements-pages`** (+ issues ouvertes `#8721` → `#8731`,
toutes préfixées `[DRAFT]` : *Page, Blocs, Domaines personnalisés, Vérification, Migration,
Tenant, Superadmin, Public, SEO*). Objet : **le modèle horizontal `Site` / « Établissements
& Pages »** de l'expérience publique.

**Constat majeur : ce chantier ne contient aucun élément de messagerie.** Il est
néanmoins le **point d'intégration minimal** du chat horizontal (voir §7) : c'est lui qui
portera l'agrégat public de l'établissement auquel un fil de discussion peut être rattaché.

### 2.3 Aucune issue ni PR « chat »

Recherches GitHub (`in:title`) : `messagerie` → **0**, `expérience publique` → **0**,
`chat` → 30 résultats, **tous** relatifs au **chat IA** (assistant Leopardo, `ChatView`
super-admin 501, anti-injection BOS-034) — **aucun ne concerne un chat humain**.
`conversation` → 9 résultats, tous IA ou audit de l'existant `PA2-COMM-002`.

→ **Le chat horizontal est un besoin non couvert, mais partiellement outillé par
`PA2-COMM-002`.** Aucune architecture parallèle n'est justifiée.

---

## 3. Inventaire des bounded contexts (37, pas 36)

> Le brief annonce « 36 BC ». Le registre machine (`dev-hub/governance/bounded-context-registry.json`,
> source de vérité vérifiée par CI, garde `check-bounded-context-registry.sh`) en déclare
> **37**, dont **1 `planned`** (BC-22 ANALYTICS) et **1 récent** (BC-38 PAIEMENTS, ajouté
> par #8696 après la numérotation initiale 01→36). La numérotation saute de BC-36 à BC-38 ;
> il n'existe **aucun BC-37** dans le registre (la branche `bc/bc-37-farm-os` en prépare un).

| Code | Nom | Priority | Chemins propriétaires (relatifs à `api/app/`) | Responsabilité |
|---|---|---|---|---|
| BC-01 | PLATFORM | P0 | `Modules/Platform`, `Modules/Onboarding`, `Core/Feature` | Catalogue, modules, provisioning, feature flags |
| BC-02 | TENANT | P0 | `Core/Tenant` | Entreprises, contexte actif, `TenantManager`, search_path, isolation |
| BC-03 | IDENTITY | P0 | `Core/Auth`, `Policies` | Utilisateurs, memberships, rôles, permissions, sessions |
| BC-04 | HR | P1 | `Modules/HR` | Employés, contrats, départements, cycle de vie |
| BC-05 | WORKFORCE | P1 | `Modules/Attendance`, `Modules/Planning` | Présence, shifts, planning, géoloc |
| BC-06 | LEAVE | P1 | `Modules/Absence` | Congés, justificatifs, soldes, validations |
| BC-07 | PAYROLL | P0 | `Modules/Payroll` | Périodes, calculs, bulletins, exports |
| BC-08 | ACCOUNTING | P0 | `Modules/Accounting` | Plan comptable, journaux, écritures, FEC |
| BC-09 | EXPENSE | P2 | `Modules/Expense` | Dépenses, avances, prêts, remboursements |
| BC-10 | RECRUITMENT | P2 | `Modules/Recruitment` | Candidatures, postes, entretiens |
| BC-11 | CRM | P1 | `Modules/CRM` | Accounts, contacts, leads, pipelines, canaux |
| BC-12 | GROWTH | P1 | `Modules/Marketing`, `Modules/Growth` | Segments, campagnes, consentements |
| **BC-13** | **COMMS** | **P0** | **`Modules/Notification`**, `Contracts/Communication` | **Email, SMS, WhatsApp, préférences, inbox/outbox, délivrabilité — ET les conversations** |
| BC-14 | INTEGRATION | P0 | `Jobs`, `Contracts/Queue` | Webhooks, queues, inbox durable, replay |
| BC-15 | FUEL | P0 | `Modules/FuelStation` | Stations, pompes, compteurs, ventes |
| BC-16 | EDU | P1 | `Modules/EduManager` | Campus, élèves, classes, notes, bulletins |
| BC-17 | RETAIL | P3 | `Modules/Retail` | Produits, caisse, stocks, POS |
| BC-18 | FIELD | P2 | `Modules/Fleet` | Véhicules, interventions, maintenance |
| BC-19 | DEVICE | P1 | `Modules/Cameras`, `Modules/EdgeSync` | Appareils, kiosques, biométrie, edge |
| BC-20 | DOCUMENTS | P1 | `Modules/Cabinet`, `Modules/Documents` | Fichiers, pièces, rétention, signatures |
| BC-21 | BILLING | P2 | `Modules/Billing` | Plans, facturation plateforme, entitlements |
| BC-22 | ANALYTICS | P1 | `Modules/Reporting` (**planned**) | Read models, indicateurs, exports |
| BC-23 | AI | P2 | `AI`, `Core/AI` | OCR, assistants bornés, registry, traçabilité |
| BC-24 | TRAVEL | P1 | `Modules/TravelAgency` | Agences, routes, réservations, billets |
| BC-25 | RESTAURANT | P1 | `Modules/RestaurantManager`, `Modules/Restaurant` | Tables, menus, POS, commandes |
| BC-26 | DELIVERY | P1 | `Modules/Delivery` | Colis, tournées, livreurs, preuves |
| BC-27 | SHOWCASE | P1 | `Modules/Showcase` | Site vitrine public du tenant |
| BC-28 | CATALOG | P0 | `Modules/Catalog` | Catalogue produits B2B |
| BC-29 | COMMUNICATION | P1 | `Modules/Communication` | **Boîte mail connectée Gmail + IA** (pas de chat) |
| BC-30 | PHARMACY | P1 | `Modules/Pharmacy` | Officines, stock lots, péremption |
| BC-31 | HEALTH | P1 | `Modules/HealthManager` | Patients, RDV, prescriptions, lits |
| BC-32 | HOSPITALITY | P1 | `Modules/HospitalityManager` | Hôtels, inventaire, réservations |
| BC-33 | FUNDRAISING | P1 | `Modules/Fundraising` | Cagnottes solidaires |
| BC-34 | GEO | P1 | `Modules/Geo` | Core géospatial transverse |
| BC-35 | VTC | P1 | `Modules/Vtc` | Réservation de courses, dispatch |
| BC-36 | TRADE | P1 | `Modules/Trade` | Trade OS (RFQ, deals) |
| BC-38 | PAIEMENTS | P1 | `Modules/Paiements` | Socle de paiement interne (#8696) |

### 3.1 Où le chat peut-il être hébergé ?

| Candidat | Verdict | Justification |
|---|---|---|
| **BC-13 COMMS** | ✅ **retenu** | C'est **déjà** le propriétaire des conversations (`ConversationThread`), des notifications et des préférences. Réponse : aucune nouvelle frontière. |
| BC-29 COMMUNICATION | ❌ | Propriété = boîte mail Gmail OAuth + classification IA. Un chat humain y créerait un second modèle de « message » concurrent de `communication_messages` (emails). Dépend de BC-13, pas l'inverse. |
| BC-11 CRM | ❌ | `CrmChannelConversation` = conversations de **canaux marketing** (WhatsApp/IG entrant), pas de la messagerie interne. Sémantique et autorisation différentes. |
| BC-03 IDENTITY | ❌ | Rôles et permissions seulement ; n'héberge pas de données de conversation. |
| BC-23 AI | ⛔ **interdit** | **Léa / l'assistant restent strictement distincts du chat humain** (contrainte mission). Les tables `ai_conversations` ne doivent jamais être jointes aux conversations humaines. |
| BC-20 DOCUMENTS | ⚠️ partiel | Réutilisable comme **contrat de stockage**, pas comme hôte. |

---

## 4. Ce qui existe réellement (lecture de code)

### 4.1 Modèles (`api/app/Modules/Notification/Domain/Models/`)

**`ConversationThread`** — `company_id` (uuid), `employee_id`, `manager_id` (nullable),
`subject_type`/`subject_id` (polymorphe, 3 types autorisés : `salary_advance`,
`attendance_correction`, `absence`), `title`, `status` (`open`), `last_message_id`,
`last_message_at`, `employee_last_read_at`, `manager_last_read_at`.
Méthodes : `hasParticipant()`, `markReadFor()`, `isUnreadFor()`.

**`ConversationMessage`** — `conversation_thread_id`, `author_id`, `body` (text),
`attachment_path`, `attachment_original_name`, `attachment_mime_type`, `attachment_size`,
`created_at`. **`UPDATED_AT = null`** → messages **immuables** (pas d'édition).
Pas de colonne `seq`, pas de `reply_to_id`, pas de `client_message_id` (idempotence),
pas de `deleted_at`.

### 4.2 Service (`ConversationService`, 261 lignes)

- `createThread()` : résout les participants (employé → son `manager` ; manager →
  `recipient_id` obligatoire), valide le sujet polymorphe **pour l'employé concerné**,
  crée le fil puis poste le premier message.
- `postMessage()` : pièce jointe **unique**, max **5 120 Ko**, disque **`local`**
  (`storage_path('app/private')`), chemin `conversations/{company_id}/{thread_id}`,
  met à jour `last_message_*`, marque lu pour l'auteur, **notifie l'autre participant**.
- `threadsFor()` : liste scopée tenant + rôle (manager → `manager_id`, sinon `employee_id`).
- `findForActor()` : **404 si non-participant** (`ConversationThreadNotFoundException`).
- `notifyOtherParticipant()` : `CommunicationService::notifyEmployee($recipient,
  'conversation_message_received', […], ['app','push'])` → **réutilise** le canal
  canonique (préférences, heures calmes, quotas, audit `communication_events`, push FCM).

### 4.3 API (`api/routes/modules/rh.php`, lignes 247-252)

| Méthode | Chemin | Contrôleur |
|---|---|---|
| GET | `/conversations` | `index` (paginé 1..100) |
| POST | `/conversations` | `store` (multipart, `attachment`) |
| GET | `/conversations/{thread}` | `show` (marque lu, charge **tout** l'historique) |
| POST | `/conversations/{thread}/messages` | `storeMessage` |
| GET | `/conversations/{thread}/messages/{message}/attachment` | `downloadAttachment` |

Contrat documenté dans `api/openapi.yaml` (5 chemins, bloc lignes 14990-15160) +
miroir `dev-hub/openapi/v1.yaml` (garde CI « Route → OpenAPI coverage »).

### 4.4 Migration

`api/database/migrations/tenant/2026_07_25_060001_create_conversation_threads_and_messages.php`
`conversation_threads` + `conversation_messages`, `schemaTableExists()` (réentrante),
`down()` complet, index tenant-first (`company_id, employee_id`), FK messages → threads
en cascade. **Conforme** à `docs/architecture/MIGRATIONS_CONVENTIONS.md`.

### 4.5 Tests

`api/tests/Feature/ConversationControllerTest.php` — **9 tests** couvrant : création
libre, ancrage à une avance salariale, refus d'ancrage sur le sujet d'autrui, réponse
manager + état non-lu, cloisonnement inter-managers, **cloisonnement inter-tenants**,
pièce jointe téléchargeable par les 2 participants, rejet de pièce > 5 Mo, listing
restreint aux subordonnés du manager.

### 4.6 Ce qui n'existe **pas** (recherche exhaustive)

| Recherche | Résultat |
|---|---|
| `reverb`, `pusher`, `websocket`, `broadcast` (config) | **Aucun** `config/broadcasting.php` ; aucune dépendance Reverb/Pusher |
| `text/event-stream`, `EventSource` | **0** occurrence applicative — le SSE a été retiré (#7481) |
| `presence`, `typing`, `read_receipt` | 0 |
| Table `conversation_participants` | 0 |
| Rôles de conversation (`creator`/`admin`/`member`) | 0 |
| Blocage / signalement / modération chat | 0 |
| Rate limiting sur `/conversations` | 0 |
| Idempotence sur l'envoi de message | 0 (le middleware `IdempotencyMiddleware` existe pour d'autres surfaces) |
| Présence dans `front/web` | 0 (seul l'assistant IA consomme des « conversations ») |
| Présence dans `front/mobile_apps` | 0 |
| Présence dans `front/admin-dashboard` | `views/chat/ChatView.vue` = **chat IA super-admin, désactivé** (501 `ADMIN_CHAT_UNAVAILABLE`) |

---

## 5. Cartographie des capacités

> Légende « Écart réel » : **—** = couvert ; **partiel** = existe mais insuffisant pour
> un chat horizontal ; **absent** = à construire.

| Capacité | État actuel | Réutilisation possible | Écart réel | Action |
|---|---|---|---|---|
| Modèle de fil de discussion | `conversation_threads` (2 participants) | Réutilisé tel quel | partiel (binaire) | étendre (`kind`, `created_by`) |
| Modèle de message | `conversation_messages` immuable | Réutilisé tel quel | partiel | ajouter `seq`, `client_message_id`, `reply_to_id`, `edited_at`, `deleted_at` |
| Conversation privée 1:1 | **existe** (`PA2-COMM-002`) | 100 % | — | conserver ; ajouter idempotence par contexte |
| Idempotence par contexte | **absente** | `IdempotencyMiddleware` existe ailleurs | **absent** | clé d'unicité `(thread_id, client_message_id)` |
| Groupe (> 2 participants) | **inexistant** | fil + messages | **absent** | `conversation_participants` + `kind='group'` |
| Rôles créateur / admin / membre | **inexistants** | `Employee::role`, Policies | **absent** | colonne `role` sur participants + Policy |
| Ajout / retrait / quitter | **inexistants** | — | **absent** | endpoints + service |
| Accusé de lecture | **partiel** : 2 colonnes `*_last_read_at` sur le fil | `isUnreadFor()` | partiel (scalaire binaire) | lecture **par participant** (`last_read_message_id`) |
| Édition / suppression | **absentes** (messages immuables) | — | **absent** | `edited_at` / `deleted_at` + politique auteur/admin |
| Réponse à un message | **absente** | — | **absent** | `reply_to_message_id` + `reply_preview` |
| Pagination des messages | **absente** (`show()` charge tout) | pagination Laravel | **absent** | `?before_id` / `?after_id` + `per_page` |
| Anti-doublon | **absent** | — | **absent** | clé client + index unique |
| Ordre garanti | **partiel** (`created_at` seul, précision seconde) | — | **absent** | `seq` bigint monotone par fil |
| Pièce jointe | **existe** : 1 max, 5 Mo, `local` privé, mimes whitelist, contrôle d'accès à chaque GET | `downloadAttachment()` | partiel (1 seule, pas de scan, pas de type réel) | passe 2 : type MIME réel (`finfo`), multi-pièces, URL signée courte |
| Notifications | **existe** (`CommunicationService::notifyEmployee`, canaux `app` + `push`) | 100 % | — | réutiliser, éviter le doublon sur édition |
| Temps réel | **aucun** (SSE retiré, pas de Reverb) | polling `GET` | **absent** | polling adaptatif + curseur (`after_id`) |
| Reprise après reconnexion | **partiel** (rechargement complet) | pagination par curseur | **absent** | `GET /messages?after_id=` |
| Blocage utilisateur | **absent** | — | **absent** | table `conversation_blocks` |
| Signalement | **absent** | — | **absent** | table `conversation_reports` |
| Rate limiting / anti-spam | **absent** | `ResilientThrottleRequests` existe | **absent** | throttle par participant + fil |
| Journalisation / modération admin | **absent** | `Auditable`, policies | **absent** | endpoints admin + audit |
| Référence typée à un contexte métier | **existe** (`subject_type/subject_id`, 3 types) | réutilisable | partiel (3 types) | ajouter `order`, `booking`, `professional_space` |
| Isolation multi-tenant | **existe** (`BelongsToCompany`, fail-closed #3727) | 100 % | — | tests d'isolation à étendre |
| Autorisation par rôle | **partiel** (participant / non-participant) | Policies du module | partiel | Policy conversation + rôle dans le fil |
| UI Next.js | **absente** | design system (`components/ui`, `module-page-shell`) | **absent** | `/communication/chat` + composants |
| UI Vue admin | `ChatView.vue` = IA, désactivé | structure de layout | **absent** | ne pas réutiliser le composant IA |
| UI Flutter | **absente** | `leopardo_core` | **absent** | passe ultérieure |

---

## 6. Temps réel : état réel et options

### 6.1 Constat

- `api/config/broadcasting.php` **n'existe pas** → aucun driver de broadcast.
- Le seul précédent (SSE `/notifications/stream`) a été **supprimé** : `0 EventSource` côté
  client, mobile en polling 30 s. La règle opposable (commentaire de route + addendum
  `SCENARIOS_TEST_API_GITHUB_ACTIONS.md`) : *le temps réel n'a de sens que si un client
  le consomme*.
- Le seul canal temps réel actif de la stack est **Redis (Upstash / Key Value Render)**,
  utilisé comme cache + queue (`QUEUE_CONNECTION=database` par défaut, `redis` en local).

### 6.2 Options compatibles avec la stack et les contraintes de coût

| Option | Coût | Compatibilité | Fiabilité sur l'hébergement actuel | Verdict |
|---|---|---|---|---|
| **A. Polling adaptatif + curseur** (`GET /messages?after_id=`) | **0 €** | native (HTTP) | **bonne** : survit au spin-down, reprend après reconnexion | ✅ **socle retenu** |
| B. SSE + client (réintroduction) | 0 € | Laravel natif | **faible** : connexion longue tuée par le spin-down Render free et les proxys Vercel | ❌ pas en phase 1 |
| C. Laravel Reverb (self-hosté) | 0 € logiciel + **CPU/RAM** | Laravel 11+ | **incompatible** : nécessite un process long-vivant ; worker bloqué (402 billing), free tier spin-down | ❌ bloqué par l'infra |
| D. Pusher / Ably / Supabase Realtime | payant | SDK | bonne | ⛔ **fournisseur payant nouveau — interdit sans autorisation** |

**Décision** : phase 1 = **A**. La bascule vers B/C est *préparée* (curseur monotone `seq`
+ `after_id`), pas *implémentée* : un consumer client complet sera nécessaire, conformément
à la leçon #7481.

### 6.3 Limites connues des offres actuelles (factuelles, documentées dans le dépôt)

| Limite | Source dans le dépôt | Impact chat |
|---|---|---|
| Web free Render spin-down ~15 min | `render.prod.yaml` (en-tête) | connexion longue impossible ; le polling doit tolérer un cold start (~30-60 s) |
| Workers de queue non provisionnés (402 billing) | `render.yaml`, `render.prod.yaml` (#7845/#7649) | notifications push différées ; l'envoi in-app reste synchrone |
| Queue + scheduler **dans le conteneur web** | `docker-entrypoint.sh` (`WEB_QUEUE_DRAIN`, `WEB_SCHEDULER_LOOP`) | perte de tâches planifiées pendant le spin-down |
| Postgres **Neon** (branche prod) | `render.prod.yaml`, `docs/infra/POSTGIS_NEON.md` | latence réseau par requête ; éviter N+1 sur les listes |
| Redis = instance **gratuite unique** `leopardoai` | `render.yaml` | pas de pub/sub garanti pour un broadcast applicatif |
| `FILESYSTEM_DISK=local` par défaut | `config/filesystems.php` | pièces jointes sur disque **éphémère** du conteneur Render → perte au redéploiement |

> ⚠️ **Ce dernier point est le risque de production le plus grave du chat actuel** : les
> pièces jointes sont écrites sur `storage_path('app/private')`, disque **non persistant**
> sur Render. Aucun `FILESYSTEM_DISK=s3` n'est imposé par la configuration déployée.

---

## 7. Intégration minimale avec l'expérience publique Leopardo

Le chantier « expérience publique » (branche `design/etablissements-pages`, issues
`#8721`→`#8731`) introduit le modèle horizontal **`Site`** (établissement) et ses
**Pages**. L'intégration minimale, sans couplage, est :

1. **Le chat ne connaît pas `Site`** : il expose une **référence typée** générique
   (`subject_type` + `subject_id`) — mécanisme **déjà existant**.
2. Ajouter `professional_space` et `site` à `ConversationThread::SUBJECT_TYPES`, avec un
   **résolveur** qui vérifie l'appartenance au tenant **avant** création du fil.
3. La page publique d'un établissement ouvre un fil **`site`** ; le contrôle d'accès
   reste **entièrement** côté chat (jamais délégué au contexte métier — contrainte
   mission : « sans jamais remplacer les contrôles d'accès »).
4. Aucune dépendance de code : le module `Notification` ne référence **pas** les classes
   du chantier public (évite un cycle BC-13 → BC-27/BC-01 et permet de livrer le chat
   avant le merge de ce chantier).

---

## 8. Sécurité, autorisation, multi-tenant

| Mécanisme | État | Preuve |
|---|---|---|
| Scope tenant global | `BelongsToCompany` : `where company_id = current` + fail-closed (`TenantContextMissingException`) si `tenant_scope_required` et pas de tenant | code du trait, #3727 |
| Anti-spoof `company_id` | `creating` et `updating` **écrasent** `company_id` + log warning | #7646 |
| Bypass explicite | `forCompany()`, `crossTenantForPlatformAdmin()`, `crossTenantForSystemTask($justification)` — gardés par CI | `check-without-global-scope.sh` |
| Contrôle participant | `findForActor()` → 404 si non-participant, **avant** toute lecture de message | `ConversationService` |
| Téléchargement de pièce jointe | chemin résolu **dans le fil autorisé** (`$thread->messages()->whereKey($message)`) → aucun accès par ID de message global | `ConversationController::downloadAttachment` |
| Validation d'entrée | `max:2000` sur `body`, mimes whitelist, `max:5120` | requests |
| Isolation testée | 2 tests inter-tenants / inter-managers | `ConversationControllerTest` |

**Faiblesses à corriger** (constats réels, non des hypothèses) :
- `StoreConversationThreadRequest::authorize()` et `PostConversationMessageRequest::authorize()`
  retournent `true` : l'autorisation repose **entièrement** sur le service. Acceptable
  aujourd'hui, **fragile** dès l'ajout de rôles → à remplacer par une Policy.
- Aucun rate limit : un participant peut inonder le fil et déclencher autant de
  notifications push (coût FCM + charge).
- `attachment_mime_type` provient de `getMimeType()` (déclaré par le client/navigateur) :
  **type réel non vérifié** (`finfo`) → un `.pdf` peut être un exécutable. Contrôle à
  ajouter en phase 2 (**jamais** faire confiance au `Content-Type` client).

---

## 9. Scénarios de capacité `[HYPOTHÈSE]`

> **Aucune de ces valeurs n'est mesurée.** Elles sont des ordres de grandeur dérivés de la
> configuration (Render free : 0,1 vCPU / 512 Mo ; Neon free : connexions limitées ;
> polling 30 s côté mobile historique). Chaque hypothèse indique **ce qu'il faudrait
> mesurer** pour la valider.

| Dimension | S1 — dev/tests internes (≈10 users) | S2 — lancement (10 professionnels) | S3 — croissance (quelques centaines) | À mesurer pour valider |
|---|---|---|---|---|
| Connexions simultanées | 1-3 | 10-15 | 100-300 | `k6`/`autocannon` sur `GET /conversations` + cold start |
| Débit messages (msg/min) | < 20 | 30-80 | 300-1 000 | écriture `INSERT` + index unique |
| Requêtes HTTP / user / h | ~120 (poll 30 s) | ~120 | ~360 (poll 10 s) | comptage réel côté API |
| Stockage pièces jointes | négligeable | ~0,5-2 Go/an | ~10-50 Go/an | ⚠️ **disque Render éphémère → perte** ; chiffrer un passage S3 |
| Notifications push | < 100/j | ~500/j | 5 000-20 000/j | quota FCM + coût |
| CPU/RAM API | OK (0,1 vCPU) | **limite haute** | **insuffisant** | charge réelle ; le free tier sature dès ~20 req/s |
| Coût | 0 € | 0 € (free) | ~7 € (worker) + 25 € (web payant) + S3 | devis Render/Neon réels |
| Fiabilité temps réel | acceptable (poll 30 s) | acceptable (poll 10-15 s) | **insuffisant** → besoin Reverb/WS | test de reconnexion |

**Verdict honnête** : la stack **tient S1 et S2 en polling**, avec trois risques réels —
(1) spin-down (latence de réveil), (2) pièces jointes non persistantes, (3) saturation CPU
du free tier en S3. Elle **ne tient pas** un chat temps réel à plusieurs centaines de
professionnels sans worker payant + WebSocket dédié.

---

## 10. Architecture recommandée

```
BC-13 COMMS — api/app/Modules/Notification
├── Domain/Models/
│   ├── ConversationThread      (existant) + kind, created_by
│   ├── ConversationMessage     (existant) + seq, client_message_id, reply_to_message_id,
│   │                                        edited_at, deleted_at
│   └── ConversationParticipant (NOUVEAU — groupe, rôle, lecture)
├── Domain/Policies/ConversationParticipantPolicy.php     (NOUVEAU)
├── Application/DTOs/…          (étendus)
├── Infrastructure/Services/ConversationService.php       (étendu)
├── Interfaces/Api/V1/Controllers/ConversationController.php (étendu)
│   └── …/Requests/ (StoreGroupThreadRequest, AddParticipantRequest, …)
└── Providers/NotificationServiceProvider.php

Migrations tenant (conventions MAT-005) :
  2026_10_19_0009xx_<issue>_create_conversation_participants_table.php
  2026_10_19_0009xx_<issue>_add_chat_columns_to_conversation_tables.php
```

**Principes**
1. **Aucun nouveau BC.** BC-13 est déjà propriétaire.
2. **Rétro-compatibilité** : les fils `employee_id`/`manager_id` restent valides
   (`kind='direct'`) ; un participant synthétique est matérialisé pour les fils existants.
3. **Temps réel = polling + curseur**, prêt à basculer sans refonte.
4. **Notifications = canal existant** (`CommunicationService::notifyEmployee`), jamais un
   second dispatcher.
5. **Autorisation = serveur**, jamais le contexte métier.
6. **Léa / assistant IA = surface séparée**, aucune jointure, aucune action métier
   déclenchable par un message humain.

---

## 11. Risques prioritaires

| # | Risque | Gravité | Mitigation |
|---|---|---|---|
| R1 | Pièces jointes sur disque **éphémère** Render → perte totale au redéploiement | **Critique** | Exiger `FILESYSTEM_DISK=s3` (ou volume persistant) avant mise en prod du chat ; documenter dans les variables d'env |
| R2 | Fuite cross-tenant si un endpoint de groupe oublie le scope | **Critique** | `BelongsToCompany` + Policy + tests d'isolation par endpoint |
| R3 | Free tier saturé en S3 | Élevé | polling adaptatif, pagination, `per_page` borné, cache court |
| R4 | Réintroduire un SSE sans client (leçon #7481) | Moyen | pas de SSE en phase 1 ; consumer d'abord |
| R5 | Double notification sur édition/suppression | Moyen | événements distincts (`edited` ≠ `received`) |
| R6 | Type MIME déclaré, non réel | Moyen | `finfo` + whitelist serveur en phase 2 |
| R7 | Fusion accidentelle IA ↔ chat humain | Élevé (produit) | tables et namespaces distincts, garde de test d'architecture |
| R8 | Messages non ordonnés (même seconde) | Moyen | `seq` monotone par fil |

---

## 12. Plan incrémental

| Phase | Contenu | État |
|---|---|---|
| **P0** | Audit + cartographie + issues | ✅ cette livraison |
| **P1** | **Groupes + rôles** (créateur/admin/membre, ajout/retrait/quitter) + Policy + tests | ✅ cette livraison |
| **P2** | Messages : `seq`, `client_message_id` (anti-doublon), `reply_to`, pagination par curseur, édition/suppression | à faire (issue) |
| **P3** | Pièces jointes : MIME réel, multi-pièces, URL temporaires, **persistance S3** | à faire (issue) |
| **P4** | Temps réel : endpoint `after_id` + client à polling adaptatif + reprise/reconnexion | à faire (issue) |
| **P5** | Modération : blocage, signalement, rate limiting, journal d'audit, outils admin | à faire (issue) |
| **P6** | UI horizontale : Next.js (design system) + Vue admin + Flutter | à faire (issue) |
| **P7** | Intégration `site` / `professional_space` avec le chantier public | à faire (issue) |

---

## 13. Spécifications de tests (reproductibles)

> Ces specs sont **documentées** ici. Les tests unitaires/intégration/autorisation ont été
> **exécutés** (voir §14). Les tests de charge et de reconnexion **n'ont pas été exécutés**
> dans cet environnement (pas de déploiement Render/Neon accessible) — ils sont donc
> fournis comme specs prêtes à l'emploi, et **ne sont pas** revendiqués comme passés.

### 13.1 Charge (à exécuter contre un environnement déployé)

```bash
# 1) Liste des conversations (lecture chaude)
k6 run - <<'EOF'
import http from 'k6/http'; import { check, sleep } from 'k6';
export const options = { scenarios: { ramping: { executor: 'ramping-vus',
  startVUs: 1, stages: [ {duration:'1m',target:10}, {duration:'2m',target:50},
  {duration:'1m',target:0} ] } }, thresholds: { http_req_failed:['rate<0.01'],
  http_req_duration:['p(95)<800'] } };
const BASE = __ENV.BASE_URL, TOKEN = __ENV.TOKEN;
export default function () {
  const r = http.get(`${BASE}/api/v1/conversations?per_page=20`,
    { headers: { Authorization: `Bearer ${TOKEN}`, Accept: 'application/json' } });
  check(r, { ok: (x) => x.status === 200 });
  sleep(1);
}
EOF
```

Critères : `p(95) < 800 ms`, `http_req_failed < 1 %`, aucune erreur 5xx.
Mesurer **séparément** le premier appel après spin-down (cold start attendu 30-60 s).

### 13.2 Reconnexion

1. Charger un fil, noter `last seq`.
2. Couper le réseau client (DevTools ▸ Offline) pendant 60 s.
3. Poster 3 messages depuis un second client.
4. Rétablir : l'UI doit appeler `GET /conversations/{id}/messages?after_seq=<last>` et
   afficher **exactement** les 3 messages, **sans doublon** et dans l'ordre.
5. Rejouer avec un `client_message_id` déjà utilisé → réponse **idempotente** (même id,
   pas de second message).

### 13.3 Isolation multi-tenant

Pour **chaque** endpoint de chat : créer un fil dans le tenant A, appeler avec un token du
tenant B → **404** (jamais 403 révélateur, jamais 200). Idem pour un participant retiré
d'un groupe → 404 sur `show`/`messages`.

---

## 14. Résultats de tests réellement exécutés

| Suite | Commande | Résultat |
|---|---|---|
| Tests existants de conversation | `php artisan test --filter=ConversationControllerTest` | voir §14.1 |
| Nouveaux tests de groupe (P1) | `php artisan test --filter=ConversationGroupTest` | voir §14.1 |

### 14.1 Sorties — `[MESURÉ]`

Environnement réel d'exécution : PHP 8.4.15, PostgreSQL 15 (base locale
`leopardo_test`), `CI=true` (le harness `tests/TestCase.php` réécrit sinon l'hôte en
`pgsql` pour un réseau Docker absent).

**Nouveaux tests — `ConversationGroupTest` :**

```
PASS  Tests\Feature\ConversationGroupTest
✓ group is created with creator role and membership rows               2.95s
✓ group creation rejects a participant from another tenant             0.93s
✓ member cannot add or remove participants                             0.91s
✓ creator can add and remove a member but never remove creator         0.87s
✓ member can leave and then loses access                               0.98s
✓ creator cannot leave without transferring the role                   0.96s
✓ non participant gets 404 on every group endpoint                     0.94s
✓ group thread does not leak into another tenant listing               0.94s
✓ per participant read state is tracked independently                  0.93s
✓ existing direct thread contract is unchanged                         0.88s

Tests:    10 passed (50 assertions)
Duration: 13.28s
```

**Non-régression — `ConversationControllerTest` (suite préexistante, inchangée) :**

```
PASS  Tests\Feature\ConversationControllerTest
✓ employee can start a free standing thread with their manager         1.37s
✓ thread can be anchored to a salary advance subject                   1.05s
✓ employee cannot anchor a thread to someone elses subject             1.07s
✓ manager can reply and thread marks unread for employee               1.06s
✓ manager cannot see a thread belonging to another managers report     1.03s
✓ employee cannot see another companys thread                          0.98s
✓ message can carry a single small attachment and is downloadable…     0.94s
✓ attachment over size limit is rejected                               0.95s
✓ manager lists only threads of their own reports                      1.01s

Tests:    9 passed (26 assertions)
Duration: 11.60s
```

**Style (garde CI) — Laravel Pint :**

```
./vendor/bin/pint --test app/Modules/Notification … routes/modules/rh.php
PASS   49 files
```

**Gardes de migration du dépôt :**

```
python3 dev-hub/tools/check-migration-destructive-down.py
  Fichiers analysés : 516 (+ 14 ignorés — table dynamique)
  OK — aucun down() destructeur cross-migration détecté.
```

> ℹ️ `check-migration-basename-collisions.sh` signale un doublon de préfixe
> `2026_10_17_140000` (`8629` + `8652`). **Il préexiste sur la branche de mission** :
> `8652` est sur `main`, `8629` a été ajouté par un autre lot. La migration de cette
> mission (`2026_10_19_000926`) n'est pas concernée et n'introduit aucun doublon.

### 14.2 Tests NON exécutés (et pourquoi)

| Test | Raison | Statut |
|---|---|---|
| Charge `k6` (§13.1) | aucun environnement déployé Render/Neon accessible depuis la session | **non exécuté** — spec fournie |
| Reconnexion (§13.2) | dépend d'une UI qui n'existe pas encore (phase P6) | **non exécuté** — spec fournie |
| `migrate` complet sur base réelle | `php artisan migrate` a dépassé le délai de 180 s dans cet environnement (bootstrap complet de l'application + migrations public) | **non exécuté** ; la migration a été validée par lint PHP, par les gardes du dépôt (§14.1) et par le miroir de schéma `CreatesMvpSchema` utilisé par les 19 tests |

Aucun de ces trois points n'est présenté comme validé.

---

## 15. Fichiers concernés (état de départ)

| Fichier | Rôle |
|---|---|
| `api/app/Modules/Notification/Domain/Models/ConversationThread.php` | fil |
| `api/app/Modules/Notification/Domain/Models/ConversationMessage.php` | message |
| `api/app/Modules/Notification/Infrastructure/Services/ConversationService.php` | logique |
| `api/app/Modules/Notification/Interfaces/Api/V1/Controllers/ConversationController.php` | API |
| `api/app/Modules/Notification/Interfaces/Api/V1/Requests/*.php` | validation |
| `api/app/Http/Resources/Api/V1/Conversation{Thread,Message}Resource.php` | sérialisation (**hors module** — dette à noter) |
| `api/routes/modules/rh.php` (247-252) | routes |
| `api/database/migrations/tenant/2026_07_25_060001_create_conversation_threads_and_messages.php` | schéma |
| `api/tests/Feature/ConversationControllerTest.php` | 9 tests |
| `api/openapi.yaml` (14990-15160) + `dev-hub/openapi/v1.yaml` | contrat |

> **Dette constatée** : les `Resource` de conversation vivent dans
> `api/app/Http/Resources/Api/V1/` alors que tout le reste du module est dans
> `Modules/Notification` — contraire à la règle `ARCHITECTURE.md` (« tout nouveau code
> métier va dans `api/app/Modules/` »). Non refactoré ici (hors périmètre, risque de
> régression sur 5 endpoints) ; proposé en issue dédiée.

---

## 16. Issues GitHub créées par cette mission

| Issue | Objet | Phase |
|---|---|---|
| [#8746](https://github.com/kitokoh/leopardo-hr/issues/8746) | Groupes : rôles créateur/admin/membre, ajout/retrait, quitter | **P1 — implémenté dans cette livraison** |
| [#8747](https://github.com/kitokoh/leopardo-hr/issues/8747) | Messages : `seq`, anti-doublon, pagination par curseur, édition/suppression, réponse | P2 |
| [#8748](https://github.com/kitokoh/leopardo-hr/issues/8748) | Pièces jointes : MIME réel, multi-pièces, URL temporaires, **persistance** | P3 |
| [#8749](https://github.com/kitokoh/leopardo-hr/issues/8749) | Temps réel et reprise après reconnexion | P4 |
| [#8750](https://github.com/kitokoh/leopardo-hr/issues/8750) | Modération : blocage, signalement, rate limiting, audit | P5 |
| [#8751](https://github.com/kitokoh/leopardo-hr/issues/8751) | UI horizontale : Next.js, Vue admin, Flutter | P6 |

Intégration à l'expérience publique (§7) : traitée dans #8751 (référence `site`) et
#8746 (types de sujet `order`, `booking`, `professional_space`, `site`).

---

## 17. Contrat d'intégration livré en P1 (#8746)

### 17.1 Nouveaux endpoints

| Méthode | Chemin | Corps | Réponse | Droits |
|---|---|---|---|---|
| POST | `/api/v1/conversations/groups` | `title`, `body`, `participant_ids[]`, `subject_type?`, `subject_id?`, `attachment?` | `201` thread (`kind: group`) | tout employé authentifié ; membres résolus **dans son tenant** |
| GET | `/api/v1/conversations/{thread}/participants` | — | `200 {data:[{employee_id,name,role,last_read_message_id,joined_at}]}` | participant du fil |
| POST | `/api/v1/conversations/{thread}/participants` | `employee_id` | `201 {data:{employee_id,role,joined_at}}` | `creator` ou `admin` |
| DELETE | `/api/v1/conversations/{thread}/participants/{employee}` | — | `204` | `creator`/`admin` ; jamais le `creator` cible |
| POST | `/api/v1/conversations/{thread}/leave` | — | `204` | tout participant sauf le `creator` |

### 17.2 Champs ajoutés aux payloads existants (additif, non cassant)

`ConversationThreadResource` expose désormais :

| Champ | Type | Valeur |
|---|---|---|
| `kind` | `"direct"\|"group"` | `direct` par défaut (fils historiques inclus) |
| `created_by` | `int\|null` | auteur du groupe ; `null` pour un fil direct |
| `participants` | `array\|null` | `null` si relation non chargée ; `[]` pour un direct chargé |

Aucun champ existant n'est retiré ni renommé : les clients actuels (Flutter, admin) ne
régressent pas — vérifié par le test `existing direct thread contract is unchanged`.

### 17.3 Matrice d'autorisation (serveur uniquement)

| Action | `creator` | `admin` | `member` | non-participant |
|---|---|---|---|---|
| Lire le fil / poster | ✅ | ✅ | ✅ | **404** |
| Ajouter un membre | ✅ | ✅ | **404** | **404** |
| Retirer un `member` | ✅ | ✅ | **404** | **404** |
| Retirer un `admin` | ✅ | **404** | **404** | **404** |
| Retirer le `creator` | **422** (même pour lui) | **404** | **404** | **404** |
| Quitter | **422** (doit transmettre) | ✅ | ✅ | **404** |

> Le **404** est délibéré : un 403 confirmerait à un tiers l'existence du fil.

### 17.4 Variables d'environnement

**Aucune nouvelle variable.** Le module réutilise `FILESYSTEM_DISK`, `QUEUE_CONNECTION`
et la connexion PostgreSQL existants. ⚠️ Voir R1 (§11) : `FILESYSTEM_DISK=local` sur un
disque éphémère reste le risque bloquant de production pour les pièces jointes (#8748).

### 17.5 Fichiers modifiés / créés

| Fichier | Nature |
|---|---|
| `api/database/migrations/tenant/2026_10_19_000926_8746_create_conversation_participants_table.php` | **créé** |
| `api/app/Modules/Notification/Domain/Models/ConversationParticipant.php` | **créé** |
| `api/app/Modules/Notification/Application/DTOs/CreateGroupThreadDTO.php` | **créé** |
| `api/app/Modules/Notification/Domain/Policies/ConversationParticipantPolicy.php` | **créé** |
| `api/app/Modules/Notification/Interfaces/Api/V1/Requests/StoreGroupConversationThreadRequest.php` | **créé** |
| `api/app/Modules/Notification/Interfaces/Api/V1/Requests/AddConversationParticipantRequest.php` | **créé** |
| `api/tests/Feature/ConversationGroupTest.php` | **créé** (10 tests) |
| `api/app/Modules/Notification/Domain/Models/ConversationThread.php` | modifié (`kind`, `created_by`, `participants()`, `isGroup()`, lecture par participant) |
| `api/app/Modules/Notification/Infrastructure/Services/ConversationService.php` | modifié (groupes, rôles, notification multi-destinataires) |
| `api/app/Modules/Notification/Interfaces/Api/V1/Controllers/ConversationController.php` | modifié (5 actions) |
| `api/app/Http/Resources/Api/V1/ConversationThreadResource.php` | modifié (champs additifs) |
| `api/routes/modules/rh.php` | modifié (4 routes + ordre de déclaration) |
| `api/config/communication.php` | modifié (template `conversation_group_added`) |
| `api/lang/{fr,en,ar,tr}/notifications.php` | modifié (2 clés × 4 locales) |
| `api/tests/Support/CreatesMvpSchema.php` | modifié (miroir de schéma pour les tests) |
| `docs/mission-business-os/16_chat_horizontal_audit.md` | **créé** (ce document) |

### 17.6 Commandes de test

```bash
cd api
CI=true php artisan test --filter=ConversationGroupTest     # 10 passed (50 assertions)
CI=true php artisan test --filter=ConversationControllerTest # 9 passed (26 assertions)
./vendor/bin/pint --test app/Modules/Notification routes/modules/rh.php
```

> `CI=true` est nécessaire hors Docker : `tests/TestCase.php` réécrit `DB_HOST` en
> `pgsql` (nom de service Compose) quand `CI` n'est pas défini.

---

## 18. Conflits avec l'architecture existante (et résolution minimale)

| Conflit constaté | Décision |
|---|---|
| `ConversationThread` porte une sémantique binaire (`employee_id`/`manager_id`) que les groupes ne peuvent pas exprimer | **Extension additive**, pas de refonte : `kind` + table de participants ; `direct` reste le défaut, donc aucune migration de données |
| Les `Resource` de conversation vivent hors du module (`app/Http/Resources/Api/V1`) | **Non refactoré** : risque de régression sur 5 endpoints pour zéro bénéfice fonctionnel. Documenté comme dette. |
| `ConversationMessage` est immuable (`UPDATED_AT = null`) | Respecté en P1 (aucune édition). L'édition/suppression est cadrée en #8747, avec `edited_at` explicite plutôt qu'un `updated_at` global. |
| Le temps réel a été retiré volontairement (#7481) | **Aucune réintroduction.** Polling + curseur en #8749, avec la règle « client d'abord ». |
| `config/broadcasting.php` absent | **Non créé** : déclarer un driver sans consumer reproduit exactement le défaut corrigé par #7481. |
| `ChatView.vue` (Vue admin) est le chat **IA** | **Non réutilisé** : composant marqué 501 côté API ; le chat humain ne partage rien avec lui (contrainte Léa distincte). |

---

## 19. Ce qui reste à faire (phases suivantes)

| Phase | Issue | Contenu restant |
|---|---|---|
| P2 | #8747 | `seq`, `client_message_id`, `reply_to`, pagination par curseur, édition/suppression |
| P3 | #8748 | MIME réel (`finfo`), multi-pièces, URLs signées, **persistance S3/volume** |
| P4 | #8749 | `after_seq`, client à polling adaptatif, états offline, tests de reconnexion |
| P5 | #8750 | Blocage, signalement, rate limiting, journal d'audit, outils admin |
| P6 | #8751 | UI Next.js (design system), écran de modération Vue, écran Flutter |
| P7 | #8751 + #8746 | Branchement `site` / `professional_space` avec le chantier expérience publique |

Non traité, conformément aux contraintes : module de paiement, auth, notifications, stockage —
aucun second système n'a été créé dans ces domaines.
