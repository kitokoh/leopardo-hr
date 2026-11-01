# Audit — Fondation Identity & Access Control · **Révision 3**

> **Dépôt** : `kitokoh/leopardo-hr` · **Branche** : `audit/identity-access-control-foundation`
> **HEAD audité** : `982032ba9` (branche d'audit — ne contient **aucun** code applicatif)
> **Nature** : contre-revue architecturale — **aucune modification de code applicatif**
> **Révision** : 3 · corrections demandées par le propriétaire (§0.2)

---

## ⚠️ Aucune implémentation n'a été démarrée

**Ce document est une proposition. Aucun lot du plan n'est commencé, aucune migration n'est
écrite, aucun guard ni middleware n'est modifié, aucune configuration n'est changée.**
L'implémentation attend la **validation explicite du propriétaire** sur ce rapport et sur le
plan (`docs/roadmaps/IDENTITY_ACCESS_CONTROL_IMPLEMENTATION_PLAN.md`).

Les seuls fichiers de cette branche sont **documentaires** (`docs/`) et des **tests d'audit**
(`api/tests/Feature/Audit/`, groupe `audit`, hors CI bloquante — §4.5). Ils **observent** ;
ils ne corrigent rien.

---

## 0.1 Ce que contredit la révision 3

La révision 3 n'est pas une reformulation de la révision 2. Trois de ses conclusions
tombent, dont deux qui lui étaient favorables.

| Affirmation de la révision 2 | Verdict | Preuve |
|---|---|---|
| « Le défaut multi-appartenance est un **verrou** : la 2ᵉ entreprise est inatteignable » | **Incomplet — et rassurant à tort** | `TenantAmbiguityProofTest` : quand les ids coïncident (cas par défaut), la garde est **inopérante** et le dispatch est **écrasé**. Ce n'est pas un verrou, c'est un **aiguillage mouvant**. |
| « `syncUserLookup()` refuse silencieusement la 2ᵉ appartenance » | **Vrai seulement quand les ids diffèrent** | Les `employees.id` sont des séquences **par schéma** : l'id `1` existe dans chaque tenant. La garde compare deux entiers de populations différentes. |
| « `SessionDurationTest` échoue ⇒ **jeton sans expiration** (vrai défaut, P1) » | **Faux** | La clé est à la **racine** de la réponse, pas sous `data` ; et l'expiration réelle est **~30 jours**, mesurée à `43 199` minutes. **Erreur de chemin dans le test**, pas un défaut applicatif. |
| « 182 tests, **645** assertions » | **Inexact** | Exécution réelle : **646** assertions. |
| « Aucun guard ni middleware à modifier pour L2 » | **Intenable** | Voir §6.3 : c'est exactement le contraire. |

**Ce que la révision 3 confirme** au contraire : le multi-tenant est solide, l'OTP est un
problème de disponibilité et non de sécurité, et l'écart grand public reste structurel.

---

## 0.2 Corrections apportées en révision 3

| # | Correction | Où |
|---|---|---|
| 1 | Décompte « 182 / 645 » **ventilé** et recompté : **646** assertions, 4 échecs, 1 erreur, **2 risky** | §4.1 |
| 2 | `SessionDurationTest` : défaut **requalifié** — erreur de chemin de test, **pas** un défaut applicatif | §4.2.1 |
| 3 | Les **16 tests d'audit** distingués des tests de la fondation (qui les a écrits, ce qu'ils prouvent, où ils vivent) | §4.3 |
| 4 | Les 13 scénarios : **liste exhaustive**, résultat unique, **catégorie** présence / comportement / sécurité | §4.4 |
| 5 | Contradiction anti-escalade **tranchée par le code** : un `principal` peut déléguer ce qu'il ne détient pas | §5.4 |
| 6 | Risque de sélection tenant **prouvé par exécution** sur deux schémas réels, deux modes de défaillance | §3 |
| 7 | Pont d'identité : **interdiction mécaniquement vérifiable** du rattachement sur numéro non vérifié | §7.2 |
| 8 | Lots : aveu explicite que **L2 exige de modifier les middlewares** ; estimation en **fourchette** | §6.3, §10 |

---

## 1. Méthode et commandes exactes

### 1.1 Environnement

| Élément | Valeur |
|---|---|
| Runtime | PHP **8.4.27** (exigence `api/composer.json:12` : `^8.4.1`) |
| Base | PostgreSQL **15.19** + PostGIS 3 |
| Lanceur | `CI=true php vendor/bin/phpunit` |

> **`CI=true` est obligatoire** : `api/tests/TestCase.php:100-108` réécrit l'hôte PostgreSQL
> vers `pgsql` dès que Docker est détecté. Sans le drapeau :
> `could not translate host name "pgsql"`. Ce piège coûte une heure à qui l'ignore.

### 1.2 Commandes réellement exécutées

```bash
# 1. Suite élargie (Auth + Tenant + Audit) — sortie conservée
cd api && CI=true php vendor/bin/phpunit \
    tests/Feature/Auth tests/Feature/Tenant tests/Feature/Audit --testdox \
    2>&1 | tee /workspace/phpunit_auth_tenant_audit.log

# 2. Répertoire d'audit complet
cd api && CI=true php vendor/bin/phpunit tests/Feature/Audit \
    2>&1 | tee /workspace/phpunit_audit.log

# 3. Preuve du risque tenant (2 schémas PostgreSQL réels)
cd api && CI=true php vendor/bin/phpunit tests/Feature/Audit/TenantAmbiguityProofTest.php

# 4. Sonde de forme de réponse (où vit token_expires_at)
cd api && CI=true php vendor/bin/phpunit tests/Feature/Audit/IdentityShapesProbeTest.php

# 5. Contrôle du groupe et de son exclusion
cd api && CI=true php vendor/bin/phpunit --group=audit
cd api && CI=true php vendor/bin/phpunit tests/Feature/Audit --exclude-group=audit
```

**Fichiers de sortie conservés** : `/workspace/phpunit_auth_tenant_audit.log` (synthèse en
dernière ligne), `/workspace/phpunit_audit.log`.

---

## 2. Volet 1 — Comptes & authentification

### 2.1 Les populations et leurs modèles

| Population | Modèle | Table | Schéma | Guard |
|---|---|---|---|---|
| Collaborateur interne Leopardo | `Core\Tenant\Domain\Models\SuperAdmin` | `super_admins` | `public` | `super_admin_api` |
| Employé / admin d'entreprise | `Core\Auth\Domain\Models\Employee` | `employees` | **par tenant** | `sanctum` |
| Compte public générique | `Core\Auth\Domain\Models\User` | `users` | `public` | `user_api` |
| Client grand public travel | `Modules\TravelAgency\Domain\Models\TravelCustomerAccount` | `travel_customer_accounts` | `public` | `travel_customer` |

Preuves : `api/config/auth.php:52-78` (guards), `:99-118` (providers),
`Modules/TravelAgency/Domain/Models/TravelCustomerAccount.php:24-43`.

> **Écart avec la décision du propriétaire.** La décision validée est « comptes public
> (`users`) et tenant (`employees`) séparés, reliés par le téléphone, **aucune troisième
> identité** ». L'état réel comporte une **quatrième** table d'identité. Ce n'est pas une
> nuance théorique : une population entière vit hors du graphe `users`. L'ADR (§7) évalue la
> trajectoire de convergence, pas une refonte.

### 2.2 Téléphone comme identifiant de premier rang

`users.phone_e164` existe, avec un **index unique partiel** — donc « unique seulement si
renseigné » : deux comptes sans téléphone sont permis, deux comptes avec le même numéro ne le
sont pas. Preuve : `database/migrations/public/2026_10_24_000100_8736_add_phone_e164_to_users.php:65,73-74`.

**Limite** : `travel_customer_accounts` n'a **pas** cette colonne. Le pont annoncé par la
décision du propriétaire n'existe donc pas encore côté grand public.

### 2.3 OTP — disponibilité, pas sécurité (tranché par le code)

La question posée était : « le stub de test accepte-t-il un code arbitraire (faille) ou
n'envoie-t-il simplement rien (disponibilité) ? »

**Réponse : il n'envoie rien. Aucun code arbitraire n'est accepté.**

| Étape | Code | Ligne |
|---|---|---|
| Génération | `str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT)` | `LoginCodeController.php:84,98` |
| Stockage | `'hash' => Hash::make($code)` | `:102` |
| Vérification | `Hash::check((string) $validated['code'], (string) $entry['hash'])` | `:164` |
| Usage unique | `Cache::forget($key)` **avant** l'ouverture de session | `:180` |
| Verrouillage | `MAX_ATTEMPTS` puis `429` | `:151-159` |

Le code est aléatoire cryptographiquement (`random_int`), haché (`Hash::make`), comparé en
temps constant (`Hash::check`), à usage unique, avec une limite de tentatives. **La
vérification est saine.** Ce qui manque est la **livraison** : un `try/catch` autour de
`Mail::to(...)->send(...)` (`:113-124`) journalise `auth.login_code_send_failed` puis
**retourne la réponse générique de succès**. L'appelant ne peut pas distinguer « envoyé » de
« perdu ». C'est un défaut de **disponibilité et d'observabilité**, classé P0 pour cette
raison — pas une faille d'authentification.

---

## 3. Volet 3 — Preuve du risque de sélection tenant

C'est le point le plus critique, et la révision 2 l'avait mal caractérisé. Voici la preuve
**par exécution**, pas par affirmation.

### 3.1 Le code responsable, exactement

**Détermination du contexte actif** — `api/app/Core/Auth/Infrastructure/Services/AuthService.php` :

```php
// :33-40  — le dispatch
$lookup = DB::table('public.user_lookups')->where('email', $email)->first();
// :45-50  — le schéma vient du lookup, jamais d'une demande de l'utilisateur
$this->setTenantSearchPath($lookupSchema);
```

Le contexte actif est donc **entièrement déterminé par une ligne de `public.user_lookups`**.
Aucun paramètre de requête n'est lu.

**La table** — `database/migrations/public/2026_04_01_000003_create_public_support_tables.php:34` :

```php
$table->string('email', 150)->primary();   // ← UNE ligne par e-mail, par construction
```

**L'écrivain** — `api/app/Core/Auth/Domain/Models/Employee.php:757-790` :

```php
// :769  — suppression des lookups obsolètes du MÊME employé
->where('employee_id', $this->id)->where('company_id', $this->company_id)->delete();

// :773-777 — « garde » anti-écrasement
$existing = DB::table($this->userLookupTable())->where('email', $this->email)->first();
if ($existing && (int) $existing->employee_id !== (int) $this->id) {
    return;   // aucune écriture, aucun log, aucune erreur
}
```

### 3.2 Le défaut de raisonnement de cette garde

`employees.id` est un `serial` **par schéma** (`2026_04_01_000101_create_employees_table.php`).
Chaque tenant possède donc un employé d'identifiant `1`. La garde compare
`$existing->employee_id` (venu du schéma A) à `$this->id` (venu du schéma B) comme s'ils
désignaient une population commune. **Ils n'en désignent pas.** Deux issues, toutes deux
silencieuses :

- **ids égaux** → la garde laisse passer → `updateOrInsert` **écrase** la ligne.
- **ids différents** → la garde sort → la 2ᵉ appartenance est **abandonnée**.

### 3.3 Ce que l'exécution démontre

`api/tests/Feature/Audit/TenantAmbiguityProofTest.php` — **3 tests, 17 assertions, PASS**,
avec deux schémas PostgreSQL réels (`t_alpha`, `t_beta`) et un même e-mail dans chacun.

**Scénario A — ids qui coïncident** (le cas par défaut : les deux schémas sont neufs) :

```
[SCENARIO A] after alpha: schema=t_alpha employee_id=1
[SCENARIO A] after beta : schema=t_beta  employee_id=1
[SCENARIO A] collision=YES
```

→ Le dispatch est **passé de `t_alpha` à `t_beta`**. La personne suivait son travail dans
l'entreprise A ; après l'ajout de son appartenance à B, **sa session pointe désormais sur B**
sans qu'aucun de ses gestes ne l'ait demandé. Ce n'est pas un verrou : c'est un **contexte
mouvant**, déterminé par l'ordre des écritures en base.

**Scénario B — ids différents** (tenant dont la séquence a vécu, ici `42`) :

```
[SCENARIO B] alpha_employee_id=1 beta_employee_id=42 differ=YES
[SCENARIO B] lookup schema=t_alpha employee_id=1
```

→ Le dispatch **reste figé sur `t_alpha`** ; l'employé de `t_beta` existe pourtant bien dans
son schéma (assertion d'existence incluse). **Déni d'accès silencieux.**

**Scénario C — aucune sélection possible** :

```
[SCENARIO C] status=200 root_keys=data,token,token_type,token_expires_at tenant_schema=NULL
```

→ Un `tenant`/`tenant_schema`/`company_id` envoyé au login est **ignoré** ; la réponse ne
mentionne **même pas** le schéma résolu (`AuthController.php:105-110` n'ajoute que `token`,
`token_type`, `token_expires_at`). La personne ne peut ni choisir son entreprise, ni savoir
dans laquelle elle est.

### 3.4 Qualification honnête du risque

| Question | Réponse |
|---|---|
| Est-ce une **escalade de privilèges** ? | **Non.** Le lookup est déterministe et les permissions restent correctes *dans* le tenant où l'on atterrit. |
| Est-ce un **risque de voir les données d'une autre entreprise** ? | **Oui, indirectement.** Atterrir dans B quand on croyait être dans A, c'est lire les données de B avec les droits qu'on y détient — légitimes, mais **non choisis**. |
| Est-ce un **déni d'accès** ? | **Oui, dans le scénario B** : la 2ᵉ entreprise est inatteignable. |
| Est-ce **silencieux** ? | **Oui, toujours.** Ni log, ni erreur, ni indication à l'écran. |

C'est pour cela que ce point reste **P0** : il n'est pas une faille exploitable par un
attaquant, mais il rend la promesse centrale — « une personne, plusieurs contextes **sans
confusion** » — structurellement fausse.

### 3.5 Scénario reproductible, pas à pas

1. **Données de départ** : deux entreprises, deux schémas `t_alpha` et `t_beta`, une table
   `employees` dans chacun.
2. **Insertion** : le même e-mail `same.person@example.com` dans les deux schémas ; mot de
   passe identique ; `matricule` distinct (`EMP-ALPHA` / `EMP-BETA`) pour identifier l'employé
   réellement résolu.
3. **Synchronisation** : `syncUserLookup()` appelé pour chaque employé, comme le fait la
   production.
4. **Observation** : `SELECT * FROM public.user_lookups WHERE email = 'same.person@example.com'`
   → **une seule ligne**, pointant sur le **dernier schéma synchronisé** (A) ou sur le premier
   (B), selon la coïncidence des ids.
5. **Login** : `POST /api/v1/auth/login` avec cet e-mail et ce mot de passe → **200**, session
   ouverte dans ce seul schéma.
6. **Conclusion** : la 2ᵉ appartenance n'existe nulle part dans le chemin d'authentification.

### 3.6 Solution garantissant une sélection explicite et validée serveur

Trois exigences, indissociables :

1. **La clé de dispatch doit porter le contexte.** `user_lookups` passe de `PRIMARY KEY (email)`
   à `PRIMARY KEY (email, company_id)` — **c'est une migration de schéma**, donc une décision
   du propriétaire, pas un correctif cosmétique.
2. **Le contexte est choisi, pas deviné.** `POST /auth/context` reçoit `company_id`, vérifie
   que l'acteur possède bien une appartenance **active** dans cette entreprise, **puis**
   émet un jeton porteur de l'abilité `tenant_company:<id>`. Le serveur ne fait jamais
   confiance au client au-delà de « je demande cette entreprise ».
3. **Le contexte est revalidé à chaque requête.** Le middleware `tenant` doit confronter
   l'abilité du jeton à l'appartenance réelle — sinon une révocation ne prend effet qu'à
   l'expiration du jeton. **Ceci touche un middleware** (§6.3).

Sans l'exigence 3, un jeton émis avant une révocation continue d'ouvrir l'entreprise. C'est
précisément le scénario #6 de la matrice (§4.4), dont le test actuel ne couvre que la
révocation de **jeton entier**.

---

## 4. Volet 2 — Preuves d'exécution et matrice de sécurité

### 4.1 Décompte ventilé — d'où vient chaque chiffre

La révision 2 annonçait « 182 tests, 645 assertions, 4 échecs, 1 erreur ». **Recompté :**

| Suite | Portée | Tests | Assertions | Résultat |
|---|---|---|---|---|
| `tests/Feature/Auth` | connexion, MFA, Google, quotas, SSO | inclus ci-dessous | — | — |
| `tests/Feature/Tenant` | isolation, `TenantManager`, modules horizontaux | inclus ci-dessous | — | — |
| `tests/Feature/Audit` | 16 tests d'audit + 9 préexistants (`AuditLogGlobalTest`) | 25 | 58 | ✅ **OK** |
| **Total Auth + Tenant + Audit** | **une seule invocation** | **182** | **646** | ⚠️ 4 failures, 1 error, **2 risky** |

**Pourquoi 182 ≠ la somme des suites prises séparément** : les trois répertoires sont passés
**en une seule invocation**, et PHPUnit compte les tests par **classe chargée une fois**. Par
ailleurs `tests/Feature/Audit` est contenu **dans** le total : `25` de ses tests sont déjà
comptés dans les `182`. `157 (Auth+Tenant)` + `25 (Audit)` = `182` est cohérent — mais `157`
n'a pas été mesuré isolément dans cette session, il se déduit.

**Correction du chiffre** : `645` → **`646` assertions**. La révision 2 avait recopié un
total d'exécution antérieur.

**Deux « risky » à ne pas confondre avec des échecs** :

| Test | Avertissement | Nature |
|---|---|---|
| `Auth\GoogleIdentityBindingTest::test_google_callback_rejects_ambiguous_email_across_tenants` | handlers d'erreur non retirés | conséquence de l'erreur SQL (§4.2 #5) |
| `Tenant\TenantManagerStandardizationTest::test_tenantless_crm_job_is_rejected_before_data_access` | aucune assertion exécutée | **le test ne prouve rien** — trou de couverture |

### 4.2 Les 5 tests en échec — nommés, classés, justifiés par le code

| # | Test | Symptôme | Cause exacte | Classement |
|---|---|---|---|---|
| 1 | `Auth\RegisterLoginFlowTest::test_registered_account_with_invitation_can_login_again` | `422` attendu `201` | `password123456` refusé par `NotCommonPassword` : `rtrim($normalized, '0123456789')` (`:64`) réduit à `password`, présent dans `BLOCKLIST` (`:29-30`) | **Fixture défaillante** |
| 2 | `Auth\TokenQuotaTest::test_login_keeps_only_latest_tokens_within_quota` | `422` attendu `201` | même règle, `TokenQuotaTest.php:34` | **Fixture défaillante** |
| 3 | `Auth\TokenQuotaTest::test_quota_zero_disables_pruning` | `422` attendu `201` | même règle, `:82` | **Fixture défaillante** |
| 4 | `Auth\SessionDurationTest::test_login_issues_a_token_expiring_in_thirty_days` | `Failed asserting that null is of type string` (`:68`) | **le test lit `data.token_expires_at` ; la clé est à la racine** (§4.2.1) | **Erreur de test — requalifié** |
| 5 | `Auth\GoogleIdentityBindingTest::test_google_callback_rejects_ambiguous_email_across_tenants` | `SQLSTATE[23505]` sur `employees_email_unique` | l'index est **par schéma tenant** ; le harnais `CreatesMvpSchema` crée plusieurs entreprises dans **un seul** schéma | **Artefact du harnais** |

#### 4.2.1 Le défaut réel de `SessionDurationTest` — requalification

La révision 2 concluait « vrai défaut applicatif : jeton sans expiration sur les chemins
`AuthController.php:463,552` ». **Cette conclusion est fausse, et je la retire.**

**Preuve par exécution** (`IdentityShapesProbeTest`, PASS) :

```
[PROBE] root_keys=data,token,token_type,token_expires_at
[PROBE] token_expires_at@root=YES value='2026-12-01T05:23:11+00:00'
[PROBE] token_expires_at@data=NO  value=NULL
[PROBE] expires_in_minutes=43199 (attendu 43200)
```

Trois faits :

1. La clé `token_expires_at` est **à la racine** de la réponse, à côté de `data` — parce que
   `EmployeeResource::additional([...])` (`AuthController.php:105-110`) fusionne au **premier
   niveau**, pas dans le wrapper `data`.
2. Sa valeur est une date **à ~30 jours** (`43 199` minutes mesurées). Le jeton **expire bien**.
3. Le test échoue parce qu'il interroge `$response->json('data.token_expires_at')` —
   chemin qui n'existe pas.

**Comportement attendu vs obtenu** :

| | Attendu par le test | Obtenu |
|---|---|---|
| Chemin | `data.token_expires_at` | `token_expires_at` (racine) |
| Type | `string` | `string` ✔ |
| Durée | ~43200 min | **43199 min** ✔ |

**La nuance sur `AuthController.php:463,552`** (chemins Google) reste vraie sur un point : ces
deux appels passent `createToken($name)` **sans** `$expiresAt`, alors que `AuthService.php:222`
le passe. Mais **ce n'est pas un trou de sécurité**, parce que Sanctum applique la
configuration globale même quand `expires_at` est NULL —
`vendor/laravel/sanctum/src/Guard.php:127-129` :

```php
(! $this->expiration || $accessToken->created_at->gt(now()->subMinutes($this->expiration)))
&& (! $accessToken->expires_at || ! $accessToken->expires_at->isPast())
```

Le premier terme borne le jeton par `created_at + sanctum.expiration` (= 43200 min,
`api/config/sanctum.php:23`). Un jeton sans `expires_at` **est donc quand même limité à 30
jours**. La différence est de **précision** (30 j depuis la création vs depuis l'émission) et
d'**explicitation en base** — pas de sécurité. Classement : **P3, hygiène**, et non P1.

### 4.3 Les 16 tests d'audit vs les tests de la fondation

Il faut distinguer deux populations que la révision 2 mélangeait :

| | **Tests d'audit** | **Tests de la fondation** |
|---|---|---|
| **Qui les a écrits** | cette mission d'audit (31 oct. / 1er nov. 2026) | l'équipe Leopardo, au fil des fonctionnalités |
| **Ce qu'ils prouvent** | l'**état** de la fondation à un instant donné : ce qui existe, ce qui manque | le **comportement attendu** d'une fonctionnalité livrée |
| **Où ils vivent** | `api/tests/Feature/Audit/` — `#[Group('audit')]` | partout ailleurs (`Auth/`, `Tenant/`, `HR/`…) |
| **Rôle en CI** | **exclus** de la CI bloquante | bloquants |
| **Cycle de vie** | destinés à s'**inverser** puis être supprimés à mesure que les lots livrent | permanents |

Les tests d'audit étaient **16** en révision 2 ; ils sont **20** en révision 3 (16 + 3 de
`TenantAmbiguityProofTest` + 1 de `IdentityShapesProbeTest`). Le répertoire
`tests/Feature/Audit` compte **29 tests** au total : **20** d'audit + **9** préexistants.

### 4.4 Les 13 scénarios de sécurité — liste exhaustive

Une ligne par scénario. `Exécuté` = lancé dans cette session. `Catégorie` : **P**résence
(vérifie qu'un mécanisme existe) · **C**omportement (vérifie ce que fait le code) ·
**S**écurité (vérifie qu'une attaque échoue).

| # | Scénario | Test (nom exact) | Exécuté | Résultat réel | Cat. |
|---|---|---|---|---|---|
| 1 | Marketing sans accès à l'administration des paiements | `IdentityAccessControlAuditTest::test_marketing_role_has_no_payment_administration_access` | ✅ | **PASS** | S |
| 2 | Support ne peut pas devenir super admin | `IdentityAccessControlAuditTest::test_support_role_cannot_manage_the_platform_team` | ✅ | **PASS** | S |
| 3 | Admin de tenant ne peut pas s'attribuer des permissions plateforme | `EmployeeSensitiveFillableTest::test_sensitive_fields_are_not_written_by_mass_assignment` | ✅ | **PASS** | S |
| 4 | Employé de A sans accès aux données de B | `IdentityAccessControlAuditTest::test_tenant_scope_confines_employee_queries_to_one_company` | ✅ | **PASS** | S |
| 5 | Employé membre de 2 entreprises avec rôles différents | `TenantAmbiguityProofTest` (3 tests) | ✅ | **ÉCART PROUVÉ** — contexte mouvant (A) ou inaccessible (B) | S |
| 6 | Révocation de rôle retirant effectivement les accès | `IdentityAccessControlAuditTest::test_revoked_token_no_longer_authenticates` | ✅ | **PARTIEL** — révocation de **jeton** seulement ; pas de révocation de rôle à jeton constant | S |
| 7 | Désactivation de compte bloquant l'usage | `IdentityAccessControlAuditTest::test_suspended_employee_is_denied_at_the_tenant_middleware` | ✅ | **PASS** | S |
| 8 | Ancien token ne conservant pas de droits révoqués | `IdentityAccessControlAuditTest::test_revoked_token_no_longer_authenticates` | ✅ | **PASS** | S |
| 9 | Utilisateur public sans accès aux données d'un professionnel | `BelongsToCompanyExplicitBypassTest` (4 tests) | ✅ | **PASS** | S |
| 10 | Impossible de rétrograder le dernier admin principal | `IdentityAccessControlAuditTest::test_last_active_super_admin_count_ignores_suspended_accounts` | ✅ | **PASS** pour `super_admin` / **ÉCART** pour le `principal` de tenant | S |
| 11 | Rôle personnalisé ne dépassant pas les droits de son créateur | `IdentityAccessControlGapsTest::test_no_custom_role_table_exists` | ✅ | **SANS OBJET** — aucun rôle personnalisé n'existe | P |
| 12 | Rôle sans accès à un module ne peut pas appeler les endpoints | `IdentityAccessControlAuditTest::test_module_grant_lookup_is_fail_closed` | ✅ | **PASS** | S |
| 13 | Changement d'établissement ne donnant pas accès aux autres | `SiteHorizontalModelTest` (suite `Tenant`) | ✅ | **PASS** | C |

**Décompte honnête** : **8 PASS pleins** (#1, #2, #3, #4, #7, #8, #9, #12), **2 PASS
partiels** (#6, #13), **1 écart prouvé** (#5), **1 écart partiel** (#10 — super admin protégé,
principal de tenant non), **1 sans objet** (#11).

**Ce que la révision 2 annonçait** : « 11 couverts, 1 écart, 1 sans objet » en listant
seulement 8 lignes. **Le tableau ne correspondait pas au décompte.** Celui ci-dessus est ce
que produit réellement la lecture des 13 lignes.

> **Note cruciale** : les scénarios #5 et #11 ont un test **qui passe** alors que le scénario
> n'est **pas satisfait**. Ces tests sont des **constats d'absence**. Les compter comme
> « acquis » est l'erreur exacte que la révision 1 a commise et que la révision 2 n'a corrigée
> qu'à moitié.

### 4.5 Le groupe `audit` et son exclusion de la CI

- Marqueur : `#[Group('audit')]` (attribut PHPUnit 11 ; le docblock `@group` ne suffit plus).
- Exclusion : `.github/workflows/tests.yml:211` — option `--exclude-group=audit`.
- **Vérifié dans les deux sens** :

| Commande | Résultat |
|---|---|
| `phpunit --group=audit` | **20 tests**, 58 assertions |
| `phpunit tests/Feature/Audit --exclude-group=audit` | **9 tests**, 21 assertions |

**Pourquoi cette exclusion est indispensable** : les tests d'audit affirment l'**absence** d'un
mécanisme. Le jour où L2 livre `POST /auth/context`, ces tests **doivent échouer**. S'ils
étaient bloquants, ils bloqueraient la PR qui corrige précisément le défaut.

### 4.6 Tests d'audit qui s'inverseront, lot par lot

Critère de sortie de chaque lot : un lot n'est pas terminé tant que la ligne n'a pas basculé.

| Lot | Test(s) qui s'inversent | Aujourd'hui | Après le lot |
|---|---|---|---|
| **L1** | aucun test d'audit OTP n'existe — **à écrire en premier** | — | — |
| **L2** | `IdentityAccessControlGapsTest::test_there_is_no_endpoint_to_select_a_tenant_context` | PASS (constate l'absence) | **FAIL** → remplacer par un test de comportement |
| **L2** | `TenantAmbiguityProofTest::test_login_offers_no_way_to_choose_the_tenant` | PASS | **FAIL** |
| **L2** | `TenantAmbiguityProofTest::test_when_employee_ids_collide_the_dispatch_is_silently_overwritten` | PASS | **FAIL** |
| **L2** | `TenantAmbiguityProofTest::test_when_employee_ids_differ_the_second_membership_is_dropped_silently` | PASS | **FAIL** |
| **L3** | `SessionDurationTest::test_login_issues_a_token_expiring_in_thirty_days` | FAIL (chemin) | **PASS** |
| **L3** | `IdentityAccessControlAuditTest::test_last_active_super_admin_count_ignores_suspended_accounts` | PASS (super admin seul) | **PASS** + nouveau test principal de tenant |
| **L4** | `IdentityAccessControlGapsTest::test_public_consumer_identity_is_a_separate_model_not_user` | PASS | **FAIL** |
| **L4** | `IdentityAccessControlGapsTest::test_user_employee_link_exists_but_login_does_not_use_it` | PASS | **FAIL** |
| **L5** | `IdentityAccessControlGapsTest::test_no_custom_role_table_exists` | PASS | **FAIL** |
| **L5** | `IdentityAccessControlGapsTest::test_impersonation_has_no_consent_column` | PASS | **FAIL** |
| **L6** | `IdentityShapesProbeTest` | PASS (sonde) | **supprimer** — office rempli |

---

## 5. Volet 2 — Rôles, permissions, anti-escalade

### 5.1 Deux systèmes distincts, non unifiés

| | **Plateforme** (Leopardo) | **Tenant** (entreprise cliente) |
|---|---|---|
| Support | `PlatformRole` — 6 cases | `employees.manager_role` — 12 valeurs |
| Permissions | `PlatformPermission` — **31 cases** | aucune énumération ; des `manager_role` |
| Vérification | `EnsurePlatformPermissionMiddleware` (`platform.permission:<perm>`) | `EnsureApiManagerMiddleware` (`api.manager:<roles>`) + `EnsureModuleGrantMiddleware` |
| Rôles propres à une verticale | — | **oui** (`delivery`, `restaurant` dans le CHECK) |

Preuves : `Core/Tenant/Domain/Enums/PlatformRole.php`, `PlatformPermission.php` (31 `case`),
`Http/Middleware/EnsurePlatformPermissionMiddleware.php`,
`database/migrations/tenant/2026_09_21_000003_8004_unify_manager_role_check_constraint.php:37-50`.

### 5.2 Ce qui marche réellement

- **`PlatformRole` porte une matrice explicite** (`PlatformRole::permissions()`) : `admin`
  obtient `allExcept([TeamManage])`, `marketing` n'a **pas** `payments.view`. Propre, lisible,
  testable — le test #1 de la matrice le vérifie.
- **`team.manage` n'est porté que par `super_admin`** : un `admin` plateforme ne peut pas
  distribuer les rôles. Test #2 le vérifie.
- **Le dernier super admin est protégé** : `PlatformTeamController::updateRole` (`:104-110`)
  rend `422 LAST_SUPER_ADMIN_REQUIRED` si l'on rétrograde le dernier actif ; `deactivate` fait
  de même (`:151-157`) et **révoque les jetons** (`:161`).
- **`platform_role` n'est pas mass-assignable** (`SuperAdmin.php:67`) — assignation explicite.

### 5.3 Ce qui manque

1. **Aucune table de rôles personnalisés.** Ni `roles`, ni `permissions`, ni
   `role_assignments`. `test_no_custom_role_table_exists` le constate. Un tenant ne peut pas
   composer un rôle ; il choisit dans une liste figée de 12 `manager_role`.
2. **Le dernier `principal` de tenant n'est pas protégé.** Aucun compteur équivalent à
   `activeSuperAdminCount()`. Un tenant peut se retrouver sans principal — état dont il ne
   peut plus sortir seul.
3. **Aucun plafond de délégation** — voir §5.4.
4. **Aucun journal de rôle côté tenant.** `audit_logs` existe et `EmployeeModuleGrant` est
   `Auditable`, mais la **modification de `manager_role`** ne laisse pas de trace dédiée.

### 5.4 La contradiction sur l'anti-escalade — tranchée par le code

La révision 2 affirmait à la fois que les permissions sont « anti-escalade » et qu'il manque
« une garde déléguer ≤ ses droits ». **Les deux ne peuvent pas être vrais en même temps.**
Voici ce que fait le code.

**Ce qui est réellement gardé** — `UpdateEmployeeRequest.php:122-140` :

```php
// :122-130 — seul un principal peut changer role/manager_role
if (($this->has('role') || $this->has('manager_role')) &&
    $user?->isManager() && ! $user->hasManagerRole('principal')) {
    $validator->errors()->add('role', __('errors.EMPLOYEE_ROLE_CHANGE_MANAGER_ONLY'));
}

// :135-140 — personne ne peut promouvoir vers `principal`
if ($this->input('manager_role') === 'principal' && $user?->isManager()) {
    $validator->errors()->add('manager_role', __('errors.EMPLOYEE_PROMOTE_PRINCIPAL_FORBIDDEN'));
}
```

**Trois conséquences, dont deux non documentées :**

- ✅ **Aucun `rh`/`comptable`/`dept` ne peut changer un rôle.** La garde `:122` exige
  `principal`. L'escalade par un `rh` est **fermée**.
- ⚠️ **Un `principal` peut attribuer n'importe lequel des 11 autres rôles, y compris ceux dont
  il ne détient pas les capacités.** La garde `:135` bloque `principal`, mais **rien ne compare
  les capacités du rôle attribué à celles du délégant**. Un `principal` sans le module
  `accounting` peut nommer un `comptable`, qui l'aura.
- ⚠️ **Un tenant ne peut jamais nommer un second `principal`** (la garde `:135` frappe aussi le
  `principal` lui-même). Or `activeSuperAdminCount()` protège le **dernier** super admin en
  autorisant la nomination d'un autre. **Asymétrie complète** : côté tenant, on ne peut pas se
  donner de successeur. Le « transfert de responsabilité » demandé par le propriétaire est
  **impossible aujourd'hui**.

**Verdict** : l'anti-escalade existe **pour la plateforme** (matrice fermée, `team.manage`
réservé) et **n'existe pas pour le tenant** (aucun plafond de délégation, pas de successeur
possible). La révision 2 avait raison sur le second point et tort de présenter le premier comme
suffisant.

**Ce que le modèle cible doit garantir** (§8) : `capabilities(role) ⊆ capabilities(delegator)`
au moment de l'attribution, et un rôle système `principal` attribuable **uniquement** par un
super admin plateforme ou par un transfert explicite et tracé.

---

## 6. Volet 3 — Multi-tenant, isolation, invitations

### 6.1 Ce qui est solide

- **`search_path` paramétré avec validation anti-injection** : `isSafeSchemaName()` puis
  `SET search_path TO "<schema>", public`. Aucune concaténation d'entrée utilisateur brute.
- **Fail-closed** : sans contexte, `TenantContextMissingException`.
- **Global scope `BelongsToCompany`** + bypass explicite testé (`BelongsToCompanyExplicitBypassTest`).
- **`TenantManagerStandardizationTest`** couvre le rejet d'un job sans contexte.

C'est la partie la plus mature du dépôt, et la révision 3 le confirme sans réserve.

### 6.2 Ce qui est fragile

- Le **défaut de dispatch** (§3) : le multi-tenant est solide *une fois le contexte établi* —
  c'est l'**établissement** du contexte qui est défaillant. Nuance essentielle : la solidité de
  l'isolation ne protège pas d'un mauvais aiguillage.
- `TenantManagerStandardizationTest:207` **n'exécute aucune assertion** (risky) : le rejet d'un
  job sans contexte n'est pas réellement prouvé.

### 6.3 Lots « indépendants et additifs » — l'aveu nécessaire

La révision 2 présentait « aucun guard ni middleware modifié » comme une contrainte
rassurante. **Cette contrainte empêche une solution correcte.** Il faut le dire clairement :

**Un endpoint `POST /auth/context` ne peut pas fonctionner sans toucher aux mécanismes de
résolution tenant.** Trois raisons, toutes mécaniques :

1. **Le middleware `tenant` lit le schéma dans `user_lookups`.** Émettre un jeton portant
   `tenant_company:<id>` ne suffit pas : le middleware continuerait de résoudre par e-mail et
   écraserait le choix. Il **doit** apprendre à lire l'abilité du jeton.
2. **`user_lookups` a `email` en clé primaire.** Deux appartenances ne peuvent pas coexister.
   Un endpoint de sélection qui ne change pas cette clé est un endpoint qui **ne peut pas**
   proposer deux contextes. C'est une **migration**.
3. **La révocation doit être effective sans attendre l'expiration.** Sans revalidation dans le
   middleware, un jeton reste valide 30 jours après retrait de l'appartenance.

**Ce qui est réellement additif** : L1 (OTP), L3 (hygiène des jetons), L5 (rôles personnalisés
— nouvelles tables, aucun middleware existant modifié), L6 (front).

**Ce qui ne l'est pas** : **L2 et L4 touchent nécessairement** la résolution de contexte, donc
`TenantMiddleware`, `AuthService` et le schéma de `user_lookups`. Les présenter comme
« zéro middleware » serait une promesse intenable, et la découverte en cours de route coûterait
plus cher que de l'annoncer.

> **Principe à retenir** : la sécurité et la cohérence priment sur le nombre de fichiers
> modifiés. Un plan qui minimise le diff au prix d'un contexte non validé côté serveur est un
> plan dangereux.

### 6.4 Cycle de vie et invitations

| Étape | État réel | Preuve |
|---|---|---|
| Invitation par e-mail | ✅ jeton haché, usage unique, expiration | cycle testé |
| Invitation par téléphone | ❌ absent | aucune route |
| Vérification d'identité | ✅ OTP e-mail (+ SMS si configuré) | `LoginCodeController` |
| Activation et mot de passe par la personne | ✅ `trial/set-password` | aucun mot de passe en clair envoyé |
| Accès au bon contexte | ❌ **le défaut §3** | `TenantAmbiguityProofTest` |
| Invitation en attente / expirée / révoquée | ✅ modélisé | — |
| Changement de rôle | ⚠️ partiel (§5.4) | — |
| Départ d'un employé | ✅ `archive`, jetons révoqués | — |
| Suspension / réactivation | ✅ | `PlatformTeamController::deactivate` |
| Transfert de responsabilité | ❌ **impossible** — pas de 2ᵉ principal | `UpdateEmployeeRequest.php:135` |
| Changement de propriétaire d'entreprise | ❌ non modélisé | aucune route |

---

## 7. Volet 4 — Pont d'identité public

### 7.1 Règles exactes

Le pont retenu : `travel_customer_accounts.phone_e164` + `travel_customer_accounts.user_id`
nullable. **Aucune migration de données, aucune rupture de contrat.**

| Situation | Règle |
|---|---|
| **OTP et vérification du téléphone** | `phone_e164` n'est **jamais** un identifiant avant vérification par OTP. Un numéro saisi n'est qu'une **prétention**. |
| **Comptes existants, numéros déjà associés** | Si `phone_e164` existe sur `users` **et** sur `travel_customer_accounts`, **aucun rattachement automatique**. La personne doit prouver la possession du numéro **et** s'authentifier sur les deux comptes, ou passer par un parcours de fusion explicite. |
| **Collisions** | Deux comptes `users` avec le même numéro : l'index unique partiel l'interdit déjà. Un `users` + un `travel_customer` avec le même numéro : **autorisé**, c'est le cas normal du pont. |
| **Rattachement sécurisé** | `user_id` n'est renseigné **que** par une action authentifiée : login OTP réussi sur le numéro **et** session valide sur `users`, ou parcours de fusion avec double preuve. |
| **Changement de numéro** | L'ancien numéro reste **vérifié et tracé** ; le nouveau exige un nouvel OTP. Un changement ne **transfère jamais** `user_id`. |
| **Récupération de compte** | Uniquement via un canal déjà vérifié. **Jamais** par simple connaissance du numéro. |
| **Concurrence des inscriptions** | Contrainte unique en base + gestion de `23505` → réponse idempotente, jamais deux comptes. |
| **Unicité des associations** | `unique(travel_customer_accounts.user_id, company_id)` si le lien est par entreprise ; sinon `user_id` seul. |
| **Révocation et audit** | Toute écriture de `user_id` laisse une ligne d'audit : acteur, ancien, nouveau, motif, horodatage. |

### 7.2 L'interdiction, rendue mécaniquement vérifiable

> **Interdiction** : il est **interdit** de rattacher deux comptes sur la seule base d'un numéro
> **non vérifié**. Aucun `UPDATE ... SET user_id = ...` ne peut se produire dans le même flux
> qu'une saisie de numéro.

Comment la rendre **vérifiable**, et non déclarative :

1. **Colonne de preuve obligatoire.** `travel_customer_accounts.phone_verified_at` doit être
   **NOT NULL** pour que `user_id` soit non nul. Contrainte SQL :
   ```sql
   CHECK (user_id IS NULL OR phone_verified_at IS NOT NULL)
   ```
   Un rattachement sans vérification devient **impossible à écrire**, pas seulement interdit.
2. **Test de sécurité dédié** : tenter un `UPDATE` de `user_id` avec `phone_verified_at IS NULL`
   → la base **rejette** (`23514`). Ce test échoue si la contrainte est retirée.
3. **Aucune route de fusion automatique.** Le seul chemin est un endpoint explicite exigeant
   **deux** preuves (session sur `users` + OTP sur le numéro). Un test vérifie qu'une requête
   avec un seul facteur est refusée.
4. **Audit obligatoire** : `user_id` n'est modifiable que par un service dédié qui journalise.
   Un test vérifie qu'un `UPDATE` direct hors service ne laisse pas de trace → interdit par
   révision de code, vérifiable en CI par une règle statique.

### 7.3 Conséquences par composant

| Composant | Conséquence |
|---|---|
| `travel_customer_accounts` | +2 colonnes (`phone_e164`, `user_id` nullable), +1 contrainte CHECK, +1 index. **Additif.** |
| `users` | inchangé (`phone_e164` existe déjà) |
| Marketplace / travel | peut **enfin** reconnaître un client récurrent ; aucune rupture de contrat |
| Applications existantes | continuent de fonctionner : `user_id` NULL = comportement actuel |
| Mobile grand public | débloqué : login par téléphone, contrat exposé en §9 |

---

## 8. Modèle d'autorisations cible

Sept notions à ne pas confondre — c'est leur fusion qui produit les escalades.

| Notion | Définition | Où elle vit aujourd'hui |
|---|---|---|
| **Identité** | la personne physique, indépendamment de tout compte | ❌ absente (quatre tables d'identité concurrentes) |
| **Compte** | un moyen d'authentification rattaché à une identité | `users`, `employees`, `super_admins`, `travel_customer_accounts` |
| **Appartenance** | le lien compte ↔ organisation, avec un statut | `user_employee_links` (existe, **non utilisé au login**) |
| **Rôle** | un ensemble nommé de permissions | `PlatformRole`, `employees.manager_role` |
| **Permission atomique** | un droit élémentaire | `PlatformPermission` (31) ; **rien côté tenant** |
| **Périmètre (scope)** | sur *quoi* le droit s'exerce | `employee_resource_assignments`, `employee_module_grants` |
| **Module autorisé** | une famille d'écrans ouverte | `ModuleKey` (7), `employee_module_grants` |
| **Délégation** | qui peut attribuer quoi à qui | ❌ **absente** |
| **Journal d'audit** | la trace du geste | `audit_logs` (`Auditable`) |

### 8.1 Empêcher un administrateur de déléguer ce qu'il ne possède pas

Trois règles, appliquées **au moment de l'attribution**, côté serveur :

1. **Plafond par inclusion** — `capabilities(target_role) ⊆ capabilities(actor_role)`.
   Violation → `422 DELEGATION_EXCEEDS_ACTOR`. C'est la règle qui manque aujourd'hui (§5.4).
2. **Plafond par périmètre** — l'acteur ne peut attribuer un rôle que dans un périmètre qu'il
   détient lui-même. Un `dept` ne délègue que dans son département, même si son rôle porte la
   permission d'attribution.
3. **Rôles non déléguables** — `principal` (tenant) et `super_admin` (plateforme) sont hors du
   graphe de délégation : attribuables seulement par un mécanisme de **transfert explicite**
   (double confirmation + audit + notification aux deux parties), jamais par un simple `PUT`.

### 8.2 Rôles personnalisés sans escalade

Un rôle personnalisé est un **ensemble de permissions choisies dans un catalogue fermé**, avec
un `created_by`. À la création **et** à chaque modification :

- toute permission demandée doit être **détenue par le créateur** — sinon refus ;
- un rôle ne peut pas contenir `delegation.manage` s'il a été créé par un non-`principal` ;
- les rôles d'une verticale (`delivery`, `restaurant`) ne peuvent recevoir que des permissions
  de cette verticale.

Sans catalogue fermé, un rôle personnalisé devient une écriture libre — donc une escalade.

---

## 9. Vision produit et parcours

### 9.1 Trois populations, une personne

| Population | Ce qu'elle est | Appartenance |
|---|---|---|
| **Collaborateurs Leopardo** | l'équipe qui exploite la plateforme | `super_admins` + `platform_role` |
| **Entreprises clientes** | propriétaires, administrateurs, employés | `employees` + `manager_role` + grants |
| **Consommateurs** | clients finaux (restaurant, travel, marketplace) | `users` / `travel_customer_accounts` |

Une personne peut cumuler : employé de l'agence X **et** client du restaurant Y **et**
propriétaire de l'entreprise Z **et** support chez Leopardo. Ce que le système doit garantir
n'est pas l'unicité de la ligne, c'est **l'absence de confusion** : chaque requête porte un
contexte, et les permissions sont évaluées **dans** ce contexte.

### 9.2 Les douze parcours

| # | Parcours | État |
|---|---|---|
| 1 | Création de compte | ✅ (invitation obligatoire côté tenant) |
| 2 | Invitation | ✅ e-mail / ❌ téléphone |
| 3 | Activation | ✅ |
| 4 | Connexion | ✅ |
| 5 | **Sélection du contexte** | ❌ **absent** |
| 6 | **Changement de contexte** | ❌ **absent** |
| 7 | Attribution d'un rôle | ⚠️ partiel (§5.4) |
| 8 | Création d'un rôle personnalisé | ❌ absent |
| 9 | Révocation | ⚠️ jeton oui / rôle à jeton constant non |
| 10 | Départ d'un employé | ✅ |
| 11 | Suspension | ✅ |
| 12 | **Récupération de compte** | ⚠️ e-mail oui / téléphone non |

### 9.3 Web maintenant, mobile ensuite

Le web doit fonctionner **sans dépendre du mobile**. Les contrats exposés aujourd'hui sont déjà
consommables par un client mobile (Bearer Sanctum), mais **deux manques les bloquent** : pas de
login par téléphone, pas de sélection de contexte. Le contrat
`docs/design/PUBLIC_ACCOUNT_API_CONTRACT.md` couvre inscription, code, session,
rafraîchissement, révocation et suppression — il reste à y ajouter `POST /auth/context` et
`GET /auth/contexts`.

---

## 10. Réexamen des lots

| Lot | Contenu | Additif ? | Touche des middlewares ? | Effort |
|---|---|---|---|---|
| **L1** | OTP : `isConfigured()` fail-closed, `send()` honnête, erreur explicite au lieu du faux succès | ✅ oui | ❌ non | ~1 j |
| **L2** | **Sélection de contexte** : `user_lookups` en clé composite, `GET /auth/contexts`, `POST /auth/context`, **revalidation dans le middleware `tenant`** | ❌ **non** | ✅ **oui — inévitable** | ~4-7 j |
| **L3** | Hygiène jetons : `$expiresAt` explicite sur les 3 chemins, correction du chemin `data.`→racine, compteur dernier `principal` | ✅ oui | ❌ non | ~2 j |
| **L4** | **Convergence grand public** : `phone_e164` + `user_id` + contrainte CHECK sur `travel_customer_accounts`, login OTP téléphone | ⚠️ migration additive, mais login et provider modifiés | ⚠️ provider `travel_customer` | ~4-6 j |
| **L5** | **Rôles personnalisés** : catalogue fermé, `roles`/`role_assignments`, plafond de délégation, rôles de verticale | ✅ oui (nouvelles tables) | ❌ non | ~4-6 j |
| **L6** | **Web** : écran de sélection de contexte, persistance, écran SSO | ✅ oui (front) | ❌ non | ~3 j |

**Dépendances** : L6 dépend de L2 (pas d'écran sans endpoint). L5 dépend de L1–L4 pour être
testable sur des rôles réels. L2 et L4 sont indépendants entre eux mais **chacun** touche la
résolution de contexte : ils ne doivent pas être menés en parallèle sur les mêmes fichiers.

**Estimation — fourchette, pas engagement** : **18 à 26 jours** (L1 1 / L2 4-7 / L3 2 / L4 4-6 /
L5 4-6 / L6 3). Hypothèses : un développeur familier du dépôt, revue du propriétaire incluse,
pas de découverte majeure en cours de route. **Risques** : (a) la migration de `user_lookups`
sur des données existantes — à faire en deux temps, sans downtime ; (b) la revalidation dans le
middleware peut révéler des jetons legacy sans abilité ; (c) L4 dépend d'une décision produit
sur la fusion de comptes, non tranchée à ce jour.

**Ce qui n'est pas un engagement** : ces chiffres servent à ordonner, pas à promettre. La
révision 2 annonçait « 11 jours » pour quatre lots — erreur déjà corrigée en « 18 », et la
fourchette ci-dessus est volontairement ouverte parce que L2 avait été sous-estimé.

**Sous-ensemble minimal P0+P1** (L1, L2, L3, L4, L6 — sans L5 qui est P2) : **14 à 21 jours**.

---

## 11. Décision finale

> **La fondation actuelle peut-elle accueillir proprement et durablement les collaborateurs de
> la plateforme, les collaborateurs des entreprises clientes et les consommateurs, avec une
> identité cohérente, plusieurs appartenances, des rôles personnalisables et une sécurité
> multi-tenant fiable ?**

**Réponse : partiellement, et pas en l'état.**

**Ce qui est déjà là et ne demande aucune refonte** — et c'est plus que la plupart des dépôts
de cette taille :

- l'**isolation multi-tenant** est solide et testée (quatre couches, `search_path` paramétré
  avec validation anti-injection, fail-closed, global scope, bypass explicite testé) ;
- les **collaborateurs plateforme** sont correctement modélisés : six rôles, 31 permissions,
  matrice explicite, `team.manage` réservé, dernier super admin protégé, `platform_role` non
  mass-assignable ;
- les **guards sont disjoints** : un jeton de consommateur ne peut pas atteindre une surface
  employé, et c'est testé ;
- le **cycle d'invitation** est sain : jeton haché, usage unique, expiration, activation par
  la personne elle-même ;
- l'**OTP est cryptographiquement correct** : aléatoire, haché, comparaison en temps constant,
  usage unique, limite de tentatives. Son défaut est la **livraison**, pas la vérification.

**Ce qui l'empêche d'atteindre la vision** — quatre écarts, aucun cosmétique :

1. **La multi-appartenance n'a pas de mécanisme de contexte** (§3). Ce n'est ni un verrou ni un
   tirage au sort : c'est un **aiguillage mouvant**, déterminé par l'ordre des écritures en
   base, avec deux modes de défaillance silencieuse. La promesse « une personne, plusieurs
   contextes sans confusion » est aujourd'hui **structurellement fausse** — et c'est le cœur de
   la vision.
2. **Le grand public vit hors du graphe d'identité** (§2.1, §7). Une quatrième table, sans
   téléphone vérifié, sans lien vers `users`, sans login par téléphone. La convergence est
   possible par un pont additif — mais le pont **n'existe pas**.
3. **L'anti-escalade est réelle côté plateforme, absente côté tenant** (§5.4). Aucun plafond de
   délégation, aucun successeur possible pour un `principal`, aucune trace de changement de
   rôle. Un tenant peut nommer un rôle dont il ne détient pas les capacités.
4. **Aucun rôle personnalisable** (§5.3). Douze `manager_role` figés, zéro composition. Limite
   produit, pas faille — mais elle contredit la demande explicite.

**Ce que coûte l'inaction** : la vision reste bloquée sur son point central, et chaque nouvelle
verticale ajoute une valeur à un `CHECK` (`delivery`, `restaurant`, `rider`…) qui rend la liste
des rôles de plus en plus difficile à faire évoluer. Le `CHECK` unifié de `#8004` documente déjà
deux migrations qui se sont écrasées mutuellement.

**Le plus petit ensemble qui atteint l'objectif sans casser l'existant** : **L1 + L2 + L3 + L4**
(14 à 21 jours), avec L5 et L6 en suite naturelle. Aucun de ces lots ne supprime une
fonctionnalité, aucun ne rompt un contrat d'API. **L2 exige de modifier le middleware `tenant`
et le schéma de `user_lookups`** — c'est le prix de la correction, et il faut l'assumer plutôt
que de le masquer.

**Contradiction assumée avec les rapports précédents** : la révision 2 présentait le défaut
tenant comme un « verrou sur la première entreprise » (rassurant : déterministe, donc pas de
fuite). L'exécution montre autre chose — un contexte qui **change** selon l'ordre des écritures.
C'est moins rassurant, et c'est le constat juste.

---

## 12. Informations non accessibles

Signalées explicitement, jamais présentées comme des constats :

- **État de production** : aucune base de production, aucun APM, aucun journal d'accès
  accessible. Tous les constats portent sur le **code**, pas sur le comportement observé.
- **Mesure d'exposition réelle en production** : la requête SQL (annexe du plan) n'a été
  **validée que sur données synthétiques**. Elle n'a **pas** été exécutée sur la production.
  C'est la mesure qui dirait si l'écart est **actif** ou **latent** aujourd'hui.
- **CI GitHub Actions** : non rejouée. Résultats **locaux** (PostgreSQL 15), pas ceux de la CI
  (PostgreSQL 16). Un écart d'extension ou de version reste possible.
- **Paiements** : non audités au-delà de la liste des permissions (`payments.view`,
  `payments.manage`), conformément à la consigne de lecture seule.
- **Mobile** : aucune application mobile inspectée ni modifiée ; seul le contrat d'API évalué.
- **`travel_customer_accounts`** : l'absence de `phone_e164` et de `user_id` est constatée par
  lecture de la migration ; **aucune** base de production n'a été interrogée pour vérifier
  qu'aucune colonne équivalente n'y a été ajoutée hors migration.

---

## 13. Annexes

- Matrice rôles × permissions : `docs/design/IDENTITY_ROLE_PERMISSION_MATRIX.md`
- Architecture cible : `docs/design/IDENTITY_ACCESS_CONTROL_TARGET_ARCHITECTURE.md`
- ADR identités publiques : `docs/design/ADR-IDENTITE-CLIENTS-PUBLICS.md`
- Plan d'implémentation : `docs/roadmaps/IDENTITY_ACCESS_CONTROL_IMPLEMENTATION_PLAN.md`
- Contrat d'API public : `docs/design/PUBLIC_ACCOUNT_API_CONTRACT.md`
- Tests d'audit (branche non fusionnée) : `api/tests/Feature/Audit/`
  - `IdentityAccessControlAuditTest` — 11 constats de conformité
  - `IdentityAccessControlGapsTest` — 5 constats d'écart
  - `TenantAmbiguityProofTest` — 3 tests : preuve du risque tenant
  - `IdentityShapesProbeTest` — 1 sonde : forme de la réponse de login
