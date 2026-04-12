# RUNBOOK : SAUVEGARDE ET RESTAURATION (Neon.tech)
# DOCUMENT_VERSION = 1.1.0 | 12 Avril 2026

Ce document décrit la procédure de sauvegarde et de restauration de la base de données PostgreSQL sur Neon.

## 1. Sauvegardes Automatiques (Neon Managed)
Neon effectue des sauvegardes automatiques continues (Point-in-Time Recovery - PITR).
- **Rétention** : 30 jours (Plan Free/Starter).
- **Restauration** : Via la console Neon, rubrique **"Branches"** > **"Create branch from point in time"**.

## 2. Sauvegardes Manuelles (DUMP)
Pour exporter les données localement :
```bash
pg_dump -h ep-odd-morning-abt600ow-pooler.eu-west-2.aws.neon.tech -U neondb_owner -d neondb > backup_leopardo_$(date +%Y%m%d).sql
```

## 3. Restauration Manuelle
Pour restaurer un dump vers Neon :
```bash
psql -h ep-odd-morning-abt600ow-pooler.eu-west-2.aws.neon.tech -U neondb_owner -d neondb -f backup_leopardo.sql
```

---
> [!IMPORTANT]
> En cas d'incident majeur sur Neon, contactez le support ou utilisez la fonction de branchement pour repartir d'un état sain.
