<?php

declare(strict_types=1);

namespace Tests\Feature\Training;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\TrainingCourse;
use App\Modules\HR\Domain\Models\TrainingEnrollment;
use App\Modules\HR\Domain\Models\TrainingSession;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * QA wave 2026-08-14 — T003 (#2228).
 *
 * GET /training/sessions et GET /training/enrollments — listes globales
 * (toutes formations) scopées tenant, paginées, appelées par l'admin SPA
 * (TrainingView.vue:248-249). Avant : routes inexistantes → onglets vides.
 */
class TrainingGlobalListTest extends TestCase
{
    use CreatesMvpSchema;

    protected Company $company;

    protected Company $otherCompany;

    protected Employee $manager;

    protected Employee $employee;

    protected Employee $otherManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        /** @var Company $company */
        $company = Company::factory()->create();
        $this->company = $company;
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create();
        $this->otherCompany = $otherCompany;
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->manager = $manager;
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->employee = $employee;
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $this->otherCompany->id]);
        $this->otherManager = $otherManager;
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function makeSession(string $status = 'planned'): TrainingSession
    {
        $course = TrainingCourse::query()->create([
            'company_id' => $this->company->id,
            'title' => 'Formation '.$status,
            'type' => 'internal',
        ]);

        return TrainingSession::query()->create([
            'company_id' => $this->company->id,
            'training_course_id' => $course->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
            'status' => $status,
        ]);
    }

    /** @test */
    public function manager_lists_global_sessions_scoped_to_tenant(): void
    {
        $own = $this->makeSession('planned');
        $own->update(['start_date' => '2026-10-01']);

        // Session d'un autre tenant (ne doit pas remonter)
        $otherCourse = TrainingCourse::query()->create([
            'company_id' => $this->otherCompany->id,
            'title' => 'Autre tenant',
            'type' => 'internal',
        ]);
        TrainingSession::query()->create([
            'company_id' => $this->otherCompany->id,
            'training_course_id' => $otherCourse->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
            'status' => 'planned',
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/training/sessions');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $own->id);
        $this->assertCount(1, $response->json('data'));
    }

    /** @test */
    public function sessions_list_filters_by_status(): void
    {
        $this->makeSession('planned');
        $this->makeSession('completed');

        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/training/sessions?status=completed');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.status', 'completed');
    }

    /** @test */
    public function manager_lists_global_enrollments_scoped_to_tenant(): void
    {
        $session = $this->makeSession();
        $enrollment = TrainingEnrollment::query()->create([
            'company_id' => $this->company->id,
            'training_session_id' => $session->id,
            'employee_id' => $this->employee->id,
            'status' => 'enrolled',
        ]);

        $otherSession = TrainingSession::query()->create([
            'company_id' => $this->otherCompany->id,
            'training_course_id' => TrainingCourse::query()->create([
                'company_id' => $this->otherCompany->id,
                'title' => 'Autre',
                'type' => 'internal',
            ])->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
            'status' => 'planned',
        ]);
        TrainingEnrollment::query()->create([
            'company_id' => $this->otherCompany->id,
            'training_session_id' => $otherSession->id,
            'employee_id' => $this->otherManager->id,
            'status' => 'enrolled',
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/training/enrollments');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.id', $enrollment->id);
        $response->assertJsonPath('data.0.employee_id', $this->employee->id);
    }

    /** @test */
    public function enrollments_list_filters_by_status(): void
    {
        $session = $this->makeSession();
        TrainingEnrollment::query()->create([
            'company_id' => $this->company->id,
            'training_session_id' => $session->id,
            'employee_id' => $this->employee->id,
            'status' => 'enrolled',
        ]);
        $completed = TrainingEnrollment::query()->create([
            'company_id' => $this->company->id,
            'training_session_id' => $session->id,
            'employee_id' => $this->manager->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/training/enrollments?status=completed');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.id', $completed->id);
    }

    /** @test */
    public function cross_tenant_session_is_not_visible_via_global_list(): void
    {
        $this->makeSession('planned');

        $otherCourse = TrainingCourse::query()->create([
            'company_id' => $this->otherCompany->id,
            'title' => 'Autre tenant',
            'type' => 'internal',
        ]);
        $otherSession = TrainingSession::query()->create([
            'company_id' => $this->otherCompany->id,
            'training_course_id' => $otherCourse->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
            'status' => 'planned',
        ]);

        Sanctum::actingAs($this->otherManager);

        $response = $this->getJson('/api/v1/training/sessions');

        // La ressource ne sérialise PAS company_id (isolation tenant — l'id
        // interne du tenant ne fuite jamais côté client) : on identifie la
        // session retournée par son id (issue #5201).
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.id', $otherSession->id);
    }
}
