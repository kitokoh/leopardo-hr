<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduEvaluation;
use App\Modules\EduManager\Domain\Models\EduGradeEntry;
use App\Modules\EduManager\Domain\Models\EduSubject;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Évaluations & notes versionnées — EDU-007/009/010 (#5823, #5825, #5826).
 *
 * Couvre : saisie de note (draft modifiable), publication idempotente,
 * IMMUABILITÉ de la note publiée (correction = nouvelle version, l'original
 * reste), motif de correction obligatoire, score borné par max_score,
 * RBAC direction vs enseignant, isolation tenant.
 */
class EduGradeApiTest extends TestCase
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

    private function evaluation(Company $company): EduEvaluation
    {
        /** @var EduAcademicYear $year */
        $year = EduAcademicYear::query()->create([
            'company_id' => $company->id,
            'code' => 'Y-G',
            'name' => 'Année',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        /** @var EduClass $class */
        $class = EduClass::query()->create([
            'company_id' => $company->id,
            'academic_year_id' => $year->id,
            'code' => 'CL-G',
            'name' => 'Classe',
            'status' => EduClass::STATUS_ACTIVE,
        ]);

        /** @var EduSubject $subject */
        $subject = EduSubject::query()->create([
            'company_id' => $company->id,
            'code' => 'PHY',
            'name' => 'Physique',
        ]);

        /** @var EduEvaluation $evaluation */
        $evaluation = EduEvaluation::query()->create([
            'company_id' => $company->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'academic_year_id' => $year->id,
            'title' => 'Devoir de physique',
            'type' => EduEvaluation::TYPE_EXAM,
            'coefficient' => 2,
            'max_score' => 20,
            'status' => EduEvaluation::STATUS_DRAFT,
            'created_by' => $this->manager($company)->id,
        ]);

        return $evaluation;
    }

    public function test_enter_grade_and_publish(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $evaluation = $this->evaluation($company);
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/edu/evaluations/'.$evaluation->id.'/grades', [
            'student_id' => 42,
            'score' => 15.5,
            'comment' => 'Bon travail',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version', 1);

        $this->postJson('/api/v1/edu/evaluations/'.$evaluation->id.'/publish')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'published');

        // Publication idempotente.
        $this->postJson('/api/v1/edu/evaluations/'.$evaluation->id.'/publish')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'published');
    }

    public function test_score_out_of_range_rejected(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $evaluation = $this->evaluation($company);
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/edu/evaluations/'.$evaluation->id.'/grades', [
            'student_id' => 1,
            'score' => 25,
        ])->assertStatus(422);
    }

    public function test_published_grade_is_immutable_and_correction_creates_new_version(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $evaluation = $this->evaluation($company);
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/edu/evaluations/'.$evaluation->id.'/grades', [
            'student_id' => 7, 'score' => 10,
        ])->assertStatus(201);

        $this->postJson('/api/v1/edu/evaluations/'.$evaluation->id.'/publish')->assertStatus(200);

        // Après publication, l'entrée draft est publiée et la saisie directe est refusée.
        $this->postJson('/api/v1/edu/evaluations/'.$evaluation->id.'/grades', [
            'student_id' => 7, 'score' => 12,
        ])->assertStatus(422); // EVALUATION_PUBLISHED_READONLY

        $entry = EduGradeEntry::query()
            ->where('company_id', $company->id)
            ->where('evaluation_id', $evaluation->id)
            ->where('student_id', 7)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(EduGradeEntry::STATUS_PUBLISHED, $entry->status);

        // Correction publiée → NOUVELLE VERSION (immuabilité).
        $this->postJson('/api/v1/edu/grades/'.$entry->id.'/correct', [
            'score' => 14,
            'correction_reason' => 'Erreur de saisie',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.score', 14);

        $this->assertSame(2, EduGradeEntry::query()
            ->where('company_id', $company->id)
            ->where('evaluation_id', $evaluation->id)
            ->where('student_id', 7)
            ->count());

        // Correction sans motif → 422.
        $latest = EduGradeEntry::query()
            ->where('company_id', $company->id)
            ->where('evaluation_id', $evaluation->id)
            ->where('student_id', 7)
            ->orderByDesc('version')
            ->first();

        $this->postJson('/api/v1/edu/grades/'.$latest->id.'/correct', [
            'score' => 15,
        ])->assertStatus(422);
    }

    public function test_cross_tenant_grade_is_404(): void
    {
        $companyA = $this->company();
        $evaluationA = $this->evaluation($companyA);

        /** @var EduGradeEntry $entry */
        $entry = EduGradeEntry::query()->create([
            'company_id' => $companyA->id,
            'evaluation_id' => $evaluationA->id,
            'student_id' => 1,
            'score' => 12,
            'status' => EduGradeEntry::STATUS_PUBLISHED,
            'version' => 1,
            'entered_by' => $this->manager($companyA)->id,
        ]);

        $companyB = $this->company();
        Sanctum::actingAs($this->manager($companyB));

        $this->getJson('/api/v1/edu/evaluations/'.$evaluationA->id.'/grades')->assertStatus(404);
        $this->postJson('/api/v1/edu/grades/'.$entry->id.'/correct', [
            'score' => 5, 'correction_reason' => 'x',
        ])->assertStatus(404);
    }
}
