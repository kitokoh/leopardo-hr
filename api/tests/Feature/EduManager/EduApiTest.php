<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API EduManager — EDU-010 (issue #5826).
 *
 * Couvre : auth 401, solution inactive 403 (fail-closed), employé lambda
 * 403, flux direction complet (campus → année → matière → classe → élève →
 * admission → conversion → présence → créneau → évaluation → note →
 * bulletin), corrections versionnées, isolation cross-tenant 404.
 */
class EduApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $lambdaA;

    private function baseUrl(): string
    {
        return '/api/v1/edu-manager';
    }

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['edumanager' => true],
        ]);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['edumanager' => true],
        ]);
        $this->companyB = $companyB;

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principalA = $principalA;

        /** @var Employee $lambdaA */
        $lambdaA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->lambdaA = $lambdaA;
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson($this->baseUrl().'/campuses')->assertStatus(401);
        $this->postJson($this->baseUrl().'/admissions', [])->assertStatus(401);
    }

    public function test_inactive_solution_gets_403(): void
    {
        /** @var Company $inactive */
        $inactive = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'features' => []]);
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $inactive->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($manager);

        $this->getJson($this->baseUrl().'/campuses')->assertStatus(403)->assertJsonPath('error', 'EDU_SOLUTION_INACTIVE');
    }

    public function test_plain_employee_gets_403(): void
    {
        Sanctum::actingAs($this->lambdaA);

        $this->getJson($this->baseUrl().'/campuses')->assertStatus(403);
        $this->postJson($this->baseUrl().'/academic-years', [
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
        ])->assertStatus(403);
        $this->getJson($this->baseUrl().'/admissions')->assertStatus(403);
    }

    public function test_full_flow_from_campus_to_report_card(): void
    {
        Sanctum::actingAs($this->principalA);
        $url = $this->baseUrl();

        // Campus
        $campusId = $this->postJson($url.'/campuses', [
            'code' => 'CAMPUS-01',
            'name' => 'Campus Principal',
            'timezone' => 'Africa/Algiers',
        ])->assertStatus(201)->json('data.id');

        // Année scolaire
        $yearId = $this->postJson($url.'/academic-years', [
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
        ])->assertStatus(201)->json('data.id');

        // Matière
        $subjectId = $this->postJson($url.'/subjects', [
            'code' => 'MATH',
            'name' => 'Mathématiques',
            'default_coefficient' => 2,
        ])->assertStatus(201)->json('data.id');

        // Enseignant (employé RH du même tenant)
        /** @var Employee $teacher */
        $teacher = Employee::factory()->create(['company_id' => $this->companyA->id]);

        // Classe
        $classId = $this->postJson($url.'/classes', [
            'campus_id' => $campusId,
            'academic_year_id' => $yearId,
            'code' => 'CL-6A',
            'name' => '6ème A',
            'teacher_id' => (int) $teacher->getAttribute('id'),
            'capacity' => 30,
        ])->assertStatus(201)->json('data.id');

        // Affectation enseignant → matière
        $this->postJson($url."/classes/{$classId}/teachers", [
            'subject_id' => $subjectId,
            'teacher_id' => (int) $teacher->getAttribute('id'),
        ])->assertStatus(201);

        // Élève
        $studentId = $this->postJson($url.'/students', [
            'student_number' => 'STU-2025-001',
            'display_name' => 'Lina Benali',
            'birth_date' => '2014-03-12',
        ])->assertStatus(201)->json('data.id');

        // Admission + conversion (consentement)
        $admissionId = $this->postJson($url.'/admissions', [
            'academic_year_id' => $yearId,
            'campus_id' => $campusId,
            'applicant_first_name' => 'Yacine',
            'applicant_last_name' => 'Meziane',
            'applicant_email' => 'yacine@example.com',
            'applied_at' => '2026-06-01',
            'consent_contact' => true,
            'consented_at' => '2026-06-01',
            'external_id' => 'ext-full-flow-1',
        ])->assertStatus(201)->json('data.id');

        $this->postJson($url."/admissions/{$admissionId}/convert")
            ->assertStatus(201)
            ->assertJsonPath('data.student_number', fn ($v): bool => is_string($v));

        // Présence (enseignant)
        Sanctum::actingAs($teacher);
        $this->postJson($url."/classes/{$classId}/attendances", [
            'student_id' => $studentId,
            'attendance_date' => '2026-09-07',
            'status' => EduAttendance::STATUS_PRESENT,
        ])->assertStatus(201);

        // Créneau (direction)
        Sanctum::actingAs($this->principalA);
        $this->postJson($url.'/course-slots', [
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'academic_year_id' => $yearId,
            'teacher_id' => (int) $teacher->getAttribute('id'),
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '09:00',
        ])->assertStatus(201);

        // Conflit de créneau refusé (422)
        $this->postJson($url.'/course-slots', [
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'academic_year_id' => $yearId,
            'teacher_id' => (int) $teacher->getAttribute('id'),
            'day_of_week' => 1,
            'start_time' => '08:30',
            'end_time' => '09:30',
        ])->assertStatus(422)->assertJsonPath('error', 'EDU_COURSE_SLOT_TEACHER_CONFLICT');

        // Évaluation + note + publication + correction
        Sanctum::actingAs($teacher);
        $assessmentId = $this->postJson($url.'/assessments', [
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'academic_year_id' => $yearId,
            'title' => 'Devoir surveillé n°1',
            'type' => EduAssessment::TYPE_EXAM,
            'coefficient' => 2,
            'max_score' => 20,
            'assessment_date' => '2026-10-05',
        ])->assertStatus(201)->json('data.id');

        $gradeId = $this->postJson($url."/assessments/{$assessmentId}/grades", [
            'student_id' => $studentId,
            'score' => 14.5,
        ])->assertStatus(201)->json('data.id');

        $this->postJson($url."/grades/{$gradeId}/publish")->assertOk();
        $this->postJson($url."/grades/{$gradeId}/correct", [
            'score' => 15,
            'comment' => 'Erreur de report',
        ])->assertOk()->assertJsonPath('data.version', 2);

        // Note hors barème refusée (422)
        $this->postJson($url."/assessments/{$assessmentId}/grades", [
            'student_id' => $studentId,
            'score' => 21,
        ])->assertStatus(422)->assertJsonPath('error', 'EDU_GRADE_OUT_OF_RANGE');

        // Bulletin : génération → validation → publication
        Sanctum::actingAs($this->principalA);
        $cardId = $this->postJson($url.'/report-cards/generate', [
            'student_id' => $studentId,
            'academic_year_id' => $yearId,
            'period' => EduReportCard::PERIOD_TERM1,
        ])->assertStatus(201)->json('data.id');

        $this->postJson($url."/report-cards/{$cardId}/validate")->assertOk();
        $this->postJson($url."/report-cards/{$cardId}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', EduReportCard::STATUS_PUBLISHED);

        // Bulletin publié → verrouillé
        $this->postJson($url.'/report-cards/generate', [
            'student_id' => $studentId,
            'academic_year_id' => $yearId,
            'period' => EduReportCard::PERIOD_TERM1,
        ])->assertStatus(422)->assertJsonPath('error', 'EDU_REPORT_CARD_LOCKED');
    }

    public function test_cross_tenant_resource_is_404(): void
    {
        /** @var Employee $principalB */
        $principalB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($this->principalA);
        /** @var EduCampus $campusB */
        $campusB = \App\Modules\EduManager\Domain\Models\EduCampus::query()->create([
            'company_id' => $this->companyB->id,
            'code' => 'CAMPUS-B',
            'name' => 'Campus B',
        ]);

        // Le principal A ne voit pas le campus du tenant B.
        $this->getJson($this->baseUrl().'/campuses/'.$campusB->getAttribute('id'))->assertStatus(404);

        // Le principal B voit le sien.
        Sanctum::actingAs($principalB);
        $this->getJson($this->baseUrl().'/campuses/'.$campusB->getAttribute('id'))->assertOk();
    }

    public function test_admission_conversion_requires_consent(): void
    {
        Sanctum::actingAs($this->principalA);
        $url = $this->baseUrl();

        /** @var EduAcademicYear $year */
        $year = EduAcademicYear::query()->create([
            'company_id' => $this->companyA->id,
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
        ]);

        $admissionId = $this->postJson($url.'/admissions', [
            'academic_year_id' => (int) $year->getAttribute('id'),
            'applicant_first_name' => 'Sans',
            'applicant_last_name' => 'Consentement',
            'applied_at' => '2026-06-01',
            'consent_contact' => false,
        ])->assertStatus(201)->json('data.id');

        $this->postJson($url."/admissions/{$admissionId}/convert")
            ->assertStatus(422)
            ->assertJsonPath('error', 'EDU_CONSENT_REQUIRED');
    }
}
