# ADR BC-38-003 — Idempotence garantie par la base, pas par l'application

- **Statut** : accepté
- **BC** : BC-38 PAIEMENTS
- **Issues** : #8682, #8688

## Contexte

Un appel financier peut être rejoué : retry réseau, webhook livré deux fois,
utilisateur qui double-clique. Sans idempotence, chaque rejeu crée un mouvement
d'argent — c'est-à-dire un doublon comptable réel.

Un contrôle applicatif (`if (exists) return;`) ne suffit pas : entre le `SELECT`
et l'`INSERT`, deux requêtes concurrentes passent toutes les deux.

## Décision

1. **Contrainte unique en base** sur `(company_id, idempotency_key)`. C'est la
   base qui arbitre, pas le code.
2. **Verrou transactionnel** (`lockForUpdate()`) sur la recherche de transaction
   existante, pour sérialiser les appels concurrents.
3. **Violation de contrainte rattrapée** (SQLSTATE `23505`) : le perdant de la
   course relit et retourne la transaction du gagnant. Résultat : un rejeu
   concurrent produit **exactement une** paire d'écritures.
4. **Rejeu de webhook = no-op** retournant le résultat existant, sans effet de
   bord.
5. **Clé obligatoire** : sans clé, l'opération est rejetée (422).

## Conséquences

- L'idempotence tient même sous concurrence réelle, pas seulement en test
  séquentiel.
- Le client doit fournir une clé stable par intention métier (pas par tentative).

## Alternatives écartées

- **`if (exists)` applicatif seul** : rejetée — race condition garantie.
- **Verrou applicatif (cache lock)** : rejetée — dépend d'un service externe
  (Redis) pour une garantie qui doit tenir au niveau des données.
- **Clé d'idempotence globale** : rejetée — deux tenants peuvent légitimement
  utiliser la même clé ; l'unicité doit être par tenant.
