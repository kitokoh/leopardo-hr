# ADR — Identité unifiée : `users` vs `travel_customers`

**Date :** 2026-10-21
**Statut :** **proposition — en attente de décision.** Aucun code d'identité n'est modifié,
aucune migration d'unification n'est écrite, aucune troisième identité n'est créée.
**Contexte :** audit Phase 1 §14.3 (constat P2-03), issue #8804.

---

## 1. Le constat, vérifié dans le code

Le dépôt porte **trois** identités distinctes, et non deux :

| Identité | Table | Schéma | Guard Sanctum | Provider `config/auth.php` | Portée |
|---|---|---|---|---|---|
| Employé | `employees` | `shared_tenants` (tenant) | `sanctum` | `employees` | un tenant |
| Compte plateforme | `users` | `public` | `user_api` | `users` | plateforme |
| Client grand public travel | `travel_customer_accounts` | `public` | guard dédié (#7739) | `travel_customer_accounts` | plateforme |

Points mesurés, pas supposés :

- **`users` et `travel_customer_accounts` n'ont pas la même clé naturelle.**
  `travel_customer_accounts` porte `email VARCHAR(255) UNIQUE` **et** un `phone VARCHAR(40) NULLABLE`
  non unique. `users` porte `phone` nullable, sans contrainte d'unicité. Le téléphone, que la
  mission identifie comme identifiant d'avenir (BC-03, #8736), **n'est aujourd'hui unique
  nulle part**.
- **`employees` est dans le schéma tenant**, donc une même personne physique peut être
  employée d'un tenant **et** cliente d'un autre — sans lien entre les deux lignes.
- **Le guard est le seul point de séparation réel** : `sanctum` → `employees`,
  `user_api` → `users`, guard dédié → `travel_customer_accounts`. C'est ce qui a rendu le
  constat P0-07 faux : un token `users` ne peut pas atteindre une route `auth:sanctum`
  (§13.5 du rapport Phase 1).

## 2. Ce que « unifier » veut dire — trois questions distinctes

Le mot « unification » recouvre trois problèmes qu'il faut séparer, sous peine de traiter
le mauvais :

1. **Une personne, un compte** (identité fonctionnelle) — la même personne qui est
   employée ici et cliente là-bas doit-elle avoir **un** identifiant ?
2. **Un identifiant de connexion** (authentification) — quel facteur prouve l'identité ?
   Aujourd'hui : e-mail (+ mot de passe), avec `google_id` sur `users`.
3. **Un cumul de rôles** (autorisation) — BC-03 (#8736–#8740) veut qu'une personne cumule
   des rôles ; cela suppose un sujet unique **avant** les rôles.

La question 3 dépend de la 1. La question 2 est indépendante et peut avancer seule.

## 3. Options

### Option A — Statu quo, guards séparés (aucune unification)

Garder trois tables, trois guards, et ne jamais les relier.

- **Pour** : zéro risque de fuite de privilèges entre surfaces ; zéro migration ; c'est
  l'état testé aujourd'hui.
- **Contre** : une personne qui est employée et cliente a **deux mots de passe** et deux
  historiques ; BC-03 (cumul de rôles) devient impossible sans une table de jointure
  supplémentaire ; toute future surface grand public (app mobile) recréera une quatrième
  identité, exactement le défaut qu'on cherche à éviter.
- **Verdict** : ne résout pas le problème posé, mais c'est le seul choix **sans** risque.

### Option B — Liaison additive par téléphone (recommandée)

**Ne fusionne rien.** Ajoute une table de correspondance `identity_links`
(`identity_id`, `identity_kind`, `subject_id`, `verified_at`) et un identifiant de
personne stable, en **conservant** les trois tables et les trois guards.

- **Pour** : réversible (une table, un `DROP` suffit à revenir) ; aucune réécriture de
  guard donc **aucun risque de fuite de privilèges** — c'est le point décisif ; permet à
  BC-03 de raisonner sur une personne sans toucher à l'authentification ; permet
  d'introduire le téléphone comme identifiant vérifié **progressivement**, compte par
  compte, là où il est déjà collecté.
- **Contre** : ajoute une indirection ; ne supprime pas la duplication des mots de passe
  tant que la question 2 n'est pas tranchée ; demande une règle de résolution quand deux
  lignes revendiquent le même téléphone (voir §5).
- **Verdict** : répond à la question 1 **sans** toucher aux guards, donc sans mettre en
  danger l'autorisation — le seul axe où une erreur serait grave.

### Option C — Un compte unique à terme (cible)

Une seule table d'identité, des **rôles** en relation, et `employees`/`travel_customer_accounts`
réduits à des profils métier rattachés.

- **Pour** : c'est la cible propre ; un seul mot de passe ; BC-03 trivial ; l'app mobile
  grand public n'a plus à inventer d'identité.
- **Contre** : **migration lourde et non réversible** sur une base multi-tenant ; réécrit
  les trois guards, donc **exactement la surface** où le constat P0-07 croyait voir une
  faille ; demande de réconcilier des doublons (deux comptes, un seul e-mail) ; demande une
  politique de conservation et de suppression (RGPD) différente par type de compte.
- **Verdict** : bonne destination, mauvaise première étape.

## 4. Recommandation

**Option B maintenant, Option C comme cible documentée, jamais une troisième identité.**

1. **Court terme (décision requise : Option B)** — table `identity_links` + identifiant de
   personne stable. Les guards ne changent pas. Le téléphone devient un facteur de
   **liaison** (jamais un facteur d'authentification seul) une fois vérifié par OTP.
2. **Moyen terme** — OTP téléphone (#8736) comme vérification de liaison, puis comme second
   facteur possible. À ce stade seulement, la question 2 peut être tranchée.
3. **Long terme** — convergence vers Option C, verticale par verticale, en commençant par la
   moins risquée.
4. **Interdiction explicite** — **aucune quatrième identité**. L'app mobile grand public
   (#8807) doit consommer l'identité existante, pas en créer une.

## 5. Questions ouvertes à trancher

| # | Question | Impact |
|---|---|---|
| Q1 | Deux lignes (une `users`, une `travel_customer_accounts`) revendiquent le même téléphone : qui gagne ? | erreur de liaison = accès au mauvais historique |
| Q2 | Le téléphone devient-il un identifiant de **connexion**, ou seulement de **liaison** ? | sécurité : un numéro recyclé par un opérateur devient une clé de compte |
| Q3 | Un employé qui quitte un tenant garde-t-il son identité de personne ? | RGPD : effacement vs conservation |
| Q4 | Le cumul de rôles (BC-03) est-il cross-tenant ou intra-tenant ? | modèle de données de BC-03 |
| Q5 | Faut-il vérifier l'e-mail des comptes existants avant toute liaison ? | qualité de la réconciliation |

## 6. Ce que cet ADR **n'autorise pas**

- Aucune migration d'unification n'est écrite.
- Aucun guard n'est modifié — `sanctum` reste `employees`, `user_api` reste `users`, le
  guard travel reste dédié.
- Aucune colonne `unique` n'est ajoutée sur `phone` : cela casserait des données existantes
  (doublons probables) et transformerait une décision produit en contrainte technique
  irréversible.
- L'app mobile grand public ne démarre pas avant la décision : elle dépend de Q1 et Q2.
