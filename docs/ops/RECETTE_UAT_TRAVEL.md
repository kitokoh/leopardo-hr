# Plan de recette UAT — TravelAgency (TRAVEL-1010, issue #6123)

> **Gates :** [MAT-018 #5876](https://github.com/kitokoh/leopardo-hr/issues/5876) — la recette signée (gate `recette`) conditionne le GO pilote.
> **Golden journey :** GJ-TRAVEL-01 (TRAVEL-1007) automatisé dans `TravelGoldenJourneyTest`.

## 1. Cadre

La recette métier UAT couvre les parcours : **référentiel, réseau/trajets,
vente guichet, vente en ligne, paiements, billets/check-in, annulations/
remboursements, caisse PDV, rapports, correspondances, corporate, fidélité**.
Chaque scénario est **signé par le métier** : date, exécutant, résultat
(pass/fail), évidence, anomalies ouvertes. **Zéro anomalie bloquante** avant release.

## 2. Scénarios de recette

| # | Parcours | Scénario | Critère de succès |
|---|---|---|---|
| U-01 | Référentiel | Pays/villes/stations/offices/compagnies/classes/véhicules | CRUD + isolation tenant (404 sûr) |
| U-02 | Réseau | Route + étapes → trajet + tarifs → publication | Publication contrôlée, sièges générés |
| U-03 | Vente guichet | Réservation multi-passagers → confirmation comptant | Verrouillage sièges, idempotence, événements |
| U-04 | Vente en ligne | Recherche shop → réservation online → paiement → callback | Tunnel public complet (TRAVEL-1002) |
| U-05 | Billets | Émission → PDF (URL signée) → check-in → manifeste | Code de validation jamais en clair au repos |
| U-06 | Annulations | Annulation → remboursement (partiel) → politique | Pénalités serveur, pas de double remboursement |
| U-07 | Caisse PDV | Session → encaissements cash → clôture | Écart = 0 ou expliqué (TRAVEL-810) |
| U-08 | Rapports | Sales/occupancy/revenue/cancellations + export CSV | Agrégats exacts, export idempotent |
| U-09 | Correspondances | Recherche multi-trajets → vente groupée | Compatibilité horaire validée |
| U-10 | Corporate | Devis → acceptation → réservation groupe → plafond | Devis et plafonds respectés |
| U-11 | Fidélité | Opt-in → points → récompense | Points crédités une seule fois par billet |
| U-12 | Kill switch | Désactivation flag `travelagency` en exploitation | 403 explicite, réactivation propre |
| U-13 | Restauration | Restore scratch du tenant pilote (drill) | Preuve datée dans le drill log |

## 3. Rôles de recette

- **Principal** : signe la recette (gate `recette`).
- **RH / Manager** : exécutants des scénarios, tracent les anomalies.
- **Agent PM** : vérifie les critères d'acceptation automatisés (tests
  Feature Travel) et la CI verte avant le GO.
