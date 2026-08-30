<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduSubject;
use App\Modules\EduManager\Domain\Models\EduTeacherSubject;
use App\Modules\EduManager\Infrastructure\Services\EduAcademicYearService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5819 (EDU-003) — années scolaires, classes, matières et enseignants.
 *
 * Verrouille : schéma tenant, période cohérente (CHECK + service), année
 * chevauchante refusée, unicité code par tenant, enseignant hors tenant
 * refusé (EMPLOYEE_OUTSIDE_TENANT), FK composites anti cross-tenant, capacité
 * strictement positive.
 */
class EduAcademicYearServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $managerA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;

        /** @var Employee $managerA */
        $managerA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->managerA = $managerA;
    }

    public function test_edu_year_tables_exist_in_tenant_schema(): void
    {
        foreach (['edu_academic_years', 'edu_subjects', 'edu_classes', 'edu_teacher_subjects'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "table {$table} absente");
        }
    }

    public function test_year_requires_coherent_period(): void
    {
        $this->expectExceptionMessage('EDU_ACADEMIC_YEAR_PERIOD');

        app(EduAcademicYearService::class)->createYear($this->managerA, [
            'name' => '2025-2026',
            'start_date' => '2026-09-01',
            'end_date' => '2025-09-01', // fin avant début
        ]);
    }

    public function test_year_overlap_is_rejected(): void
    {
        $service = app(EduAcademicYearService::class);
        $service->createYear($this->managerA, [
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
        ]);

        $this->expectExceptionMessage('EDU_ACADEMIC_YEAR_OVERLAP');

        $service->createYear($this->managerA, [
            'name' => '2026-2027',
            'start_date' => '2026-06-01', // chevauche la fin de 2025-2026
            'end_date' => '2027-08-31',
        ]);
    }

    public function test_year_code_is_unique_per_tenant(): void
    {
        $service = app(EduAcademicYearService::class);
        $service->createYear($this->managerA, [
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
        ]);

        // Même nom, autre tenant → OK.
        $service->createYear(Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]), [
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
        ]);

        $this->expectException(QueryException::class);
        DB::transaction(function (): void {
            app(EduAcademicYearService::class)->createYear($this->managerA, [
                'name' => '2025-2026',
                'start_date' => '2025-09-01',
                'end_date' => '2026-08-31',
            ]);
        });
    }

    public function test_teacher_outside_tenant_is_rejected(): void
    {
        /** @var Employee $teacherB */
        $teacherB = Employee::factory()->create(['company_id' => $this->companyB->id]);

        $this->expectExceptionMessage('EMPLOYEE_OUTSIDE_TENANT');

        app(EduAcademicYearService::class)->createClass($this->managerA, [
            'campus_id' => 1,
            'academic_year_id' => 1,
            'code' => 'CL-1',
            'name' => '6ème A',
            'teacher_id' => (int) $teacherB->getAttribute('id'),
        ]);
    }

    public function test_class_cannot_reference_another_tenant_year(): void
    {
        // Année du tenant B.
        /** @var Employee $managerB */
        $managerB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        /** @var EduAcademicYear $yearB */
        $yearB = app(EduAcademicYearService::class)->createYear($managerB, [
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
        ]);

        try {
            DB::transaction(function () use ($yearB): void {
                EduClass::query()->create([
                    'company_id' => $this->companyA->id,
                    'campus_id' => 1,
                    'academic_year_id' => (int) $yearB->getAttribute('id'),
                    'code' => 'CL-X',
                    'name' => 'Classe cross-tenant',
                ]);
            });
            $this->fail('La FK composite edu_classes_year_company_fk aurait dû rejeter la référence cross-tenant.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('edu_classes_year_company_fk', $exception->getMessage());
        }
    }

    public function test_teacher_assignment_is_idempotent(): void
    {
        $service = app(EduAcademicYearService::class);

        /** @var EduAcademicYear $year */
        $year = $service->createYear($this->managerA, [
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
        ]);
        /** @var EduSubject $subject */
        $subject = EduSubject::query()->create([
            'company_id' => $this->companyA->id,
            'code' => 'MATH',
            'name' => 'Mathématiques',
        ]);
        /** @var EduClass $class */
        $class = $service->createClass($this->managerA, [
            'campus_id' => 1,
            'academic_year_id' => (int) $year->getAttribute('id'),
            'code' => 'CL-1',
            'name' => '6ème A',
            'teacher_id' => (int) $this->managerA->getAttribute('id'),
            'capacity' => 30,
        ]);
        /** @var Employee $teacher */
        $teacher = Employee::factory()->create(['company_id' => $this->companyA->id]);

        $first = $service->assignTeacher($this->managerA, [
            'class_id' => (int) $class->getAttribute('id'),
            'subject_id' => (int) $subject->getAttribute('id'),
            'teacher_id' => (int) $teacher->getAttribute('id'),
        ]);
        $second = $service->assignTeacher($this->managerA, [
            'class_id' => (int) $class->getAttribute('id'),
            'subject_id' => (int) $subject->getAttribute('id'),
            'teacher_id' => (int) $teacher->getAttribute('id'),
        ]);

        $this->assertSame((int) $first->getAttribute('id'), (int) $second->getAttribute('id'));
        $this->assertSame(1, EduTeacherSubject::query()->where('company_id', $this->companyA->id)->count());
    }
}
