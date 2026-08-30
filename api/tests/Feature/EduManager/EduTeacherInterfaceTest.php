<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * EDU-012 (#5828) — interface enseignant.
 *
 * Périmètre strictement borné aux classes de l'enseignant : consultation
 * classes/élèves/évaluations (404 sur une autre classe), soumission d'une
 * note pour validation (enseignant de la classe uniquement), validation
 * serveur (score, barème).
 */
class EduTeacherInterfaceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $teacher;

    private EduClass $myClass;

    private EduClass $otherClass;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['edumanager' => true],
        ]);
        $this->company = $company;

        /** @var Employee $teacher */
        $teacher = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'first_name' => 'Prof',
            'last_name' => 'Test',
        ]);
        $this->teacher = $teacher;
        Sanctum::actingAs($teacher);

        [$campusId, $yearId] = app(\App\Core\Tenant\TenantManager::class)->withinTenant($company, function (): array {
            $campus = EduCampus::query()->create([
                'company_id' => $this->company->id,
                'code' => 'CMP-1',
                'name' => 'Campus Principal',
            ]);
            $year = EduAcademicYear::query()->create([
                'company_id' => $this->company->id,
                'name' => '2025-2026',
                'start_date' => '2025-09-01',
                'end_date' => '2026-07-31',
                'status' => 'active',
            ]);

            return [$campus->id, $year->id];
        });

        $this->myClass = EduClass::query()->create([
            'company_id' => $company->id,
            'campus_id' => $campusId,
            'academic_year_id' => $yearId,
            'code' => 'CL-3A',
            'name' => '3A',
            'teacher_id' => $teacher->id,
        ]);

        $this->otherClass = EduClass::query()->create([
            'company_id' => $company->id,
            'campus_id' => $campusId,
            'academic_year_id' => $yearId,
            'code' => 'CL-4B',
            'name' => '4B',
            'teacher_id' => null,
        ]);
    }

    public function test_teacher_sees_only_own_classes(): void
    {
        $this->getJson('/api/v1/edu-manager/teacher/classes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'CL-3A');
    }

    public function test_teacher_cannot_view_students_of_another_class(): void
    {
        $this->getJson("/api/v1/edu-manager/teacher/classes/{$this->otherClass->id}/students")
            ->assertStatus(404);
    }

    public function test_teacher_sees_students_of_own_class_from_attendance(): void
    {
        $student = app(\App\Core\Tenant\TenantManager::class)->withinTenant($this->company, function (): EduStudent {
            $student = EduStudent::query()->create([
                'company_id' => $this->company->id,
                'student_number' => 'STU-001',
                'display_name' => 'Eleve 1',
                'status' => 'active',
            ]);
            EduAttendance::query()->create([
                'company_id' => $this->company->id,
                'class_id' => $this->myClass->id,
                'student_id' => $student->id,
                'attendance_date' => '2026-01-10',
                'status' => 'present',
            ]);

            return $student;
        });

        $this->getJson("/api/v1/edu-manager/teacher/classes/{$this->myClass->id}/students")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $student->id);
    }

    public function test_teacher_submits_grade_of_own_class(): void
    {
        $gradeId = app(\App\Core\Tenant\TenantManager::class)->withinTenant($this->company, function (): int {
            $subject = \App\Modules\EduManager\Domain\Models\EduSubject::query()->create([
                'company_id' => $this->company->id,
                'campus_id' => $this->myClass->campus_id,
                'code' => 'MAT',
                'name' => 'Mathématiques',
            ]);
            $assessment = EduAssessment::query()->create([
                'company_id' => $this->company->id,
                'class_id' => $this->myClass->id,
                'subject_id' => $subject->id,
                'academic_year_id' => $this->myClass->academic_year_id,
                'title' => 'Compo 1',
                'type' => 'exam',
                'coefficient' => '2',
                'max_score' => '20',
                'assessment_date' => '2026-02-01',
            ]);
            $student = EduStudent::query()->create([
                'company_id' => $this->company->id,
                'student_number' => 'STU-002',
                'display_name' => 'Eleve 2',
                'status' => 'active',
            ]);
            $grade = EduGrade::query()->create([
                'company_id' => $this->company->id,
                'assessment_id' => $assessment->id,
                'student_id' => $student->id,
                'score' => '15',
                'status' => 'draft',
                'graded_by' => $this->teacher->id,
            ]);

            return (int) $grade->id;
        });

        $this->postJson("/api/v1/edu-manager/teacher/grades/{$gradeId}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
    }
}
