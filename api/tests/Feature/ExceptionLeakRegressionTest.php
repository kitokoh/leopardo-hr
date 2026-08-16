<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\CompanyAnnouncement;
use App\Modules\Notification\Infrastructure\Services\AnnouncementService;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #3810 (audit 360° 2026-08-15) — plus aucun message d'exception brut dans les
 * réponses JSON : les RuntimeExceptions métier sortent en codes stables
 * (PAYROLL_RUN_VALIDATION_FAILED, ANNOUNCEMENT_PUBLISH_FAILED…) avec le détail
 * en logs serveur uniquement.
 *
 * Complète #3725 (qui couvrait ~10 endpoints) : PayrollRunController ×4,
 * SSOController ×3, AnnouncementController ×2, GeoAttendanceController,
 * RateValidationAdminController ×2, SocialContributionController, TaxSlabController.
 */
class ExceptionLeakRegressionTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_payroll_run_validation_runtime_failure_returns_stable_code(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        // Run DRAFT : validateRh() jette une RuntimeException au message métier
        // interne (« Un run doit être calculé avant validation RH… »).
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($manager);
        $response = $this->postJson("/api/v1/payroll-runs/{$run->id}/validate");

        $response->assertStatus(422)
            ->assertJsonPath('error', 'PAYROLL_RUN_VALIDATION_FAILED')
            ->assertJsonPath('message', 'PAYROLL_RUN_VALIDATION_FAILED');

        // Le message brut (français interne) ne doit JAMAIS sortir.
        $this->assertStringNotContainsString('doit être calculé', $response->getContent());
        $this->assertStringNotContainsString('Exception', $response->getContent());
    }

    public function test_payroll_run_already_validated_returns_stable_domain_code(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => PayrollRun::STATUS_VALIDATED,
        ]);

        Sanctum::actingAs($manager);
        $response = $this->postJson("/api/v1/payroll-runs/{$run->id}/validate");

        $response->assertStatus(422);
        // Code de domaine stable (errorCode de PayrollAlreadyValidatedException).
        $this->assertSame('PAYROLL_ALREADY_VALIDATED', $response->json('error'));
        $this->assertNotNull($response->json('localized_message'));
    }

    public function test_announcement_publish_runtime_failure_returns_localized_error(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $announcement = CompanyAnnouncement::create([
            'company_id' => $company->id,
            'created_by' => $manager->id,
            'title' => 'Test',
            'body' => 'Body',
            'status' => 'draft',
        ]);

        // Le service échoue avec un message interne type SQL/PDO.
        $service = $this->createMock(AnnouncementService::class);
        $service->method('publishNow')->willThrowException(
            new \RuntimeException('SQLSTATE[42P01]: relation "announcements" does not exist')
        );
        $this->app->instance(AnnouncementService::class, $service);

        Sanctum::actingAs($manager);
        $response = $this->postJson("/api/v1/announcements/{$announcement->id}/publish");

        $response->assertStatus(422);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('does not exist', $response->getContent());
        // Le message exposé est la version localisée du code stable, jamais
        // le message brut du service.
        $this->assertSame('La publication de l\'annonce a échoué. Réessayez.', $response->json('errors.status.0'));
    }

    /**
     * #4310 — les DomainExceptions métier de paie (déjà validé / verrouillé /
     * non verrouillé) ne doivent plus exposer `$e->getMessage()` en
     * `localized_message` : la réponse passe par le catalogue `errors.*`
     * (traduisible), même quand la locale demandée n'est pas le français.
     */
    public function test_payroll_domain_exception_uses_catalog_not_raw_message(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => PayrollRun::STATUS_VALIDATED,
        ]);

        // Locale anglaise (préférence user, prioritaire sur Accept-Language) :
        // si le message venait encore de l'exception (FR codé en dur), il
        // resterait français — le catalogue prouve la localisation.
        $manager->preferred_language = 'en';
        $manager->save();

        Sanctum::actingAs($manager);
        $response = $this->postJson("/api/v1/payroll-runs/{$run->id}/validate");

        $response->assertStatus(422)
            ->assertJsonPath('error', 'PAYROLL_ALREADY_VALIDATED')
            ->assertJsonPath('message', 'PAYROLL_ALREADY_VALIDATED')
            ->assertJsonPath(
                'localized_message',
                'This payroll run is already validated and can no longer be modified.'
            );
        $this->assertStringNotContainsString('déjà validée', $response->getContent());
    }

    public function test_payroll_locked_exception_uses_catalog_not_raw_message(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => PayrollRun::STATUS_LOCKED,
        ]);

        $manager->preferred_language = 'en';
        $manager->save();

        Sanctum::actingAs($manager);
        $response = $this->postJson("/api/v1/payroll-runs/{$run->id}/validate");

        $response->assertStatus(423)
            ->assertJsonPath('error', 'PAYROLL_RUN_LOCKED')
            ->assertJsonPath('localized_message', 'This payroll run is locked (accounting close) and can no longer be modified.');
        $this->assertStringNotContainsString('verrouillé', $response->getContent());
    }
}
