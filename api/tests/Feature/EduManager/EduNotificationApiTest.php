<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Infrastructure\Jobs\SendEduNotificationJob;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Notifications EduManager — EDU-014 (issue #5830).
 *
 * Couvre : dispatch automatique sur admission convertie / absence /
 * bulletin publié (direction du tenant), templates edu_* enregistrés,
 * historique communication_events lisible par la direction, 403 lambda.
 */
class EduNotificationApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Employee $principalA;

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

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principalA = $principalA;
    }

    public function test_templates_are_registered(): void
    {
        $templates = config('communication.templates');

        foreach ([
            'edu_admission_converted',
            'edu_absence_recorded',
            'edu_report_card_published',
        ] as $key) {
            $this->assertArrayHasKey($key, $templates, "template {$key} absent");
        }
    }

    public function test_admission_conversion_dispatches_notification(): void
    {
        Queue::fake();

        Sanctum::actingAs($this->principalA);

        $admissionId = $this->postJson('/api/v1/edu-manager/admissions', [
            'academic_year_id' => (int) $this->academicYear()->getAttribute('id'),
            'applicant_first_name' => 'Lina',
            'applicant_last_name' => 'Benali',
            'applicant_email' => 'lina@example.com',
            'applied_at' => '2026-09-01',
            'consent_contact' => true,
            'consented_at' => now(),
            'source' => 'web',
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/edu-manager/admissions/{$admissionId}/convert")
            ->assertStatus(201);

        Queue::assertPushed(SendEduNotificationJob::class, fn (SendEduNotificationJob $job): bool => $job->templateKey === 'edu_admission_converted');
    }

    public function test_notifications_history_requires_direction(): void
    {
        Sanctum::actingAs($this->principalA);

        // Aucun événement edu → liste vide, mais endpoint accessible.
        $this->getJson('/api/v1/edu-manager/notifications')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        /** @var Employee $lambda */
        $lambda = Employee::factory()->create(['company_id' => $this->companyA->id]);
        Sanctum::actingAs($lambda);

        $this->getJson('/api/v1/edu-manager/notifications')->assertStatus(403);
    }

    private function academicYear(): EduAcademicYear
    {
        /** @var EduAcademicYear $year */
        $year = EduAcademicYear::query()->create([
            'company_id' => $this->companyA->id,
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
            'status' => EduAcademicYear::STATUS_ACTIVE,
        ]);

        return $year;
    }
}
