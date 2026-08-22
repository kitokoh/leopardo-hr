# Feature Specification: Rapports de pointage — périodes, filtres, exports (issue #5268)

**Feature Branch**: `mod/attendance/5268-reports`

**Created**: 2026-08-22

**Status**: Draft → Implemented

**Input**: Issue #5268 [P2] « Rapports de pointage — présence, retards, heures, export CSV/PDF ». Le rapport mensuel existe déjà (`AttendanceMonthlyReportService` + `GET /attendance/monthly-report`, testé) ; il manque les périodes journalière et hebdomadaire, les filtres équipe/employé et l'export PDF/CSV par période.

## Problème

- Le rapport de pointage n'existe qu'en **mensuel** : pas de rapport journalier (fiche du jour) ni hebdomadaire.
- Pas de filtre par **équipe (département)** ni par **employé** (fiche de pointage individuelle).
- Les exports CSV/PDF sont liés au mois dans le nom de fichier (`attendance-monthly-report-<month>.csv`), pas génériques à la période.
- Le schéma OpenAPI de la route ne documente ni `period` ni `format` (ni les vrais types : `month` est déclaré `integer` alors qu'il est `Y-m`).

## Décision

Généraliser le moteur existant sans casser le contrat actuel :

1. **`AttendanceMonthlyReportService` → `AttendanceReportService`** : paramètre `period` (`day` | `week` | `month`, défaut `month` — rétro-compatible), ancres `date` (Y-m-d), `week` (Y-m-d — n'importe quel jour de la semaine ISO, lundi → dimanche), `month` (Y-m).
2. **Filtres** : `department_id` (équipe) et `employee_id` (fiche individuelle), appliqués sur la liste des employés puis sur les logs (même logique que le scope manager `visibleToManager` — PA2-SEC-002/003 conservé).
3. **Payload** : `data.period` gagne `type` et conserve `month` (rétro-compatibilité) + `date_from`/`date_to` ; chaque ligne employé gagne `department_id`/`department_name` (regroupement équipe côté client).
4. **Exports** : CSV et PDF pour toutes les périodes ; noms de fichiers `attendance-report-<period>-<from>_<to>.<ext>` ; le PDF réutilise la vue existante `pdf.attendance-monthly-report` (déjà i18n ×4 et générique via `date_from`/`date_to`).
5. **Synthèse paie** conservée pour chaque période : heures travaillées, HS, minutes de retard, estimation brute (taux horaire ou salaire_base / 173,33).
6. **Route unique** : `GET /attendance/monthly-report` (URL inchangée — consommée par `front/web` reports page) ; méthode contrôleur renommée `monthlyReport` → `report`.
7. **OpenAPI** : schéma de la route corrigé (paramètres `period`, `date`, `week`, `month`, `department_id`, `employee_id`, `format` ; `month` en string `Y-m`) puis `make openapi-sync` (miroir `dev-hub/openapi/v1.yaml` + SDK JS/Python — issue #2450).

## User Scenarios & Testing

### User Story 1 — Rapport journalier (Priority: P2)

**Independent Test**: `php artisan test --filter=AttendanceReportTest` — scénarios verts en CI (PostgreSQL).

**Acceptance Scenarios**:

1. **Given** des logs sur une journée, **When** `GET /attendance/monthly-report?period=day&date=2026-05-06`, **Then** le rapport ne couvre que cette journée (`date_from == date_to == 2026-05-06`).
2. **Given** `period=day` sans `date`, **Then** le rapport couvre aujourd'hui (fuseau entreprise).
3. **Given** un manager RH, **When** `period=day&format=csv`, **Then** 200 + `Content-Type: text/csv`.

### User Story 2 — Rapport hebdomadaire (Priority: P2)

**Acceptance Scenarios**:

1. **Given** des logs lundi et mercredi, **When** `GET /attendance/monthly-report?period=week&week=2026-05-06`, **Then** le rapport couvre la semaine ISO (2026-05-04 → 2026-05-10) avec les totaux des 2 jours.
2. **Given** un filtre `department_id`, **When** le rapport hebdo est demandé, **Then** seuls les employés du département sont inclus.
3. **Given** un filtre `employee_id`, **When** le rapport hebdo est demandé, **Then** une seule ligne (fiche de pointage).

### User Story 3 — Rétro-compatibilité + RBAC (Priority: P1)

**Acceptance Scenarios**:

1. **Given** `GET /attendance/monthly-report?month=2026-05` (sans `period`), **Then** comportement mensuel identique à l'existant (`data.period.month`, totaux).
2. **Given** un manager `manager_role=dept` (scope département), **When** il filtre sur un autre département, **Then** seuls ses propres employés apparaissent (pas d'élargissement de scope).
3. **Given** un employé ordinary, **When** il appelle la route, **Then** 403 (autorisation `viewAny` conservée).

## Edge Cases

- **Fuseau entreprise** : les ancres `date`/`week`/`month` sont interprétées dans le fuseau de l'entreprise (`$company->timezone`), comme le rapport mensuel existant.
- **Semaine ISO** : `startOfWeek(Carbon::MONDAY)` explicite (indépendant de la locale PHP).
- **Période vide** : aucun log sur la période → lignes employés présentes avec des zéros (comportement existant conservé).
- **Filtres hors scope** : un `department_id`/`employee_id` d'une autre entreprise ou hors scope manager → zéro ligne (isolation tenant et scope RBAC, jamais d'erreur).
- **CSV** : neutralisation des préfixes de formule (`CsvCellSanitizer`, #4169) conservée sur `matricule`/`name`.
- **PDF** : `date_from`/`date_to` déjà génériques dans la vue ; le nom de fichier devient `attendance-report-<period>-<from>_<to>.pdf`.

## Hors périmètre

- i18n ×4 + docs utilisateur : issue #5269 (dette tests/i18n/docs pointage).
- Mode kiosque / géo / ZKTeco unifiés : issue #5265.
- Corrections/validations workflow : issue #5267.
- UI web (sélecteur de période) : consomme le contrat API via la page reports existante ; amélioration visuelle suivie séparément pour ne pas toucher `front/web` (anti-collision module `attendance`).
