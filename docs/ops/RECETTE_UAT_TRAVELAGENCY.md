# Plan de recette UAT — TravelAgency (TRAVEL-050/051)

> **Issue :** [TRAVEL-050 #5998](https://github.com/kitokoh/leopardo-hr/issues/5998) — plan de recette (phase 1)
> **Exécution :** [TRAVEL-051 #5999](https://github.com/kitokoh/leopardo-hr/issues/5999) — recette signée sur tenant pilote
> **Gates :** [MAT-018 #5876](https://github.com/kitokoh/leopardo-hr/issues/5876) — la recette signée (gate `recette`) conditionne le GO pilote `travelagency`.

## 1. Cadre

La recette métier UAT couvre les parcours : **référentiel, réseau & trajets, réservations
guichet, billetterie, vente en ligne, paiements, locations & hôtels, rapports, permissions,
kill switch & restauration**.
Chaque scénario doit être **signé par le métier** avec : date, exécutant, résultat (pass/fail),
évidence (log/lien), anomalies ouvertes. **Zéro anomalie bloquante** avant release.

## 2. Scénarios de recette

| # | Parcours | Scénario | Critère de succès |
|---|---|---|---|
| U-01 | Référentiel | CRUD pays/villes/gares/bureaux/compagnies/classes/véhicules + lecture tenant | CRUD tracé, 404 cross-tenant, Policies appliquées |
| U-02 | Routes & trajets | Route avec étapes (tri par rang) → trajet → génération transactionnelle des sièges → tarifs par classe | Sièges cohérents avec capacité, tarifs par classe |
| U-03 | Publication | `publish`/`cancel` d'un trajet + événements ; recherche interne | Seuls les trajets publiés sont cherchables ; cancel libère les sièges |
| U-04 | Réservation guichet | Réservation multi-passagers (guichet) → confirm comptant → cancel avec motif → refund | Stock sièges verrouillé/restitué, transitions d'état tracées, idempotence |
| U-05 | Billetterie | `issue-ticket` → `check-in` → manifeste du trajet | Billet émis une seule fois, check-in unique, manifest exact |
| U-06 | Vente en ligne (shop) | Recherche publique → réservation online (source `online`, expiration 15 min) → suivi par référence | Disponibilité dérivée de l'inventaire, jamais de code de validation en clair |
| U-07 | Paiements | `initiate` → callback signé HMAC idempotent → `verify` → `refund` | Montant vérifié, callback rejoué sans double effet, payload redacté |
| U-08 | Locations & hôtels | Véhicules + images → réservation sans chevauchement → hôtels/chambres | Chevauchement refusé, cycle de location tracé |
| U-09 | Rapports | Ventes, occupation, recettes, annulations + export CSV | Totaux cohérents, export signé/URL limitée, permissions `travel.reports` |
| U-10 | Kill switch & restauration | Désactivation flag `travelagency` en exploitation → restore scratch (drill DR-26) | 403 explicite, aucune écriture, réactivation propre ; preuve datée dans `RUNBOOK_DRILLS_LOG.md` |

## 3. Rôles de recette

- **Métier** (signataire) : responsable exploitation pilote agence de voyage ;
- **PM/QA** : exécution, évidence, suivi des anomalies ;
- **Support** : fenêtre planifiée, escalade P1 (RUNBOOK_INCIDENT_P1.md).

## 4. Sortie de recette

- PV de recette signé (par scénario) ;
- liste des anomalies bloquantes (doit être vide) ;
- release notes + formation agents guichet/manager livrées ;
- gate `recette` passé à `validated` dans `pilot-gates.json` (décision du chef de projet, jamais de l'agent) ;
- bascule du registre BC-24 en `status: active`.

## 5. Prérequis

- Fondations TravelAgency mergées sur `main` (PRs #6127/#6129, #6273, #6340) ;
- runbook pilote (`RUNBOOK_PILOT_TRAVELAGENCY.md`) appliqué ;
- kill switch et restauration testés avant la recette (U-10).
