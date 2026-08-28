# Données EduManager — Classification PII et cycle de vie RGPD (issue #5818, EDU-002)

- **Statut :** ratifié (périmètre EDU-002 : campus, élèves, responsables légaux)
- **Date :** 2026-08-28
- **Prépare :** #5819 (années/classes/enseignants), #5820 (admissions + lien CRM client), #5825 (RBAC), #5826 (API), #5829 (portail guardian)

---

## 1. Classification des données

| Table / colonne | Classification | Mesure |
|---|---|---|
| `edu_students.display_name` | PII nominative (mineurs) | Clair (affichage listes/bulletins), RBAC strict, jamais hors tenant |
| `edu_students.birth_date_encrypted` | PII sensible | **Chiffré au repos** (cast `encrypted`), non indexable, non interrogeable en base |
| `edu_students.metadata` | PII potentielle (libre) | **Chiffré au repos** (`encrypted:array`), bornes serveur (EDU-006+) |
| `edu_guardians.first_name/last_name` | PII nominative | Clair (affichage portail guardian), RBAC (self-only hors gestionnaire) |
| `edu_guardians.contact_reference` | PII sensible (contact) | **Chiffré au repos** (cast `encrypted`), non indexable |
| `edu_guardians.employee_id` | Donnée de lien | Clair (référence interne, pas de FK — pattern CRM `owner_id`) |
| `edu_guardians.verified_at` | Preuve de consentement | Clair (horodatage), utilisé par le cycle de vie RGPD |
| `edu_student_guardians.*` | Données de relation | Clair (aucune PII directe), droits fins `can_view_grades`/`can_receive_notifications` |
| `edu_campuses.address` | Donnée de localisation | Clair (affichage administratif) |

## 2. Cycle de vie RGPD

- **Consentement :** le lien gardien ↔ élève est une relation **explicite et vérifiée** (`verified_at`). Un gardien ne voit que les élèves liés — jamais une liste globale (spec §6.5).
- **Minimisation :** pas de donnée médicale ni de champ libre non borné ; `metadata` chiffré et borné serveur.
- **Rétention / effacement :** archivage logique via `status` (`active|inactive|archived`) ; le droit à l'effacement passe par le registre `privacy_requests` existant (pattern CRM, PR #5713) — effacement physique hors périmètre EDU-002.
- **Journaux :** aucune PII dans les logs, fixtures, réponses d'erreur ou commits (règle obligatoire des issues EDU).
- **Portage :** les migrations portent les contraintes (FK composites tenant-safe, CHECK), l'application porte les Policies (`EduStudentPolicy`, `EduGuardianPolicy`, `EduCampusPolicy`, `EduStudentGuardianPolicy`).

## 3. Rollback

- `php artisan migrate:rollback --path=database/migrations/tenant --step=4` supprime
  `edu_student_guardians` → `edu_guardians` → `edu_students` → `edu_campuses`
  (ordre de down() inversé des dépendances, testé).
- `php artisan leopardo:migrate --fresh` recrée les deux schémas (restauration : runbook backup existant).

## 4. Non-régression

- Les données scolaires restent **isolées du CRM commercial Leopardo** (schéma `public` / plateforme) : aucune migration EDU ne touche `marketing_*`, aucune Policy Edu ne référence Platform/Marketing (garde isolation #5584).
- Chaque nouvelle table tenant est couverte par la fixture `CreatesMvpSchema` (parité #5443) et par des tests `RefreshTenantDatabase` (`EduManagerMigrationsTest`, `EduGuardianAccessTest`).
