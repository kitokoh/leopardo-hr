# ADR BC-38-002 — Ledger en partie double, immuable, solde dérivé

- **Statut** : accepté
- **BC** : BC-38 PAIEMENTS
- **Issues** : #8680, #8681

## Contexte

Un solde de wallet peut être stocké (colonne `balance`) ou dérivé (agrégation
du journal). Le stockage est plus rapide à lire, mais crée une **seconde source
de vérité** : toute écriture qui oublie de mettre à jour le solde — ou toute
mise à jour concurrente — produit une divergence silencieuse et non
reconstructible.

## Décision

1. **Le solde n'est jamais stocké.** `Wallet::balance()` agrège le ledger :
   `SUM(crédits) - SUM(débits)`. Un cache n'est autorisé que s'il est invalidé
   dans la même transaction que l'écriture, et une commande d'artisan doit
   pouvoir détecter l'écart cache/ledger. Le ledger reste la seule vérité.
2. **Partie double stricte.** Chaque mouvement produit **exactement deux**
   écritures (débit + crédit) partageant un `transfer_group_id`. Invariant
   vérifiable : `SUM(débit) == SUM(crédit)` et nombre d'écritures pair.
3. **Immuabilité au niveau modèle.** Les gardes `saving`/`deleting` de
   `PaymentLedgerEntry` font échouer toute modification ou suppression, y
   compris depuis du code qui contournerait la couche service. Pas d'`updated_at`.
4. **Correction par contre-passation.** `LedgerService::reverse()` crée le groupe
   inverse ; les deux groupes restent dans l'historique.

## Conséquences

- Le solde est reconstructible à tout moment depuis le journal — c'est ce qui
  rend la réconciliation possible.
- Une erreur ne disparaît jamais : elle est compensée, donc auditable.
- Coût : une agrégation par lecture de solde. Acceptable, et optimisable plus
  tard par un cache invalidé transactionnellement.

## Alternatives écartées

- **Colonne `balance` mise à jour à chaque écriture** : rejetée — divergence
  silencieuse, et aucune reconstruction possible en cas de bug.
- **Écritures modifiables** : rejetée — un journal modifiable n'est pas un
  journal ; l'audit devient impossible.
- **Suppression d'une écriture erronée** : rejetée — même raison ; la
  contre-passation conserve la trace.
