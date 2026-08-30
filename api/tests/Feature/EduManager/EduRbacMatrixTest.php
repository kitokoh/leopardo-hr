<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Solutions\SolutionActivator;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduAttendanceRecord;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;
use App\Modules\EduManager\Domain\Models\EduTeacher;
use App\Modules\EduManager\Domain\Models\EduTimetableSlot;
use App\Modules\EduManager\Domain\Policies\EduAdmissionPolicy;
use App\Modules\EduManager\Domain\Policies\EduAttendanceRecordPolicy;
use App\Modules\EduManager\Domain\Policies\EduGradePolicy;
use App\Modules\EduManager\Domain\Policies\EduReportCardPolicy;
use App\Modules\EduManager\Domain\Policies\EduStudentPolicy;
use App\Modules\EduManager\Domain\Policies\EduTeacherPolicy;
use App\Modules\EduManager\Domain\Policies\EduTimetableSlotPolicy;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5825 (EDU-009) — RBAC et confidentialité scolaire : matrice
 * allow/deny des policies EduManager (#5818 → #5824).
 *
 * Les policies sont instanciées DIRECTEMENT (`app(EduXxxPolicy::class)`) avec
 * des acteurs construits :
 *   - manager principal : Employee role='manager' + manager_role='principal' ;
 *   - enseignant : Employee role='manager' + manager_role=null + profil
 *     EduTeacher lié (employee_id) — le rôle enseignant « pur »
 *     (role='employee' + EduTeacher) est couvert pour les chemins
 *     best-effort (EduReportCardPolicy::view → teachesClass) ;
 *   - gardien : Employee simple + EduGuardian (employee_id) + lien
 *     EduStudentGuardian (can_view_grades) ;
 *   - employé simple : Employee role='employee' sans profil ;
 *   - acteur d'un AUTRE tenant : chaque scénario deny cross-tenant.
 *
 * Matrice complète : docs/architecture/EDUMANAGER_RBAC_MATRIX.md
 */
class EduRbacMatrixTest extends TestCase
{
    use RefreshTenantDatabase;
    use WithFaker;

    private Company $company;

    private Company $otherCompany;

    private Employee $manager;

    /** Enseignant « spec » : role='manager' + manager_role=null + EduTeacher. */
    private Employee $teacherActor;

    /** Enseignant « pur » : role='employee' + EduTeacher (chemins best-effort). */
    private Employee $employeeTeacher;

    private Employee $guardianActor;

    private Employee $employee;

    private Employee $otherManager;

    private int $teacherId;

    private int $employeeTeacherId;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->otherCompany = $other;

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->manager = $manager;

        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->create([
            'company_id' => $other->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->otherManager = $otherManager;

        /** @var Employee $teacherActor */
        $teacherActor = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => null,
        ]);
        $this->teacherActor = $teacherActor;
        $this->teacherId = $this->teacherRow($company, (int) $teacherActor->id, 'M. Enseignant A');

        /** @var Employee $employeeTeacher */
        $employeeTeacher = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        $this->employeeTeacher = $employeeTeacher;
        $this->employeeTeacherId = $this->teacherRow($company, (int) $employeeTeacher->id, 'Mme Enseignante B');

        /** @var Employee $guardianActor */
        $guardianActor = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        $this->guardianActor = $guardianActor;

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        $this->employee = $employee;
    }

    // ── EduStudentPolicy ───────────────────────────────────────────────────

    public function test_student_policy_manager_and_teacher(): void
    {
        $student = $this->student($this->company, 'S-001');
        $otherTenantStudent = $this->student($this->otherCompany, 'S-X1');
        $policy = app(EduStudentPolicy::class);

        // Gestionnaire du tenant : accès complet sur SON tenant.
        $this->assertTrue($policy->viewAny($this->manager));
        $this->assertTrue($policy->view($this->manager, $student));
        $this->assertTrue($policy->viewGrades($this->manager, $student));
        $this->assertTrue($policy->create($this->manager));
        $this->assertTrue($policy->update($this->manager, $student));

        // Enseignant : aucun accès élève (pas de lien gardien, pas de rôle
        // de gestion — manager_role=null).
        $this->assertFalse($policy->viewAny($this->teacherActor));
        $this->assertFalse($policy->view($this->teacherActor, $student));

        // Employé simple : aucun accès.
        $this->assertFalse($policy->viewAny($this->employee));
        $this->assertFalse($policy->view($this->employee, $student));

        // Cross-tenant : un gestionnaire ne voit JAMAIS un élève d'un autre
        // tenant (view ET update).
        $this->assertFalse($policy->view($this->manager, $otherTenantStudent));
        $this->assertFalse($policy->update($this->manager, $otherTenantStudent));
        $this->assertFalse($policy->delete($this->manager, $otherTenantStudent));
    }

    public function test_student_policy_guardian_linked_only(): void
    {
        $linked = $this->student($this->company, 'S-001');
        $otherStudent = $this->student($this->company, 'S-002');
        $otherTenantStudent = $this->student($this->otherCompany, 'S-X1');
        $this->linkGuardian($this->guardianActor, $linked, canViewGrades: false);

        $policy = app(EduStudentPolicy::class);

        // Gardien : voit UNIQUEMENT l'élève explicitement lié.
        $this->assertTrue($policy->view($this->guardianActor, $linked));
        // Même tenant mais non lié : REFUSÉ (« guardian non autorisé »).
        $this->assertFalse($policy->view($this->guardianActor, $otherStudent));
        // Jamais un élève d'un autre tenant.
        $this->assertFalse($policy->view($this->guardianActor, $otherTenantStudent));
        $this->assertFalse($policy->viewGrades($this->guardianActor, $otherTenantStudent));

        // viewGrades : can_view_grades=false → refusé.
        $this->assertFalse($policy->viewGrades($this->guardianActor, $linked));
    }

    public function test_student_policy_guardian_view_grades_flag(): void
    {
        $student = $this->student($this->company, 'S-001');
        $this->linkGuardian($this->guardianActor, $student, canViewGrades: true);

        $policy = app(EduStudentPolicy::class);

        $this->assertTrue($policy->view($this->guardianActor, $student));
        $this->assertTrue($policy->viewGrades($this->guardianActor, $student));
    }

    // ── EduAdmissionPolicy ─────────────────────────────────────────────────

    public function test_admission_policy_manager_only(): void
    {
        $admission = $this->admission($this->company, 'ADM-2026-0001');
        $otherTenantAdmission = $this->admission($this->otherCompany, 'ADM-2026-X001');
        $policy = app(EduAdmissionPolicy::class);

        // Gestionnaire : gestion complète du dossier, bornée au tenant.
        $this->assertTrue($policy->viewAny($this->manager));
        $this->assertTrue($policy->view($this->manager, $admission));
        $this->assertTrue($policy->create($this->manager));
        $this->assertTrue($policy->update($this->manager, $admission));
        // Conversion dossier → élève : acte réservé aux gestionnaires.
        $this->assertTrue($policy->convert($this->manager, $admission));

        // Enseignant / gardien / employé simple : aucun accès.
        $this->assertFalse($policy->viewAny($this->teacherActor));
        $this->assertFalse($policy->viewAny($this->guardianActor));
        $this->assertFalse($policy->viewAny($this->employee));
        $this->assertFalse($policy->view($this->employee, $admission));

        // Cross-tenant : jamais le dossier d'un autre tenant.
        $this->assertFalse($policy->view($this->manager, $otherTenantAdmission));
        $this->assertFalse($policy->convert($this->manager, $otherTenantAdmission));
        $this->assertFalse($policy->update($this->manager, $otherTenantAdmission));
        $this->assertFalse($policy->delete($this->manager, $otherTenantAdmission));
    }

    // ── EduReportCardPolicy ────────────────────────────────────────────────

    public function test_report_card_policy_manager_and_guardian(): void
    {
        $student = $this->student($this->company, 'S-001');
        $classId = $this->classId($this->company);
        $yearId = $this->yearId($this->company);
        $card = $this->reportCard($this->company, $student, $classId, $yearId);
        $otherCard = $this->reportCard(
            $this->otherCompany,
            $this->student($this->otherCompany, 'S-X1'),
            $this->classId($this->otherCompany),
            $this->yearId($this->otherCompany),
        );

        $policy = app(EduReportCardPolicy::class);

        // Gestionnaire : listage, lecture, validation et publication.
        $this->assertTrue($policy->viewAny($this->manager));
        $this->assertTrue($policy->view($this->manager, $card));
        $this->assertTrue($policy->validate($this->manager, $card));
        $this->assertTrue($policy->publish($this->manager, $card));

        // Gardien SANS droit de notes : lecture du bulletin REFUSÉE.
        $this->linkGuardian($this->employee, $student, canViewGrades: false);
        $this->assertFalse($policy->view($this->employee, $card));

        // Gardien AVEC can_view_grades=true : lecture de SON enfant.
        $this->linkGuardian($this->guardianActor, $student, canViewGrades: true);
        $this->assertTrue($policy->view($this->guardianActor, $card));
        $this->assertFalse($policy->viewAny($this->guardianActor));

        // Cross-tenant : bulletin d'un autre tenant invisible.
        $this->assertFalse($policy->view($this->manager, $otherCard));
        $this->assertFalse($policy->publish($this->manager, $otherCard));

        // Employé simple (ni gestionnaire, ni gardien lié) : aucun accès.
        /** @var Employee $plainEmployee */
        $plainEmployee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
        $this->assertFalse($policy->view($plainEmployee, $card));
        $this->assertFalse($policy->validate($plainEmployee, $card));
        $this->assertFalse($policy->publish($plainEmployee, $card));
    }

    public function test_report_card_policy_teacher_of_the_class(): void
    {
        $student = $this->student($this->company, 'S-001');
        $classId = $this->classId($this->company);
        $subjectId = $this->subjectId($this->company);
        $yearId = $this->yearId($this->company);
        // L'enseignant « pur » (role='employee') a une séance dans la classe.
        $this->slot($this->company, $classId, $subjectId, $this->employeeTeacherId);
        $card = $this->reportCard($this->company, $student, $classId, $yearId);

        $policy = app(EduReportCardPolicy::class);

        // Best-effort : l'enseignant de la classe lit le bulletin de SA classe.
        $this->assertTrue($policy->view($this->employeeTeacher, $card));
        // Mais ne liste ni ne publie (actes d'administration).
        $this->assertFalse($policy->viewAny($this->employeeTeacher));
        $this->assertFalse($policy->publish($this->employeeTeacher, $card));

        // Enseignant SANS séance dans la classe : refusé (fail-closed).
        $otherClassId = $this->classId($this->company, '6AP-B');
        $otherCard = $this->reportCard($this->company, $this->student($this->company, 'S-002'), $otherClassId, $yearId);
        $this->assertFalse($policy->view($this->employeeTeacher, $otherCard));
    }

    // ── EduGradePolicy ─────────────────────────────────────────────────────

    public function test_grade_policy_manager_draft_published_and_cross_tenant(): void
    {
        $student = $this->student($this->company, 'S-001');
        $classId = $this->classId($this->company);
        $subjectId = $this->subjectId($this->company);
        $yearId = $this->yearId($this->company);
        $assessment = $this->assessment($this->company, $classId, $subjectId, $yearId, 'Composition S1');
        $draft = $this->grade($this->company, $assessment, $student, EduGrade::STATUS_DRAFT);
        $published = $this->grade($this->company, $assessment, $this->student($this->company, 'S-002'), EduGrade::STATUS_PUBLISHED);

        $otherAssessment = $this->assessment(
            $this->otherCompany,
            $this->classId($this->otherCompany),
            $this->subjectId($this->otherCompany),
            $this->yearId($this->otherCompany),
            'Composition S1'
        );
        $otherGrade = $this->grade(
            $this->otherCompany,
            $otherAssessment,
            $this->student($this->otherCompany, 'S-X1'),
            EduGrade::STATUS_DRAFT,
        );

        $policy = app(EduGradePolicy::class);

        // Gestionnaire : saisie et modification des brouillons.
        $this->assertTrue($policy->create($this->manager));
        $this->assertTrue($policy->update($this->manager, $draft));
        $this->assertTrue($policy->view($this->manager, $draft));

        // Note publiée : IMMUABLE — update refusé, correct (auditable) autorisé.
        $this->assertFalse($policy->update($this->manager, $published));
        $this->assertTrue($policy->correct($this->manager, $published));

        // Cross-tenant : jamais une note d'un autre tenant.
        $this->assertFalse($policy->view($this->manager, $otherGrade));
        $this->assertFalse($policy->update($this->manager, $otherGrade));
        $this->assertFalse($policy->correct($this->manager, $otherGrade));

        // Employé simple : aucun accès (notes sensibles).
        $this->assertFalse($policy->viewAny($this->employee));
        $this->assertFalse($policy->create($this->employee));
        $this->assertFalse($policy->view($this->employee, $draft));
    }

    public function test_grade_policy_teacher_of_the_assessment_class(): void
    {
        $student = $this->student($this->company, 'S-001');
        $classId = $this->classId($this->company);
        $subjectId = $this->subjectId($this->company);
        $yearId = $this->yearId($this->company);
        // L'enseignant (spec : role='manager' + manager_role=null + EduTeacher)
        // a une séance dans la classe de l'évaluation.
        $this->slot($this->company, $classId, $subjectId, $this->teacherId);
        $assessment = $this->assessment($this->company, $classId, $subjectId, $yearId, 'Devoir de mathématiques');
        $draft = $this->grade($this->company, $assessment, $student, EduGrade::STATUS_DRAFT);

        $policy = app(EduGradePolicy::class);

        // Enseignant : listage, saisie et modification des brouillons de SES
        // classes (lien edu_timetable_slots), jamais les actes d'admin.
        $this->assertTrue($policy->viewAny($this->teacherActor));
        $this->assertTrue($policy->view($this->teacherActor, $draft));
        $this->assertTrue($policy->create($this->teacherActor));
        $this->assertTrue($policy->update($this->teacherActor, $draft));

        // Enseignant SANS séance dans la classe de l'évaluation : refusé.
        $otherClassId = $this->classId($this->company, '6AP-C');
        $otherAssessment = $this->assessment($this->company, $otherClassId, $subjectId, $yearId, 'Composition 6AP-C');
        $otherGrade = $this->grade($this->company, $otherAssessment, $this->student($this->company, 'S-002'), EduGrade::STATUS_DRAFT);
        $this->assertFalse($policy->view($this->teacherActor, $otherGrade));
        $this->assertFalse($policy->update($this->teacherActor, $otherGrade));

        // Correction d'une note publiée : gestionnaire du tenant uniquement.
        $published = $this->grade($this->company, $assessment, $this->student($this->company, 'S-003'), EduGrade::STATUS_PUBLISHED);
        $this->assertFalse($policy->update($this->teacherActor, $published));
        $this->assertFalse($policy->correct($this->teacherActor, $published));
    }

    // ── EduAttendanceRecordPolicy ──────────────────────────────────────────

    public function test_attendance_policy_manager_teacher_and_cross_tenant(): void
    {
        $student = $this->student($this->company, 'S-001');
        $classId = $this->classId($this->company);
        $subjectId = $this->subjectId($this->company);
        $this->slot($this->company, $classId, $subjectId, $this->employeeTeacherId);
        $record = $this->attendanceRecord($this->company, $classId, $student);

        $otherClassId = $this->classId($this->company, '6AP-D');
        $otherRecord = $this->attendanceRecord($this->company, $otherClassId, $this->student($this->company, 'S-002'));

        $otherTenantRecord = $this->attendanceRecord(
            $this->otherCompany,
            $this->classId($this->otherCompany),
            $this->student($this->otherCompany, 'S-X1'),
        );

        $policy = app(EduAttendanceRecordPolicy::class);

        // Gestionnaire : listage, saisie et lecture sur SON tenant.
        $this->assertTrue($policy->viewAny($this->manager));
        $this->assertTrue($policy->create($this->manager, $classId));
        $this->assertTrue($policy->view($this->manager, $record));
        $this->assertTrue($policy->update($this->manager, $record));

        // Enseignant lié (profil EduTeacher) : accès limité à SES classes.
        $this->assertTrue($policy->viewAny($this->employeeTeacher));
        $this->assertTrue($policy->view($this->employeeTeacher, $record));
        $this->assertFalse($policy->view($this->employeeTeacher, $otherRecord));
        $this->assertFalse($policy->create($this->employeeTeacher, $otherClassId));

        // Employé simple : aucun accès (présence = PII élève).
        $this->assertFalse($policy->viewAny($this->employee));
        $this->assertFalse($policy->view($this->employee, $record));
        $this->assertFalse($policy->create($this->employee, $classId));

        // Cross-tenant : enregistrement d'un autre tenant invisible.
        $this->assertFalse($policy->view($this->manager, $otherTenantRecord));
        $this->assertFalse($policy->update($this->manager, $otherTenantRecord));
        $this->assertFalse($policy->view($this->otherManager, $record));
    }

    // ── EduTimetableSlotPolicy ─────────────────────────────────────────────

    public function test_timetable_slot_policy_manager_and_teacher(): void
    {
        $classId = $this->classId($this->company);
        $subjectId = $this->subjectId($this->company);
        $ownSlot = $this->slot($this->company, $classId, $subjectId, $this->teacherId);
        // Créneau d'un AUTRE enseignant du même tenant (2e profil)…
        $otherSlot = $this->slot(
            $this->company,
            $this->classId($this->company, '6AP-E'),
            $subjectId,
            $this->employeeTeacherId,
            start: '08:00:00',
            end: '09:00:00',
        );
        $otherTenantSlot = $this->slot(
            $this->otherCompany,
            $this->classId($this->otherCompany),
            $this->subjectId($this->otherCompany),
            $this->teacherRow($this->otherCompany, (int) $this->otherManager->id, 'M. Autre Tenant'),
        );

        $policy = app(EduTimetableSlotPolicy::class);

        // Gestionnaire : CRUD complet, borné au tenant.
        $this->assertTrue($policy->viewAny($this->manager));
        $this->assertTrue($policy->create($this->manager));
        $this->assertTrue($policy->view($this->manager, $ownSlot));
        $this->assertTrue($policy->update($this->manager, $ownSlot));
        $this->assertFalse($policy->view($this->manager, $otherTenantSlot));

        // Enseignant : viewAny OUI, mais limité à SES créneaux (teacher_id),
        // jamais create/update (administration).
        $this->assertTrue($policy->viewAny($this->teacherActor));
        $this->assertTrue($policy->view($this->teacherActor, $ownSlot));
        $this->assertFalse($policy->view($this->teacherActor, $otherSlot));
        $this->assertFalse($policy->create($this->teacherActor));
        $this->assertFalse($policy->update($this->teacherActor, $ownSlot));
        $this->assertFalse($policy->delete($this->teacherActor, $ownSlot));

        // Employé simple : aucun accès.
        $this->assertFalse($policy->viewAny($this->employee));
        $this->assertFalse($policy->view($this->employee, $ownSlot));
    }

    // ── EduTeacherPolicy ───────────────────────────────────────────────────

    public function test_teacher_policy_manager_only(): void
    {
        $teacher = $this->teacherModel($this->company, (int) $this->employee->id, 'M. Référentiel');
        $otherTenantTeacher = $this->teacherModel($this->otherCompany, (int) $this->otherManager->id, 'Mme Autre Tenant');
        $policy = app(EduTeacherPolicy::class);

        // Le référentiel des enseignants est géré par les gestionnaires du
        // tenant uniquement.
        $this->assertTrue($policy->viewAny($this->manager));
        $this->assertTrue($policy->view($this->manager, $teacher));
        $this->assertTrue($policy->create($this->manager));
        $this->assertTrue($policy->update($this->manager, $teacher));

        // Enseignant : ne gère pas le référentiel — même SON propre profil.
        /** @var EduTeacher $ownTeacherRow */
        $ownTeacherRow = EduTeacher::query()->whereKey($this->teacherId)->firstOrFail();
        $this->assertFalse($policy->viewAny($this->teacherActor));
        $this->assertFalse($policy->view($this->teacherActor, $ownTeacherRow));
        $this->assertFalse($policy->viewAny($this->employeeTeacher));

        // Employé simple : aucun accès.
        $this->assertFalse($policy->viewAny($this->employee));
        $this->assertFalse($policy->view($this->employee, $teacher));

        // Cross-tenant : jamais le référentiel d'un autre tenant.
        $this->assertFalse($policy->view($this->manager, $otherTenantTeacher));
        $this->assertFalse($policy->delete($this->manager, $otherTenantTeacher));
    }

    // ── Audit ──────────────────────────────────────────────────────────────

    public function test_audit_log_traces_school_activation_with_company_id(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['rh' => true, 'documents' => true, 'notifications' => true],
        ]);

        app(SolutionActivator::class)->activate($company, 'edumanager');

        // L'activation de la solution scolaire est tracée avec company_id —
        // l'audit des mutations scolaires (créations/modifications) sera
        // câblé avec l'API EDU-010 (cf. EDUMANAGER_RBAC_MATRIX.md §5).
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'solution.activated',
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function student(Company $company, string $number): EduStudent
    {
        /** @var EduStudent $student */
        $student = EduStudent::query()->create([
            'company_id' => $company->id,
            'student_number' => $number,
            'display_name' => $this->faker->name(),
        ]);

        return $student;
    }

    private function guardian(Company $company, Employee $employee): EduGuardian
    {
        /** @var EduGuardian $guardian */
        $guardian = EduGuardian::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
        ]);

        return $guardian;
    }

    private function linkGuardian(Employee $guardianActor, EduStudent $student, bool $canViewGrades = false): EduStudentGuardian
    {
        $guardian = $this->guardian($this->company, $guardianActor);

        /** @var EduStudentGuardian $link */
        $link = EduStudentGuardian::query()->create([
            'company_id' => $this->company->id,
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'can_view_grades' => $canViewGrades,
        ]);

        return $link;
    }

    private function teacherRow(Company $company, int $employeeId, string $displayName): int
    {
        return (int) $this->teacherModel($company, $employeeId, $displayName)->id;
    }

    private function teacherModel(Company $company, int $employeeId, string $displayName): EduTeacher
    {
        /** @var EduTeacher $teacher */
        $teacher = EduTeacher::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeId,
            'display_name' => $displayName,
            'status' => EduTeacher::STATUS_ACTIVE,
        ]);

        return $teacher;
    }

    private function yearId(Company $company): int
    {
        DB::table('edu_academic_years')->insertOrIgnore([
            'company_id' => $company->id,
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
        ]);

        /** @var int|string|null $yearId */
        $yearId = DB::table('edu_academic_years')
            ->where('company_id', $company->id)
            ->where('name', '2025-2026')
            ->value('id');

        return (int) $yearId;
    }

    private function classId(Company $company, string $name = '6AP'): int
    {
        $yearId = $this->yearId($company);

        DB::table('edu_classes')->insertOrIgnore([
            'company_id' => $company->id,
            'academic_year_id' => $yearId,
            'name' => $name,
        ]);

        /** @var int|string|null $classId */
        $classId = DB::table('edu_classes')
            ->where('company_id', $company->id)
            ->where('name', $name)
            ->value('id');

        return (int) $classId;
    }

    private function subjectId(Company $company): int
    {
        DB::table('edu_subjects')->insertOrIgnore([
            'company_id' => $company->id,
            'code' => 'MATH',
            'name' => 'Mathématiques',
        ]);

        /** @var int|string|null $subjectId */
        $subjectId = DB::table('edu_subjects')
            ->where('company_id', $company->id)
            ->where('code', 'MATH')
            ->value('id');

        return (int) $subjectId;
    }

    private function slot(
        Company $company,
        int $classId,
        int $subjectId,
        int $teacherId,
        string $start = '08:00:00',
        string $end = '09:00:00',
    ): EduTimetableSlot {
        /** @var EduTimetableSlot $slot */
        $slot = EduTimetableSlot::query()->create([
            'company_id' => $company->id,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,
            'day_of_week' => EduTimetableSlot::DAY_MONDAY,
            'start_time' => $start,
            'end_time' => $end,
        ]);

        return $slot;
    }

    private function assessment(Company $company, int $classId, int $subjectId, int $yearId, string $title): EduAssessment
    {
        /** @var EduAssessment $assessment */
        $assessment = EduAssessment::query()->create([
            'company_id' => $company->id,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'academic_year_id' => $yearId,
            'title' => $title,
            'assessment_type' => EduAssessment::TYPE_TEST,
            'max_score' => 20,
            'coefficient' => 1,
            'assessment_date' => '2026-10-05',
            'status' => EduAssessment::STATUS_DRAFT,
            'created_by' => (int) $this->manager->id,
        ]);

        return $assessment;
    }

    private function grade(Company $company, EduAssessment $assessment, EduStudent $student, string $status): EduGrade
    {
        /** @var EduGrade $grade */
        $grade = EduGrade::query()->create([
            'company_id' => $company->id,
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'score' => 15.5,
            'status' => $status,
        ]);

        return $grade;
    }

    private function reportCard(Company $company, EduStudent $student, int $classId, int $yearId): EduReportCard
    {
        /** @var EduReportCard $card */
        $card = EduReportCard::query()->create([
            'company_id' => $company->id,
            'student_id' => $student->id,
            'class_id' => $classId,
            'academic_year_id' => $yearId,
            'period_label' => 'S1',
            'period_start' => '2026-09-01',
            'period_end' => '2027-01-31',
            'data' => ['subjects' => [], 'grade_count' => 0],
            'status' => EduReportCard::STATUS_DRAFT,
        ]);

        return $card;
    }

    private function admission(Company $company, string $number): EduAdmission
    {
        /** @var EduAdmission $admission */
        $admission = EduAdmission::query()->create([
            'company_id' => $company->id,
            'admission_number' => $number,
            'applicant_name' => $this->faker->name(),
            'status' => EduAdmission::STATUS_PENDING,
            'consent_marketing' => false,
        ]);

        return $admission;
    }

    private function attendanceRecord(Company $company, int $classId, EduStudent $student): EduAttendanceRecord
    {
        /** @var EduAttendanceRecord $record */
        $record = EduAttendanceRecord::query()->create([
            'company_id' => $company->id,
            'class_id' => $classId,
            'student_id' => $student->id,
            'attendance_date' => '2026-10-05',
            'status' => EduAttendanceRecord::STATUS_PRESENT,
        ]);

        return $record;
    }
}
