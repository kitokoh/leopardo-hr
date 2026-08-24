# Feature Specification: Backup/restore + DR — tests de restauration documentés (issue #5283)

**Feature Branch**: `mod/platform/5283-backup-dr`

**Created**: 2026-08-22

**Status**: Draft → Implemented

**Module**: `platform` (cross-cutting) — périmètre touché : `docs/ops/**`, `.specify/features/5283-backup-dr/`, `CHANGELOG.md`. Aucune collision avec les autres waves (W1 Payroll DZ, W2 MA/HR/Accounting) : aucun fichier de module métier modifié.

## Contexte

Les données (RH + paie + factures) sont vitales : la sauvegarde automatisée existe
(`.github/workflows/database-backup.yml` — dump quotidien 02:15 UTC + drill mensuel
1er du mois 03:15 UTC, `dev-hub/scripts/backup_drill.sh`), mais **aucun exercice de
restauration réussi n'est consigné** (DoD de l'issue), et le **stockage PDF n'est
pas couvert** par la sauvegarde.

État vérifié le 2026-08-22 :

| Brique | Existant | Gap |
|---|---|---|
| Base Postgres (prod Neon) | Backup quotidien custom dump → S3 STANDARD_IA + SSE AES256 (+ chiffrement `age` optionnel) ; drill mensuel avec vérif row-count sur base scratch | **Aucun exercice réussi consigné** (`RUNBOOK_DRILLS_LOG.md` DR-01 = SCHEDULED) ; rétention dépend du bucket (non documentée dans le repo) |
| Storage PDF (bulletins, documents) | `Storage::disk('local')` (`storage_path('app/private')`) — **disque local, éphémère sur Render** (wiped au redeploy) ; aucun backup | **Risque DR majeur non documenté** ; aucune procédure de snapshot du storage |
| Objectifs RPO/RTO | Mentionnés dans `RUNBOOK_BACKUP_RESTORE.md` (RPO < 24 h, RTO < 4 h) | Pas de doc `docs/ops/DR.md` consolidée |

## Décisions

1. **`docs/ops/DR.md` = document DR de référence** (comme `INCIDENTS.md` pour les
   incidents) : topologie des données, objectifs RPO/RTO, stratégie de backup
   (base + storage PDF), procédure de restauration (workflow drill + fallback
   manuel), tables vérifiées, rétention, escalade. Référence croisée avec
   `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md` (runbook opérationnel) — DR.md
   est le contrat, le runbook est le mode d'emploi.
2. **RPO/RTO cibles** : RPO ≤ 24 h (dump quotidien 02:15 UTC) ; RTO ≤ 4 h
   (restore sur base scratch Neon + bascule applicative). Storage PDF :
   RPO ≤ 24 h **objectif** — l'activation d'un cron on-instance (procédure
   documentée dans DR.md) est la mitigation immédiate du disque éphémère.
3. **Storage PDF** : pas de modification de code applicatif dans cette issue
   (scope `platform`, pas de module métier). La procédure de snapshot
   on-instance (`tar` + `age` + `aws s3 cp`) est documentée dans DR.md avec le
   risque « disque éphémère Render » explicite et le correctif durable
   (migration vers le disque `s3` — `config/filesystems.php` dispose déjà du
   disque) tracé comme suite. Le workflow GitHub Actions ne peut PAS sauvegarder
   le storage : il ne voit que la base (externe), pas le disque de l'instance.
4. **Preuve du drill** : un exercice de restauration RÉEL est exécuté dans cette
   session (instance scratch locale, clone du schéma critique) via
   `dev-hub/scripts/backup_drill.sh` ; le log complet est consigné dans
   `docs/ops/DR.md` (§ Exercice consigné) et la trace dans
   `docs/GESTION_PROJET/RUNBOOK_DRILLS_LOG.md` (ligne DR-01 → pass).

## User Scenarios & Testing

### User Story 1 — Un opérateur retrouve la prod après sinistre (Priority: P1)

**Independent Test**: exercice documenté — `dev-hub/scripts/backup_drill.sh`
sur base scratch, exit 0, row-count identique sur les 6 tables critiques.

**Acceptance Scenarios**:

1. **Given** un dump quotidien existe sur S3, **When** la prod est perdue,
   **Then** la procédure de restauration de `docs/ops/DR.md` est exécutable en
   < 4 h (RTO) avec une perte maximale de 24 h (RPO).
2. **Given** le workflow drill mensuel, **When** il tourne, **Then** le résultat
   est consigné (log + trace `RUNBOOK_DRILLS_LOG.md`) — un échec est remonté en
   incident P2.
3. **Given** un opérateur doit restaurer à la main (fallback), **When** il suit
   la procédure manuelle de DR.md, **Then** les commandes `pg_restore` +
   vérification row-count sont exactes et sûres (base scratch, jamais la prod).

### User Story 2 — Le storage PDF est couvert (Priority: P1)

1. **Given** le storage PDF vit sur le disque local de l'instance Render
   (éphémère), **When** un snapshot est pris, **Then** la procédure documentée
   (`tar` + `age` + S3) est exécutable sur l'instance et le restore vérifié.
2. **Given** la mitigation immédiate est un cron on-instance, **When** il est
   activé, **Then** le RPO storage ≤ 24 h est atteignable sans code applicatif.

## Edge Cases

- **Ne jamais restaurer vers la prod** : le drill refuse (garde #3518) si
  `RESTORE_DB_URL` == `DATABASE_URL` et exige `CONFIRM_RESTORE=YES` — documenté.
- **Warnings `pg_restore` non fatals** : le row-count (6 tables critiques) est
  l'autorité finale, pas le code de sortie de pg_restore.
- **Rétention** : 30 j (quotidien) / 13 mois (mensuel) / 5 ans (annuel) — dépend
  du lifecycle du bucket ; DR.md documente l'objectif et la vérification.
- **Disque éphémère Render** : un redeploy détruit les PDF non sauvegardés —
  c'est le risque n° 1 du DR ; la procédure on-instance + la migration S3 sont
  les deux niveaux de réponse.

## Deliverables

- [x] Spec `.specify/features/5283-backup-dr/spec.md`
- [x] `docs/ops/DR.md` — topologie, RPO/RTO, backup base + storage PDF,
      procédure de restauration, exercice consigné
- [x] Exercice de restauration réel exécuté (drill local scratch, PG 16
      aligné prod Neon) + log dans `docs/ops/DR.md` et trace dans
      `RUNBOOK_DRILLS_LOG.md`
- [x] **Fix bug drill** (`dev-hub/scripts/backup_drill.sh`) : `pg_dump`
      n'émet jamais `CREATE SCHEMA public` (toutes versions) — le script
      supprimait `public` sans le recréer → `pg_restore` échouait
      (« schema public does not exist »). Correctif : `DROP ...; CREATE
      SCHEMA public;` + commentaire corrigé. Découvert et prouvé par
      l'exercice §6.3 de `docs/ops/DR.md`.
- [x] Entrée CHANGELOG `[Unreleased]` + PR avec `Closes #5283`
