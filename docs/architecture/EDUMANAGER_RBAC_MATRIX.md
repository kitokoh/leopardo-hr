# EDUMANAGER — Matrice RBAC et confidentialité scolaire

> **Issue :** [EDU-009 #5825](https://github.com/kitokoh/leopardo-hr/issues/5825) — RBAC et confidentialité scolaire.
> **Périmètre :** `App\Modules\EduManager\Domain\Policies\*` (EDU-002 → EDU-008) — matrice des permissions par rôle,
> principes de confidentialité (élèves mineurs, notes, présence), support audit.
> **Complément :** classification des données — `docs/architecture/EDUMANAGER_DONNEES.md`.
> **Câblage API :** l'enregistrement des policies dans le Gate (`AuthServiceProvider`) et les routes relèvent d'EDU-010
> (tâche séparée) — hors périmètre de ce document.

## 1. Principes

| Principe | Application concrète |
|---|---|
| **Deny-by-default** | Toute demande non explicitement autorisée est refusée. Aucune policy ne retourne `true` par défaut : chaque méthode exige soit un rôle de gestion du tenant, soit un lien explicite (gardien → élève, enseignant → classe). Les chemins « best-effort » (enseignant, modèles non encore livrés) refusent quand le lien n'est pas exploitable (**fail-closed**, jamais de fuite). |
| **Tenant-scoped** | Toute ressource porte `company_id` (uuid non nullable, `BelongsToCompany`). Chaque policy vérifie `resource.company_id === actor.company_id` avant tout accès. Les FK composites `(id, company_id)` / `(class_id, company_id)` rendent un rattachement cross-tenant **structurellement impossible** en base. Un gestionnaire du tenant A ne voit jamais une ressource du tenant B. |
| **PII jamais hors tenant** | Données scolaires (élèves mineurs, notes, présence, bulletins, dossiers d'inscription) : jamais exposées hors du tenant, jamais à un employé sans rôle. Champs sensibles chiffrés au repos (`birth_date_encrypted`, `contact_reference`, `metadata` — casts `encrypted`). Les bulletins (`data`) ne contiennent que des moyennes par matière, aucune donnée nominative. |
| **Least privilege** | Chaque rôle reçoit le minimum requis : enseignant → uniquement SES classes/créneaux (lien `edu_timetable_slots`), gardien → uniquement SES enfants avec `can_view_grades=true` pour les notes/bulletins, employé simple → aucun accès. |
| **Séparation plateforme / tenant** | Le super-admin plateforme (`App\Core\Tenant\Domain\Models\SuperAdmin`, modèle distinct) n'est **pas** un `Employee` : il ne peut instancier aucune policy EduManager (toutes typent `Employee`) → **aucun accès** aux données scolaires tenant. Les données scolaires sont exclusivement accessibles aux acteurs du tenant. |

## 2. Acteurs

| Acteur | Construction (tests) |
|---|---|
| **principal / rh / manager** (gestion du tenant) | `Employee` `role='manager'` + `manager_role` ∈ {`principal`, `rh`, `manager`} — `hasManagerRole('principal','rh','manager')` |
| **enseignant** | `Employee` + profil `EduTeacher` lié via `employee_id` (même tenant) ; accès borné à SES classes via `EduTimetableSlot` (une séance dans la classe → il l'enseigne) |
| **gardien / responsable légal** | `Employee` + dossier `EduGuardian` lié via `employee_id` ; accès aux élèves via `EduStudentGuardian` (lien explicite) |
| **élève** | Aucun compte applicatif : accès indirect uniquement via son gardien (lecture) ou le personnel du tenant (gestion) |
| **comptable** | `manager_role='comptable'` : **aucun** accès aux données scolaires (matrice RBAC paie, hors périmètre EDU — seuls les frais de scolarité, hors périmètre de ce lot, le concerneraient) |
| **employé simple** | `Employee` `role='employee'`, sans profil enseignant ni gardien : **aucun** accès |
| **super_admin plateforme** | `SuperAdmin` (modèle distinct) : **aucun** accès (voir §1) |

## 3. Matrice des permissions

Légende : ✅ autorisé · ❌ refusé · 🔗 conditionnel (lien explicite, voir notes) · 🚫 jamais (fail-closed).

| Ressource / capacité | principal | rh | manager | enseignant | gardien | élève | comptable | employé simple | autre tenant |
|---|---|---|---|---|---|---|---|---|---|
| **Élèves** `EduStudentPolicy` | | | | | | | | | |
| viewAny | ✅ | ✅ | ✅ | ❌ | ❌ | 🚫 | ❌ | ❌ | — |
| view | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | ❌ | 🔗 ses enfants | 🚫 | ❌ | ❌ | ❌ |
| viewGrades | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | ❌ | 🔗 `can_view_grades=true` | 🚫 | ❌ | ❌ | ❌ |
| create / update / delete | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | ❌ | ❌ | 🚫 | ❌ | ❌ | ❌ |
| **Dossiers d'inscription** `EduAdmissionPolicy` | | | | | | | | | |
| viewAny / create | ✅ | ✅ | ✅ | ❌ | ❌ | 🚫 | ❌ | ❌ | — |
| view / update / delete | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | ❌ | ❌ | 🚫 | ❌ | ❌ | ❌ |
| convert (dossier → élève) | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | ❌ | ❌ | 🚫 | ❌ | ❌ | ❌ |
| **Bulletins** `EduReportCardPolicy` | | | | | | | | | |
| viewAny | ✅ | ✅ | ✅ | ❌ | ❌ | 🚫 | ❌ | ❌ | — |
| view | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | 🔗 ses classes | 🔗 ses enfants + `can_view_grades=true` | 🚫 | ❌ | ❌ | ❌ |
| validate / publish | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | ❌ | ❌ | 🚫 | ❌ | ❌ | ❌ |
| **Notes** `EduGradePolicy` | | | | | | | | | |
| viewAny | ✅ | ✅ | ✅ | ✅ | ❌ | 🚫 | ❌ | ❌ | — |
| view | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | 🔗 classe de l'évaluation | ❌ | 🚫 | ❌ | ❌ | ❌ |
| create | ✅ | ✅ | ✅ | ✅ (saisie, verrou métier via publication) | ❌ | 🚫 | ❌ | ❌ | — |
| update (draft) | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | 🔗 classe de l'évaluation | ❌ | 🚫 | ❌ | ❌ | ❌ |
| update (publiée) | ❌ | ❌ | ❌ | ❌ | ❌ | 🚫 | ❌ | ❌ | ❌ |
| correct (publiée, auditable) | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | ❌ | ❌ | 🚫 | ❌ | ❌ | ❌ |
| **Présence** `EduAttendanceRecordPolicy` | | | | | | | | | |
| viewAny | ✅ | ✅ | ✅ | ✅ | ❌ | 🚫 | ❌ | ❌ | — |
| view / update | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | 🔗 ses classes | ❌ | 🚫 | ❌ | ❌ | ❌ |
| create | ✅ | ✅ | ✅ | 🔗 sa classe (`class_id`) | ❌ | 🚫 | ❌ | ❌ | — |
| **Emploi du temps** `EduTimetableSlotPolicy` | | | | | | | | | |
| viewAny | ✅ | ✅ | ✅ | ✅ (SES créneaux — requête bornée au contrôleur) | ❌ | 🚫 | ❌ | ❌ | — |
| view | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | 🔗 `teacher_id` = son profil | ❌ | 🚫 | ❌ | ❌ | ❌ |
| create | ✅ | ✅ | ✅ | ❌ | ❌ | 🚫 | ❌ | ❌ | — |
| update / delete | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | ❌ | ❌ | 🚫 | ❌ | ❌ | ❌ |
| **Référentiel enseignants** `EduTeacherPolicy` | | | | | | | | | |
| viewAny / create | ✅ | ✅ | ✅ | ❌ | ❌ | 🚫 | ❌ | ❌ | — |
| view / update / delete | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | ❌ | ❌ | 🚫 | ❌ | ❌ | ❌ |
| **Référentiel général** (campus, années, classes, matières, affectations) — `EduCampusPolicy`, `EduAcademicYearPolicy`, `EduClassPolicy`, `EduSubjectPolicy`, `EduTeacherSubjectPolicy`, `EduStudentGuardianPolicy` | | | | | | | | | |
| viewAny / create | ✅ | ✅ | ✅ | ❌ | ❌ | 🚫 | ❌ | ❌ | — |
| view / update / delete | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | ❌ | ❌ | 🚫 | ❌ | ❌ | ❌ |
| **Évaluations** `EduAssessmentPolicy` | | | | | | | | | |
| viewAny | ✅ | ✅ | ✅ | ✅ | ❌ | 🚫 | ❌ | ❌ | — |
| view | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | 🔗 classe de l'évaluation | ❌ | 🚫 | ❌ | ❌ | ❌ |
| create / update / delete / publish | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | ❌ | ❌ | 🚫 | ❌ | ❌ | ❌ |
| **Responsables légaux** `EduGuardianPolicy` | | | | | | | | | |
| viewAny / create | ✅ | ✅ | ✅ | ❌ | ❌ | 🚫 | ❌ | ❌ | — |
| view | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | ❌ | 🔗 SON profil (`employee_id`) | 🚫 | ❌ | ❌ | ❌ |
| update / delete | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | ❌ | ❌ | 🚫 | ❌ | ❌ | ❌ |
| **Corrections de présence** `EduAttendanceCorrectionPolicy` | | | | | | | | | |
| viewAny / create | ✅ | ✅ | ✅ | ❌ | ❌ | 🚫 | ❌ | ❌ | — |
| view | ✅ (tenant) | ✅ (tenant) | ✅ (tenant) | ❌ | ❌ | 🚫 | ❌ | ❌ | ❌ |

> « — » : méthode sans ressource (pas de contrôle tenant possible) ; le contrôle tenant s'applique aux méthodes
> portant une ressource. « 🚫 » : aucun compte applicatif (accès indirect via le gardien uniquement).

## 4. Notes d'implémentation et écarts documentés

1. **Gestionnaire ≠ super-admin plateforme.** Toutes les policies typent `Employee` (acteur du tenant). Le
   `SuperAdmin` plateforme n'est pas un `Employee` → aucun chemin d'accès aux données scolaires (deny par
   construction, cf. §1).

2. **`role === 'manager'` court-circuite `manager_role`.** `EduReportCardPolicy::isManager()` et
   `EduAttendanceRecordPolicy::isManager()` acceptent tout `Employee` avec `role='manager'`, y compris
   `manager_role=null`. Un employé `role='manager'` sans `manager_role` est donc traité comme gestionnaire du
   tenant par ces deux policies (mais **pas** par les policies basées sur `hasManagerRole()`, ex.
   `EduStudentPolicy`, `EduTeacherPolicy`, `EduTimetableSlotPolicy`, `EduGradePolicy`, `EduAssessmentPolicy`).
   Conséquence test : l'acteur « enseignant » du test RBAC (défini `role='manager'` + `manager_role=null` +
   profil `EduTeacher`) passe `isManager` sur ces deux policies ; le chemin enseignant « pur »
   (`role='employee'` + profil `EduTeacher`) est couvert séparément pour la lecture du bulletin
   (`EduReportCardPolicy::view` → `teachesClass`) et reste le seul à exercer le best-effort.

3. **Enseignant = best-effort fail-closed.** Le lien enseignant → classe est porté par `edu_timetable_slots`
   (EDU-006) : une séance dans la classe ⇒ il l'enseigne. Sans lien exploitable (`teacher_id` introuvable,
   `class_id <= 0`, modèle absent), refus — jamais de fuite.

4. **`EduAttendanceRecordPolicy::MANAGER_ROLES = ['principal','rh']`** (le `manager` passe via
   `role === 'manager'`), **`EduAttendanceCorrectionPolicy::MANAGER_ROLES = ['principal','rh']`** —
   mêmes effets que `['principal','rh','manager']` pour les acteurs construits `role='manager'`.

5. **Notes publiées immuables.** `EduGradePolicy::update()` refuse toute note `status='published'` ;
   la seule mutation post-publication est `correct()` (gestionnaire du tenant) — opération auditable et
   versionnée (`edu_grade_versions`, `GradeService::correctGrade`).

6. **Gardien strictement borné à ses enfants.** `view` élève = lien `edu_student_guardians` (tenant + gardien
   + élève) ; `viewGrades`/bulletin = même lien **avec `can_view_grades=true`**. Jamais l'élève d'un autre
   gardien, jamais un élève d'un autre tenant, jamais les notes sans droit explicite.

7. **PII chiffrée au repos** : `edu_students.birth_date_encrypted`, `edu_admissions.contact_reference` /
   `metadata` (casts `encrypted`) ; `edu_report_cards.data` = moyennes par matière (aucune donnée nominative).

## 5. Support audit

- **Aujourd'hui (hors API) :** l'activation de la solution EduManager (`SolutionActivator` → manifest
  EDU-001) est auditée dans `audit_logs` avec `company_id` (`action='solution.activated'`) — vérifiée par
  `EduRbacMatrixTest::test_audit_log_traces_school_activation_with_company_id`.
- **Mutations scolaires :** les services métier préparent le traçage (`GradeService` versionne chaque
  correction dans `edu_grade_versions` avec justification obligatoire ; `ReportCardService` conserve
  `validated_by`/`published_at`/`created_by`), mais **l'écriture d'`AuditLog` (action, module, old/new
  values) sur les créations/modifications de données scolaires arrivera avec l'API EDU-010** (câblage Gate +
  routes + journalisation des mutations). Les politiques RBAC de ce lot garantissent déjà que seuls les
  acteurs autorisés déclenchent ces mutations.
- Le journal `edu_attendance_corrections` versionne chaque correction de présence (jamais d'écrasement
  silencieux) — réservé aux gestionnaires du tenant (`EduAttendanceCorrectionPolicy`).

## 6. Couverture de test

`api/tests/Feature/EduManager/EduRbacMatrixTest.php` — instancie chaque policy via `app(EduXxxPolicy::class)`
avec des acteurs construits (manager principal, enseignant, gardien lié, employé simple, acteur d'un autre
tenant) et vérifie la matrice allow/deny ci-dessus, y compris les refus cross-tenant pour chaque scénario.
