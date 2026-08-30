# EduManager — Modèle de données (EDU-001..010)

> Référence du schéma tenant du module EduManager (solution verticale, flag
> `edumanager` — activation #5817). Toute table vit dans `shared_tenants`,
> `company_id` uuid NON nullable partout (isolation tenant `BelongsToCompany`,
> fail-closed #3727). PK bigint (`$table->id()`), FK COMPOSITES
> `(id, company_id)` partout où une référence pointe vers une table du même
> BC (cross-tenant physiquement impossible). Migrations additives/idempotentes
> (`schemaTableExists`), préfixes 2026_08_30_000401+ contrôlés par la garde
> #1962. Données scolaires (élèves mineurs, responsables légaux, notes)
> classées PII — jamais hors tenant, jamais loggées.

## Tables

### `edu_campuses` (#5818, EDU-002)
| Colonne | Contrainte |
|---|---|
| `code` | `UNIQUE (company_id, code)` ; `UNIQUE (id, company_id)` → FK composites |
| `status` | CHECK `active\|inactive\|archived` |
| `timezone` / `address` | défaut `UTC` / nullable (adresse = localisation d'établissement, en clair pour l'affichage admin) |

Index tenant-first : `(company_id, status)`, `(company_id, name)`, `(company_id, created_at)`.

### `edu_students` (#5818, EDU-002) — élèves (PII)
`student_number` `UNIQUE (company_id, student_number)` ; `status` CHECK
`active|inactive|archived` ; `birth_date_encrypted` + `metadata` chiffrés au
repos (casts `encrypted`). `UNIQUE (id, company_id)` pour les FK composites
des tables filles. Suppression physique interdite — archivage RGPD uniquement.

### `edu_guardians` (#5818, EDU-002) — responsables légaux (PII)
`relationship_code` CHECK `parent|guardian|other` ; `contact_reference`
chiffré ; `employee_id` nullable (si le responsable est aussi employé RH).

### `edu_student_guardians` (#5818, EDU-002)
FK composites `(student_id, company_id)` → `edu_students` et
`(guardian_id, company_id)` → `edu_guardians` — cross-tenant impossible.
`UNIQUE (company_id, student_id, guardian_id)` ; `can_view_grades` /
`can_receive_notifications` (permissions guardian).

### `edu_academic_years` (#5819, EDU-003)
`UNIQUE (company_id, name)` ; CHECK `status IN (active|closed)` ; CHECK
`start_date < end_date` (`edu_academic_years_period_check`). Chevauchement
d'année active contrôlé au niveau application (`EduAcademicYearService`,
EDU_ACADEMIC_YEAR_OVERLAP).

### `edu_subjects` (#5819, EDU-003)
`UNIQUE (company_id, code)` ; FK composite `(campus_id, company_id)` →
`edu_campuses` (nullOnDelete) ; `default_coefficient` > 0 (validation request).

### `edu_classes` (#5819, EDU-003)
`UNIQUE (company_id, code, academic_year_id)` ; FK composites campus + année
(cascade) ; `teacher_id` = employé RH du même tenant (pas de FK dure —
pattern FuelStation, `employees.company_id` nullable en base) ; CHECK
`capacity IS NULL OR capacity > 0`.

### `edu_teacher_subjects` (#5819, EDU-003)
Pivot `UNIQUE (company_id, class_id, subject_id, teacher_id)` ; FK composites
classe + matière ; `status` CHECK `active|inactive` (retrait = inactif,
historique conservé).

### `edu_admissions` (#5820, EDU-004)
`UNIQUE (company_id, admission_number)` + `UNIQUE (company_id, external_id)`
→ rejeu idempotent des webhooks. Pipeline CHECK `new|document_pending|review|
accepted|waitlisted|rejected|cancelled|converted`. `consent_contact` +
`consented_at` (RGPD) ; conversion élève idempotente côté service
(EDU_CONSENT_REQUIRED). `crm_contact_id` SANS FK — simple référence de
contrat (le CRM commercial plateforme reste inaccessible, spec §2).

### `edu_attendances` + `edu_attendance_corrections` (#5821, EDU-005)
`UNIQUE (company_id, class_id, student_id, attendance_date)` → saisie
idempotente ; statut CHECK `present|absent|late|excused`. Journal de
corrections VERSIONNÉ (ancien/nouveau statut, motif, auteur, horodatage) —
jamais d'UPDATE silencieux.

### `edu_course_slots` (#5822, EDU-006)
CHECK `day_of_week BETWEEN 0 AND 6`, CHECK `end_time > start_time`, statut
CHECK `active|cancelled`. Conflits classe/enseignant contrôlés au niveau
application (`EduCourseSlotService`, EDU_COURSE_SLOT_CLASS_CONFLICT /
EDU_COURSE_SLOT_TEACHER_CONFLICT). Index tenant-first `(class, day)` et
`(teacher, day)`.

### `edu_assessments` + `edu_grades` + `edu_grade_versions` (#5823, EDU-007)
- `edu_assessments` : type CHECK `exam|quiz|homework|project`, CHECK
  `max_score > 0`, CHECK `coefficient > 0` ; FK composites classe/matière/année.
- `edu_grades` : `UNIQUE (company_id, assessment_id, student_id)` ; statut
  CHECK `draft|published|corrected` ; `version` croissante.
- `edu_grade_versions` : journal d'audit, `UNIQUE (company_id, grade_id,
  version)` — chaque correction ajoute une version, l'historique n'est
  jamais écrasé.

### `edu_report_cards` + `edu_report_card_lines` (#5824, EDU-008)
- `edu_report_cards` : `UNIQUE (company_id, student_id, academic_year_id,
  period)` ; période CHECK `term1|term2|term3|final` ; statut CHECK
  `draft|validated|published` ; cycle de vie direction → publication
  verrouillante (EDU_REPORT_CARD_LOCKED).
- `edu_report_card_lines` : read model recalculable (moyenne, coefficient,
  nb d'évaluations) — régénéré à chaque `generate()`, jamais édité à la main.

## Règles transverses

- **RBAC** : direction (principal/rh/manager) = gestion complète ; enseignant
  = SES classes (référentes + `edu_teacher_subjects`) ; employé lambda = 403 ;
  guardian = portail dédié (EDU-013, `can_view_grades`).
- **Rétention RGPD** (EDU-019, batch 2) : archivage plutôt que suppression ;
  `student_number`/`admission_number` jamais réutilisés après archivage.
- **Confidentialité** : notes/bulletins visibles uniquement par l'enseignant
  de la classe, la direction et le guardian autorisé.
