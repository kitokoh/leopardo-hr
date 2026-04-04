# RUNBOOK - BACKUP / RESTORE
# Version 4.1.3 | 04 Avril 2026

## Backup standard
- Commande: `pg_dump -Fc -U leopardo_user leopardo_db > backup.dump`
- Frequence minimale: quotidien + avant chaque migration prod
- Retention: 10 backups minimum

## Verification backup (obligatoire)
1. Verifier presence fichier + taille non nulle
2. Executer un restore test sur base de staging
3. Verifier tables critiques: companies, user_lookups, employees, attendance_logs, payrolls

## Restore procedure
1. Maintenance mode ON
2. `pg_restore -Fc -U leopardo_user -d leopardo_db backup.dump`
3. Verification integrite (counts de base)
4. Maintenance mode OFF

## RPO/RTO cibles
- RPO: <= 24h
- RTO: <= 60 min
