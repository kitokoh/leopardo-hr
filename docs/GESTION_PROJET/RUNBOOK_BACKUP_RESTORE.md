# RUNBOOK - BACKUP / RESTORE

Version 4.1.67 | 2026-04-22

## 1. Perimetre

Sauvegarde et verification de restauration de la base PostgreSQL Leopardo RH
(Neon en production, Postgres 16 auto-herberge en pre-prod).

Le "drill" (exercice de reprise) se fait sur une base **scratch isolee**,
jamais contre la production.

Cible :
- **Pre-prod** : drill hebdomadaire automatise (CI cron)
- **Production** : drill mensuel manuel, trace dans `RUNBOOK_DRILLS_LOG.md`

## 2. Secrets requis

| Nom | Usage | Source recommandee |
|---|---|---|
| `DATABASE_URL` | URL Postgres source a dumper | Render / Neon dashboard |
| `RESTORE_DB_URL` | URL Postgres scratch ou on restaure | Neon branch dediee `leopardo-drill` |
| `BACKUP_AGE_RECIPIENT` | cle publique `age` pour chiffrer le dump (optionnel) | generee via `age-keygen` |
| `BACKUP_AGE_IDENTITY_FILE` | cle privee `age` (pour restaurer un dump chiffre) | secret GitHub / 1Password |
| `BACKUP_DIR` | dossier local ou on ecrit le dump | `/var/lib/leopardo/backups` en prod |

## 3. Drill automatise

Un script prêt a l'emploi est livre : `scripts/backup_drill.sh`.

```bash
DATABASE_URL="postgres://user:pwd@source/leopardo_db" \
RESTORE_DB_URL="postgres://user:pwd@scratch/leopardo_drill" \
BACKUP_DIR="/tmp/leopardo-drills" \
BACKUP_AGE_RECIPIENT="age1..." \
./scripts/backup_drill.sh
```

Le script :
1. `pg_dump` au format custom (compression inclue, pas de proprietaire/droits)
2. Chiffre le dump avec `age` si `BACKUP_AGE_RECIPIENT` est fournie
3. Purge puis restaure dans `RESTORE_DB_URL`
4. Compare les count des tables critiques (source vs cible) et echoue si delta
5. Nettoie la base scratch pour ne garder aucune donnee sensible

Sortie :
- Dump : `${BACKUP_DIR}/leopardo-YYYYmmdd-HHMMSS.dump[.age]`
- Log : `${BACKUP_DIR}/last-drill.log`
- Exit 0 si OK, != 0 si mismatch

## 4. Tables verifiees

Le drill compte les lignes des tables suivantes (source vs restore) :

| Schema | Table | Pourquoi |
|---|---|---|
| `public` | `companies` | catalogue des tenants |
| `public` | `plans` | abonnements |
| `public` | `super_admins` | comptes plateforme |
| `shared_tenants` | `employees` | donnees metier principales |
| `shared_tenants` | `attendance_logs` | pointages (append-only) |
| `shared_tenants` | `user_invitations` | onboarding |

Un mismatch sur l'une de ces tables = echec du drill.

## 5. Procedure manuelle (fallback)

```bash
# 1. Dump source (prod)
pg_dump --format=custom --no-owner --no-privileges \
  --file=leopardo-$(date -u +%Y%m%d-%H%M%S).dump \
  "$DATABASE_URL"

# 2. Chiffrement (recommande)
age --recipient "$BACKUP_AGE_RECIPIENT" \
  --output leopardo-$(date -u +%Y%m%d).dump.age \
  leopardo-$(date -u +%Y%m%d).dump
rm leopardo-$(date -u +%Y%m%d).dump

# 3. Transfert vers stockage froid (R2/S3 avec chiffrement SSE-KMS)
aws s3 cp leopardo-*.dump.age \
  s3://leopardo-backups/$(date -u +%Y/%m)/ \
  --storage-class DEEP_ARCHIVE

# 4. Restauration (uniquement en cas de besoin)
age --decrypt --identity $BACKUP_AGE_IDENTITY_FILE \
  --output leopardo.dump leopardo-*.dump.age
pg_restore --no-owner --no-privileges \
  --dbname="$RESTORE_DB_URL" leopardo.dump
```

## 6. Retention

- **Dumps quotidiens chiffres** : 30 jours (R2/S3 Standard)
- **Dumps mensuels** : 13 mois (R2/S3 Glacier Deep Archive)
- **Dumps annuels** : 5 ans (R2/S3 Glacier Deep Archive)

## 7. En cas d'echec

- Si `pg_dump` echoue -> incident P1, escalade DBA
- Si le drill detecte un mismatch -> incident P2, analyser `last-drill.log`
- Si la base scratch devient inaccessible -> basculer sur une Neon branch fresh
- Trace obligatoire dans `RUNBOOK_DRILLS_LOG.md` apres chaque drill

## 8. References

- Script : `scripts/backup_drill.sh`
- Rollback DB : `RUNBOOK_ROLLBACK.md` (option B)
- Incident P1 : `RUNBOOK_INCIDENT_P1.md`
- Trace des drills : `RUNBOOK_DRILLS_LOG.md`
