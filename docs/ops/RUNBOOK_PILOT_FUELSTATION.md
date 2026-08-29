# RUNBOOK — Pilote FuelStation (tenant pilote, kill switch, backup, restauration, rollback)

> **Issue :** [FUEL-021 #5815](https://github.com/kitokoh/leopardo-hr/issues/5815) — phase 1 : préparation pilote
> **Gates :** [MAT-018 #5876](https://github.com/kitokoh/leopardo-hr/issues/5876) (`pilot-gates.json`, 9 gates — aucun GO prématuré)
> **Références :** `RUNBOOK_BACKUP_RESTORE.md`, `RUNBOOK_ROLLBACK.md`, `RUNBOOK_INCIDENT_P1.md` (docs/GESTION_PROJET)

## 1. Cadre du pilote

Le pilote FuelStation démarre **uniquement** quand les 9 gates de
`dev-hub/tools/pilot-gates.json` sont `validated` (manifest FUEL-001, core flow
FUEL-002..009, API/Policies FUEL-011, runbook FUEL-021, sécurité MAT-017,
performance MAT-014, observabilité MAT-009, golden journey GJ-06, recette
signée FUEL-022). Ce document prépare la phase pilote : périmètre, données,
supervision et procédures d'urgence — à valider pendant la recette.

## 2. Tenant pilote

| Élément | Valeur cible |
|---|---|
| Tenant | `fuel-pilot-001` (déterministe, seeder dédié) |
| Pays | DZ (premier marché) — devise DZD |
| Rôles | `principal` (manager pilote), `rh` (supervision), `operator` (pompiste) |
| Données | Synthétiques uniquement (aucune PII réelle, aucune station réelle) |
| Feature flag | `fuel` activé tenant-scope, désactivation coupante (kill switch) |

## 3. Données synthétiques (critère « pilote reproductible »)

- 1 site, 2 stations, 4 pompes, compteurs initialisés ;
- relevés et ventes générés de façon déterministe (seed idempotent, réentrant) ;
- aucun secret ni PII dans les fixtures (garde secret-scan/TruffleHog) ;
- benchmark séparé des fixtures fonctionnelles (pattern `CrmBenchmarkSeeder`).

## 4. Kill switch et désactivation

- `feature flags` tenant : désactivation coupante de la solution `fuel` (opt-in
  activé par le manifest, désactivation immédiate) ;
- après désactivation : routes `/api/v1/fuel/*` → 403 explicite, aucune écriture ;
- le kill switch est **testé avant le pilote** (arrêt d'urgence).

## 5. Sauvegarde / restauration (critère « restauration validée »)

Suivre `RUNBOOK_BACKUP_RESTORE.md` (procédure minimale) :
- backup quotidien PostgreSQL (dump chiffré, S3/R2) ;
- **restore mensuel vérifié** sur base scratch isolée (jamais la production) ;
- preuve datée dans `RUNBOOK_DRILLS_LOG.md` (format : date, type restore,
  environnement staging, résultat, durée, évidence) ;
- RPO < 24 h, RTO < 4 h.

## 6. Incident et rollback (critère « arrêt d'urgence testé »)

Suivre `RUNBOOK_INCIDENT_P1.md` + `RUNBOOK_ROLLBACK.md` :

| Déclencheur | Décision |
|---|---|
| `/api/v1/health` fail > 2 min | Rollback immédiat |
| Écart compteur/ventes/stock inexpliqué (FUEL-009) | Gel des écritures + investigation, pas d'ajustement silencieux |
| Erreur > 5 % / 5 min après déploiement | Rollback immédiat |

- Rollback code : revert du tag de release pilote (aucune migration destructive —
  les migrations FuelStation sont additives) ;
- Rollback données : restore scratch puis bascule applicative ;
- **drill d'arrêt d'urgence exécuté avant le go** : preuve datée dans
  `RUNBOOK_DRILLS_LOG.md`.

## 7. Supervision (observabilité)

- `correlation_id` sur les écritures de ventes/relevés ;
- alertes : lag de file, échec OCR, écart de stock, dead-letters ;
- dashboards : ventes par pompe, sessions de caisse, rapprochement.

## 8. Preuve de préparation (phase 1)

- [ ] Tenant pilote défini (tableau §2)
- [ ] Données synthétiques spécifiées (§3)
- [ ] Kill switch documenté + test planifié (§4)
- [ ] Procédure backup/restore alignée (§5)
- [ ] Procédure incident/rollback alignée (§6)
- [ ] Supervision définie (§7)

Exécution réelle (restore, kill switch, recette signée) : **gated** par la
fusion des fondations FUEL-001..009 et le go/no-go MAT-018.
