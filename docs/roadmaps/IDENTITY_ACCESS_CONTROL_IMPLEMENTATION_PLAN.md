# Plan d'implémentation — Identity & Access Control

- **Date** : 1er novembre 2026 · **HEAD de référence** : `982032ba9` · **Révision** : 3
- **Statut** : proposition — **aucune implémentation ne démarre avant validation du propriétaire**
- **Documents liés** : audit (`docs/audits/IDENTITY_ACCESS_CONTROL_FOUNDATION_AUDIT.md`), matrice,
  architecture cible, ADR, contrat d'API public

> ⚠️ **Aucun lot n'est commencé.** Ce plan est une proposition soumise à validation.
> Aucune migration, aucun guard, aucun middleware n'a été modifié. Aucun code applicatif n'a
> été écrit : les seuls ajouts de cette branche sont documentaires et des tests d'audit.

---

## 0. Règles du plan

1. **Tests de sécurité d'abord.** Chaque lot commence par l'écriture du test qui **échoue**,
   puis l'implémentation qui le fait passer. Un lot sans test écrit en premier n'est pas
   accepté en revue.
2. **Aucune migration destructive, aucune migration de données.** Les migrations de **schéma**
   (nouvelles colonnes, nouvelle clé) sont permises et explicitées lot par lot.
3. **Relecture du propriétaire obligatoire** sur les lots marqués 🔒 — ceux qui touchent une
   décision produit, une frontière de sécurité ou une migration de schéma.
4. **Chaque lot laisse le système cohérent** : on peut s'arrêter après n'importe lequel.
5. **Les tests d'audit ne bloquent pas la CI.** Les tests de `api/tests/Feature/Audit/` portent
   le groupe `audit` et sont exclus de la CI bloquante (`--exclude-group=audit`,
   `.github/workflows/tests.yml:211`). Ce sont des **constats**, pas des garde-fous : un test
   qui asserte « aucun endpoint de contexte n'existe » **doit** échouer quand L2 est livré. Le
   critère de sortie d'un lot est donc la **bascule** de ses tests d'inversion (audit §4.6),
   pas leur maintien au vert.

### 0.1 Ce que la révision 3 corrige dans ce plan

| Correction | Détail |
|---|---|
| **La règle « aucun middleware modifié » tombe** | Elle était présentée comme une contrainte de sécurité ; elle **empêche** en réalité une solution correcte. Voir §0.2. |
| **Motif de L2 corrigé** | L'arbitrage n'est pas « premier schéma alphabétique » (faux : `AuthService.php:66-72` désactive ce balayage en production) mais un **dispatch par `user_lookups`** écrasé ou bloqué selon la coïncidence des ids. Voir audit §3. |
| **Estimations en fourchette** | 18-26 j, pas « 18 j » : L2 avait été sous-estimé à 3 j alors qu'il porte une migration. |
| **L3 requalifié** | Le défaut « jeton sans expiration » n'existe pas (Sanctum borne par `created_at`). L3 devient de l'**hygiène** P3 + la protection du dernier `principal`, elle-même P1. |

### 0.2 L'aveu nécessaire : L2 et L4 touchent les middlewares

La révision 2 posait « aucun guard ni middleware modifié » comme une contrainte rassurante.
**Elle rend L2 impossible.** Un endpoint `POST /auth/context` qui n'apprend rien au middleware
`tenant` est un endpoint décoratif : le middleware continue de résoudre le schéma par e-mail et
ignore le choix. Trois verrous mécaniques :

1. **Le middleware `tenant` lit `user_lookups`.** Sans lecture de l'abilité du jeton, il écrase
   ou ignore le contexte demandé.
2. **`user_lookups.email` est clé primaire.** Deux appartenances ne peuvent pas y coexister :
   sans migration de clé, l'endpoint ne peut pas proposer deux contextes.
3. **La révocation doit être immédiate.** Sans revalidation dans le middleware, un jeton reste
   valide 30 jours après retrait de l'appartenance (scénario #6 de la matrice).

**Décision du plan** : la sécurité prime sur le nombre de fichiers modifiés. L2 et L4 modifient
donc explicitement `TenantMiddleware`, `AuthService` et le schéma de `user_lookups`, sous
relecture 🔒 du propriétaire. L1, L3, L5 et L6 restent strictement additifs.

---

## L1 — Fiabiliser l'OTP (P0) · ~1 j · additif

**Pourquoi** : un code peut n'être délivré **nulle part**, silencieusement. Un stub SMS rend un
`traceId` sans émettre d'appel (`LogOnlySmsCodeSender.php:119-128`) et fait sauter l'e-mail
(`LoginCodeController.php:87,102`) ; et le `catch` de `:113-124` retourne la réponse générique
de succès après une perte.

> **Précision de la révision 3** : ce n'est **pas** une faille d'authentification. Code
> aléatoire (`random_int`), haché (`Hash::make`), comparé en temps constant (`Hash::check`),
> usage unique, limite de tentatives (`:151-159`). C'est un défaut de **disponibilité et
> d'observabilité**.

### Tests à écrire EN PREMIER

| Test | Assertion |
|---|---|
| `SmsChannelTest::test_incomplete_sms_config_does_not_swallow_the_email` | endpoint seul, ou clé seule, ou driver ≠ `http` ⇒ **l'e-mail part** |
| `SmsChannelTest::test_send_returns_null_when_no_http_call_was_made` | `send()` ne rend un `traceId` que si un appel HTTP a abouti |
| `LoginCodeControllerTest::test_delivery_failure_returns_explicit_error` | envoi impossible ⇒ `503 LOGIN_CODE_SEND_FAILED`, **pas** `200 success` |
| `LoginCodeControllerTest::test_cache_is_cleared_on_delivery_failure` | un nouvel essai produit un **nouveau** code |

### Implémentation

- `isConfigured()` **fail-closed** : exige `SMS_OTP_DRIVER=http` **et** endpoint **et** clé
  **et** transport capable d'émettre.
- `send()` rend `null` si aucun appel n'a abouti → repli e-mail.
- `catch` du contrôleur : réponse d'erreur explicite + log + alerte, et **purge du cache**.
- `HttpSmsTransport` : implémenter réellement le POST que l'ancien code annonçait.

### Relecture 🔒
Décision produit : **accepter ou non** de révéler l'échec de livraison. Cela renonce à une
partie de l'anti-énumération (on révèle qu'un envoi a échoué, pas qu'un compte existe).

### Sortie
La dev ne peut plus « répondre succès » sans qu'un code soit parti.

---

## L2 — Sélection de contexte tenant (P0) · ~4-7 j · 🔒 · **modifie des middlewares**

**Pourquoi** : une personne dans N entreprises n'a **aucun moyen de choisir** son contexte, et
le contexte qu'elle obtient dépend de l'ordre des écritures en base.

**Ce que l'exécution a établi** (`TenantAmbiguityProofTest`, 3 tests, PASS) :

| Situation | Ce qui arrive | Qualité |
|---|---|---|
| ids d'employés **égaux** (deux schémas neufs) | le dispatch est **écrasé** par le dernier synchronisé | contexte **mouvant** |
| ids d'employés **différents** (tenant ancien) | la 2ᵉ appartenance est **abandonnée**, sans log | **déni d'accès** silencieux |
| `tenant`/`company_id` envoyé au login | **ignoré** ; la réponse ne dit même pas le schéma | aucune sélection |

**Ce que ce n'est pas** : ni un tirage alphabétique, ni une escalade de privilèges. C'est un
**mauvais aiguillage**, avec deux modes d'échec muets.

### Tests à écrire EN PREMIER

| Test | Assertion |
|---|---|
| `ContextSelectionTest::test_contexts_lists_every_company_of_the_person` | N entreprises ⇒ N entrées, chacune avec son rôle |
| `ContextSelectionTest::test_employee_cannot_select_a_company_they_do_not_belong_to` | **le** test critique : sélection de B par un employé de A ⇒ `403` |
| `ContextSelectionTest::test_issued_token_is_bound_to_the_selected_company` | le token réémis porte `tenant_company:<id>` |
| `ContextSelectionTest::test_selecting_context_does_not_widen_permissions` | permissions ⊆ celles du rôle dans cette entreprise |
| `ContextSelectionTest::test_person_in_two_companies_gets_different_roles` | scénario #5, **enfin couvert** |
| `ContextSelectionTest::test_revoked_membership_is_rejected_without_waiting_for_token_expiry` | retrait d'appartenance ⇒ `403` **au prochain appel**, pas dans 30 j |
| `TenantContextMigrationTest::test_two_lookups_can_coexist_for_one_email` | la clé composite permet deux lignes |

> Sans le 2ᵉ et le 6ᵉ test, l'endpoint devient un moyen de choisir son tenant — donc une faille.

### Implémentation

1. **Migration de schéma** (🔒) : `user_lookups` passe de `PRIMARY KEY (email)` à
   `PRIMARY KEY (email, company_id)`. En deux temps : ajout d'une colonne d'idempotence, backfill,
   bascule de clé, sans downtime.
2. **`GET /api/v1/auth/contexts`** — liste les appartenances **actives** avec rôle et module.
3. **`POST /api/v1/auth/context`** — vérifie l'appartenance **avant** de réémettre ; le jeton
   porte `tenant_company:<id>` via les abilities Sanctum existantes (`AuthService.php:214-220`).
4. **`TenantMiddleware`** (🔒) : résolution = abilité du jeton **∩** appartenance réelle en base.
   Sans abilité (jeton legacy) → résolution par `user_lookups` + log de migration.
5. **`AuthService::syncUserLookup`** : corrige la garde (§3.2 de l'audit) — la comparaison
   d'ids doit inclure `company_id`, et tout refus doit **journaliser**.

### Relecture 🔒
Trois décisions : (a) que faire d'un jeton legacy sans abilité ; (b) contexte par défaut quand
N > 1 — **demande explicite recommandée** ; (c) acceptation de la migration de `user_lookups`.

### Sortie
Une personne dans N entreprises voit N contextes, en choisit un, et le serveur le valide.

---

## L3 — Hygiène des sessions & dernier `principal` (P3 + P1) · ~2 j · additif

> **Requalification de la révision 3** : la révision 2 classait « jeton sans expiration » en
> P1. **Ce défaut n'existe pas.** Sanctum borne tout jeton par `created_at + sanctum.expiration`
> même quand `expires_at` est NULL (`vendor/laravel/sanctum/src/Guard.php:127-129`,
> `config/sanctum.php:23` = 43200 min). La sonde `IdentityShapesProbeTest` mesure **43 199**
> minutes réelles. Ce qui reste est de l'**hygiène** — plus la protection du dernier `principal`,
> qui est un vrai P1.

### Tests à écrire EN PREMIER

| Test | Assertion | Priorité |
|---|---|---|
| `SessionDurationTest::test_login_issues_a_token_expiring_in_thirty_days` | **corrigé** : lit `token_expires_at` à la racine | P3 |
| `TokenExpiryTest::test_google_login_paths_set_an_explicit_expires_at` | `AuthController.php:463,552` passent le 3ᵉ argument | P3 |
| `TenantPrincipalTest::test_last_principal_cannot_be_demoted` | `422 LAST_PRINCIPAL_REQUIRED` | **P1** |
| `TenantPrincipalTest::test_a_second_principal_can_be_appointed` | successeur possible — **aujourd'hui impossible** | **P1** |

### Implémentation

- Correction du **test** `SessionDurationTest:68` (`data.` → racine). Aucun code applicatif.
- `$expiresAt` explicite sur les trois chemins `createToken`.
- Compteur `activePrincipalCount()` par tenant + garde symétrique à celle de
  `PlatformTeamController::updateRole:104-110`.
- Journal d'audit sur tout changement de `manager_role`.

### Relecture 🔒
Décision produit : qui peut nommer un second `principal` — le `principal` en place, un super
admin, ou les deux ?

---

## L4 — Convergence du grand public (P0) · ~4-6 j · 🔒 · **migration additive + provider**

**Pourquoi** : `travel_customer_accounts` vit hors du graphe `users` — quatrième table
d'identité, sans `phone_e164`, sans `user_id`, sans login par téléphone.

### Tests à écrire EN PREMIER

| Test | Assertion |
|---|---|
| `PublicIdentityBridgeTest::test_bridge_cannot_be_written_without_a_verified_phone` | `UPDATE user_id` avec `phone_verified_at IS NULL` ⇒ **`23514`** (contrainte CHECK) |
| `PublicIdentityBridgeTest::test_two_accounts_are_never_linked_on_an_unverified_phone` | fusion avec un seul facteur ⇒ `403` |
| `PublicIdentityBridgeTest::test_phone_login_issues_a_session_bound_to_the_verified_number` | login OTP téléphone |
| `PublicIdentityBridgeTest::test_changing_phone_never_transfers_user_id` | changement de numéro ⇒ `user_id` inchangé |
| `PublicIdentityBridgeTest::test_concurrent_registrations_produce_one_account` | `23505` géré ⇒ réponse idempotente |

### Implémentation

- Migration **additive** : `travel_customer_accounts.phone_e164` (nullable), `user_id`
  (nullable), `phone_verified_at` (nullable), contrainte
  `CHECK (user_id IS NULL OR phone_verified_at IS NOT NULL)`, index.
- Provider `travel_customer` : accepte le téléphone vérifié.
- Service de rattachement **unique** et audité ; aucune route de fusion automatique.
- `PUBLIC_ACCOUNT_API_CONTRACT.md` complété (`/auth/context`, `/auth/contexts`).

### Relecture 🔒
Décision produit non tranchée : **que devient `travel_customer_accounts` après convergence** —
supprimée, ou conservée comme profil métier ? Le plan technique est le même ; le devenir ne
l'est pas.

---

## L5 — Rôles personnalisés (P2) · ~4-6 j · 🔒 · additif

**Pourquoi** : douze `manager_role` figés, zéro composition. Un tenant ne peut pas exprimer un
rôle métier sans passer par une migration qui étend un `CHECK`.

### Tests à écrire EN PREMIER

| Test | Assertion |
|---|---|
| `CustomRoleTest::test_a_role_cannot_exceed_its_creator_capabilities` | `capabilities(role) ⊄ capabilities(creator)` ⇒ `422 DELEGATION_EXCEEDS_ACTOR` |
| `CustomRoleTest::test_a_non_principal_cannot_grant_delegation_manage` | |
| `CustomRoleTest::test_a_role_is_scoped_to_its_tenant` | un rôle de A n'est pas assignable dans B |
| `CustomRoleTest::test_a_principal_cannot_delegate_more_than_it_holds` | **couvre l'écart §5.4 de l'audit** |
| `CustomRoleTest::test_a_vertical_role_only_receives_vertical_permissions` | `delivery`/`restaurant` |

### Implémentation

- Catalogue **fermé** de permissions côté tenant (parité avec `PlatformPermission`).
- Tables `roles`, `role_permissions`, `role_assignments` (tenant-scoped).
- Moteur de plafond : `capabilities(role) ⊆ capabilities(delegator)` + plafond de périmètre.
- `principal`/`super_admin` hors graphe de délégation (transfert explicite seulement).

### Relecture 🔒
Décision produit : le catalogue de permissions tenant est-il **dérivé** des 31 plateforme ou
**indépendant** ? Le premier est cohérent, le second est plus libre.

---

## L6 — Web : sélection de contexte (P1) · ~3 j · additif · dépend de L2

### Tests à écrire EN PREMIER

| Test | Assertion |
|---|---|
| `context-switcher.spec` | l'écran liste N entreprises et bascule |
| `context-switcher.spec` | après bascule, les données affichées sont celles de la nouvelle entreprise |
| régression | aucune requête ne part sans abilité de contexte |

### Implémentation
Écran de sélection, persistance du choix, gestion du `403` (appartenance révoquée) avec retour
propre à l'écran de choix.

### Relecture 🔒
UX : demande-t-on le contexte **à chaque connexion** ou le mémorise-t-on ? Mémoriser est plus
confortable et moins sûr ; la recommandation est de mémoriser **avec revalidation serveur**.

---

## L7 — Hygiène transverse (P2, continu)

- Réparer `TenantManagerStandardizationTest:207` (test **risky**, aucune assertion exécutée) —
  le rejet d'un job sans contexte n'est pas réellement prouvé.
- Nettoyer les 3 fixtures à mot de passe faible (`password123456`) pour rendre `17/17` suites
  vertes : elles échouent à cause de `NotCommonPassword`, pas du produit.
- Clarifier l'écart harnais vs production sur `employees_email_unique`.

---

## Séquencement

```
L1 (OTP fiable) ──► L2 (contexte) ──► L4 (grand public)
                         │
L3 (principal) ──────────┤
                         ├──► L5 (rôles custom)
                         └──► L6 (web contexte)
```

- **L1 avant tout** : sans envoi fiable, L4 est un mensonge.
- **L2 avant L4 et L6** : le grand public multi-entreprises et l'écran de choix ont besoin du contexte.
- **L3 en parallèle** : indépendant, faible risque.
- **L5 après L2** : dépend de la notion de contexte pour être testable.
- **L6 après L2** : consomme l'endpoint, ne le crée pas.
- **L2 et L4 ne se mènent pas en parallèle** : tous deux touchent la résolution de contexte.

### Estimation — fourchette, pas engagement

| Lot | Priorité | Effort | Additif ? |
|---|---|---|---|
| L1 — OTP fiable | P0 | 1 j | ✅ |
| L2 — Sélection de contexte | P0 | **4-7 j** | ❌ migr. + middleware |
| L3 — Hygiène jetons + dernier principal | P3 / **P1** | 2 j | ✅ |
| L4 — Convergence grand public | P0 | 4-6 j | ⚠️ migr. additive + provider |
| L5 — Rôles personnalisés | P2 | 4-6 j | ✅ |
| L6 — Web (contexte) | P1 | 3 j | ✅ |
| **Total six lots** | | **18-26 j** | |
| **Sous-ensemble P0+P1** (L1+L2+L3+L4+L6) | | **14-21 j** | |

**Hypothèses** : un développeur familier du dépôt, revue du propriétaire incluse, pas de
découverte majeure en cours de route.

**Risques** : (a) migration de `user_lookups` sur données existantes — deux temps, sans
downtime ; (b) la revalidation middleware peut révéler des jetons legacy sans abilité ;
(c) L4 dépend d'une décision produit non tranchée (devenir de `travel_customer_accounts`).

**Ce que ce n'est pas** : un engagement. Les chiffres servent à ordonner les lots. La révision 1
annonçait « 11 jours » pour quatre lots — erreur déjà corrigée en « 18 ». La fourchette est
volontairement ouverte parce que **L2 était sous-estimé** : il porte une migration de clé
primaire et une modification de middleware, pas un simple endpoint.

---

## Annexe — Requête de mesure d'exposition en production

Lecture seule. À exécuter par le propriétaire pour dire si l'écart tenant est **actif** ou
**latent** aujourd'hui. Validée sur deux schémas synthétiques (`t_alpha`, `t_beta`) : elle
rapporte bien `1 | 3 | 2` pour un e-mail et un téléphone partagés.

```sql
DROP TABLE IF EXISTS pg_temp.expo;
CREATE TEMP TABLE expo (schema_name text, email text, phone_raw text);

DO $$
DECLARE s text;
BEGIN
  FOR s IN
    SELECT schema_name FROM public.companies
    WHERE schema_name IS NOT NULL
      AND schema_name ~ '^[a-zA-Z0-9_]+$'   -- même garde que le TenantManager
      AND schema_name <> 'public'
  LOOP
    IF to_regclass(quote_ident(s) || '.employees') IS NOT NULL THEN
      EXECUTE format(
        'INSERT INTO pg_temp.expo SELECT %L, lower(trim(email)),
                coalesce(nullif(trim(phone),''''), personal_phone) FROM %I.employees',
        s, s);
    END IF;
  END LOOP;
END $$;

SELECT 'email' AS identifiant,
       count(*) FILTER (WHERE n > 1) AS multi_schemas,
       count(*) AS valeurs_distinctes,
       coalesce(max(n), 0) AS max_schemas
FROM (SELECT email AS v, count(DISTINCT schema_name) n FROM expo
      WHERE email IS NOT NULL AND email <> '' GROUP BY 1) x
UNION ALL
SELECT 'phone',
       count(*) FILTER (WHERE n > 1), count(*), coalesce(max(n), 0)
FROM (SELECT regexp_replace(phone_raw, '[^0-9+]', '', 'g') AS v,
             count(DISTINCT schema_name) n
      FROM expo WHERE phone_raw IS NOT NULL AND trim(phone_raw) <> '' GROUP BY 1) y;
```

**Comment lire le résultat** :

- `multi_schemas = 0` ⇒ le défaut est **latent, pas absent** : la clé primaire `email` interdit
  encore toute multi-appartenance **par construction**. Ne pas lire 0 comme « pas de problème ».
- `multi_schemas > 0` ⇒ des personnes réelles sont concernées **dès aujourd'hui** ; L2 devient
  urgent et non plus structurel.
- `max_schemas = 2` sur les e-mails avec des téléphones à `1` ⇒ le lien `user_employee_links`
  n'est pas alimenté : le repli par e-mail est le seul chemin.

---

## Ce que ce plan refuse de faire

| Refus | Raison |
|---|---|
| Migrer `travel_customer_accounts` d'un coup | Migration de données interdite ; casse le contrat de la marketplace |
| Fusionner `super_admins` dans `users` | Population interne, guards disjoints, aucune confusion — gain cosmétique |
| Remplacer les enums par Spatie | Réécriture des gates existants = risque de régression de sécurité |
| Ajouter un service d'autorisation externe | Un aller-retour réseau par requête, sans gain |
| Toucher aux paiements | Hors périmètre (lecture seule) |
| **Prétendre corriger L2 sans toucher aux middlewares** | **Intenable** — voir §0.2. Une promesse qu'on ne peut pas tenir coûte plus cher que l'aveu. |

---

## Conditions de sortie

Un lot est **terminé** quand :

1. ses tests de sécurité ont été écrits **avant** le code et **passent réellement** (exécution
   tracée, pas « considérés comme passants ») ;
2. aucun test existant n'a régressé (suite `Auth` + `Tenant` verte) ;
3. les tests d'audit de la ligne correspondante (§4.6 de l'audit) ont **basculé** ;
4. la matrice de tests de sécurité (§4.4 de l'audit) est mise à jour avec le **résultat réel** ;
5. le propriétaire a relu les lots 🔒.

---

## Décisions en attente du propriétaire

| # | Décision | Bloque |
|---|---|---|
| 1 | Révéler l'échec de livraison du code (renonce à une part d'anti-énumération) | L1 |
| 2 | Migration de `user_lookups` (clé composite) acceptée | **L2** |
| 3 | Contexte par défaut quand N > 1 : demande explicite / dernier utilisé | L2 |
| 4 | Qui nomme un second `principal` de tenant | L3 |
| 5 | Devenir de `travel_customer_accounts` après convergence | **L4** |
| 6 | Catalogue de permissions tenant : dérivé des 31 plateforme ou indépendant | L5 |
| 7 | Contexte mémorisé entre sessions ou redemandé | L6 |
