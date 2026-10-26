# Décisions d'architecture — BC-03 IDENTITÉ (arbitrages fondateur)

**Date :** 2026-10-26
**Statut :** **tranché** — exécutoire.
**Portée :** SSO SAML, canal OTP, cumul de rôles intra-tenant.
**Complément de :** `docs/design/DECISIONS_BATCH_A.md` (D1 — identité client).

> Fil conducteur : **on ne promet pas ce qu'on ne tient pas, et on ne détruit
> pas de données pour tenir une promesse.** Chaque arbitrage ci-dessous
> privilégie le refus explicite sur le faux succès, et l'additif sur le
> destructif.

---

## D8 — SSO SAML : le refus explicite est la BONNE réponse (issue #8737)

**Décision : on conserve le refus explicite (501). SAML reste hors périmètre ;
OIDC est le chemin SSO supporté.**

Le code lève `SSOValidationNotImplementedException` sur le callback SAML. Ce
n'est **pas** un bug : c'est un choix de sécurité délibéré, documenté dans le
code lui-même (« l'ancien stub retournait un succès vide sans valider
l'assertion — fausse garantie de sécurité »).

**Raisonnement :**

- Un SSO qui **accepte** une assertion non validée est une **faille
  d'authentification** : n'importe qui forge une assertion et entre. Le refus
  est donc la seule option sûre tant que la validation n'existe pas.
- Le chemin **OIDC est, lui, implémenté** (`handleOIDCCallback`) : les tenants
  qui veulent du SSO ont un chemin fonctionnel aujourd'hui.
- Implémenter SAML correctement (OneLogin/php-saml, validation de signature,
  gestion des métadonnées IdP, horloge, replay) est un chantier **réel** qui
  n'a de sens qu'avec un **tenant demandeur identifié** — sinon on écrit du
  code non exercé, donc non fiable, sur une surface d'authentification.

**Décision :** l'écart promesse/code est tranché **en faveur du code**. La
promesse produit est corrigée : le SSO supporté est **OIDC** ; SAML est
explicitement « non supporté » et le refus est le comportement attendu. Le
chantier SAML s'ouvrira sur demande d'un tenant, avec la validation complète
d'emblée — jamais un stub.

---

## D9 — OTP : le SMS est IN scope, séquencé après D1 (issue #8738)

**Décision : le canal SMS est retenu comme canal de premier rang pour les
clients finaux, mais il est séquencé APRÈS la liaison d'identités (D1).**

Le public visé (clients finaux superapp) est majoritairement mobile et peut
n'avoir **aucune adresse e-mail** : un OTP e-mail seul exclut une part réelle
du marché. Le canal SMS est donc **nécessaire**, pas optionnel.

**Pourquoi ne pas le brancher immédiatement :** le téléphone est précisément
l'identifiant que D1 a décidé de traiter comme **identifiant de liaison**
(et non d'authentification de premier rang). Brancher le SMS avant la table
`identity_links` reviendrait à faire du téléphone un identifiant
d'authentification **de fait**, en contradiction avec D1 — et sans résolution
des doublons de numéro.

**Séquence arrêtée :**

1. D1 — table `identity_links` + liaison consentie (prérequis).
2. Ajout du champ `phone` à la requête OTP + abstraction de canal
   (`OtpChannelInterface` : `email` | `sms`).
3. Branchement du `SmsAdapter` existant (`Modules/CRM/Infrastructure/Integrations/Sms`)
   derrière cette abstraction — **aucun nouvel adaptateur à écrire**, il existe.
4. Le canal est choisi par l'utilisateur, jamais deviné.

**Décision :** issue **laissée ouverte** — c'est un chantier d'implémentation
réel, désormais cadré et séquencé. Le prérequis (D1) est tranché.

---

## D10 — Cumul de rôles intra-tenant : par liaison d'identités, pas par multi-rôle (issue #8740)

**Décision : le cumul « employé + client final dans la même entreprise » est
résolu par `identity_links` (D1), PAS par une table pivot de rôles sur
`employees`.**

Le constat est exact : `employees.role` est un `enum('manager','employee')`
unique, donc un même humain ne peut pas être simultanément salarié et client
de la même entreprise.

**Deux voies possibles, et pourquoi l'une est écartée :**

| Voie | Verdict |
|---|---|
| Table pivot `employee_roles` (multi-rôle sur une ligne employé) | **Écartée.** Elle mélange deux **domaines** distincts : un salarié (relation de travail, paie, pointage) et un client (relation commerciale, commandes). Leur donner le même porteur de permissions rendrait toute policy ambiguë — « ce porteur a-t-il le droit de voir la paie ? » n'a pas de réponse unique. |
| **`identity_links` (D1)** | **Retenue.** Une **personne** (identifiant stable) est reliée à **plusieurs identités** : son identité salarié ET son identité client. Chaque identité garde ses guards, ses permissions et son domaine. Le cumul est une propriété de la **personne**, pas de la ligne employé. |

**Conséquence :** le critère d'acceptation de l'issue (« la décision produit
est… ») est satisfait par D1. Aucune migration de `employees.role` n'est
nécessaire — ce qui évite de toucher une colonne lue par ~1 086 fichiers
(cf. #8061).

**Décision :** issue **close** — la décision produit est rendue et elle
n'implique aucun changement de schéma sur `employees`.

---

## Suivi

| Issue | Décision | Statut |
|---|---|---|
| #8737 | D8 — refus explicite conservé, OIDC = chemin supporté, SAML hors périmètre | tranchée → close |
| #8738 | D9 — SMS in scope, séquencé après D1, `SmsAdapter` réutilisé | tranchée → reste ouverte (chantier) |
| #8740 | D10 — cumul par `identity_links`, pas de pivot de rôles | tranchée → close |
