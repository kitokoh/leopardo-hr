# RUNBOOK — Pilote TravelAgency (tenant pilote, kill switch, backup, restauration, rollback)

> **Issue :** [TRAVEL-050 #5998](https://github.com/kitokoh/leopardo-hr/issues/5998) — maturité DEP-BC24, runbook pilote, recette UAT, pilot gates
> **Exécution :** [TRAVEL-051 #5999](https://github.com/kitokoh/leopardo-hr/issues/5999) — pilote tenant synthétique + recette signée
> **Gates :** [MAT-018 #5876](https://github.com/kitokoh/leopardo-hr/issues/5876) (`pilot-gates.json`, pilote `travelagency`, 9 gates — aucun GO prématuré)
> **Références :** `RUNBOOK_BACKUP_RESTORE.md`, `RUNBOOK_ROLLBACK.md`, `RUNBOOK_INCIDENT_P1.md` (docs/GESTION_PROJET)

## 1. Cadre du pilote

Le pilote TravelAgency démarre **uniquement** quand les 9 gates de
`dev-hub/tools/pilot-gates.json` (pilote `travelagency`) sont `validated` :
manifest (TRAVEL-105/106), core flow (TRAVEL-2xx/3xx), API/Policies (TRAVEL-322),
runbook (TRAVEL-050), sécurité (TRAVEL-1013), performance (MAT-014), observabilité
(MAT-009), golden journey GJ-TRAVEL-01 (TRAVEL-1007), recette signée (TRAVEL-051).
Ce document prépare la phase pilote : périmètre, données, supervision et procédures
d'urgence — à valider pendant la recette.

## 2. Tenant pilote

| Élément | Valeur cible |
|---|---|
| Tenant | `travel-pilot-001` (déterministe, seeder dédié `leopardo:travel:seed-demo`, TRAVEL-107) |
| Pays | DZ (premier marché) — devise DZD |
| Rôles | `principal` (manager pilote), `rh` (supervision), `operator` (agent guichet/agence) |
| Données | Synthétiques uniquement (aucune PII réelle, aucun voyage réel) |
| Feature flag | `travelagency` activé tenant-scope, désactivation coupante (kill switch) |

## 3. Données synthétiques (critère « pilote reproductible »)

- Référentiel : 2 pays, 5 villes, 4 gares, 2 bureaux de vente, 3 compagnies, 2 classes, 4 véhicules ;
- Réseau : 4 routes avec étapes (tri par rang), 3 trajets publiés, sièges générés de façon
  transactionnelle et déterministe (TRAVEL-208) ;
- Ventes : réservations multi-passagers, billets émis, paiements cash/PVIT sandbox, check-ins —
  générés de façon déterministe (seed idempotent, réentrant) ;
- locations (véhicules + images) et hôtels (catalogue + chambres) avec réservations sans chevauchement ;
- aucun secret ni PII dans les fixtures (garde secret-scan/TruffleHog) ;
- benchmark séparé des fixtures fonctionnelles (pattern `CrmBenchmarkSeeder`).

## 4. Kill switch et désactivation

- `feature flags` tenant : désactivation coupante de la solution `travelagency` (opt-in activé
  par le manifest, désactivation immédiate) ;
- après désactivation : routes `/api/v1/travel/*` → 403 explicite, aucune écriture ;
- le kill switch est **testé avant le pilote** (arrêt d'urgence, scénario U-10 de la recette).

## 5. Sauvegarde / restauration (critère « restauration validée »)

Suivre `RUNBOOK_BACKUP_RESTORE.md` (procédure minimale) :
- backup quotidien PostgreSQL (dump chiffré, S3/R2) ;
- **restore mensuel vérifié** sur base scratch isolée (jamais la production) ;
- preuve datée dans `RUNBOOK_DRILLS_LOG.md` (drill **DR-26** planifié — restore schéma tenant
  TravelAgency sur staging avant GO pilote) ;
- RPO < 24 h, RTO < 4 h.

## 6. Incident et rollback (critère « arrêt d'urgence testé »)

Suivre `RUNBOOK_INCIDENT_P1.md` + `RUNBOOK_ROLLBACK.md` :

| Déclencheur | Décision |
|---|---|
| `/api/v1/health` fail > 2 min | Rollback immédiat |
| Écart sièges/réservations/billets inexpliqué (TravelAgency) | Gel des écritures + investigation, pas d'ajustement silencieux |
| Callback paiement PVIT en échec > seuil ou montant incohérent | Suspension des paiements en ligne + investigation (webhook signé HMAC, réconciliation TRAVEL-410) |
| Erreur > 5 % / 5 min après déploiement | Rollback immédiat |

- Rollback code : revert du tag de release pilote (aucune migration destructive — les migrations
  TravelAgency sont additives) ;
- Rollback données : restore scratch puis bascule applicative ;
- **drill d'arrêt d'urgence exécuté avant le go** : preuve datée dans `RUNBOOK_DRILLS_LOG.md`.

## 7. Supervision (observabilité)

- `correlation_id` sur les écritures de ventes/réservations/billets ;
- alertes : lag de file, dead-letters `travel_outbox_events`, échec callback PVIT, écart
  sièges/ventes, échec génération billets PDF ;
- dashboards : ventes, occupation des trajets, recettes, annulations, locations/hôtels.

## 8. Preuve de préparation (phase 1)

- [ ] Tenant pilote défini (tableau §2)
- [ ] Données synthétiques spécifiées (§3)
- [ ] Kill switch documenté + test planifié (§4, scénario U-10)
- [ ] Procédure backup/restore alignée (§5, drill DR-26)
- [ ] Procédure incident/rollback alignée (§6)
- [ ] Supervision définie (§7)

Exécution réelle (restore, kill switch, recette signée) : **gated** par la fusion des
fondations TravelAgency sur `main` (PRs #6127/#6129, #6273, #6340) et le go/no-go MAT-018.
