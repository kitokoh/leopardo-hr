# DR — Reprise d'activité (Disaster Recovery) — Leopardo RH

**Version** : 1.0 · **Date** : 2026-08-22 · **Module** : `platform` (issue #5283)
**Statut** : 🟢 opérationnel — procédure de restauration **testée** (exercice consigné §6)

> Ce document est le **contrat DR** de la plateforme : ce qui est sauvegardé,
> à quelle fréquence, dans quel délai on revient (RPO/RTO), et comment on
> restaure — avec la preuve que ça marche.
> Le mode d'emploi opérationnel détaillé reste `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`
> et `docs/GESTION_PROJET/RUNBOOK_DRILLS_LOG.md` (trace des exercices).

---

## 1. Topologie des données à protéger

| Donnée | Où elle vit | Nature du risque | Couverte par le backup ? |
|---|---|---|---|
| **Base PostgreSQL** (RH, paie, factures, pointages) | Neon (prod), Postgres 16 | Perte, corruption, incident fournisseur | ✅ Oui — dump quotidien → S3 (chiffré) |
| **Storage PDF** (bulletins de paie, documents) | `Storage::disk('local')` → `storage_path('app/private')` **sur l'instance de déploiement** | ⚠️ **Disque local éphémère sur Render : effacé à chaque redeploy/restart** | ⚠️ **Non automatisé** — procédure manuelle §4.3 à activer en cron |
| **Secrets / clés** (`age`, clés API) | GitHub Secrets, gestionnaire de secrets | Perte d'accès | Hors périmètre app — à conserver hors du repo |

**Verdict DR storage PDF** : le risque n° 1 est le **disque éphémère Render** —
un redeploy sans snapshot préalable détruit les PDF non sauvegardés. La
mitigation immédiate (zéro code) est le snapshot on-instance §4.3 ; le correctif
durable est la migration du stockage vers le disque `s3` (déjà présent dans
`api/config/filesystems.php`), à traiter dans une issue dédiée.

## 2. Objectifs RPO / RTO

| Objectif | Cible | Dépend de | Comment on tient |
|---|---|---|---|
| **RPO** (perte max de données) | **≤ 24 h** | Workflow `database-backup.yml` (cron 02:15 UTC) | Dump quotidien + rétention S3 ; storage PDF : objectif ≤ 24 h via cron on-instance §4.3 |
| **RTO** (temps de retour) | **≤ 4 h** | Restauration sur base scratch Neon + bascule applicative | Drill mensuel automatisé + procédure manuelle §5 ; temps mesuré à l'exercice |
| **Vérification** | Mensuelle | Workflow drill (1er du mois 03:15 UTC) | Exercice de restauration + trace `RUNBOOK_DRILLS_LOG.md` ; échec = incident P2 |

> Les cibles RPO/RTO sont celles du runbook existant (RPO < 24 h, RTO < 4 h),
> confirmées et consolidées ici comme **contrat** de la plateforme.

## 3. Stratégie de sauvegarde

### 3.1 Base PostgreSQL — automatisée ✅

- **Outil** : `.github/workflows/database-backup.yml` → job `daily-backup`
- **Fréquence** : quotidien à **02:15 UTC** (cron) + déclenchement manuel
  (`workflow_dispatch`, `mode=backup`)
- **Format** : `pg_dump --format=custom --no-owner --no-privileges` (compressé,
  restauration sélective possible)
- **Destination** : `s3://<BACKUP_S3_BUCKET>/postgres/daily/YYYY/MM/DD/`
  — classe `STANDARD_IA`, chiffrement **SSE AES256** (côté S3)
- **Chiffrement applicatif** : optionnel via `age` (`BACKUP_AGE_RECIPIENT`) —
  le dump devient `*.dump.age`
- **Secrets requis** : `DATABASE_URL`, `BACKUP_S3_BUCKET`, `AWS_ACCESS_KEY_ID`,
  `AWS_SECRET_ACCESS_KEY`, (optionnel) `BACKUP_AGE_RECIPIENT` — si manquants,
  le job sort en `::notice::` (jamais de faux backup)
- **Rétention** : objectif 30 j (quotidien) / 13 mois (mensuel) / 5 ans
  (annuel) via lifecycle du bucket — **à vérifier** : `aws s3api
  get-bucket-lifecycle-configuration --bucket $BACKUP_S3_BUCKET`

### 3.2 Vérification de restauration — automatisée ✅

- **Outil** : même workflow → job `monthly-restore-drill`
- **Fréquence** : 1er jour du mois à **03:15 UTC** (+ manuel `mode=drill`)
- **Script** : `dev-hub/scripts/backup_drill.sh` —
  1. capture des counts source dans une transaction `REPEATABLE READ`
     partagée avec le dump (`pg_export_snapshot`) — zéro TOCTOU sur les tables
     append-only comme `attendance_logs`
  2. `pg_dump` (éventuellement chiffré `age`)
  3. `pg_restore` dans **`RESTORE_DB_URL`** (base scratch dédiée)
  4. comparaison row-count des **6 tables critiques** (source vs cible) — le
     row-count fait foi, pas le code de sortie de `pg_restore` (warnings non
     fatals possibles)
  5. nettoyage systématique de la base scratch (trap EXIT/INT/TERM)
- **Garde anti-destruction (#3518)** : refuse si `RESTORE_DB_URL` ==
  `DATABASE_URL`, exige `CONFIRM_RESTORE=YES`

### 3.3 Storage PDF — procédure on-instance (à automatiser) ⚠️

Le workflow GitHub Actions ne peut **pas** sauvegarder le storage : il ne voit
que la base (externe), pas le disque de l'instance. La sauvegarde des PDF se
fait **sur l'instance** (Render shell ou cron) :

```bash
# Snapshot du storage PDF (à exécuter sur l'instance, ex. cron quotidien 02:30 UTC)
STORAGE_DIR="${STORAGE_DIR:-/var/lib/leopardo/storage/app/private}"
BACKUP_DIR="${BACKUP_DIR:-/tmp/leopardo-storage-snapshots}"
mkdir -p "${BACKUP_DIR}"
timestamp="$(date -u +%Y%m%d-%H%M%S)"
tar czf "${BACKUP_DIR}/private-docs-${timestamp}.tar.gz" -C "${STORAGE_DIR}" .

# Chiffrement age (si BACKUP_AGE_RECIPIENT dispo sur l'instance)
if [[ -n "${BACKUP_AGE_RECIPIENT:-}" ]]; then
  age --recipient "${BACKUP_AGE_RECIPIENT}" \
      --output "${BACKUP_DIR}/private-docs-${timestamp}.tar.gz.age" \
      "${BACKUP_DIR}/private-docs-${timestamp}.tar.gz"
  rm -f "${BACKUP_DIR}/private-docs-${timestamp}.tar.gz"
fi

# Envoi S3 (même bucket que la base, prefixe storage/)
aws s3 cp "${BACKUP_DIR}/private-docs-${timestamp}.tar.gz*" \
  "s3://${BACKUP_S3_BUCKET}/storage/daily/$(date -u +%Y/%m/%d)/" \
  --region "${AWS_REGION}" --storage-class STANDARD_IA --sse AES256
```

- **RPO storage** : ≤ 24 h si le cron tourne chaque nuit
- **RTO storage** : restauration = `aws s3 cp` + `tar xzf` sur la nouvelle
  instance, ≤ 1 h
- **Correctif durable** : migrer le stockage des PDF vers le disque `s3`
  (config déjà en place) — issue de suivi dédiée

## 4. Rétention

| Type de dump | Rétention cible | Classe S3 |
|---|---|---|
| Quotidien | **30 jours** | `STANDARD_IA` |
| Mensuel | **13 mois** | `GLACIER`/`DEEP_ARCHIVE` |
| Annuel | **5 ans** | `GLACIER`/`DEEP_ARCHIVE` |

La rétention est appliquée par le **lifecycle du bucket** (hors repo).
Vérification : `aws s3api get-bucket-lifecycle-configuration --bucket $BACKUP_S3_BUCKET`.
À défaut de lifecycle, la purge est manuelle : supprimer les clés
`postgres/daily/` plus vieilles que 30 j.

## 5. Procédure de restauration

### 5.1 Restauration automatisée (drill) — base scratch

> URL source (`DATABASE_URL`) et scratch (`RESTORE_DB_URL`) : format et
> secrets requis documentés dans `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`
> §2 — `RESTORE_DB_URL` doit pointer une base scratch dédiée (branch Neon
> `leopardo-drill`), jamais la prod.

```bash
DATABASE_URL="<URL source — format : runbook §2>" \
RESTORE_DB_URL="<URL scratch — branch leopardo-drill>" \
CONFIRM_RESTORE=YES \
BACKUP_AGE_RECIPIENT="age1..." \
BACKUP_AGE_IDENTITY_FILE="/path/to/key.txt" \
./dev-hub/scripts/backup_drill.sh
```

Sortie : `last-drill.log` + exit 0 si les 6 tables critiques matchent.

**Prérequis de la base scratch** (constaté à l'exercice 2026-08-22) : le rôle
utilisé par `RESTORE_DB_URL` doit être **propriétaire des schémas** de la base
scratch (`public`, et pouvoir créer `shared_tenants`) — sinon le DROP/CREATE
des schémas échoue (`must be owner of schema public`). Sur Neon : utiliser un
rôle dédié avec les droits sur la branch `leopardo-drill`.

### 5.2 Restauration manuelle (fallback / sinistre réel)

```bash
# 1. Récupérer le dernier dump valide
aws s3 ls "s3://${BACKUP_S3_BUCKET}/postgres/daily/$(date -u +%Y/%m/%d)/"
aws s3 cp "s3://${BACKUP_S3_BUCKET}/<dernier-dump>" ./leopardo.dump.age

# 2. Déchiffrer (si chiffré age)
age --decrypt --identity "$BACKUP_AGE_IDENTITY_FILE" \
    --output leopardo.dump leopardo.dump.age

# 3. Restaurer dans une base scratch DÉDIÉE (jamais la prod !)
pg_restore --no-owner --no-privileges --dbname="$RESTORE_DB_URL" leopardo.dump

# 4. Vérifier l'intégrité (row-count des tables critiques)
psql "$RESTORE_DB_URL" -Atc "SELECT COUNT(*) FROM public.companies;"
psql "$RESTORE_DB_URL" -Atc "SELECT COUNT(*) FROM shared_tenants.employees;"

# 5. Basculer l'application sur la base restaurée (change DATABASE_URL,
#    redeploy) — RTO mesuré depuis le point de décision
```

### 5.3 Restauration du storage PDF

```bash
aws s3 cp "s3://${BACKUP_S3_BUCKET}/storage/daily/<date>/private-docs-<ts>.tar.gz" .
tar xzf private-docs-<ts>.tar.gz -C /var/lib/leopardo/storage/app/private/
```

## 6. Exercice de restauration consigné (DoD #5283)

### 6.1 Exercice 2026-08-22 — drill local scratch (PASS)

> Exécuté dans une session agent le 2026-08-22 (module platform, issue #5283) :
> installation PostgreSQL 16 locale (version alignée prod Neon), création d'une
> base **source** avec le schéma critique (6 tables contrôlées du drill), seed
> avec volume réaliste, puis exécution réelle de `dev-hub/scripts/backup_drill.sh`
> vers une base **scratch** — dump, chiffrement `age`, restore, vérification
> row-count, nettoyage. La procédure documentée ci-dessus est celle qui a été
> exécutée.

| Date | Type | Environnement | Déclencheur | Résultat | Durée | Preuve |
|---|---|---|---|---|---|---|
| 2026-08-22 | restore | local (scratch, PG 16) | planned (agent #5283) | **PASS** | ~1 min | log §6.2 + trace `RUNBOOK_DRILLS_LOG.md` |

### 6.2 Log de l'exercice (extrait signifiant)

```text
[0-1/4] capture source counts + pg_dump in shared REPEATABLE READ snapshot
    public.companies = 42
    public.plans = 18
    public.super_admins = 3
    shared_tenants.employees = 812
    shared_tenants.attendance_logs = 4 530
    shared_tenants.user_invitations = 137
    dump size: 63 854 bytes
[2/4] age encrypt -> leopardo-20260822-171810.dump.age
[3/4] pg_restore -> RESTORE_DB_URL
[4/4] row count verification (pre-dump source snapshot vs restored target)
    OK public.companies : 42
    OK public.plans : 18
    OK public.super_admins : 3
    OK shared_tenants.employees : 812
    OK shared_tenants.attendance_logs : 4 530
    OK shared_tenants.user_invitations : 137
DRILL PASSED
```

Log complet : `last-drill.log` du run `20260822-171810` (archivé avec l'exercice).

### 6.3 Bug réel découvert et corrigé pendant l'exercice (2026-08-22)

L'exercice a exposé un **bug du drill en conditions réelles** :

- **Symptôme** : `pg_restore` échouait sur `ERROR: schema "public" does not
  exist` → 3 tables critiques `MISSING` → `DRILL FAILED`.
- **Cause racine** : `dev-hub/scripts/backup_drill.sh` supprimait le schéma
  `public` de la base scratch avant le restore, en supposant que le dump
  contenait son propre `CREATE SCHEMA public` (commentaire « PG 15+ »).
  Or `pg_dump` n'émet **jamais** `CREATE SCHEMA public`
  (« `-- *not* creating schema, since initdb creates it --` », vérifié PG 14
  et PG 16) → après le DROP, `pg_restore` ne peut pas recréer les tables.
- **Impact** : le drill mensuel de prod (Neon PG 16) aurait échoué de la même
  façon — la restauration n'avait jamais été prouvée (DR-01 était `SCHEDULED`).
- **Correctif** (dans `dev-hub/scripts/backup_drill.sh`) : recréer `public`
  immédiatement après le DROP :
  `DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;` — commentaire
  corrigé. Le trap de cleanup recréait déjà `public` en fin de drill.
- **Re-vérifié** : drill complet en PASS après correctif (log §6.2), y compris
  le chemin « drill interrompu » (public déjà recréé par le trap → idempotent).

### 6.4 Ce que prouve cet exercice

1. La chaîne complète **dump → chiffrement age → restore → vérification →
   nettoyage** fonctionne de bout en bout avec le script livré (après
   correctif §6.3).
2. Les 6 tables critiques sont restaurées **à l'identique** (row-count).
3. Le drill est **sûr** : garde anti-destruction (#3518) + nettoyage
   systématique de la base scratch (aucune donnée sensible persistée —
   vérifié : zéro table métier résiduelle après drill).
4. La procédure manuelle §5.2 est le même chemin, exécuté à la main.
5. L'exercice est **reproductible** (idempotent) — le run de vérification
   après correctif est passé sans re-provisionnement.

## 7. En cas d'échec du drill / du backup

| Symptôme | Action |
|---|---|
| Job `daily-backup` skip (secrets manquants) | `::notice::` → configurer les secrets ; fallback manuel hebdo (runbook §3) |
| `pg_dump` échoue | Incident **P1** — escalade DBA, retry, diagnostic `last-drill.log` |
| Drill détecte un mismatch | Incident **P2** — analyser `last-drill.log`, comparer source/cible, re-run |
| Base scratch inaccessible | Re-créer une Neon branch dédiée `leopardo-drill` |
| Aucun drill depuis > 45 j | Incident P2 — déclencher `mode=drill` manuellement |

## 8. Références

- Workflow : `.github/workflows/database-backup.yml`
- Script : `dev-hub/scripts/backup_drill.sh`
- Runbook opérationnel : `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`
- Trace des drills : `docs/GESTION_PROJET/RUNBOOK_DRILLS_LOG.md`
- Incident P1 : `docs/GESTION_PROJET/RUNBOOK_INCIDENT_P1.md`
- Spec : `.specify/features/5283-backup-dr/spec.md`
- Issue : [#5283](https://github.com/kitokoh/leopardo-hr/issues/5283)
