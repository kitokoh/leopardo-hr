# ADR BC-38-001 — Séparation `PaymentProviderType` (code) / `PaymentProviderInstance` (configuration)

- **Statut** : accepté
- **BC** : BC-38 PAIEMENTS
- **Issues** : #8683, #8685, #8686

## Contexte

Un socle de paiement doit supporter plusieurs moyens de paiement par marché, et
cette liste change pour des raisons **commerciales** (ouverture d'un pays,
changement de prestataire, bascule d'un marché vers un autre), pas techniques.

Le piège classique : modéliser chaque moyen de paiement comme une classe du
domaine. Chaque nouveau prestataire devient alors un chantier de développement
— branche, revue, déploiement — pour un changement qui devrait être une saisie
dans un back-office.

## Décision

Deux niveaux, strictement séparés :

1. **`PaymentProviderType` — le CODE.** Une famille technique générique
   (« monnaie électronique », « carte bancaire », « virement ») implémentant
   `PaymentRailInterface`. Enregistrée dans `ProviderTypeRegistry`, **lecture
   seule** côté admin. Aucun nom commercial n'y figure.
2. **`PaymentProviderInstance` — la CONFIGURATION.** Une ligne en base : nom
   d'affichage, `type_code`, pays/devises couverts, statut, `config` chiffrée,
   priorité, `company_id` nullable. CRUD complet depuis le Superadmin.

## Conséquences

- Ajouter un moyen de paiement d'un type **déjà implémenté** = zéro ligne de
  code (critère d'acceptation du BC, couvert par un test automatisé).
- Ajouter une **famille technique** = un nouveau type + son adapter, sans
  modifier les entités ni l'API (Open/Closed).
- Le choix du fournisseur est centralisé dans `ProviderResolver` : aucun BC
  consommateur ne décide.

## Alternatives écartées

- **Une classe par prestataire** : rejetée — transforme une décision commerciale
  en déploiement, et fait fuiter des noms commerciaux dans le domaine.
- **Un `switch` sur un code de fournisseur dans le service** : rejetée — viole
  Open/Closed et disperse la logique de choix.
- **Tout en base, y compris les types** : rejetée — un type sans adapter est une
  ligne morte qui casse à l'exécution ; le type doit exister dans le code pour
  être exécutable.
