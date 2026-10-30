# #8191 — Revue correction par correction de l'audit `scheduler-duplicates`

> Date : 2026-10-30 · Statut : **dry-run exécuté, en attente de validation humaine avant `--execute`**
> Commande : `php artisan audit:scheduler-duplicates --from=2026-05-12 --to=2026-10-30`

## Ce qui a été exécuté

L'outil a été exécuté de bout en bout en **mode dry-run** (aucune écriture) sur une
instance PostgreSQL 15 locale, schémas `public` + `shared_tenants` migrés depuis
`database/migrations/{public,tenant}`.

Sortie réelle :

```
BOS-006B — audit des doublons du double scheduler | fenêtre [2026-05-12 → 2026-10-30] | mode : DRY-RUN (aucune écriture)

── LEAVE : acquisitions mensuelles en double (leave:accrue daily + monthly)
  Aucun doublon d'acquisition détecté sur la fenêtre.

── BILLING : factures en double (contrôle confirmatoire)
  Aucune facture en double — protection par construction confirmée
  (avancement de période transactionnel + unicité company/subscription/period).

── TRAVEL : événements travel.booking.expired.v1 en double par réservation
  Aucun événement d'expiration en double détecté sur la fenêtre.

Rapport JSON : storage/app/audit-bos006b/report-20261030_004841.json
Dry-run : aucune donnée modifiée.
```

## ⚠️ Limite honnête de ce run — à lire avant de conclure

**Ces compteurs à zéro ne prouvent PAS que la production est saine.** Ils ont été
obtenus sur une base **vide** : les migrations créent le schéma, mais aucune ligne
métier n'y a été insérée.

La raison est concrète : le dump de production vit sur Backblaze B2, et les
secrets `B2_KEY_ID` / `B2_APP_KEY` sont **write-only** côté GitHub Actions
(`GET /actions/secrets/B2_KEY_ID` → 200 mais aucune valeur lisible). Un agent ne
peut donc pas télécharger le dump pour le restaurer.

Ce que ce run **prouve** : l'outillage fonctionne de bout en bout, sans crash,
sur un schéma conforme aux migrations. Ce qu'il **ne prouve pas** : l'absence de
doublons en production.

## Protocole de revue — correction par correction

À exécuter par le propriétaire, sur une **copie restaurée** de la production :

```bash
# 1. Restaurer le dump vérifié du 2026-10-30 dans une base de travail
#    (jamais la prod) :
#    leopardo-20261030-000016.dump.age  (2 499 573 octets, fraîcheur 0 h)
age -d -i "$BACKUP_AGE_IDENTITY" leopardo-20261030-000016.dump.age \
  | pg_restore -d "$SCRATCH_DB" --no-owner --no-privileges

# 2. Audit en lecture seule
php artisan audit:scheduler-duplicates --from=2026-05-12 --to=<date du déploiement réel du correctif BOS-006A>

# 3. Revue du rapport JSON, domaine par domaine
cat storage/app/audit-bos006b/report-*.json
```

### Grille de décision par domaine

| Domaine | Correction automatique | Ce qu'un humain doit valider |
|---|---|---|
| `leave` | Oui, si `--execute` | Conserver la 1re acquisition, supprimer les doublons, décrémenter le solde sous verrou. **Impact paie/RH réel** : valider le nombre de jours avant écriture. |
| `billing` | **Jamais** | Tout groupe trouvé est remonté pour revue manuelle. Une facture en double est un incident comptable (TVA, date de prestation). |
| `travel` | Oui, si `--execute` | Les doublons `pending` passent en `failed` (jamais dispatchés). Les `published` sont **comptés, jamais réécrits** — ils sont déjà partis chez le transporteur. |

### Garde-fous déjà en place

- `--execute` est **obligatoire** pour toute écriture ; le défaut est dry-run.
- `--execute` écrit systématiquement une sauvegarde pré-correction
  (`storage/app/audit-bos006b/<domain>-backup-*.json`).
- Aucune PII dans la sortie ni les logs : identifiants techniques uniquement.

## Ce qui reste bloquant

1. **Accès au dump** : il faut soit restaurer le dump côté propriétaire, soit
   fournir un accès lecture au bucket B2. Sans données réelles, l'audit ne peut
   pas produire de verdict de production.
2. **Validation humaine** : aucune exécution `--execute` ne doit avoir lieu sur
   des données de paie sans revue explicite du rapport.

L'issue reste ouverte jusqu'à ce que (1) soit levé et que le rapport porte sur
des données réelles.
