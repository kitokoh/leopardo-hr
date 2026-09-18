# Spécification — Module transversal « Communication » (boîte mail connectée + IA)

**Statut** : proposé — en attente de validation fondateur (procédure `[FREEZE-EXCEPTION]`, cf. `docs/GOUVERNANCE/FREEZE_SCOPE_60J.md`)
**Version** : 0.1 · **Date** : 2026-09-18 · **Auteur** : agent (demande directe du fondateur, session du 18/09/2026)
**Bounded context** : nouveau BC `Communication` (à enregistrer dans `dev-hub/governance/bounded-context-registry.json`)

---

## 1. Contexte et objectif

La plateforme sert deux populations :

- **Admin plateforme** (le fondateur et son équipe) — gestion interne ;
- **Clients tenants** (fondateurs d'entreprises : agences de voyages, restaurateurs, etc.) — chacun dans son espace, avec les outils de son domaine.

Les deux populations partagent un même besoin : **déléguer la gestion de leur communication par email à la plateforme**, avec une IA derrière. L'utilisateur connecte son compte Google une fois ; ensuite la plateforme :

1. **lit et synchronise** sa boîte Gmail ;
2. **classifie** les messages et **rattache/enrichit les contacts** (CRM existant) ;
3. **relance automatiquement** les fils sans réponse (séquences paramétrables) ;
4. **répond** — en brouillon, avec confirmation de l'utilisateur, ou en automatique **selon la politique choisie par l'utilisateur, cas par cas et par catégorie**.

Le module est **transversal** : rattaché fonctionnellement au marketing/communication, mais activable pour n'importe quel profil de tenant et pour l'admin plateforme.

## 2. Ce qui existe déjà (réutilisation obligatoire)

| Besoin | Socle existant | Référence |
|---|---|---|
| Envoi email multicanal, retry, quotas, quiet hours | `CommunicationService` + `DispatchCommunicationJob` | `api/app/Modules/Notification/Infrastructure/Services/` |
| Contacts, consentements RGPD, timeline, unsubscribe | Module CRM (`CrmContact`, `ConsentChannel`, outbox) | `api/app/Modules/CRM/` |
| LLM multi-providers, classification d'intentions | `LLMClient`, `IntentEngine`, `Orchestrator` | `api/app/AI/` |
| **Action IA avec confirmation utilisateur** | `PendingActionStore` + `WriteActionRunner` + `ToolPermissionPolicy` | `api/app/AI/` |
| Audit IA, budget tokens, DLQ | `AIAuditLogger`, `TokenBudgetGuard`, `AiDeadLetterQueue` | `api/app/AI/` |
| Tokens externes chiffrés par employé | pattern `CalendarConnection` (Attendance) | `api/app/Modules/Attendance/` |
| Login Google (Socialite, state anti-CSRF) | `AuthController` + `GoogleIdentityVerifier` | `api/app/Core/Auth/` |
| Relances planifiées idempotentes | pattern `crm:tasks:send-overdue-reminders` | `api/routes/console.php` |

**Manques réels comblés par ce module** : réception/sync Gmail, flow OAuth **serveur** avec `refresh_token` (l'existant Calendar reçoit le token du client, sans refresh), outils IA appliqués aux emails, moteur de relances sur fils de discussion.

## 3. Périmètre V1

### 3.1 Connexion Google par utilisateur (R1)
- Flow OAuth 2.0 **côté serveur** (Socialite Google, `access_type=offline`, `prompt=consent`, state anti-CSRF réutilisé du login), scopes incrémentaux : `gmail.readonly` d'abord, `gmail.send` + `gmail.modify` seulement si l'utilisateur active les réponses/relances.
- Table tenant `communication_integrations` : `employee_id`, `provider` (`google` en V1), `scopes`, `access_token`/`refresh_token` **chiffrés** (cast `encrypted`), `expires_at`, `status` (`active|revoked|error`), `company_id` (`BelongsToCompany`).
- Rafraîchissement transparent des tokens ; déconnexion = révocation Google **et** purge des tokens.
- Un utilisateur = sa boîte. Aucun accès croisé entre employés sans assignation RBAC ressource-scopée (R2-R4, `resource_types.php`).

### 3.2 Synchronisation boîte mail (R2)
- Sync incrémentale Gmail (`historyId`, fallback full sync) via jobs sur queue dédiée `communication`.
- Stockage **minimisation des données** : métadonnées (thread, expéditeur, destinataires, sujet, date, labels, snippet) + corps chiffré au repos ; pièces jointes **non** stockées en V1 (référence Gmail seulement).
- Tables tenant : `communication_threads`, `communication_messages`.
- Push notifications Gmail (watch/Pub-Sub) en V1.1 ; V1 = polling schedulé (5 min) idempotent.

### 3.3 Classification IA + enrichissement contacts (R3)
- Tool IA `email.classify` (via `ToolRegistry`) : catégorie (prospect, client, fournisseur, facture, spam/newsletter, personnel, urgent…), langue, sentiment, action attendue.
- Rattachement automatique expéditeur ↔ `CrmContact` (création proposée si inconnu, jamais silencieuse) ; la timeline CRM reçoit l'activité email.
- Taxonomie de catégories **paramétrable par tenant** (défauts fournis, i18n FR/EN/AR/TR).

### 3.4 Relances automatiques (R4)
- Règles par utilisateur : « si pas de réponse à un fil sortant après N jours → relance », séquences (max 3 relances, délais configurables), gabarits via `EmailTemplateRegistry` (surcharge par tenant/locale).
- Commande schedulée idempotente `communication:send-follow-ups` (pattern reminders CRM, table de déduplication).
- Garde-fous : respect consentement CRM + unsubscribe, quiet hours de `config/communication.php`, plafond journalier par utilisateur.

### 3.5 Réponses assistées / automatiques (R5)
- Trois politiques **par catégorie de message**, choisies par l'utilisateur : `off` / `draft` (brouillon Gmail) / `confirm` (proposition dans l'app, envoi après confirmation) / `auto` (envoi direct, réservé aux catégories à faible risque et **opt-in explicite**).
- Implémentation `confirm` = `PendingActionStore` + `WriteActionRunner` existants ; tout envoi passe par `CommunicationService` (audit `CommunicationEvent`).
- `auto` désactivé par défaut partout ; jamais d'`auto` sur les catégories finance/RH/juridique (liste bloquée en dur).

### 3.6 Activation module + UI (R6)
- `communication` ajouté à `Company::KNOWN_MODULES`, middleware `module.communication`, routes `api/routes/modules/communication.php` (kebab-case pluriel), endpoints documentés dans `api/openapi.yaml` + SDK régénéré.
- UI V1 : espace web (admin-dashboard pour la plateforme, espace client pour les tenants) — écran « Boîte connectée » : connexion Google, file des messages classés, propositions IA à confirmer, réglages des politiques et relances. Exigence fondateur : **soin particulier fond et forme** (design tokens `DESIGN_SYSTEM_TOKENS.md`).

## 4. Hors périmètre V1

Outlook/IMAP génériques, WhatsApp/SMS entrants (déjà couverts ailleurs), pièces jointes stockées, campagnes marketing de masse (reste dans CRM/Marketing), app mobile dédiée, boîtes partagées d'équipe.

## 5. Sécurité, RGPD, angles morts identifiés

1. **Vérification Google OAuth** : les scopes Gmail sont *restricted* — la mise en production publique exige la validation de l'app par Google (security assessment). À anticiper dès maintenant (compte GCP, écran de consentement, domaines). **C'est le principal risque calendrier du projet.**
2. **Consentement & minimisation** : l'utilisateur consent explicitement à chaque palier de scope ; corps de mails chiffrés au repos ; purge complète à la déconnexion et à la suppression du compte (droit à l'effacement) ; rétention paramétrable par tenant.
3. **Prompt injection** : un email entrant est une donnée **hostile**. Les contenus de mails ne doivent jamais être interprétés comme instructions par les tools IA ; sortie structurée validée (JSON schema), `WriteToolPolicy` sur tout envoi.
4. **Boucles d'automatisation** : détection réponse-automatique ↔ réponse-automatique (headers `Auto-Submitted`, `List-Id`), plafonds d'envoi, jamais de relance sur un thread où l'interlocuteur a répondu.
5. **Multi-tenant** : toutes les tables portent `company_id`, schéma tenant, policies + accès ressource-scopé R2-R4 (`communication_mailbox` déclaré dans `config/resource_types.php`, fail-closed).
6. **Coût LLM** : classification sur métadonnées+snippet d'abord, corps complet seulement si nécessaire ; `TokenBudgetGuard` par tenant.
7. **Délivrabilité** : les relances partent via le Gmail de l'utilisateur (pas via Mailgun) → réputation portée par son domaine, signature réelle, fil conservé.

## 6. Découpage en lots (issues à créer après validation)

| Lot | Contenu | Dépend de |
|---|---|---|
| R0 | Enregistrement BC, module `communication`, middleware, routes squelette, spec OpenAPI | — |
| R1 | OAuth Google serveur + `communication_integrations` + refresh + révocation | R0 |
| R2 | Sync Gmail incrémentale + modèles threads/messages + polling schedulé | R1 |
| R3 | Classification IA + liaison CRM contacts | R2 |
| R4 | Moteur de relances + gabarits + garde-fous | R2 |
| R5 | Réponses draft/confirm/auto via PendingActionStore | R3 |
| R6 | UI web (plateforme + espace client) | R1..R5 |

Chaque lot : tests Feature (CRUD + RBAC + isolation tenant), CHANGELOG, i18n 4 langues, PHPStan strict vert, OpenAPI + SDK régénérés.

## 7. Critères d'acceptation V1

- Un employé connecte son Gmail en < 2 min, voit ses 50 derniers fils classés.
- Une relance part automatiquement sur un fil sans réponse selon sa règle, jamais deux fois.
- Une réponse proposée en mode `confirm` n'est **jamais** envoyée sans action explicite de l'utilisateur ; tout envoi est audité.
- Déconnexion = plus aucun token ni corps de message en base.
- Aucun accès inter-boîtes sans assignation RBAC explicite.
