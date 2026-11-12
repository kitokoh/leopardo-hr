# Matrice de connexion du compte public — 8 cas, état vérifié

- **Dépôt** : `kitokoh/leopardo-hr`
- **Branche de référence** : `main` @ `86c2743ef` (rafraîchi le 8 novembre 2026)
- **Guard** : `user_api` (provider `users`) — **jamais** `sanctum` (employés)
- **Date** : 2 novembre 2026 — **rafraîchi le 8 novembre 2026**

> ⚠️ **Règle de lecture.** Chaque ligne indique un **test existant** (nom exact,
> présent dans le dépôt) ou **aucun test**. Un test « présent » n'est déclaré
> **exécuté** que lorsque son exécution a été constatée. La colonne *Exécuté*
> vaut ✅ **uniquement** pour les classes dont l'exécution a été relevée dans le
> JUnit du job `Backend` de `tests.yml` (run `42408094007`, commit `86c2743ef`).
> Les autres restent ❌ : ce document ne prétend pas que les cas sont « réussis »
> — seulement que le comportement est *couvert par du code de test*.
>
> **Mise à jour du 8 novembre 2026.** Ce document datait du 2 novembre et
> déclarait les cas **3 et 6 « NON COUVERTS — et non implémentés »**. C'était
> exact à sa date, mais **M2 (#9104) les a livrés depuis** :
> `PublicIdentityBridgeServiceTest` (10 tests) couvre précisément le pont par
> téléphone vérifié (cas 3) et le changement de numéro (cas 6). Le tableau et
> le §4 sont corrigés ci-dessous, avec l'exécution relevée.

---

## 0. Pourquoi cette matrice existe

Le parcours critique du canal public est : **Recherche → fiche → Contacter →
téléphone + code SMS → message**, en trois touches maximum. Le point de
rupture est l'étape « téléphone + code » : c'est là qu'on crée ou retrouve une
identité. Une erreur à ce point crée soit un doublon de compte, soit un accès
indu, soit un faux succès.

Les 8 cas ci-dessous sont ceux où une implémentation naïve se trompe.

---

## 1. La matrice

| # | Cas | Comportement attendu | Test existant | Fichier | Exécuté | Verdict |
|---|---|---|---|---|---|---|
| 1 | **E-mail seul** | inscription + connexion par e-mail, jeton porteur rendu | ✅ oui | `Feature/Auth/RegisterLoginFlowTest.php`, `Feature/AuthSelfRegistrationTest.php` | ⚠️ | **couvert — 2 échecs préexistants** (§2.5) |
| 2 | **Téléphone seul** | compte sans e-mail, OTP par SMS, jeton rendu | ✅ oui | `Feature/Auth/LoginCodeSmsChannelTest.php` (16 tests) | ✅ | **couvert ET exécuté** (16/16) — mais voir §2.1 |
| 3 | **Compte agence de voyage existant** (pont par téléphone vérifié) | un client déjà présent dans `travel_customer_accounts` retrouve **le même** compte `users`, sans doublon | ✅ oui | `Feature/LeopardoPublic/PublicIdentityBridgeServiceTest.php` (M2, #9104) — 10 tests | ✅ | **couvert ET exécuté** (10/10) |
| 4 | **Personne cliente de plusieurs métiers** | une seule identité, N conversations / N activités, aucune permission partagée | ✅ oui | `Feature/LeopardoPublic/PublicActivityTest.php` (M3, #9108) + `PublicIdentityBridgeServiceTest` (pont) | ✅ | **couvert ET exécuté** (14/14 + 10/10) |
| 5 | **Cliente ET employée** | deux comptes séparés, jetons non interchangeables | ✅ oui | `Feature/Auth/PublicPhoneOtpTest.php` : `test_user_api_token_does_not_open_employee_surface` (isolation de guard) | ✅ | **couvert ET exécuté** (14/14) |
| 6 | **Numéro changé ou réattribué** | nouveau numéro ⇒ nouvelle vérification ; l'ancien ne donne plus accès | ✅ oui | `Feature/LeopardoPublic/PublicIdentityBridgeServiceTest.php` : `test_changing_phone_never_transfers_user_id` (M2, #9104) | ✅ | **couvert ET exécuté** (10/10) |
| 7 | **SMS indisponible** | message clair + alternative e-mail, **jamais de faux succès** | ✅ oui | `Feature/Auth/LoginCodeSmsChannelTest.php` : `test_request_falls_back_to_email_when_sms_is_not_configured`, `test_request_falls_back_to_email_when_sms_sender_returns_null` | ✅ | **couvert ET exécuté** (16/16) |
| 8 | **Appareil perdu** → révocation | révocation des jetons d'un appareil, sans toucher les autres | ✅ oui | `Feature/LeopardoPublic/PublicLogoutAllTest.php` (**PR #9178 mergée**) — 5 tests ; `Feature/Auth/SanctumTokenHygieneTest.php` (purge des expirés) | ✅ | **couvert ET mergé** (§2.6) |

**Synthèse honnête (12 novembre 2026)** : **8 cas sur 8 sont couverts**, et le
cas 8 est désormais **mergé** (PR #9178, vérifiée `merged: true` le 12 novembre).
Les cas 3 et 6, que ce document déclarait « non implémentés », ont été livrés
par M2 (#9104).

> **Révision du 12 novembre 2026.** Le tableau précédent déclarait le cas 8
> « ⚠️ PR #9178 ouverte, non mergée ». C'était exact le 8 novembre, plus le 12.
> La même révision corrige **deux références de preuve fausses** :
> `Feature/Auth/AuthSelfRegistrationTest.php` n'existe pas (le fichier est
> `tests/Feature/AuthSelfRegistrationTest.php`, sans le sous-dossier `Auth`),
> et le cas 8 renvoie maintenant à §2.6 révisé.
> Audit de couverture complet : `COULOIR16_AUDIT_COUVERTURE_GITHUB.md`.

---

## 2. Ce que chaque cas exige réellement

### 2.1 Cas 2 — téléphone seul : le prérequis qui décide de tout

`LoginCodeSmsChannelTest` couvre correctement **la logique de repli** :
`test_request_falls_back_to_email_when_sms_is_not_configured` et
`test_request_falls_back_to_email_when_sms_sender_returns_null` prouvent
qu'un SMS indisponible ne produit jamais de faux succès — c'est exactement
l'exigence du cas 7.

**Mais le transport lui-même n'est pas branché.** `LogOnlySmsCodeSender`
(`api/app/Core/Auth/Infrastructure/Sms/LogOnlySmsCodeSender.php:119-128`)
retourne un `traceId` **sans aucun appel HTTP** : il *journalise* le code au
lieu de l'envoyer. Les tests ci-dessus valident donc un comportement de repli
sur un canal qui, en production, n'émet rien.

**Conséquence** : le cas 2 est « couvert par des tests » au sens du contrat,
mais **le parcours réel ne peut pas aboutir** tant que l'envoi SMS n'est pas
réellement implémenté (lot **L1**, issue #9091). C'est le sens de la porte (b) :
M2 ne démarre pas avant L1.

### 2.2 Cas 3 — le pont avec l'agence de voyage : **livré depuis**

`travel_customer_accounts` est une **troisième identité**, hors du graphe
`users`, avec son propre guard `travel_customer` et ses propres jetons. Un
client qui a déjà commandé un voyage possède donc un compte que le canal
public ne sait pas retrouver.

Le pont demandé est : **téléphone vérifié → même `users`**. **M2 (#9104) l'a
livré** : `PublicIdentityBridge`
(`api/app/Modules/TravelAgency/Application/Services/PublicIdentityBridge.php`)
et `PublicIdentityBridgeServiceTest` (10 tests, **exécutés, 0 échec**).

Ce que les tests prouvent :

- `test_bridge_cannot_be_written_without_a_verified_phone` — le pont exige
  `phone_verified_at` non nul (contrainte SQLSTATE 23514) ;
- `test_two_accounts_are_never_linked_on_an_unverified_phone` — jamais de
  fusion sur un téléphone non vérifié ;
- `test_existing_user_is_reused_not_duplicated` — un client existant retrouve
  **le même** `users`, sans doublon ;
- `test_two_users_are_never_auto_merged` — deux personnes distinctes ne sont
  jamais fusionnées automatiquement.

**Ce qui reste hors couloir 16** : le *backfill* des numéros déjà présents
(`travel_customer_accounts.phone → users.phone_e164`) dépend du lot **L4**
(#9094). Le pont est livré ; la migration des données historiques ne l'est pas.

### 2.3 Cas 4 — plusieurs métiers : ce qui est prouvé et ce qui ne l'est pas

Ce qui est prouvé (M3, PR #9108) : `public_activity_index` porte
`(user_id, company_id, vertical, external_ref)` et chaque lecture rejoue le
contrôle dans le schéma du tenant. Une personne cliente de deux commerces voit
donc deux ensembles d'activités **sans partage de permission** — le test
`PublicActivityTest` couvre l'isolation et le refus d'une ligne d'index
pointant vers l'activité d'autrui.

Ce qui n'est **plus** un trou : le pont d'identité lui-même est couvert par
`PublicIdentityBridgeServiceTest` (cas 3, §2.2). Ce qui reste non prouvé :
qu'un même parcours ramène **une seule** identité quand la personne arrive par
deux canaux différents (e-mail d'un côté, téléphone de l'autre) — c'est le
*backfill* L4, hors couloir 16.

### 2.4 Cas 6 — numéro réattribué : **livré depuis**

Un numéro libéré puis réattribué à quelqu'un d'autre ne doit **jamais**
donner accès au compte précédent. Ce cas exige :

1. de **ne pas** faire du téléphone un identifiant de compte immuable ;
2. de révoquer les jetons et sessions à tout changement de numéro ;
3. de tracer le changement (audit).

**M2 (#9104) couvre le point 1** :
`PublicIdentityBridgeServiceTest::test_changing_phone_never_transfers_user_id`
(**exécuté, 0 échec**) prouve qu'un changement de numéro ne transfère jamais le
`user_id` d'un compte à un autre. `PhoneE164UniquenessTest` (5 tests) prouve
l'unicité en base.

**Ce qui reste hors couloir 16** : la révocation systématique des jetons au
changement de numéro (point 2) dépend du lot **L1** (#9091).

### 2.5 Cas 1 — e-mail seul : **2 échecs préexistants, mesurés**

Exécution du 12 novembre 2026 (`CI=true`, PostgreSQL 15) :

```
AuthSelfRegistrationTest    FAILURES!  Tests: 3, Failures: 2
```

Cause réelle, identifiée et **non applicative** : `NotCommonPassword`
retire les chiffres de fin avant de consulter sa liste de refus
(`$root = rtrim($normalized, '0123456789')`). Le test utilise
`password123456` → racine `password` → **présente dans la liste** → `422`.
L'assertion attendait `201`, et l'autre test `REGISTRATION_NOT_AVAILABLE`
au lieu de `VALIDATION_ERROR`.

Ce n'est **pas** une régression : c'est un mot de passe de test devenu
interdit par une règle de sécurité ajoutée depuis. Non corrigé ici — changer
le mot de passe d'un test d'inscription relève de la politique de test
d'authentification, pas de ce couloir. **Décision en attente.**

`RegisterLoginFlowTest` passe (le cas 1 est donc couvert par au moins une
classe verte) ; les 2 échecs portent sur la classe d'inscription par
invitation.

---

### 2.6 Cas 8 — appareil perdu : **couvert ET mergé**

`logout` révoque **le jeton courant**. Pour un appareil perdu, il faut révoquer
**tous** les jetons, sans dépendre d'un `device_id` absent de
`personal_access_tokens`. **M2 (#9104) a livré la route `POST /user/logout-all`**
(`api/routes/modules/user.php:56`), et la PR **#9178** (**mergée** le 12 novembre
2026, vérifié via l'API GitHub : `state: closed`, `merged: true`) a ajouté
`PublicLogoutAllTest` — 5 tests / 11 assertions, vrai jeton Sanctum +
`Auth::forgetGuards()`, sur les vraies migrations : 401 sans jeton, révocation
totale (0 jeton survivant), jeton refusé après révocation, jetons d'un autre
utilisateur intacts.

**Il ne reste rien à faire sur ce cas** : la route existe, le test existe, la
PR est mergée.

---

## 3. Ce qu'il faudrait pour fermer la matrice

| Cas | Action minimale | Dépendance | Couloir |
|---|---|---|---|
| 3 | backfill `travel_customer_accounts.phone → users.phone_e164` (le pont est livré) | L4 (#9094) | hors 16 |
| 6 | révocation de tous les jetons au changement de numéro (le non-transfert est livré) | L1 (#9091) | hors 16 |
| 8 | ✅ **fait** — PR #9178 mergée | aucune | M2 |
| 2 | transport SMS réel | L1 (#9091) | hors 16 |
| 4 | test du pont d'identité bout en bout | 3 | hors 16 |

**Aucune de ces actions n'entre dans le périmètre M1/M3 déjà livré**, et aucune
n'a été réalisée ici : ce document est un état des lieux, pas une promesse.

---

## 4. Ce qui a été exécuté — et ce qui ne l'a pas été

| Contrôle | Résultat |
|---|---|
| Lecture de `LoginCodeSmsChannelTest` (9 tests) | ✅ fait |
| Lecture de `PhoneE164UniquenessTest` (5 tests) | ✅ fait |
| Lecture de `SanctumTokenHygieneTest` (4 tests) | ✅ fait |
| Existence d'un test pour le cas 3 | ✅ **`PublicIdentityBridgeServiceTest`** (M2, #9104) |
| Existence d'un test pour le cas 6 | ✅ **`PublicIdentityBridgeServiceTest::test_changing_phone_never_transfers_user_id`** |
| **Exécution de la suite PHP** | ✅ **relevée** — JUnit du job `Backend` de `tests.yml`, run `42408094007`, commit `86c2743ef` |
| Observation de la CI sur la PR #9108 | ✅ `Backend (PHP 8.4 + PostgreSQL 16 + Redis 7)` — voir rapport de fin de lot |

**Comptes exécutés** (relevés dans le JUnit ci-dessus, 0 échec) :

| Classe | Tests | Échecs |
|---|---:|---:|
| `PublicIdentityBridgeServiceTest` (cas 3, 4, 6) | 10 | 0 |
| `PublicPhoneOtpTest` (cas 2, 5, 7) | 14 | 0 |
| `LoginCodeSmsChannelTest` (cas 2, 7) | 16 | 0 |
| `PublicActivityTest` (cas 4) | 14 | 0 |
| `SanctumTokenHygieneTest` (cas 8, partiel) | 4 | 0 |
| `AuthMeLogoutTest` (cas 8, partiel) | 5 | 0 |

**Ce qui reste ❌** : les cas 1 et 5 reposent sur des classes dont l'exécution
n'est pas relevée individuellement dans le JUnit de référence. Dire le
contraire sans exécution serait exactement le faux succès que le cas 7
interdit.

**Relevé du 12 novembre 2026 (local, PostgreSQL 15)** :

| Commande | Résultat |
|---|---|
| 6 classes de la matrice (cas 1, 2, 3, 6, 7, 8) | **41 tests, 104 assertions, 2 échecs** — 2 échecs = `AuthSelfRegistrationTest` (§2.5) |
| `PublicActivityTest` (cas 4) | **14 tests, 39 assertions, 0 échec** |
| `PublicProfileCoverageTest` (nouveau — profils + guards) | **9 tests, 42 assertions, 0 échec** |

Le cas 8 n'est plus « en attente de merge » : il est mergé et sa classe est
dans les 41 tests exécutés.

---

## 5. Règle de sécurité qui en découle

Le contrat impose une règle que ce document reprend telle quelle :

> **Un échec d'envoi ne doit jamais être présenté comme un succès.**
> Si le SMS n'est pas envoyé, la réponse doit le dire et proposer l'e-mail —
> jamais retourner « code envoyé » puis refuser la vérification.

C'est la raison d'être des deux tests de repli du cas 7, et la raison pour
laquelle le cas 2 ne peut pas être déclaré opérationnel alors que le
transport est un stub : la logique est bonne, le canal ne l'est pas.
