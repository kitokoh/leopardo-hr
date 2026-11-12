# Couloir 16 — Audit de couverture des cas d'usage et des profils

- **Dépôt** : `kitokoh/leopardo-hr`
- **Base auditée** : `main` @ `1f745b0` (12 novembre 2026)
- **Nature de l'audit** : ce que la **page GitHub elle-même** définit (README, description du dépôt, issues porteuses des jalons, documents de spec du couloir) confronté à ce qui existe **réellement** dans le code et les tests.
- **Statut** : audit. **Aucune fonctionnalité, aucune table, aucune route créée par ce lot.** Un seul fichier de test ajouté.

> ⚠️ **Règle de lecture.** Une ligne n'est marquée ✅ **exécuté** que si
> l'exécution a été relevée dans ce lot, en local, avec PostgreSQL 15 réel.
> Les autres restent ❌ ou ⏳ — voir §5 pour les commandes et les chiffres.

---

## 0. Plan de compréhension (5 lignes)

1. **Ce que la page GitHub définit réellement** n'est pas le README : celui-ci ne mentionne **jamais** la surface grand public (voir §1.1). La définition vit dans quatre issues de jalon (#9102, #9104, #9105, #9106) et cinq documents de `docs/design/`.
2. **L'ossature est livrée et testée** : découverte, fiche, conversation, compte public, activités, favoris, historique, notifications, découverte par catégories/seuil.
3. **Ce qui manque n'est pas du code, c'est de la surface** : l'onglet Activités et l'onglet Profil n'existent pas dans la PWA ; la navigation basse qui les promet est dans une PR **ouverte** (#9419).
4. **Un document de référence est périmé et le dit à tort** : `PUBLIC_ACCOUNT_CONNECTION_MATRIX.md` déclare le cas 8 « couvert mais non mergé » alors que sa PR #9178 est **mergée**, et cite un chemin de test qui n'existe pas.
5. **Deux exigences de l'ADR §8 ne sont pas implémentées** (piste d'audit du rattachement, refus explicite à facteur unique) — ce sont les seuls écarts **de fond** trouvés, et ils relèvent du lot L4, hors couloir 16.

---

## 1. Ce que la page GitHub définit

### 1.1 README et description du dépôt — **aucune définition du canal public**

| Élément | Contenu réel | Conséquence |
|---|---|---|
| Description du dépôt | « The Business OS for field-based companies: HR & payroll, attendance, CRM, accounting, operations » | Décrit la **suite B2B**. Le canal grand public n'y figure pas. |
| README (24 007 octets) | 0 occurrence de `grand public`, `public surface`, `leopardo_public`, `Couloir`, `PWA`, `discover` | **La surface publique est invisible sur la page du dépôt.** |
| Wiki | `has_wiki: true`, mais `leopardo-hr.wiki.git` → **« Repository not found »** | Aucun wiki : rien à confronter. |
| Page « Live ecosystem » | 5 surfaces listées : API, « Corporate web », Platform admin, **Mobile suite** (Employee, Manager, HR, Marketing, Platform Admin), Product site | `leopardo_public` **absent** de la liste des apps mobiles. |

**Constat.** Le Couloir 16 est défini par ses **issues** et ses **documents**, pas par la page d'accueil du dépôt. C'est une découverte d'audit en soi : un lecteur qui arrive sur le dépôt ne peut pas savoir que la surface grand public existe ni qu'elle est en développement.

### 1.2 Issues porteuses des jalons (label `couloir-16` : 4 ; recherche « Couloir 16 » : 32 résultats)

| Issue | Jalon | État | Apport pour cet audit |
|---|---|---|---|
| #9101 | M0 — dossier d'architecture et contrat | fermée (PR mergée) | Plan, cartographie, modèle de données |
| #9102 | M1 — app Flutter, seuil de catégories, maquettes | fermée | **8 états d'écran**, fr/en/ar(RTL)/tr, actions depuis `supportedActions()` |
| #9103 | M1-bis — manifeste PWA propre | fermée | Manifeste de la surface publique |
| #9109 / #9108 | M0-bis / M3 | fermées | Modèle de données ; activités, favoris, historique |
| #9104 | **M2 — contact, OTP téléphone, messages, push** | fermée, **bloquée par conception** | **8 cas de la matrice de connexion**, chacun avec un test exécuté |
| #9105 | M3 | fermée | Index d'activités, favoris, historique, première habitude |
| #9106 | M4 | fermée | Budget d'images, Lighthouse, hors ligne, distribution testeurs |
| #9107 | cartographie de l'existant | fermée (PR) | Note explicite : « un état des lieux, pas une promesse » |
| #9371 → #9375, #9382 | M1 — accueil Web, tokens, Flutter | fermées | Accueil web responsive |
| **#9372** | **M1 — accueil Web responsive** | **ouverte** | Desktop/tablette/mobile/PWA |
| **#9374** | **M1 — états obligatoires de l'accueil** | **ouverte** | 11 états d'écran |

**Aucun epic séparé** : les jalons **sont** les issues. Il n'existe pas de label `epic`.

### 1.3 Documents de spec du couloir (`docs/design/`)

`COULOIR16_PLAN.md` · `COULOIR16_CARTOGRAPHIE.md` · `PUBLIC_ACCOUNT_API_CONTRACT.md` · `PUBLIC_ACCOUNT_CONNECTION_MATRIX.md` · `PUBLIC_PROFILE_DATA_MODEL.md` · `PUBLIC_APP_BUDGETS.md` · `ADR-IDENTITE-CLIENTS-PUBLICS.md` · `ADR-publication-publique.md` · `couloir16-mockups/`

---

## 2. Les profils attendus — et leur état réel

La définition opposable est `ADR-IDENTITE-CLIENTS-PUBLICS.md` **§2.1**, qui énonce « **Quatre modèles d'identité, pas deux** ».

| Profil | Modèle | Table | Guard | Existe | Test qui le prouve |
|---|---|---|---|---|---|
| Client grand public générique | `User` | `users` | `user_api` | ✅ | **`PublicProfileCoverageTest` (nouveau, exécuté)** |
| Employé / admin de tenant | `Employee` | `employees` (par schéma) | `sanctum` | ✅ | idem + `PublicPhoneOtpTest::test_user_api_token_does_not_open_employee_surface` |
| Collaborateur interne Leopardo | `SuperAdmin` | `super_admins` | `super_admin_api` | ✅ | idem |
| Client grand public travel | `TravelCustomerAccount` | `travel_customer_accounts` | `travel_customer` | ⚠️ **existe mais « non conforme »** (ADR §2.1 : troisième identité, convergence L4) | idem + `PublicIdentityBridgeServiceTest` (10 tests) |

**Quatre des cinq profils attendus par le couloir sont couverts par un test exécuté** : le client du parcours critique, la personne cliente de plusieurs métiers, la personne **à la fois** cliente et employée, et le commerçant (prouvé côté `PublicParcoursEndToEndTest`).

**Un profil est attendu et absent du code** : voir §4.4.

### 2.1 Les sept situations de rattachement (ADR §8.2)

| # | Situation | Règle attendue | Implémenté | Test exécuté |
|---|---|---|---|---|
| 1 | OTP et vérification du téléphone | un numéro est une **prétention**, pas une preuve | ✅ contrainte `CHECK` en base | ✅ `PublicIdentityBridgeServiceTest::test_bridge_cannot_be_written_without_a_verified_phone` + **`PublicProfileCoverageTest`** (présence de la contrainte) |
| 2 | Numéros déjà associés des deux côtés | **aucun** rattachement automatique ; fusion explicite, **403** à facteur unique | ⚠️ **partiel** | ✅ `test_two_users_are_never_auto_merged` — mais lève une `RuntimeException`, **pas un 403** : il n'existe **aucune route de fusion** |
| 3 | Collisions | `23505` sur `users`, aucun sur `travel_customer_accounts` | ✅ | ✅ `PhoneE164UniquenessTest` (5 tests) + **`PublicProfileCoverageTest`** (index `UNIQUE` réel) |
| 4 | Rattachement sécurisé | `user_id` écrit **uniquement** par le service, après **deux** preuves | ✅ service unique | ✅ **`PublicProfileCoverageTest`** (écrivain unique, vérifié mécaniquement) |
| 5 | Changement de numéro | ne transfère **jamais** `user_id` | ✅ | ✅ `test_changing_phone_never_transfers_user_id` |
| 6 | Récupération de compte | uniquement par un canal **déjà vérifié** | ✅ aucune route de récupération | ✅ `test_case_6_no_phone_recovery_route_exists` + **`PublicProfileCoverageTest`** |
| 7 | Concurrence des inscriptions | `23505` géré ⇒ un seul compte | ✅ | ✅ `test_concurrent_registrations_produce_one_account` |

**§8.3 — les trois propriétés du service de rattachement :**

| Propriété | État | Preuve |
|---|---|---|
| 1. Point d'entrée unique | ✅ | **`PublicProfileCoverageTest::test_only_the_bridge_service_writes_the_travel_account_user_id`** — scan récursif de `app/`, aucun autre écrivain |
| 2. Deux preuves obligatoires | ✅ | `link()` refuse `phone_verified_at IS NULL` |
| 3. **Audit systématique** (`audit_logs` à chaque écriture) | ❌ **ABSENT** | `PublicIdentityBridge.php` ne contient **aucune** référence à `AuditLog` ni `audit_logs` ; `$account->forceFill(['user_id' => …])->save()` ne journalise rien |

---

## 3. Les cas d'usage — état réel

### 3.1 Les 8 cas de la matrice de connexion

> Le document `PUBLIC_ACCOUNT_CONNECTION_MATRIX.md` est **périmé sur un point** : il déclare le cas 8 « ⚠️ couvert — PR #9178 **ouverte, non mergée** ». Vérification ce jour via l'API GitHub : **#9178 est `state: closed`, `merged: true`**. Le cas 8 est donc mergé.
>
> Second défaut du même document : il cite `Feature/Auth/AuthSelfRegistrationTest.php`. Ce chemin **n'existe pas** — le fichier est `tests/Feature/AuthSelfRegistrationTest.php`. Une référence de preuve fausse est pire qu'une absence de référence.

| # | Cas | Route / mécanisme | Test | Exécuté ce jour |
|---|---|---|---|---|
| 1 | E-mail seul | `POST /user/register`, `/user/login` | `RegisterLoginFlowTest`, `AuthSelfRegistrationTest` | ⚠️ **41 tests, 2 échecs** — voir §4.3 |
| 2 | Téléphone seul | `/user/login-code/phone/{request,verify}` | `LoginCodeSmsChannelTest` (16), `PublicPhoneOtpTest` (14) | ✅ |
| 3 | Compte travel existant (pont) | `PublicIdentityBridge` | `PublicIdentityBridgeServiceTest` (10) | ✅ |
| 4 | Cliente de plusieurs métiers | `public_activity_index` + rejeu tenant | `PublicActivityTest` (14) | ✅ **14 tests / 39 assertions** |
| 5 | Cliente ET employée | guards disjoints | `PublicPhoneOtpTest::test_user_api_token_does_not_open_employee_surface` + **`PublicProfileCoverageTest`** | ✅ (les 4 guards vérifiés) |
| 6 | Numéro réattribué | non-transfert de `user_id` | `test_changing_phone_never_transfers_user_id` | ✅ |
| 7 | SMS indisponible | repli e-mail, **jamais de faux succès** | `LoginCodeSmsChannelTest` (2 tests de repli) | ✅ |
| 8 | Appareil perdu | `POST /user/logout-all` | `PublicLogoutAllTest` (5) | ✅ **PR #9178 mergée** |

### 3.2 Parcours critique « trois touches »

**Recherche → fiche → Contacter → téléphone + code SMS → message.**

Côté **API**, le parcours est couvert de bout en bout par `PublicParcoursEndToEndTest` (20 tests) : publication, découverte, fiche, conversation, réutilisation du fil, 404 d'un autre client, retrait de publication.

Côté **PWA**, `contact-dialog.test.tsx` prouve que le formulaire n'envoie **que** les identifiants publics, qu'un 404 affiche un état « indisponible » **sans faux succès**, qu'une panne réseau est réessayable, et que la **clé d'idempotence est conservée** entre deux tentatives.

⚠️ **Le décompte « trois touches » n'est asserté nulle part** — ni en PHP, ni en TS, ni en Dart ; la seule occurrence du terme dans le dépôt est le document lui-même. C'est un critère de conception, mesuré par la maquette, pas par un test. Le dire « vérifié » serait faux.

### 3.3 Les 4 onglets

| Onglet | Surface API | Surface PWA (`main`) |
|---|---|---|
| Accueil | `/public/discover`, `/public/discovery/categories` | ✅ `/decouvrir` + racine |
| Messages (Léa séparée) | ✅ `/public/conversations`, `/conversations/{c}/{t}/messages` | ⚠️ conversation **dans la fiche**, pas d'onglet ; **aucune surface Léa** |
| Activités | ✅ `/user/activities`, `/user/favorites`, `/user/search-history`, `/user/notifications` | ❌ **aucune page** |
| Profil | ✅ `/user/me`, `/user/profile`, `/user/account`, `/user/device-tokens` | ❌ **aucune page** |

**La navigation basse qui promet ces deux onglets est dans la PR #9419 — ouverte, non mergée.** Sur `main`, aucun composant `PublicBottomNav` n'existe : si cette PR était fermée, les onglets Activités et Profil n'auraient plus aucun point d'entrée.

---

## 4. Ce qui manque — et de quel côté de la frontière c'est

### 4.1 Un manque de **couverture de test** — comblé par ce lot

Trois propriétés d'architecture n'étaient verrouillées par **aucun** test. Elles ne cassent aucun écran quand elles régressent ; elles cassent la frontière d'identité, silencieusement.

Fichier ajouté : `api/tests/Feature/LeopardoPublic/PublicProfileCoverageTest.php` — **9 tests, 42 assertions, exécutés** (§5).

| Test | Propriété verrouillée | Ce qu'une régression produirait |
|---|---|---|
| `test_the_four_identity_models_are_distinct_classes` | les 4 profils ne partagent aucune classe | fusion d'identité silencieuse |
| `test_each_identity_population_has_its_own_guard_and_provider` | 4 guards, 4 providers | un jeton ouvrirait une autre population |
| `test_a_public_client_token_opens_no_other_identity_surface` | jeton `user_api` ⇒ 401 sur employé, plateforme, travel | fuite d'autorisation inter-surfaces |
| `test_only_the_bridge_service_writes_the_travel_account_user_id` | écrivain unique (ADR §8.3-1) | écriture de `user_id` hors service, sans trace |
| `test_the_verified_phone_constraint_is_declared_in_the_migration` | contrainte `CHECK` en base | un numéro non vérifié ouvrirait un compte |
| `test_no_account_recovery_route_exists_for_the_public_profile` | aucune récupération par simple numéro | prise de contrôle par connaissance du numéro |
| `test_the_users_phone_uniqueness_index_exists_in_the_database` | index `UNIQUE` partiel réel | deux comptes sur un numéro |
| `test_the_public_channel_owns_no_identity_or_message_table` | pas de `public_accounts/sessions/messages/orders` | duplication d'identité ou de messagerie |
| `test_the_conversation_index_carries_no_business_content` | l'index route, ne stocke pas | contenu métier fuité dans `public` |

**Non-vacuité vérifiée** (un test qui passe du premier coup peut ne rien tester) : `to_regclass('public.users')` rend `'users'` (non nul) et `to_regclass('public.public_accounts')` rend `NULL` — donc l'assertion distingue réellement les deux cas ; et le motif d'écriture employé correspond bien à une écriture réelle (`DB::table('travel_customer_accounts')->update([...])` → correspondance **OUI**).

### 4.2 Un manque de **documentation**, sur la page GitHub

1. **Le README ne mentionne pas la surface grand public** (§1.1). Elle n'existe pas non plus dans « Live ecosystem ».
2. **`PUBLIC_ACCOUNT_CONNECTION_MATRIX.md` est périmé** : cas 8 déclaré « PR ouverte » alors qu'elle est mergée ; chemin de test inexistant (§3.1).

→ Le présent document comble le second point et rend le premier visible. **Le README n'est pas modifié par ce lot** : il décrit la suite B2B dans son ensemble, et y insérer une section grand public est une décision éditoriale du propriétaire, pas un correctif d'audit.

### 4.3 Un manque de **correction de test périmé** — préexistant, mesuré

`AuthSelfRegistrationTest` : **2 échecs sur 3 tests**. Cause réelle identifiée, ce n'est pas un défaut d'environnement :

`NotCommonPassword` retire les chiffres de fin avant de consulter sa liste de refus (`$root = rtrim($normalized, '0123456789')`). Le test utilise `password123456` → racine `password` → **présente dans la liste** → **422**. L'assertion attendait `201` et, pour l'autre test, `REGISTRATION_NOT_AVAILABLE` au lieu de `VALIDATION_ERROR`.

Le test est **antérieur** à la règle de sécurité : c'est un mot de passe de test qui est devenu interdit, pas une régression applicative. **Non corrigé ici** : changer le mot de passe d'un test d'inscription est une modification de la politique de test d'authentification, hors du périmètre d'un audit. **Signalé** pour arbitrage.

### 4.4 Un manque de **fonctionnalité** — dans le périmètre du couloir

| Manque | Preuve du manque | Impact |
|---|---|---|
| **Pages `/activites` et `/profil`** | aucune route dans `front/web/src/app/` ; les API existent (`routes/modules/user.php:121-146`) | les onglets 3 et 4 du couloir **n'ont pas de surface** ; la nav basse de #9419 pointerait vers des 404 |
| **Surface Léa séparée** | aucune occurrence de `Léa`/assistant dans `public-portal` ni `decouvrir` | l'onglet Messages doit séparer Léa du fil commerçant — non fait |
| **`public.profiles`** | aucune migration (`database/migrations/public/` : aucun fichier `profile`) | `PUBLIC_PROFILE_DATA_MODEL.md` §11 la déclare « à créer » ; la langue de la PWA suit l'appareil, pas le compte |
| **Piste d'audit du rattachement** (ADR §8.3-3) | `PublicIdentityBridge` n'écrit aucun `AuditLog` | une écriture de `user_id` n'est pas traçable → exigence RGPD |

### 4.5 Hors périmètre — écrit noir sur blanc

| Élément | Pourquoi hors couloir 16 |
|---|---|
| Transport SMS réel | lot **L1** (#9091), couloir Identité — le cas 2 est « couvert par des tests » mais le parcours réel ne peut pas aboutir tant que `LogOnlySmsCodeSender` journalise au lieu d'envoyer |
| Backfill `travel_customer_accounts.phone → users.phone_e164` | lot **L4** (#9094) |
| Révocation des jetons au changement de numéro | lot **L1** |
| Fusion explicite à deux facteurs (le 403 de §8.2-2) | aucune route de fusion n'existe ; c'est une opération **administrée**, donc L4 |
| Distribution aux testeurs (Firebase / App Store) | **porte (c)** — dépense nouvelle, aucun engagement |
| `AuthSelfRegistrationTest` (§4.3) | relève de la politique de test d'authentification, pas du canal public |

---

## 5. Exécutions relevées dans ce lot

Environnement : **PHP 8.2.35** (le projet déclare `^8.4.1` ; `composer install --ignore-platform-req=php` — écart d'environnement, signalé), **PostgreSQL 15.19**, `CI=true` obligatoire (sinon `TestCase::isRunningInsideDocker()` réécrit `DB_HOST` en `pgsql`), vraies migrations.

| Commande | Résultat |
|---|---|
| `phpunit tests/Feature/LeopardoPublic/PublicProfileCoverageTest.php` | **9 tests, 42 assertions, 0 échec** |
| `phpunit tests/Feature/LeopardoPublic/PublicActivityTest.php` | **14 tests, 39 assertions, 0 échec** |
| `phpunit` (6 classes de la matrice : `RegisterLoginFlowTest`, `AuthSelfRegistrationTest`, `LoginCodeSmsChannelTest`, `PublicIdentityBridgeServiceTest`, `PublicLogoutAllTest`, `SanctumTokenHygieneTest`) | **41 tests, 104 assertions, 2 échecs** (§4.3) |

**Non exécuté, et donc non affirmé** :

- **Tests front (`jest`)** — `front/web/node_modules` absent ; installer le front n'entre pas dans le budget de ce lot. Les tests front cités en §3.2 sont **lus**, pas exécutés.
- **Tests Flutter** — Flutter absent de l'environnement.
- **`PublicParcoursEndToEndTest`** — cité pour situer le parcours, **non relancé** dans ce lot.
- **Seuils de qualité** (premier écran < 3 s, poids d'images, Lighthouse mobile, zéro plantage sur 20 e2e) — **aucun mesuré ici**. Ils restent **en attente**.

⚠️ **Un piège d'environnement à connaître** : `php artisan test` affiche « 14 warnings » sur un `file_get_contents` de fichier de couverture absent. **Ce n'est pas un échec** — `./vendor/bin/phpunit` sur la même classe rend `14/14 OK`. `artisan test` tente de produire un rapport de couverture qu'aucun driver ne permet ici. Lire le code de sortie, pas le bandeau.

---

## 6. Décisions en attente

1. **Construire `/activites` et `/profil`** — c'est le vrai reste-à-faire du couloir, et il conditionne l'onglet 3 et l'onglet 4.
2. **Trancher la surface Léa** — onglet distinct, ou entrée dans Messages ?
3. **`public.profiles`** — créer la table (additive) ou reporter la préférence de langue au compte par un autre moyen ?
4. **Piste d'audit du rattachement** (ADR §8.3-3) — implémenter, ou acter explicitement que le pont n'est pas audité ?
5. **`AuthSelfRegistrationTest`** — corriger le mot de passe de test, ou revoir `NotCommonPassword` ?
6. **README** — ajouter la surface grand public à « Product map » et « Live ecosystem » ?
7. **#9419** — la nav basse ne pointe vers rien sur `main` tant que les points 1 et 3 ne sont pas faits. Merger #9419 seule créerait des liens morts.

---

## 7. Ce que ce document ne prétend pas

- Il ne dit **pas** que le couloir est terminé : les onglets 3 et 4 n'ont pas de surface.
- Il ne dit **pas** que les cas 1 à 8 « passent » : les cas 1 et 5 sont couverts par des classes dont l'exécution est relevée en partie ici, avec **2 échecs préexistants** documentés au §4.3.
- Il ne mesure **aucun** seuil de qualité.
- Il ne modifie **aucune** fonctionnalité : le lot ajoute un fichier de test et ce document.
