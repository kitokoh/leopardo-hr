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
- **O2 — Un e-mail, deux humains.** Une adresse partagée (`contact@entreprise.com`) peut
  avoir créé plusieurs `users`. L'unicité `users.email` (héritée) l'interdit aujourd'hui ;
  faut-il la conserver ? **Recommandation : la conserver**, et refuser la création.
- **O3 — Sort de `travel_customer_accounts`.** Suppression après convergence, ou
  conservation comme table de profil métier ? **Recommandation : conservation comme
  profil** (elle porte des données propres : préférences de voyage), avec `user_id`
  obligatoire en fin de transition.
