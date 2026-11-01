# ADR — Identité des clients publics

- **Statut** : **Proposé** — en attente de validation du propriétaire
- **Date** : 31 octobre 2026
- **HEAD de référence** : `31ef78e04` (branche `main`)
- **Décideur** : propriétaire du produit
- **Documents liés** :
  `docs/audits/IDENTITY_ACCESS_CONTROL_FOUNDATION_AUDIT.md`,
  `docs/design/IDENTITY_ROLE_PERMISSION_MATRIX.md`,
  `docs/design/PUBLIC_ACCOUNT_API_CONTRACT.md`

---

## 1. Contexte et décision déjà prise

Le propriétaire a **déjà tranché** :

> Les comptes **public** (`users`) et **tenant** (`employees`) sont **séparés**,
> **reliés par le numéro de téléphone**, et il n'y a **aucune troisième identité**.

Cet ADR **ne remet pas cette décision en cause**. Son objet est différent : constater que
le code contient aujourd'hui **une quatrième table d'identité** qui n'était pas prévue par
cette décision, et proposer **la trajectoire de convergence à partir de l'état réel** — pas
une refonte.

---

## 2. État réel constaté

### 2.1 Quatre modèles d'identité, pas deux

| Modèle | Population | Table | Guard | Verdict vis-à-vis de la décision |
|---|---|---|---|---|
| `User` | Public générique | `users` | `user_api` | ✅ **conforme** |
| `Employee` | Employé / admin de tenant | `employees` (par schéma) | `sanctum` | ✅ **conforme** |
| `SuperAdmin` | Collaborateur Leopardo | `super_admins` | `super_admin_api` | ✅ **acceptable** — population interne, guards disjoints, aucun chevauchement |
| `TravelCustomerAccount` | Client grand public travel | `travel_customer_accounts` | `travel_customer` | ❌ **non conforme** |

**Preuve** : `api/config/auth.php:40-118` ;
`api/app/Modules/TravelAgency/Domain/Models/TravelCustomerAccount.php:18-33`
(« Table **PLATEFORME** … guard **DÉDIÉ** ») ;
créé par #7739, commit visible dans l'historique.

**Test exécuté** :
`IdentityAccessControlGapsTest::test_public_consumer_identity_is_a_separate_model_not_user`
→ **PASS** (assert que `TravelCustomerAccount` n'hérite pas de `User`).

### 2.2 Le lien `users` ↔ `employees` existe mais n'est pas consommé

`user_employee_links` porte `unique(['user_id','company_id'])`
(`2026_05_02_100001_create_users_and_company_requests_tables.php:106`) — exactement la
structure qui permettrait à une personne d'avoir **une** identité et **N** appartenances.

**Mais** `AuthService` ne le référence jamais : la connexion employé se fait par
`employees.email` + balayage des schémas (`:618-663`). **Test exécuté** :
`test_user_employee_link_exists_but_login_does_not_use_it` → **PASS**.

### 2.3 Le téléphone n'est pas un canal d'authentification

`users.phone_e164` existe avec unicité partielle (`2026_10_24_000100_8736...:65,73-74`),
mais **aucun** endpoint n'accepte un téléphone : `LoginCodeController` valide
`'email' => ['required','email']` (`:54-55`, `:137-138`). Le téléphone ne sert que de
**destination** SMS, résolue depuis l'e-mail (`resolveSmsRecipient()`, `:255-270`).

---

## 3. Options examinées

### Option A — Statu quo documenté

On garde `travel_customer_accounts` et on documente qu'il existe deux populations
publiques.

- ➕ Aucun travail.
- ➖ **Contredit frontalement la décision** du propriétaire.
- ➖ Un consommateur qui réserve un vol **et** achète dans une boutique aura **deux
  comptes** et deux mots de passe. C'est exactement la « confusion » que la vision veut
  éviter.
- ➖ Interdit toute vue unifiée « mes commandes / mes réservations / mes entreprises ».

**Rejetée.**

### Option B — Fusion immédiate (big bang)

Migrer `travel_customer_accounts` vers `users` en une opération, supprimer le modèle.

- ➕ Converge immédiatement.
- ➖ **Migration de données** — explicitement interdite par le périmètre de cette mission.
- ➖ Casse le contrat d'API de la marketplace travel (`/public/travel/marketplace/account/*`,
  `routes/api.php:278-279`) dont dépend l'application mobile grand public.
- ➖ Le guard `travel_customer` est référencé dans des contrôleurs qui narrowent par
  `instanceof` (`config/auth.php:70-78`) : suppression = régression typée large.

**Rejetée** (viole le périmètre et casse un contrat).

### Option C — **Pont d'identité, convergence progressive** ✅ *recommandée*

On **ne supprime rien**, on **ne migre rien**. On ajoute un **pont** :

1. `travel_customer_accounts` reçoit `phone_e164` (nullable, index unique partiel —
   **même discipline** que `users`, #8736) et `user_id` (nullable, FK vers `users`).
2. À l'inscription ou au premier login d'un client travel, un **`users` est créé ou
   retrouvé par `phone_e164`** (ou par e-mail en repli), et `travel_customer_accounts.user_id`
   est renseigné. Le compte travel devient une **vue métier** d'une identité `users`.
3. L'authentification continue de passer par le guard `travel_customer` **pendant la
   transition** — aucun contrat cassé.
4. Une fois `user_id` renseigné pour 100 % des lignes actives, le guard `travel_customer`
   peut être **déprécié** et les routes basculées sur `user_api`, avec une période de
   double-acceptation.

- ➕ Respecte la décision (convergence vers `users` + téléphone).
- ➕ Aucune migration destructive, aucun contrat cassé, aucune app mobile à modifier.
- ➕ Réversible : si le pont s'avère inutile, on abandonne les deux colonnes.
- ➖ Coût : ~5 jours, et une période où **deux guards** coexistent (dette assumée et
  bornée dans le temps).

---

## 4. Décision

**Option C est retenue.**

Trois règles d'application :

1. **Aucune nouvelle table d'identité.** Toute nouvelle population (marketplace, fidélité,
  kiosque) réutilise `users` + une relation métier. Toute PR qui introduit un modèle
   `Authenticatable` supplémentaire doit être refusée en revue.
2. **`phone_e164` est la clé de convergence.** Partout où une nouvelle identité publique
   est créée, `phone_e164` est rempli et normalisé à l'écriture. L'e-mail reste un
   identifiant de contact, pas la clé d'identité.
3. **Le guard `travel_customer` est en dépréciation planifiée**, pas en extension : on
   n'ajoute **aucune** nouvelle route sous ce guard.

### Ce que cette ADR **ne** décide pas

- Elle ne tranche pas le sort de `super_admins` : cette population est **interne**, ses
  guards sont disjoints, et elle ne crée pas de confusion d'autorisation. Elle reste
  hors périmètre.
- Elle ne prescrit **aucune** migration de données. La bascule `travel_customer` →
  `user_api` est une **condition de sortie**, pas une tâche de ce lot.

---

## 5. Conséquences

### Positives

- Une personne = un `users`, quel que soit le nombre de ses relations (employé d'une
  entreprise, client d'une agence, consommateur d'une boutique).
- Le téléphone devient le pivot : cohérent avec le public mobile-first visé et avec
  l'existant (#8736).
- Les guards restent **disjoints** pendant toute la transition : aucune régression
  d'autorisation.

### Négatives / risques

| Risque | Mitigation |
|---|---|
| Deux comptes `users` pour un même humain (e-mail ≠ téléphone) | Contrainte unique sur `phone_e164` + règle de résolution documentée (§6) |
| Réattribution d'un numéro par un opérateur → un nouvel arrivant hérite d'un compte | **Non résolu par cette ADR** — voir §7, point ouvert O1 |
| Coût SMS et fraude (numéros surtaxés, énumération) | Rate limiting sur l'envoi de codes ; voir plan L4 |
| Double guard pendant la transition | Date de sortie inscrite dans le plan (§L4) |

---

## 6. Algorithme de résolution d'identité (référence)

```
resolveUserForPublicAccount(email, phoneE164):
  if phoneE164 présent:
      user = users.where(phone_e164 = phoneE164).first()
      if user: return user
  if email présent:
      user = users.where(email = email).first()
      if user: return user
  return users.create(email, phone_e164)   # nouvelle identité
```

> ⚠️ Cette fonction **ne doit jamais fusionner deux `users` existants** de façon
> automatique : une fusion est une opération **administrée** (support, avec trace), pas un
> effet de bord d'inscription. C'est le garde-fou contre la prise de contrôle de compte.

---

## 7. Points ouverts (à trancher par le propriétaire)

- **O1 — Réattribution de numéro.** Un opérateur réattribue `+221 77 000 00 00` à une
  nouvelle personne. Avec le téléphone comme clé, cette personne **hérite** de l'ancien
  compte. Trois réponses possibles : (a) exiger une vérification OTP à chaque nouveau
  appareil, (b) ajouter une fenêtre d'inactivité au-delà de laquelle le numéro est
  « libéré », (c) accepter le risque et tracer. **Recommandation : (a) + (c).**
- **O2 — Un e-mail, deux humains.** Une adresse partagée du type `contact` (arobase) `entreprise` peut
  avoir créé plusieurs `users`. L'unicité `users.email` (héritée) l'interdit aujourd'hui ;
  faut-il la conserver ? **Recommandation : la conserver**, et refuser la création.
- **O3 — Sort de `travel_customer_accounts`.** Suppression après convergence, ou
  conservation comme table de profil métier ? **Recommandation : conservation comme
  profil** (elle porte des données propres : préférences de voyage), avec `user_id`
  obligatoire en fin de transition.

---

## 8. Règles de rattachement — version mécaniquement vérifiable

> **Révision 3.** Cette section remplace toute formulation déclarative antérieure. Une règle
> qui n'est pas vérifiable par la base ou par un test n'est pas une règle, c'est une
> intention.

### 8.1 L'interdiction, énoncée une fois

> **Il est interdit de rattacher deux comptes sur la seule base d'un numéro non vérifié.**
> Aucun `UPDATE ... SET user_id = ...` ne peut se produire dans le même flux qu'une saisie
> de numéro.

La différence est décisive : « interdit » se contourne par un bug ; **impossible à écrire** ne
se contourne pas. La contrainte ci-dessous rend l'interdiction structurelle.

```sql
ALTER TABLE public.travel_customer_accounts
    ADD COLUMN phone_e164 varchar(20) NULL,
    ADD COLUMN phone_verified_at timestamptz NULL,
    ADD COLUMN user_id uuid NULL;

-- La règle, en une ligne : pas de vérification, pas de rattachement.
ALTER TABLE public.travel_customer_accounts
    ADD CONSTRAINT travel_customer_accounts_phone_verified_before_link
    CHECK (user_id IS NULL OR phone_verified_at IS NOT NULL);

CREATE INDEX travel_customer_accounts_phone_e164_idx
    ON public.travel_customer_accounts (phone_e164)
    WHERE phone_e164 IS NOT NULL;
```

**Pourquoi une contrainte et pas une validation applicative** : une validation se contourne par
un `Model::unguard()`, un `insertOrIgnore`, un import, une console. Une contrainte CHECK tient
contre tous les chemins d'écriture, y compris ceux qu'on n'a pas écrits.

### 8.2 Les sept situations, règle par règle

| Situation | Règle | Vérification |
|---|---|---|
| **OTP et vérification du téléphone** | `phone_e164` n'est **jamais** un identifiant avant OTP réussi. Un numéro saisi est une **prétention**, pas une preuve. | `phone_verified_at` NULL ⇒ `user_id` NULL (contrainte §8.1) |
| **Comptes existants, numéros déjà associés** | Si le numéro existe des deux côtés, **aucun rattachement automatique**. Il faut prouver la possession **et** s'authentifier sur les deux comptes, ou passer par un parcours de fusion explicite. | test : fusion avec un seul facteur ⇒ `403` |
| **Collisions** | Deux `users` avec le même numéro : impossible (index unique partiel `users.phone_e164`). Un `users` + un `travel_customer` avec le même numéro : **autorisé** — c'est le cas normal du pont. | `23505` sur `users`, aucun sur `travel_customer_accounts` |
| **Rattachement sécurisé** | `user_id` n'est écrit **que** par le service de rattachement, après **deux** preuves : session valide sur `users` **et** OTP réussi sur le numéro. | un test vérifie qu'un seul facteur est refusé |
| **Changement de numéro** | L'ancien reste **tracé et vérifié** ; le nouveau exige un nouvel OTP. Un changement **ne transfère jamais** `user_id`. | test : après changement, `user_id` inchangé |
| **Récupération de compte** | Uniquement par un canal **déjà vérifié**. Jamais par simple connaissance du numéro. | test : récupération par numéro non vérifié ⇒ `403` |
| **Concurrence des inscriptions** | Contrainte unique en base + gestion de `23505` ⇒ réponse idempotente, jamais deux comptes. | test : deux inscriptions simultanées ⇒ un seul compte |

### 8.3 Le service de rattachement — unique, audité

Trois propriétés, chacune testable :

1. **Point d'entrée unique.** Aucun autre code n'écrit `travel_customer_accounts.user_id`.
   Vérifiable en CI par une règle statique (grep du motif d'écriture hors service).
2. **Deux preuves obligatoires.** Signature exigeant `(User $accountHolder, string $verifiedPhone)` —
   le typage rend l'oubli visible en revue.
3. **Audit systématique.** Chaque écriture produit une ligne `audit_logs` : acteur, ancien
   `user_id`, nouveau, numéro, motif, horodatage. Un `UPDATE` direct hors service ne laisse pas
   de trace — c'est précisément pourquoi il est interdit.

### 8.4 Ce que cette version change par rapport aux révisions antérieures

| Avant | Après |
|---|---|
| « Ne jamais rattacher sur un numéro non vérifié » (déclaratif) | Contrainte SQL — **impossible à écrire** |
| « Le rattachement doit être audité » | Service unique + test statique en CI |
| Trois places où `user_id` pouvait être écrit | Une seule |

### 8.5 Conséquences, composant par composant

| Composant | Conséquence |
|---|---|
| `travel_customer_accounts` | +3 colonnes, +1 contrainte CHECK, +1 index partiel. **Additif**, aucune donnée migrée. |
| `users` | inchangé (`phone_e164` et son index unique partiel existent déjà depuis #8736) |
| Marketplace / travel | peut enfin reconnaître un client récurrent ; aucune rupture de contrat |
| Applications existantes | continuent de fonctionner : `user_id` NULL = comportement actuel |
| Mobile grand public | débloqué : login par téléphone, contrat en `PUBLIC_ACCOUNT_API_CONTRACT.md` |

**Aucune donnée n'est migrée.** Les `travel_customer_accounts` existants gardent `user_id` NULL
jusqu'à ce que leur titulaire accomplisse lui-même le rattachement. C'est plus lent qu'un
backfill, et c'est la seule façon de respecter l'interdiction du §8.1.
