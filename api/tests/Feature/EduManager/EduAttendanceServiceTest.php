<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduAttendanceCorrection;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Infrastructure\Services\EduAcademicYearService;
use App\Modules\EduManager\Infrastructure\Services\EduAttendanceService;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5821 (EDU-005) — présence scolaire.
 *
 * Verrouille : schéma tenant, saisie idempotente, statut allowlisté,
 * correction versionnée (journal), périmètre enseignant (classes référentes
 * + enseignées), isolation tenant (classe d'un autre tenant → 404).
 */
class EduAttendanceServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $managerA;

    private Employee $teacherA;

    private EduClass $classA;

    private EduStudent $studentA;

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

        /** @var Employee $teacherA */
        $teacherA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->teacherA = $teacherA;

        $yearService = app(EduAcademicYearService::class);
        /** @var EduAcademicYear $year */
        $year = $yearService->createYear($managerA, [
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
        ]);

        /** @var EduClass $classA */
        $classA = $yearService->createClass($managerA, [
            'campus_id' => 1,
            'academic_year_id' => (int) $year->getAttribute('id'),
            'code' => 'CL-1',
            'name' => '6ème A',
            'teacher_id' => (int) $teacherA->getAttribute('id'),
            'capacity' => 30,
        ]);
        $this->classA = $classA;

        /** @var EduStudent $studentA */
        $studentA = EduStudent::query()->create([
            'company_id' => $companyA->id,
            'student_number' => 'STU-A-1',
            'display_name' => 'Élève A',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
        $this->studentA = $studentA;
    }

    public function test_attendance_tables_exist_in_tenant_schema(): void
    {
        $this->assertTrue(Schema::hasTable('edu_attendances'));
        $this->assertTrue(Schema::hasTable('edu_attendance_corrections'));
    }

    public function test_record_is_idempotent(): void
    {
        $service = app(EduAttendanceService::class);

        $first = $service->record($this->teacherA, $this->classA, [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'attendance_date' => '2026-09-07',
            'status' => EduAttendance::STATUS_PRESENT,
        ]);
        $second = $service->record($this->teacherA, $this->classA, [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'attendance_date' => '2026-09-07',
            'status' => EduAttendance::STATUS_PRESENT,
        ]);

        $this->assertSame((int) $first->getAttribute('id'), (int) $second->getAttribute('id'));
        $this->assertSame(1, EduAttendance::query()->where('company_id', $this->companyA->id)->count());
    }

    public function test_record_rejects_unknown_status(): void
    {
        $this->expectExceptionMessage('EDU_ATTENDANCE_STATUS');

        app(EduAttendanceService::class)->correct($this->teacherA, new EduAttendance([
            'company_id' => $this->companyA->id,
            'status' => EduAttendance::STATUS_PRESENT,
        ]), ['status' => 'vampire']);
    }

    public function test_correction_is_versioned_in_journal(): void
    {
        $service = app(EduAttendanceService::class);
        $attendance = $service->record($this->teacherA, $this->classA, [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'attendance_date' => '2026-09-07',
            'status' => EduAttendance::STATUS_PRESENT,
        ]);

        $corrected = $service->correct($this->teacherA, $attendance, [
            'status' => EduAttendance::STATUS_ABSENT,
            'reason' => 'Oubli de pointage',
        ]);

        $this->assertSame(EduAttendance::STATUS_ABSENT, $corrected->status);
        $this->assertSame(1, EduAttendanceCorrection::query()->where('company_id', $this->companyA->id)->count());

        $correction = EduAttendanceCorrection::query()->firstOrFail();
        $this->assertSame(EduAttendance::STATUS_PRESENT, $correction->previous_status);
        $this->assertSame(EduAttendance::STATUS_ABSENT, $correction->new_status);
        $this->assertSame((int) $this->teacherA->getAttribute('id'), (int) $correction->corrected_by);
    }

    public function test_same_status_correction_is_noop(): void
    {
        $service = app(EduAttendanceService::class);
        $attendance = $service->record($this->teacherA, $this->classA, [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'attendance_date' => '2026-09-07',
            'status' => EduAttendance::STATUS_PRESENT,
        ]);

        $service->correct($this->teacherA, $attendance, ['status' => EduAttendance::STATUS_PRESENT]);

        $this->assertSame(0, EduAttendanceCorrection::query()->where('company_id', $this->companyA->id)->count());
    }

    public function test_teacher_class_query_only_returns_own_classes(): void
    {
        $service = app(EduAttendanceService::class);
        $classIds = $service->teacherClassQuery($this->teacherA)->pluck('id');

        $this->assertTrue($classIds->contains((int) $this->classA->getAttribute('id')));

        // Classe d'un autre enseignant → exclue.
        /** @var Employee $otherTeacher */
        $otherTeacher = Employee::factory()->create(['company_id' => $this->companyA->id]);
        $otherIds = $service->teacherClassQuery($otherTeacher)->pluck('id');
        $this->assertFalse($otherIds->contains((int) $this->classA->getAttribute('id')));
    }

    public function test_record_rejects_other_tenant_class(): void
    {
        /** @var Employee $managerB */
        $managerB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $yearService = app(EduAcademicYearService::class);
        /** @var EduAcademicYear $yearB */
        $yearB = $yearService->createYear($managerB, [
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
        ]);
        /** @var EduClass $classB */
        $classB = $yearService->createClass($managerB, [
            'campus_id' => 2,
            'academic_year_id' => (int) $yearB->getAttribute('id'),
            'code' => 'CL-B1',
            'name' => '6ème B',
        ]);

        $this->expectExceptionCode(404);

        app(EduAttendanceService::class)->record($this->teacherA, $classB, [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'attendance_date' => '2026-09-07',
            'status' => EduAttendance::STATUS_PRESENT,
        ]);
    }
}
