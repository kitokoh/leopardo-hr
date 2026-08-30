<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAttendanceRecord;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduSubject;
use App\Modules\EduManager\Domain\Models\EduTeacher;
use App\Modules\EduManager\Domain\Models\EduTeacherAssignment;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Présence scolaire — EDU-005/009/010 (issues #5821, #5825, #5826).
 *
 * Couvre : enregistrement idempotent (une présence par élève/séance),
 * motif obligatoire pour absent, corrections VERSIONNÉES + audit
 * append-only, RBAC enseignant (ses classes uniquement), isolation tenant.
 */
class EduAttendanceApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        return $company;
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    private function employee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        return $employee;
    }

    private function year(Company $company): EduAcademicYear
    {
        /** @var EduAcademicYear $year */
        $year = EduAcademicYear::query()->create([
            'company_id' => $company->id,
            'code' => 'Y-'.substr((string) $company->id, 0, 6),
            'name' => 'Année test',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        return $year;
    }

    private function class(Company $company, EduAcademicYear $year, string $code = 'CL-A'): EduClass
    {
        /** @var EduClass $class */
        $class = EduClass::query()->create([
            'company_id' => $company->id,
            'academic_year_id' => $year->id,
            'code' => $code,
            'name' => 'Classe '.$code,
            'status' => EduClass::STATUS_ACTIVE,
        ]);

        return $class;
    }

    private function teacherOf(Company $company, EduClass $class, Employee $employee): EduTeacher
    {
        /** @var EduTeacher $teacher */
        $teacher = EduTeacher::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
        ]);

        EduTeacherAssignment::query()->create([
            'company_id' => $company->id,
            'class_id' => $class->id,
            'subject_id' => EduSubject::query()->create([
                'company_id' => $company->id,
                'code' => 'MATH',
                'name' => 'Mathématiques',
            ])->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $class->academic_year_id,
            'status' => EduTeacherAssignment::STATUS_ACTIVE,
        ]);

        return $teacher;
    }

    public function test_manager_records_attendance_and_replay_updates_same_row(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $year = $this->year($company);
        $class = $this->class($company, $year);
        Sanctum::actingAs($manager);

        $payload = [
            'class_id' => $class->id,
            'student_id' => 999, // table edu_students absente sur main → garde applicative
            'session_date' => '2026-09-10',
            'status' => 'present',
        ];

        /** @var array<string, mixed> $first */
        $first = $this->postJson('/api/v1/edu/attendance', $payload)->assertStatus(201)->json('data');
        /** @var array<string, mixed> $second */
        $second = $this->postJson('/api/v1/edu/attendance', $payload)->assertStatus(201)->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, EduAttendanceRecord::query()->where('company_id', $company->id)->count());
    }

    public function test_absent_requires_reason(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $year = $this->year($company);
        $class = $this->class($company, $year);
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/edu/attendance', [
            'class_id' => $class->id,
            'student_id' => 1,
            'session_date' => '2026-09-10',
            'status' => 'absent',
        ])->assertStatus(422);

        $this->postJson('/api/v1/edu/attendance', [
            'class_id' => $class->id,
            'student_id' => 1,
            'session_date' => '2026-09-10',
            'status' => 'absent',
            'reason' => 'Maladie',
        ])->assertStatus(201);
    }

    public function test_correction_is_versioned_and_audited(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $year = $this->year($company);
        $class = $this->class($company, $year);
        Sanctum::actingAs($manager);

        /** @var array<string, mixed> $record */
        $record = $this->postJson('/api/v1/edu/attendance', [
            'class_id' => $class->id,
            'student_id' => 7,
            'session_date' => '2026-09-11',
            'status' => 'present',
        ])->assertStatus(201)->json('data');

        $this->postJson('/api/v1/edu/attendance/'.$record['id'].'/correct', [
            'status' => 'absent',
            'reason' => 'Maladie confirmée',
            'correction_reason' => 'Justificatif reçu',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'absent')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.previous_status', 'present');

        // Audit append-only.
        $audit = DB::table('edu_attendance_corrections')
            ->where('company_id', $company->id)
            ->where('record_id', (int) $record['id'])
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('present', $audit->from_status);
        $this->assertSame('absent', $audit->to_status);
    }

    public function test_teacher_only_manages_own_classes(): void
    {
        $company = $this->company();
        $year = $this->year($company);
        $classA = $this->class($company, $year, 'CL-A');
        $classB = $this->class($company, $year, 'CL-B');
        $teacherEmployee = $this->employee($company);
        $this->teacherOf($company, $classA, $teacherEmployee);

        Sanctum::actingAs($teacherEmployee);

        // Présence dans SA classe : autorisée.
        $this->postJson('/api/v1/edu/attendance', [
            'class_id' => $classA->id,
            'student_id' => 1,
            'session_date' => '2026-09-12',
            'status' => 'late',
            'reason' => 'Retard bus',
        ])->assertStatus(201);

        // Présence dans une AUTRE classe : 403 (policy enseignant).
        $this->postJson('/api/v1/edu/attendance', [
            'class_id' => $classB->id,
            'student_id' => 1,
            'session_date' => '2026-09-12',
            'status' => 'present',
        ])->assertStatus(403);
    }

    public function test_cross_tenant_attendance_is_404(): void
    {
        $companyA = $this->company();
        $yearA = $this->year($companyA);
        $classA = $this->class($companyA, $yearA);

        /** @var EduAttendanceRecord $record */
        $record = EduAttendanceRecord::query()->create([
            'company_id' => $companyA->id,
            'class_id' => $classA->id,
            'student_id' => 1,
            'session_date' => '2026-09-10',
            'status' => 'present',
            'recorded_by' => $this->manager($companyA)->id,
        ]);

        $companyB = $this->company();
        Sanctum::actingAs($this->manager($companyB));

        $this->postJson('/api/v1/edu/attendance/'.$record->id.'/correct', [
            'status' => 'absent', 'reason' => 'x', 'correction_reason' => 'x',
        ])->assertStatus(404);
    }
}
