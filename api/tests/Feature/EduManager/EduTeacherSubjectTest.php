<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduSubject;
use App\Modules\EduManager\Domain\Models\EduTeacher;
use App\Modules\EduManager\Domain\Models\EduTeacherSubject;
use App\Modules\EduManager\Domain\Policies\EduSubjectPolicy;
use App\Modules\EduManager\Domain\Policies\EduTeacherPolicy;
use App\Modules\EduManager\Domain\Policies\EduTeacherSubjectPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5819 (EDU-003) — enseignants, matières et affectations : modèles,
 * policies et invariants de schéma.
 *
 * Verrouille :
 *   1. l'affectation enseignant → matière pour une année (modèle + accès
 *      gestionnaire, policy directe) ;
 *   2. l'impossibilité STRUCTURELLE d'affecter un enseignant (ou une
 *      matière, ou une année) d'un AUTRE tenant — FK composites
 *      (X_id, company_id) → (id, company_id), violation FK en base ;
 *   3. l'unicité de l'affectation (company_id, teacher_id, subject_id,
 *      academic_year_id) ;
 *   4. `employee_id` unique PAR TENANT avec plusieurs NULL autorisés ;
 *   5. le référentiel enseignants réservé à la gestion du tenant (policy
 *      « manager only ») ; policies matières/affectations bornées au tenant ;
 *   6. CHECK `status` et archivage logique (historique conservé).
 */
class EduTeacherSubjectTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $otherCompany;

    private Employee $manager;

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
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
        ]);
        $this->manager = $manager;
    }

    public function test_manager_can_assign_teacher_to_subject_for_year(): void
    {
        $year = $this->academicYear($this->company, '2025-2026');
        $teacher = $this->teacher($this->company, 'Mme Benali');
        $subject = $this->subject($this->company, 'MATH', 'Mathématiques');

        /** @var EduTeacherSubject $assignment */
        $assignment = EduTeacherSubject::query()->create([
            'company_id' => $this->company->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'academic_year_id' => $year->id,
        ]);

        $policy = app(EduTeacherSubjectPolicy::class);

        $this->assertTrue($policy->viewAny($this->manager));
        $this->assertTrue($policy->create($this->manager));
        $this->assertTrue($policy->view($this->manager, $assignment));
        $this->assertTrue($policy->update($this->manager, $assignment));
        $this->assertTrue($policy->delete($this->manager, $assignment));

        // Relations Eloquent typées.
        $this->assertTrue($assignment->teacher->is($teacher));
        $this->assertTrue($assignment->subject->is($subject));
        $this->assertTrue($assignment->academicYear->is($year));

        $this->assertDatabaseHas('edu_teacher_subjects', [
            'company_id' => $this->company->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'academic_year_id' => $year->id,
        ]);
    }

    public function test_cross_tenant_teacher_assignment_is_rejected_by_database(): void
    {
        // Enseignant du tenant B affecté chez le tenant A : la FK composite
        // (teacher_id, company_id) doit rejeter l'insertion.
        $year = $this->academicYear($this->company, '2025-2026');
        $subject = $this->subject($this->company, 'MATH', 'Mathématiques');
        $otherTenantTeacher = $this->teacher($this->otherCompany, 'M. Dupont');

        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function () use ($year, $subject, $otherTenantTeacher): void {
            DB::table('edu_teacher_subjects')->insert([
                'company_id' => $this->company->id,
                'teacher_id' => $otherTenantTeacher->id,
                'subject_id' => $subject->id,
                'academic_year_id' => $year->id,
            ]);
        });
    }

    public function test_cross_tenant_subject_assignment_is_rejected_by_database(): void
    {
        // Matière du tenant B affectée chez le tenant A : la FK composite
        // (subject_id, company_id) doit rejeter l'insertion.
        $year = $this->academicYear($this->company, '2025-2026');
        $teacher = $this->teacher($this->company, 'Mme Benali');
        $otherTenantSubject = $this->subject($this->otherCompany, 'PHYS', 'Physique');

        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function () use ($year, $teacher, $otherTenantSubject): void {
            DB::table('edu_teacher_subjects')->insert([
                'company_id' => $this->company->id,
                'teacher_id' => $teacher->id,
                'subject_id' => $otherTenantSubject->id,
                'academic_year_id' => $year->id,
            ]);
        });
    }

    public function test_cross_tenant_academic_year_assignment_is_rejected_by_database(): void
    {
        // Année du tenant B utilisée pour une affectation chez le tenant A :
        // la FK composite (academic_year_id, company_id) doit rejeter.
        $otherTenantYear = $this->academicYear($this->otherCompany, '2024-2025');
        $teacher = $this->teacher($this->company, 'Mme Benali');
        $subject = $this->subject($this->company, 'MATH', 'Mathématiques');

        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function () use ($otherTenantYear, $teacher, $subject): void {
            DB::table('edu_teacher_subjects')->insert([
                'company_id' => $this->company->id,
                'teacher_id' => $teacher->id,
                'subject_id' => $subject->id,
                'academic_year_id' => $otherTenantYear->id,
            ]);
        });
    }

    public function test_duplicate_assignment_is_rejected(): void
    {
        $year = $this->academicYear($this->company, '2025-2026');
        $teacher = $this->teacher($this->company, 'Mme Benali');
        $subject = $this->subject($this->company, 'MATH', 'Mathématiques');

        DB::table('edu_teacher_subjects')->insert([
            'company_id' => $this->company->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'academic_year_id' => $year->id,
        ]);

        // Même affectation (enseignant + matière + année) chez le MÊME tenant : rejeté.
        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function () use ($year, $teacher, $subject): void {
            DB::table('edu_teacher_subjects')->insert([
                'company_id' => $this->company->id,
                'teacher_id' => $teacher->id,
                'subject_id' => $subject->id,
                'academic_year_id' => $year->id,
            ]);
        });
    }

    public function test_teacher_employee_id_is_unique_per_tenant(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        DB::table('edu_teachers')->insert([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'display_name' => 'Mme Benali',
        ]);

        // Même employee_id sur un AUTRE tenant : autorisé.
        DB::table('edu_teachers')->insert([
            'company_id' => $this->otherCompany->id,
            'employee_id' => $employee->id,
            'display_name' => 'Mme Benali',
        ]);

        // Plusieurs enseignants SANS employee_id (NULL) : autorisé
        // (PostgreSQL : plusieurs NULL sur une UNIQUE).
        DB::table('edu_teachers')->insert([
            'company_id' => $this->company->id,
            'display_name' => 'M. Dupont',
        ]);

        // Même employee_id sur le MÊME tenant : rejeté.
        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function () use ($employee): void {
            DB::table('edu_teachers')->insert([
                'company_id' => $this->company->id,
                'employee_id' => $employee->id,
                'display_name' => 'Doublon RH',
            ]);
        });
    }

    public function test_teacher_policy_is_manager_only(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        $own = $this->teacher($this->company, 'Mme Benali');
        $otherTenant = $this->teacher($this->otherCompany, 'M. Dupont');

        $policy = app(EduTeacherPolicy::class);

        // Le référentiel enseignants est réservé à la gestion du tenant :
        // un enseignant (employé simple) ne le gère pas.
        $this->assertFalse($policy->viewAny($employee));
        $this->assertFalse($policy->create($employee));
        $this->assertFalse($policy->view($employee, $own));

        // Gestionnaire : accès borné au tenant (→ 403/404 en API, EDU-006).
        $this->assertTrue($policy->viewAny($this->manager));
        $this->assertTrue($policy->view($this->manager, $own));
        $this->assertFalse($policy->view($this->manager, $otherTenant));
    }

    public function test_subject_policy_is_tenant_scoped(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        $own = $this->subject($this->company, 'MATH', 'Mathématiques');
        $otherTenant = $this->subject($this->otherCompany, 'PHYS', 'Physique');

        $policy = app(EduSubjectPolicy::class);

        $this->assertFalse($policy->viewAny($employee));
        $this->assertTrue($policy->view($this->manager, $own));
        $this->assertFalse($policy->view($this->manager, $otherTenant));
    }

    public function test_teacher_subject_policy_is_tenant_scoped(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        $own = $this->assignment($this->company, 'Mme Benali', 'MATH', '2025-2026');
        $otherTenant = $this->assignment($this->otherCompany, 'M. Dupont', 'PHYS', '2024-2025');

        $policy = app(EduTeacherSubjectPolicy::class);

        $this->assertFalse($policy->viewAny($employee));
        $this->assertTrue($policy->view($this->manager, $own));
        $this->assertFalse($policy->view($this->manager, $otherTenant));
    }

    public function test_teacher_status_check_rejects_unknown_status(): void
    {
        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function (): void {
            DB::table('edu_teachers')->insert([
                'company_id' => $this->company->id,
                'display_name' => 'Mme Benali',
                'status' => 'bogus-status',
            ]);
        });
    }

    public function test_subject_code_is_unique_per_tenant(): void
    {
        DB::table('edu_subjects')->insert([
            'company_id' => $this->company->id,
            'code' => 'MATH',
            'name' => 'Mathématiques',
        ]);

        // Même code sur un AUTRE tenant : autorisé.
        DB::table('edu_subjects')->insert([
            'company_id' => $this->otherCompany->id,
            'code' => 'MATH',
            'name' => 'Mathématiques',
        ]);

        // Même code sur le MÊME tenant : rejeté.
        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function (): void {
            DB::table('edu_subjects')->insert([
                'company_id' => $this->company->id,
                'code' => 'MATH',
                'name' => 'Mathématiques doublon',
            ]);
        });
    }

    public function test_archived_teacher_keeps_history(): void
    {
        $teacher = $this->teacher($this->company, 'Mme Benali');

        // Archive logique : jamais de DELETE dur (historique conservé).
        $teacher->update(['status' => EduTeacher::STATUS_ARCHIVED]);

        $this->assertSame(1, DB::table('edu_teachers')->where('company_id', $this->company->id)->count());
        $this->assertDatabaseHas('edu_teachers', [
            'company_id' => $this->company->id,
            'display_name' => 'Mme Benali',
            'status' => 'archived',
        ]);
    }

    private function academicYear(Company $company, string $name): EduAcademicYear
    {
        /** @var EduAcademicYear $year */
        $year = EduAcademicYear::query()->create([
            'company_id' => $company->id,
            'name' => $name,
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
        ]);

        return $year;
    }

    private function teacher(Company $company, string $displayName): EduTeacher
    {
        /** @var EduTeacher $teacher */
        $teacher = EduTeacher::query()->create([
            'company_id' => $company->id,
            'display_name' => $displayName,
        ]);

        return $teacher;
    }

    private function subject(Company $company, string $code, string $name): EduSubject
    {
        /** @var EduSubject $subject */
        $subject = EduSubject::query()->create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => $name,
        ]);

        return $subject;
    }

    private function assignment(Company $company, string $teacherName, string $subjectCode, string $yearName): EduTeacherSubject
    {
        $teacher = $this->teacher($company, $teacherName);
        $subject = $this->subject($company, $subjectCode, 'Matière '.$subjectCode);
        $year = $this->academicYear($company, $yearName);

        /** @var EduTeacherSubject $assignment */
        $assignment = EduTeacherSubject::query()->create([
            'company_id' => $company->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'academic_year_id' => $year->id,
        ]);

        return $assignment;
    }
}
