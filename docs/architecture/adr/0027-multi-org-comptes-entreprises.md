# ADR 0027 — Multi-org : un compte, plusieurs entreprises (propriétaire multi-entreprises, employé multi-employeurs, holding, cabinet comptable)

## Statut

Proposée — **validation owner requise**

**Date** : 2026-09-28
**Décideurs** : owner (validation finale) — proposition rédigée par l'agent Zentor
**Liens** : issue #8225 (BOS-036, programme Business OS Block 3) · matrice des cas A–H : `docs/architecture/business-os/08_ARCHITECTURE_CHALLENGE.md` §4 · référence d'exécution : `docs/architecture/business-os/09_EXECUTION_READINESS_REVIEW.md` (BOS-036, §2.3) · ADR-0001 (multi-tenant PostgreSQL) · ADR-0024 (scoping transitif employé) · ADR-0025 (tokens Sanctum)
**POC jetable** : branche `poc/8225-bos-036-company-switch` (hors `main`, vérifications propres au POC — voir § « POC » ci-dessous)

---

## Contexte

Le modèle Company couvre les cas mono-tenant (A), multi-établissements (C, E, F) et multi-verticales mono-entité (G-mono-entité). Quatre cas réels restent non supportés proprement (matrice 08 §4) :

- **B** — une personne possède plusieurs entreprises : en pratique elle crée un compte par email distinct ;
- **D** — un employé travaille pour plusieurs employeurs : impossible en base et en validation ;
- **G-multi-entités** — une holding avec plusieurs entités légales : companies séparées sans consolidation (reportée, F2) ;
- **H** — un cabinet comptable gère des entreprises clientes : **aucun** accès possible pour un acteur externe. C'est le cas multi-org le plus demandé du marché cible (PME + fiduciaires).

### Verrous structurels mesurés (preuves code)

1. **`employees.email` unique global** : la migration `database/migrations/tenant/2026_04_17_000105_enforce_global_unique_email_on_employees.php` fait passer l'unicité de `(company_id, email)` à `email` seul, à l'échelle plateforme ; la règle de validation `app/Rules/GlobalEmailUnique.php` refuse en amont tout email présent dans `public.user_lookups` (message `GLOBAL_COLLISION`), appliquée dans `StoreEmployeeRequest`, `UpdateEmployeeRequest`, `OnboardingQrController`, `UpdateProfileRequest`.
2. **`user_lookups.email` en clé primaire** : `database/migrations/public/2026_04_01_000003_create_public_support_tables.php` — « table de dispatch auth : email → company_id + schema_name en O(1) ». Une ligne par email = un seul tenant résolu au login (`AuthService::login`, `app/Core/Auth/Infrastructure/Services/AuthService.php`).
3. **Session/token mono-tenant** : les tokens Sanctum portent les abilities `tenant_schema:` / `tenant_email:` / `tenant_company:` / `tenant_employee:` d'un seul tenant ; `TenantMiddleware` réhydrate le contexte depuis ces abilities ou depuis `user_lookups`. **Aucun endpoint de changement de company n'existe** (`rg -i switch` : uniquement des kill-switches plateforme et des bascules `search_path` internes aux jobs).

### Socle multi-org existant (à réutiliser, REUSE > CREATE)

- `users` (schéma `public`) : identité globale déjà distincte de `employees` ;
- **`user_employee_links`** (`database/migrations/public/2026_05_02_100001_create_users_and_company_requests_tables.php`) : `unique(user_id, company_id)`, statut, `linked_at` — modèle `app/Modules/HR/Domain/Models/UserEmployeeLink.php` (exception tenant-scope canonique documentée : table résolue au login, avant contexte tenant). Déjà câblée pour les comptes « ordinary » (`UserEmployeeLinkController::linkByEmail`, `myLinks`) et l'acceptation des demandes d'intégration (`CompanyIntegrationRequestController`) — **mais non branchée au login classique, ni au kiosk, ni au mobile**.

### Conséquence aujourd'hui

Rejoindre une 2ᵉ company = créer une nouvelle ligne `employees` + une nouvelle invitation, ce que l'unicité globale d'email interdit. Le contournement réel est « un email par compte », ce qui fragmente l'identité et rend le cas H (cabinet) inadressable.

---

## Options analysées

| Option | Description | Verdict |
|---|---|---|
| **No-go (statu quo)** | Un email par compte ; B/D/H contournés par comptes multiples | Refusée : cas H inadressable, UX indigne du positionnement Business OS, et le marché cible (fiduciaires) est explicitement visé (08 §4) |
| **Go complet** | Lever l'unicité `employees.email`, refondre `user_lookups` (email → N tenants), choix du tenant au login | Refusée : touche le chemin critique auth (login, OTP, kiosk, mobile), casse la résolution O(1), risque de régression cross-tenant élevé — disproportionné sans client groupe signé |
| **Go-limité** | Conserver l'unicité email et `user_lookups` ; multi-companies via `user_employee_links` ; **switch explicite post-auth** | **Retenue** : aucune migration destructive, réversible par flag, couvre B, D et H ; G-multi-entités reste reporté (F2) |

---

## Décision

**Go-limité : un compte, plusieurs entreprises — switcher de company adossé à `user_employee_links`, sans toucher à l'unicité email ni à la table de dispatch.**

1. **Identité globale conservée.** Un email = une identité `users` + au plus une company *principale* (celle de `user_lookups`). `employees.email` reste globalement unique ; `GlobalEmailUnique` et `user_lookups` ne changent pas. Aucune table `Organization`/`BusinessGroup`/`Membership` n'est créée (concepts refusés, décision 08).
2. **Multi-appartenance par liens.** Une personne présente dans N companies l'est par N lignes `employees` (une par tenant) reliées au **même** compte `users` via `user_employee_links` (`unique(user_id, company_id)` déjà en place). Le flux d'invitation est modifié pour **réutiliser le compte existant** quand l'email possède déjà un compte `users` (création du lien au lieu d'exiger un nouvel email) — le mot de passe reste porté par le compte `users`, l'employé garde ses attributs par tenant.
3. **Switch explicite, jamais implicite.** Nouvel endpoint `POST /auth/switch-company` : le user authentifié désigne une company **parmi ses liens actifs** ; le serveur vérifie le lien (fail-closed : pas de lien ⇒ 403 uniforme, sans fuite d'existence), révoque le token courant et **réémet un token Sanctum** portant les abilities du tenant cible (+ régénération de session sur le chemin web). Une requête = un tenant, toujours. L'événement est audité (`auth.company_switched`, identifiants uniquement).
4. **Cas H (cabinet comptable) par le même mécanisme.** Le comptable dispose d'un compte `users` lié à une ligne `employees` dans chaque company cliente (rôle intra-company, ex. `manager_role=comptable`, déjà existant). Il accède à chaque cliente **séquentiellement** via le switch. **Aucune vue consolidée cross-tenant** n'est introduite par cette décision (reporting holding = F2, hors scope).
5. **Cas B/D couverts nativement.** Le propriétaire multi-entreprises lie ses companies au même compte ; l'employé multi-employeurs accumule les liens actifs. L'unicité email par company n'est plus un blocage : c'est le **lien** qui fait foi, pas la ligne `employees` seule.
6. **G-multi-entités (holding) explicitement reporté.** Consolidation, reporting groupe et partage cross-entités restent hors de cette décision (aucun client groupe signé — F2). Cette ADR ne ferme pas la porte : le switcher est le prérequis de toute console groupe future.

### Matrice d'impact (exigée par l'issue)

| Surface | Impact | Détail |
|---|---|---|
| **Kiosk** (`front/zkteco-kiosk`, `ResolveKioskDevice`) | **Aucun** | Le tenant est porté par le device (`AttendanceKiosk.company_id`), pas par un compte utilisateur ; hors champ du switch |
| **Invitations** (`UserInvitationService`) | **Modifié** | Si l'email invité possède déjà un compte `users` : création d'un lien `user_employee_links` (+ ligne `employees` dans la company invitante) au lieu d'un refus/nouveau compte ; acceptation par token inchangée |
| **OTP** (`LoginCodeController`) | **Inchangé au login** | Le code de connexion résout toujours la company principale via `user_lookups` ; le switch est un acte post-auth distinct |
| **Mobile** (`leopardo_core` auth_repository) | **Évolution additive** | Aujourd'hui la company vient de la réponse login ; ajout ultérieur d'un sélecteur appelant `POST /auth/switch-company` + `GET /employee-links` (déjà exposé) ; aucune rupture de contrat existant |
| **`user_lookups`** | **Aucun** | Reste la table de dispatch email → company principale ; jamais multi-lignes |
| **Sessions / tokens** | **Modifié (additif)** | Réémission + révocation au switch ; TTL et hygiène existants (ADR-0025) inchangés ; les tokens antérieurs au switch ne donnent plus accès au tenant précédent |
| **RBAC / policies** | **Aucun** | `manager_role` et grants restent intra-company ; le switch ne transporte **aucun** droit d'un tenant à l'autre |
| **Audit** | **Additif** | Événement `auth.company_switched` (user, from_company, to_company, horodatage — identifiants uniquement, jamais d'email en clair, convention #8164) |

### Estimation de migration (si go-limité validé)

| Étape | Contenu | Effort |
|---|---|---|
| 1 | Endpoint switch + réémission/révocation token + audit + tests (fail-closed, cross-tenant, idempotence) | 2–3 j |
| 2 | Flux d'invitation : réutilisation du compte existant + création de lien (transaction, tests d'acceptation) | 2 j |
| 3 | Sélecteur de company web (`front/web`) + appel switch | 2 j |
| 4 | Sélecteur mobile (`leopardo_core`) | 2–3 j |
| 5 | Docs (RBAC_ROUTE_MATRIX, guide auth) + OpenAPI | 1 j |

**Total ≈ 9–11 j**, sans migration de données destructive : `user_employee_links` existe ; les liens des comptes « ordinary » actuels sont déjà exploitables.

- **Plan de rollback** : feature flag `auth.company_switch` (OFF = comportement actuel) ; le retrait du flag laisse les liens en base, inertes ; aucune donnée à restaurer.
- **Stratégie email** : inchangée — unicité globale conservée, pas d'alias, pas d'email secondaire.

### POC (jetable, hors `main`)

Branche `poc/8225-bos-036-company-switch` : modélisation exécutable (Node, zéro dépendance) des invariants de la stratégie — tables simulées `users` / `employees` / `user_lookups` / `user_employee_links` / tokens — démontrant par vérifications automatiques : (a) switch sans lien → refus fail-closed ; (b) unicité email préservée (un second employé au même email n'est possible que via lien, jamais via nouveau compte) ; (c) le token réémis est scopé au tenant cible et l'ancien révoqué ; (d) le tenant kiosk (device) est inaffecté. Le POC ne sera **jamais** fusionné dans `main`.

---

## Conséquences

**Positives**
- B, D et H deviennent adressables avec un mécanisme unique, réversible, sans toucher au chemin critique du login.
- Le socle réutilisé (`users` + `user_employee_links`) est déjà en production et testé cross-tenant (`UserEmployeeLinkCrossTenantTest`).
- Aucun concept refusé n'est créé ; la décision est compatible avec l'Exit Gate Block 3 (« décision multi-org écrite »).

**Limites assumées**
- Pas de consolidation holding (G-multi-entités) : reporting groupe, partage cross-entités et console cabinet **agrégée** restent à décider plus tard (F2) — le cas H est couvert en accès **séquentiel** uniquement.
- Un email secondaire ne devient pas un alias de connexion : la connexion se fait toujours sur l'email unique.
- Le switch est un acte explicite : aucune donnée de deux tenants n'est jamais visible dans une même requête.

## Règles opérationnelles

1. **Une requête = un tenant.** Le switch réémet le contexte ; il est interdit d'introduire un paramètre `company_id` en entrée utilisateur pour changer de portée (le tenant vient de la session/token, jamais d'une entrée — règle multi-tenant inviolable).
2. **Fail-closed partout** : pas de lien actif ⇒ refus uniforme sans fuite d'existence ; lien `pending`/révoqué ⇒ refus.
3. **Audit obligatoire** de chaque switch (`auth.company_switched`, identifiants uniquement).
4. **Aucune table Organization/BusinessGroup/Membership** ; toute évolution vers une consolidation groupe passe par une **nouvelle** ADR.
5. Le kiosk ne participe jamais au switch (tenant = device).
6. Cette ADR n'autorise **aucune implémentation de production** avant validation owner : le périmètre livré ici est documentaire + POC jetable.
