# Audit rétrospectif BOS-006B — doublons du double scheduler (#8140)

> Date : 2026-09-26 · Statut : outillage livré et testé — **exécution sur copie prod + validation corrections = action owner** · Commande : `audit:scheduler-duplicates`

## Contexte

Entre le **2026-05-12** (coexistence des deux définitions du scheduler —
`bootstrap/app.php` `withSchedule` introduit le 2026-05-10, `leave:accrue`
mensuel ajouté dans `routes/console.php` le 2026-05-12) et le déploiement du
correctif **BOS-006A (#8139, PR #8154 mergée le 2026-09-26)**, 8 commandes
planifiées ont tourné en double en production. Cette procédure mesure et
corrige les dégâts sur les données historiques.

## Fenêtre d'audit

| Borne | Valeur | Source |
|---|---|---|
| `--from` (défaut) | **2026-05-12** | historique git (2e définition du scheduler) |
| `--to` (défaut) | date du jour | à aligner sur la **date de déploiement réel** du correctif en prod (vérifier le deploy Render de `48b9971bce` ou postérieur) |

La prod pouvant tourner en retard sur `main` (cf. #8092/BOS-003), `--to` doit
couvrir la période jusqu'au déploiement effectif du correctif, pas jusqu'au
merge.

## Verdicts attendus par domaine (analyse de code, à confirmer par l'audit)

| Domaine | Risque | Analyse |
|---|---|---|
| `leave` | **RÉEL** | `leave:accrue` n'est pas idempotente sur la période (`LeaveAccrual::create` + `increment` sans garde) : les runs de 00:00 (daily) et 03:00 (monthly) du 1er ont chacun créé l'acquisition → doublons probables les 1er juin/juil./août/sept. 2026 |
| `billing` | aucun dégât attendu | l'avancement de `current_period_end` est dans la même transaction que la création de facture → le run de 03:00 ne sélectionne plus rien ; unicité `(company_id, subscription_id, period)` depuis le 2026-08-31 (#6549). Audit confirmatoire seulement |
| `travel` | limité aux webhooks | les deux expireurs publient `travel.booking.expired.v1` avec des payloads différents → deux clés d'idempotence distinctes → un booking peut avoir 2 événements (→ 2 webhooks transporteurs). Les statuts `pending` sont annulables ; les `published` sont un constat (déjà émis) |

## Exécution (sur une COPIE des données de production)

```bash
# 1. Audit en lecture seule (défaut : dry-run, aucune écriture)
php artisan audit:scheduler-duplicates --from=2026-05-12 --to=<date déploiement correctif>

# 2. Revue du rapport JSON horodaté
#    storage/app/audit-bos006b/report-YYYYMMDD_HHMMSS.json

# 3. Corrections sûres uniquement, après validation owner explicite
php artisan audit:scheduler-duplicates --domain=leave --execute
php artisan audit:scheduler-duplicates --domain=travel --execute
# billing : jamais de correction automatique (revue manuelle si anomalie)
```

Le `--execute` écrit systématiquement une **sauvegarde pré-correction**
(`storage/app/audit-bos006b/<domain>-backup-*.json`) contenant les lignes
avant modification/suppression. Conserver aussi un backup base (snapshot
Neon/Render) daté avant toute passe `--execute` (CA #8140).

## Ce que font (et ne font pas) les corrections

- **leave** : pour chaque groupe d'acquisitions identiques (même société/
  employé/politique/date/libellé), la **première** est conservée ; chaque
  doublon est supprimé et le solde décrémenté **sous verrou**
  (`lockForUpdate`) avec trace `leave_balance_logs` (delta négatif, raison
  BOS-006B, solde après). **Refus sûr** : si le solde courant ne couvre plus
  le retrait (congés consommés depuis), le cas est **flagué revue manuelle**
  — jamais de solde négatif forcé.
- **travel** : seuls les doublons encore `pending` passent en `failed`
  (jamais dispatchés) avec motif explicite ; les doublons déjà `published`
  ne sont **jamais réécrits** (constat dans le rapport pour évaluation
  d'impact webhooks transporteurs).
- **billing** : aucune suppression automatique de documents financiers,
  dans aucun cas.

## Restauration depuis le backup JSON

Chaque backup contient les lignes complètes avant modification :
`accruals_deleted` + `balances_before` (leave), `events_marked_failed`
(travel). Restauration manuelle : réinsérer les accruals supprimés et
ré-appliquer les soldes `balances_before` (par `id`), ou restaurer le
snapshot base pré-correction (procédure standard, RPO ≤ 6 h — #8008).

## Validation owner (CA #8140)

1. Rapport d'audit livré : nombre de doublons par domaine, période, méthode
   (le rapport JSON + cette page).
2. Verdict par domaine : « corrigé » (script + preuve avant/après) ou
   « aucun dégât » documenté.
3. Corrections exécutées **uniquement après validation owner**, avec backup
   préalable daté.
4. Tests métier des domaines touchés verts après correction (suites
   Planning/Leave, Billing, Travel).
