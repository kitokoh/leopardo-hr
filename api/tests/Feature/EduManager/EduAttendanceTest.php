<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Application\Services\AttendanceService;
use App\Modules\EduManager\Domain\Models\EduAttendanceCorrection;
use App\Modules\EduManager\Domain\Models\EduAttendanceRecord;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Policies\EduAttendanceCorrectionPolicy;
use App\Modules\EduManager\Domain\Policies\EduAttendanceRecordPolicy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5821 (EDU-005) — présence scolaire par classe/séance.
 *
 * Couvre : enregistrement de présence (manager), idempotence (un seul
 * enregistrement par élève/classe/jour), corrections VERSIONNÉES (une
 * ligne edu_attendance_corrections par correction, le record reflète la
 * dernière), refus cross-tenant (élève d'un autre tenant → 404), absence
 * justifiée (excused + reason_code sick), et policies bornées au tenant
 * (manager) / refusées aux employés ordinaires.
 */
class EduAttendanceTest extends TestCase
{
    use RefreshTenantDatabase;
    use WithFaker;

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
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->manager = $manager;
    }

    public function test_manager_can_record_presence(): void
    {
        $student = $this->student($this->company, 'S-001');
        $classId = $this->classId($this->company);

        $record = $this->service()->record([
            'company_id' => $this->company->id,
            'class_id' => $classId,
            'student_id' => $student->id,
            'attendance_date' => '2026-09-01',
            'status' => EduAttendanceRecord::STATUS_PRESENT,
            'recorded_by' => $this->manager->id,
        ]);

        $this->assertSame(EduAttendanceRecord::STATUS_PRESENT, $record->status);
        $this->assertSame($student->id, $record->student_id);
        $this->assertSame($classId, $record->class_id);
        $this->assertSame($this->company->id, $record->company_id);
        $this->assertSame($this->manager->id, $record->recorded_by);
        $this->assertSame('2026-09-01', $record->attendance_date->toDateString());

        $this->assertDatabaseHas('edu_attendance_records', [
            'company_id' => $this->company->id,
            'class_id' => $classId,
            'student_id' => $student->id,
            'attendance_date' => '2026-09-01',
            'status' => EduAttendanceRecord::STATUS_PRESENT,
        ]);
    }

    public function test_record_is_idempotent_per_student_class_date(): void
    {
        $student = $this->student($this->company, 'S-001');
        $service = $this->service();

        $classId = $this->classId($this->company);

        $payload = [
            'company_id' => $this->company->id,
            'class_id' => $classId,
            'student_id' => $student->id,
            'attendance_date' => '2026-09-01',
            'status' => EduAttendanceRecord::STATUS_PRESENT,
            'recorded_by' => $this->manager->id,
        ];

        $first = $service->record($payload);
        // Re-post identique : aucun doublon, aucun écrasement — le même
        // enregistrement est retourné (changer le statut = correct()).
        $second = $service->record($payload);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(EduAttendanceRecord::STATUS_PRESENT, $second->status);
        $this->assertSame(
            1,
            EduAttendanceRecord::query()
                ->where('company_id', $this->company->id)
                ->where('student_id', $student->id)
                ->where('attendance_date', '2026-09-01')
                ->count()
        );
    }

    public function test_correction_is_versioned_and_record_reflects_last(): void
    {
        $student = $this->student($this->company, 'S-001');
        $service = $this->service();

        $record = $service->record([
            'company_id' => $this->company->id,
            'class_id' => $this->classId($this->company),
            'student_id' => $student->id,
            'attendance_date' => '2026-09-01',
            'status' => EduAttendanceRecord::STATUS_PRESENT,
            'recorded_by' => $this->manager->id,
        ]);

        // Correction 1 : present → absent.
        $record = $service->correct($record, EduAttendanceRecord::STATUS_ABSENT, 'Retard non signalé', $this->manager->id);
        $this->assertSame(EduAttendanceRecord::STATUS_ABSENT, $record->status);

        // Correction 2 : absent → present (erreur de saisie).
        $record = $service->correct($record, EduAttendanceRecord::STATUS_PRESENT, 'Erreur de saisie', $this->manager->id);
        $this->assertSame(EduAttendanceRecord::STATUS_PRESENT, $record->status);

        // 2 corrections = 2 lignes en base (versionnage, jamais d'écrasement).
        $corrections = EduAttendanceCorrection::query()
            ->where('company_id', $this->company->id)
            ->where('attendance_record_id', $record->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $corrections);
        $this->assertSame(EduAttendanceRecord::STATUS_PRESENT, $corrections[0]->previous_status);
        $this->assertSame(EduAttendanceRecord::STATUS_ABSENT, $corrections[0]->new_status);
        $this->assertSame('Retard non signalé', $corrections[0]->reason);
        $this->assertSame($this->manager->id, $corrections[0]->corrected_by);
        $this->assertSame(EduAttendanceRecord::STATUS_ABSENT, $corrections[1]->previous_status);
        $this->assertSame(EduAttendanceRecord::STATUS_PRESENT, $corrections[1]->new_status);
        $this->assertSame('Erreur de saisie', $corrections[1]->reason);

        // Le record en base reflète la DERNIÈRE correction.
        $this->assertDatabaseHas('edu_attendance_records', [
            'id' => $record->id,
            'status' => EduAttendanceRecord::STATUS_PRESENT,
        ]);
    }

    public function test_cross_tenant_student_is_rejected(): void
    {
        // Élève d'un AUTRE tenant : jamais enregistrable chez ce tenant.
        // La vérification élève précède tout contrôle de classe dans le
        // service — le class_id ci-dessous n'est jamais atteint.
        $otherStudent = $this->student($this->otherCompany, 'S-X1');

        $this->expectException(ModelNotFoundException::class);

        $this->service()->record([
            'company_id' => $this->company->id,
            'class_id' => 1,
            'student_id' => $otherStudent->id,
            'attendance_date' => '2026-09-01',
            'status' => EduAttendanceRecord::STATUS_PRESENT,
        ]);
    }

    public function test_excused_absence_with_reason_code(): void
    {
        $student = $this->student($this->company, 'S-001');

        $record = $this->service()->record([
            'company_id' => $this->company->id,
            'class_id' => $this->classId($this->company),
            'student_id' => $student->id,
            'attendance_date' => '2026-09-02',
            'status' => EduAttendanceRecord::STATUS_EXCUSED,
            'reason_code' => 'sick',
            'note' => 'Certificat médical transmis',
        ]);

        $this->assertSame(EduAttendanceRecord::STATUS_EXCUSED, $record->status);
        $this->assertSame('sick', $record->reason_code);
        $this->assertSame('Certificat médical transmis', $record->note);

        $this->assertDatabaseHas('edu_attendance_records', [
            'company_id' => $this->company->id,
            'student_id' => $student->id,
            'attendance_date' => '2026-09-02',
            'status' => EduAttendanceRecord::STATUS_EXCUSED,
            'reason_code' => 'sick',
        ]);
    }

    public function test_record_policy_is_tenant_bound_and_manager_scoped(): void
    {
        $student = $this->student($this->company, 'S-001');
        $otherStudent = $this->student($this->otherCompany, 'S-X1');

        $record = $this->service()->record([
            'company_id' => $this->company->id,
            'class_id' => $this->classId($this->company),
            'student_id' => $student->id,
            'attendance_date' => '2026-09-01',
            'status' => EduAttendanceRecord::STATUS_PRESENT,
        ]);

        $otherRecord = $this->service()->record([
            'company_id' => $this->otherCompany->id,
            'class_id' => $this->classId($this->otherCompany),
            'student_id' => $otherStudent->id,
            'attendance_date' => '2026-09-01',
            'status' => EduAttendanceRecord::STATUS_PRESENT,
        ]);

        $policy = app(EduAttendanceRecordPolicy::class);

        $this->assertTrue($policy->viewAny($this->manager));
        $this->assertTrue($policy->view($this->manager, $record));
        $this->assertTrue($policy->update($this->manager, $record));
        // Un gestionnaire ne voit JAMAIS les présences d'un autre tenant.
        $this->assertFalse($policy->view($this->manager, $otherRecord));
        $this->assertFalse($policy->update($this->manager, $otherRecord));
    }

    public function test_plain_employee_is_not_authorized(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        $this->assertFalse(app(EduAttendanceRecordPolicy::class)->viewAny($employee));
        $this->assertFalse(app(EduAttendanceCorrectionPolicy::class)->viewAny($employee));
    }

    public function test_correction_policy_is_manager_only_and_tenant_bound(): void
    {
        $student = $this->student($this->company, 'S-001');
        $otherStudent = $this->student($this->otherCompany, 'S-X1');

        $record = $this->service()->record([
            'company_id' => $this->company->id,
            'class_id' => $this->classId($this->company),
            'student_id' => $student->id,
            'attendance_date' => '2026-09-01',
            'status' => EduAttendanceRecord::STATUS_PRESENT,
        ]);

        $this->service()->correct($record, EduAttendanceRecord::STATUS_ABSENT, 'Test', $this->manager->id);

        /** @var EduAttendanceCorrection $correction */
        $correction = EduAttendanceCorrection::query()
            ->where('company_id', $this->company->id)
            ->where('attendance_record_id', $record->id)
            ->firstOrFail();

        $otherRecord = $this->service()->record([
            'company_id' => $this->otherCompany->id,
            'class_id' => $this->classId($this->otherCompany),
            'student_id' => $otherStudent->id,
            'attendance_date' => '2026-09-01',
            'status' => EduAttendanceRecord::STATUS_PRESENT,
        ]);
        $this->service()->correct($otherRecord, EduAttendanceRecord::STATUS_ABSENT, 'Test', $this->manager->id);

        /** @var EduAttendanceCorrection $otherCorrection */
        $otherCorrection = EduAttendanceCorrection::query()
            ->where('company_id', $this->otherCompany->id)
            ->where('attendance_record_id', $otherRecord->id)
            ->firstOrFail();

        $policy = app(EduAttendanceCorrectionPolicy::class);

        $this->assertTrue($policy->viewAny($this->manager));
        $this->assertTrue($policy->create($this->manager));
        $this->assertTrue($policy->view($this->manager, $correction));
        // Correction d'un autre tenant : invisible pour ce gestionnaire.
        $this->assertFalse($policy->view($this->manager, $otherCorrection));
    }

    private function service(): AttendanceService
    {
        return app(AttendanceService::class);
    }

    /**
     * ID d'une classe du tenant — robuste à l'état du lot EDU-003 (#5819) :
     * tant que `edu_classes` n'existe pas, la FK composite n'est pas active
     * et un id arbitraire suffit ; sinon on insère la chaîne minimale
     * année scolaire → classe pour satisfaire la contrainte en base.
     */
    private function classId(Company $company): int
    {
        if (! schemaTableExists('edu_classes')) {
            return 1;
        }

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

        DB::table('edu_classes')->insertOrIgnore([
            'company_id' => $company->id,
            'academic_year_id' => $yearId,
            'name' => '6AP',
        ]);

        /** @var int|string|null $classId */
        $classId = DB::table('edu_classes')
            ->where('company_id', $company->id)
            ->where('name', '6AP')
            ->value('id');

        return (int) $classId;
    }

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
}
