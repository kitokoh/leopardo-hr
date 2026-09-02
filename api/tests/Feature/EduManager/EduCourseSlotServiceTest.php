<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduCourseSlot;
use App\Modules\EduManager\Domain\Models\EduSubject;
use App\Modules\EduManager\Infrastructure\Services\EduAcademicYearService;
use App\Modules\EduManager\Infrastructure\Services\EduCourseSlotService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5822 (EDU-006) — emplois du temps : créneaux et conflits.
 *
 * Verrouille : schéma tenant, conflit de classe refusé, conflit
 * d'enseignant refusé, même matière même créneau OK (remplacement), période
 * incohérente refusée, CHECK day_of_week, isolation tenant (404).
 */
class EduCourseSlotServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Employee $managerA;

    private Employee $teacherA;

    private Employee $teacherB;

    private EduClass $classA;

    private EduSubject $math;

    private EduSubject $physics;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

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

        /** @var Employee $teacherB */
        $teacherB = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->teacherB = $teacherB;

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
        ]);
        $this->classA = $classA;

        /** @var EduSubject $math */
        $math = EduSubject::query()->create([
            'company_id' => $companyA->id,
            'code' => 'MATH',
            'name' => 'Mathématiques',
        ]);
        $this->math = $math;

        /** @var EduSubject $physics */
        $physics = EduSubject::query()->create([
            'company_id' => $companyA->id,
            'code' => 'PHY',
            'name' => 'Physique',
        ]);
        $this->physics = $physics;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function slotPayload(array $overrides = []): array
    {
        return array_merge([
            'class_id' => (int) $this->classA->getAttribute('id'),
            'subject_id' => (int) $this->math->getAttribute('id'),
            'academic_year_id' => 1,
            'teacher_id' => (int) $this->teacherA->getAttribute('id'),
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '09:00',
        ], $overrides);
    }

    public function test_course_slots_table_exists_in_tenant_schema(): void
    {
        $this->assertTrue(Schema::hasTable('edu_course_slots'));
    }

    public function test_class_conflict_is_rejected(): void
    {
        $service = app(EduCourseSlotService::class);
        $service->create($this->managerA, $this->slotPayload());

        $this->expectExceptionMessage('EDU_COURSE_SLOT_CLASS_CONFLICT');

        $service->create($this->managerA, $this->slotPayload([
            'subject_id' => (int) $this->physics->getAttribute('id'),
            'start_time' => '08:30', // chevauche 08:00-09:00
        ]));
    }

    public function test_teacher_conflict_is_rejected(): void
    {
        $service = app(EduCourseSlotService::class);
        $service->create($this->managerA, $this->slotPayload());

        $this->expectExceptionMessage('EDU_COURSE_SLOT_TEACHER_CONFLICT');

        $service->create($this->managerA, $this->slotPayload([
            'teacher_id' => (int) $this->teacherA->getAttribute('id'),
            'class_id' => 99, // classe différente (id inexistant, mais le conflit est détecté avant FK)
        ]));
    }

    public function test_other_teacher_same_class_conflict(): void
    {
        $service = app(EduCourseSlotService::class);
        $service->create($this->managerA, $this->slotPayload());

        $this->expectExceptionMessage('EDU_COURSE_SLOT_CLASS_CONFLICT');

        $service->create($this->managerA, $this->slotPayload([
            'teacher_id' => (int) $this->teacherB->getAttribute('id'),
            'subject_id' => (int) $this->physics->getAttribute('id'),
        ]));
    }

    public function test_same_subject_same_slot_is_allowed(): void
    {
        $service = app(EduCourseSlotService::class);
        $first = $service->create($this->managerA, $this->slotPayload());

        // Même classe + même matière + même créneau → pas un conflit (rejeu).
        $second = $service->create($this->managerA, $this->slotPayload());

        $this->assertNotSame((int) $first->getAttribute('id'), (int) $second->getAttribute('id'));
        $this->assertSame(2, EduCourseSlot::query()->where('company_id', $this->companyA->id)->count());
    }

    public function test_incoherent_period_is_rejected(): void
    {
        $this->expectExceptionMessage('EDU_COURSE_SLOT_PERIOD');

        app(EduCourseSlotService::class)->create($this->managerA, $this->slotPayload([
            'start_time' => '10:00',
            'end_time' => '09:00',
        ]));
    }

    public function test_day_of_week_is_bounded(): void
    {
        $this->expectException(QueryException::class);

        DB::transaction(function (): void {
            app(EduCourseSlotService::class)->create($this->managerA, $this->slotPayload([
                'day_of_week' => 7,
            ]));
        });
    }

    public function test_other_tenant_class_is_rejected(): void
    {
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        /** @var Employee $managerB */
        $managerB = Employee::factory()->create([
            'company_id' => $companyB->id,
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

        // Classe B avec la matière A : la FK composite échouera (cross-tenant).
        try {
            DB::transaction(function () use ($classB): void {
                EduCourseSlot::query()->create([
                    'company_id' => $this->companyA->id,
                    'class_id' => (int) $classB->getAttribute('id'),
                    'subject_id' => (int) $this->math->getAttribute('id'),
                    'academic_year_id' => 1,
                    'day_of_week' => 1,
                    'start_time' => '08:00',
                    'end_time' => '09:00',
                ]);
            });
            $this->fail('La FK composite edu_course_slots_class_company_fk aurait dû rejeter la référence cross-tenant.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('edu_course_slots_class_company_fk', $exception->getMessage());
        }
    }
}
