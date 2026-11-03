# Matrice de connexion du compte public — 8 cas, état vérifié

- **Dépôt** : `kitokoh/leopardo-hr`
- **Branche de référence** : `main` @ `4699e21f2` (+ PR #9097 pour `PUBLIC_ACCOUNT_API_CONTRACT.md`)
- **Guard** : `user_api` (provider `users`) — **jamais** `sanctum` (employés)
- **Date** : 2 novembre 2026

> ⚠️ **Règle de lecture.** Chaque ligne indique un **test existant** (nom exact,
> présent dans le dépôt) ou **aucun test**. Un test « présent » n'est déclaré
> **exécuté** que lorsque son exécution a été constatée. La colonne *Exécuté*
> vaut ❌ partout dans ce document : **aucune suite n'a été lancée pour le
> produire** (voir §4). Ce document ne prétend donc pas que les cas sont
> « réussis » — seulement que le comportement est *couvert par du code de test*.

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
| 1 | **E-mail seul** | inscription + connexion par e-mail, jeton porteur rendu | ✅ oui | `Feature/Auth/RegisterLoginFlowTest.php`, `Feature/Auth/AuthSelfRegistrationTest.php` | ❌ | **couvert par des tests** |
| 2 | **Téléphone seul** | compte sans e-mail, OTP par SMS, jeton rendu | ✅ oui | `Feature/Auth/LoginCodeSmsChannelTest.php` (9 tests) | ❌ | **couvert par des tests** — mais voir §2.1 |
| 3 | **Compte agence de voyage existant** (pont par téléphone vérifié) | un client déjà présent dans `travel_customer_accounts` retrouve **le même** compte `users`, sans doublon | ❌ **aucun** | — | ❌ | ⛔ **NON COUVERT — et non implémenté** |
| 4 | **Personne cliente de plusieurs métiers** | une seule identité, N conversations / N activités, aucune permission partagée | ⚠️ partiel | `Feature/LeopardoPublic/PublicActivityTest.php` (M3, PR #9108) | ❌ | ⚠️ **couvert pour les activités, pas pour le pont d'identité** |
| 5 | **Cliente ET employée** | deux comptes séparés, jetons non interchangeables | ✅ oui | `Feature/Audit/IdentityAccessControlAuditTest.php` (guards disjoints) | ❌ | **couvert par des tests** |
| 6 | **Numéro changé ou réattribué** | nouveau numéro ⇒ nouvelle vérification ; l'ancien ne donne plus accès | ❌ **aucun** | — | ❌ | ⛔ **NON COUVERT** |
| 7 | **SMS indisponible** | message clair + alternative e-mail, **jamais de faux succès** | ✅ oui | `Feature/Auth/LoginCodeSmsChannelTest.php` : `test_request_falls_back_to_email_when_sms_is_not_configured`, `test_request_falls_back_to_email_when_sms_sender_returns_null` | ❌ | **couvert par des tests** |
| 8 | **Appareil perdu** → révocation | révocation des jetons d'un appareil, sans toucher les autres | ⚠️ partiel | `Feature/Auth/SanctumTokenHygieneTest.php` (purge des expirés), `Feature/AuthMeLogoutTest.php` (jeton courant) | ❌ | ⚠️ **partiel : pas de révocation par appareil** |

**Synthèse honnête** : 4 cas couverts par des tests (1, 2, 5, 7), 2 partiels
(4, 8), **2 non couverts et non implémentés** (3, 6).

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

### 2.2 Cas 3 — le pont avec l'agence de voyage : le vrai trou

`travel_customer_accounts` est une **troisième identité**, hors du graphe
`users`, avec son propre guard `travel_customer` et ses propres jetons. Un
client qui a déjà commandé un voyage possède donc un compte que le canal
public ne sait pas retrouver.

Le pont demandé est : **téléphone vérifié → même `users`**. Or :

- `users.phone_e164` est **UNIQUE et nullable** (`2026_10_24_000100_8736`) :
  la clé d'appariement existe et est saine ;
- rien n'écrit `travel_customer_accounts.phone` dans `users.phone_e164` ;
- **aucun test** ne couvre ce pont.

C'est le cas qui justifie l'ADR `ADR-IDENTITE-CLIENTS-PUBLICS.md` (convergence
progressive vs maintien temporaire). Il n'est pas traitable dans le couloir 16 :
il dépend du lot **L4** (#9094).

### 2.3 Cas 4 — plusieurs métiers : ce qui est prouvé et ce qui ne l'est pas

Ce qui est prouvé (M3, PR #9108) : `public_activity_index` porte
`(user_id, company_id, vertical, external_ref)` et chaque lecture rejoue le
contrôle dans le schéma du tenant. Une personne cliente de deux commerces voit
donc deux ensembles d'activités **sans partage de permission** — le test
`PublicActivityTest` couvre l'isolation et le refus d'une ligne d'index
pointant vers l'activité d'autrui.

Ce qui n'est **pas** prouvé : que le parcours de connexion ramène bien **une
seule** identité quand la personne arrive par deux canaux différents (e-mail
d'un côté, téléphone de l'autre). C'est le cas 3, non couvert.

### 2.4 Cas 6 — numéro réattribué : le risque le plus grave

Un numéro libéré puis réattribué à quelqu'un d'autre ne doit **jamais**
donner accès au compte précédent. Ce cas exige :

1. de **ne pas** faire du téléphone un identifiant de compte immuable ;
2. de révoquer les jetons et sessions à tout changement de numéro ;
3. de tracer le changement (audit).

**Aucun des trois n'est couvert par un test.** `PhoneE164UniquenessTest`
prouve seulement l'unicité en base (5 tests), pas le comportement de
réattribution.

### 2.5 Cas 8 — appareil perdu : ce qui manque précisément

`logout` révoque **le jeton courant**. Pour un appareil perdu, il faut révoquer
**tous** les jetons de cet appareil, ou tous sauf celui-ci, sans déconnecter
les autres appareils légitimes. Il n'existe ni route `logout-all`, ni
identification d'appareil sur les jetons (`personal_access_tokens` ne porte pas
de `device_id`). D'où le partiel.

---

## 3. Ce qu'il faudrait pour fermer la matrice

| Cas | Action minimale | Dépendance | Couloir |
|---|---|---|---|
| 3 | backfill `travel_customer_accounts.phone → users.phone_e164` + test de non-doublon | L4 (#9094) | hors 16 |
| 6 | révocation de tous les jetons au changement de numéro + test | L1 (#9091) | hors 16 |
| 8 | `device_id` sur les jetons + route `logout-all` / `logout-device` | aucune | M2 |
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
| Existence d'un test pour le cas 3 | ❌ **aucun** (recherche sur `api/tests/**`) |
| Existence d'un test pour le cas 6 | ❌ **aucun** |
| **Exécution de la suite PHP** | ❌ **non exécutée** — l'environnement de cette session n'a ni binaire `php` ni `vendor/` |
| Observation de la CI sur la PR #9108 | ✅ `Backend (PHP 8.4 + PostgreSQL 16 + Redis 7)` — voir rapport de fin de lot |

**Aucun cas n'est déclaré « réussi ».** Les colonnes *Exécuté* valent ❌ par
construction : dire le contraire sans exécution serait exactement le faux
succès que le cas 7 interdit.

---

## 5. Règle de sécurité qui en découle

Le contrat impose une règle que ce document reprend telle quelle :

> **Un échec d'envoi ne doit jamais être présenté comme un succès.**
> Si le SMS n'est pas envoyé, la réponse doit le dire et proposer l'e-mail —
> jamais retourner « code envoyé » puis refuser la vérification.

C'est la raison d'être des deux tests de repli du cas 7, et la raison pour
laquelle le cas 2 ne peut pas être déclaré opérationnel alors que le
transport est un stub : la logique est bonne, le canal ne l'est pas.
