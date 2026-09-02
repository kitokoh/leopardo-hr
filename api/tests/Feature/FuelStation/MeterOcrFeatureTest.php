<?php

declare(strict_types=1);

namespace Tests\Feature\FuelStation;

use App\Core\AI\Domain\Contracts\ModelInferencePort;
use App\Core\AI\Domain\Enums\ModelExecutionStatus;
use App\Core\AI\Infrastructure\Adapters\FakeModelInferenceAdapter;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelMeterOcrRequest;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Infrastructure\Jobs\ProcessMeterOcrJob;
use App\Modules\FuelStation\Infrastructure\Services\MeterOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * AI-002 (#6771) — OCR des compteurs FuelStation : surface API.
 *
 * Couvre : soumission multipart → 202 + ligne queued persistée + job
 * dispatché ; idempotence du rejeu ; auto-enregistrement haute confiance
 * (sans jamais toucher aux sessions de caisse) ; basse confiance → revue
 * humaine sans relevé ; revue manager accept/reject + RBAC ; fail-closed
 * cross-tenant (404) ; fournisseur indisponible → failed.
 */
final class FuelMeterOcrFeatureTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private FakeModelInferenceAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['fuel_station' => true],
        ]);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['fuel_station' => true],
        ]);
        $this->companyB = $companyB;

        // Adaptateur d'inférence scriptable (défaut : succès, payload OCR
        // valide '12345' / unité 'L' / confiance 0.95).
        $this->adapter = new FakeModelInferenceAdapter;
        $this->app->instance(ModelInferencePort::class, $this->adapter);

        config(['ai.meter_ocr.confidence_threshold' => 0.92]);

        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function employee(Company $company, string $role = 'employee'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $role === 'manager' ? 'principal' : null,
            'status' => 'active',
        ]);

        return $employee;
    }

    /** @return array{FuelStation, FuelPump, FuelMeterRegister} */
    private function fixture(Company $company, string $suffix = 'ABC'): array
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-'.$suffix,
            'name' => 'Station '.$suffix,
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        /** @var FuelPump $pump */
        $pump = FuelPump::query()->create([
            'company_id' => $company->id,
            'station_id' => (int) $station->getAttribute('id'),
            'code' => 'P-'.$suffix,
            'product_types' => ['essence'],
            'status' => FuelPump::STATUS_ACTIVE,
        ]);

        /** @var FuelMeterRegister $meter */
        $meter = FuelMeterRegister::query()->create([
            'company_id' => $company->id,
            'station_id' => (int) $station->getAttribute('id'),
            'pump_id' => (int) $pump->getAttribute('id'),
            'meter_code' => 'C-'.$suffix,
            'meter_type' => FuelMeterRegister::TYPE_ELECTRONIC,
            'product_code' => 'essence',
            'unit_code' => 'l',
            'precision_scale' => 2,
            'status' => FuelMeterRegister::STATUS_ACTIVE,
        ]);

        return [$station, $pump, $meter];
    }

    private function submitUrl(FuelStation $station, FuelPump $pump, FuelMeterRegister $meter): string
    {
        return '/api/v1/fuel-station/stations/'.(int) $station->getAttribute('id')
            .'/pumps/'.(int) $pump->getAttribute('id')
            .'/meters/'.(int) $meter->getAttribute('id').'/readings/ocr';
    }

    /** @return TestResponse<JsonResponse> */
    private function submitPhoto(FuelStation $station, FuelPump $pump, FuelMeterRegister $meter, string $key): TestResponse
    {
        $photo = UploadedFile::fake()->create('compteur.jpg', 40, 'image/jpeg');

        return $this->post($this->submitUrl($station, $pump, $meter), [
            'photo' => $photo,
            'idempotency_key' => $key,
        ]);
    }

    /** Exécute le job OCR dispatché (queue fausse) — équivalent worker. */
    private function runPushedJob(): void
    {
        Queue::assertPushed(ProcessMeterOcrJob::class, function (ProcessMeterOcrJob $job): bool {
            $job->handle(app(MeterOcrService::class));

            return true;
        });
    }

    public function test_submit_returns_202_queues_job_and_stores_photo(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->employee($this->companyA));
        [$station, $pump, $meter] = $this->fixture($this->companyA);

        $response = $this->submitPhoto($station, $pump, $meter, 'ocr-feat-0001');

        $response->assertStatus(202);
        $response->assertJsonPath('data.status', 'queued');
        $response->assertJsonPath('data.correlation_id', 'ocr-feat-0001');
        // La réponse ne doit JAMAIS exposer le chemin de la photo.
        $response->assertJsonMissing(['photo_path' => 'ocr']);
        $response->assertJsonStructure(['data' => ['id', 'status', 'correlation_id', 'links' => ['self']]]);

        Queue::assertPushed(ProcessMeterOcrJob::class);

        $this->assertDatabaseHas('fuel_meter_ocr_requests', [
            'company_id' => $this->companyA->id,
            'status' => FuelMeterOcrRequest::STATUS_QUEUED,
            'correlation_id' => 'ocr-feat-0001',
            'attempts' => 0,
        ]);

        // Aucun relevé tant que le job n'a pas traité la demande.
        $this->assertDatabaseCount('fuel_meter_readings', 0);

        $row = FuelMeterOcrRequest::query()->where('correlation_id', 'ocr-feat-0001')->firstOrFail();
        $this->assertStringStartsWith('ocr/'.$this->companyA->id.'/', (string) $row->photo_path);
        Storage::disk('local')->assertExists((string) $row->photo_path);
    }

    public function test_submit_is_idempotent_on_replay(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->employee($this->companyA));
        [$station, $pump, $meter] = $this->fixture($this->companyA);

        $first = $this->submitPhoto($station, $pump, $meter, 'ocr-feat-0010');
        $first->assertStatus(202);

        // Rejeu réseau avec la même clé → même demande, aucune nouvelle photo.
        $replay = $this->submitPhoto($station, $pump, $meter, 'ocr-feat-0010');
        $replay->assertStatus(202);
        $replay->assertJsonPath('data.id', $first->json('data.id'));
        $replay->assertJsonPath('data.correlation_id', 'ocr-feat-0010');

        $this->assertDatabaseCount('fuel_meter_ocr_requests', 1);
        $this->assertCount(1, Queue::pushed(ProcessMeterOcrJob::class));
        $this->assertCount(1, Storage::disk('local')->files('ocr/'.$this->companyA->id));
    }

    public function test_high_confidence_ocr_auto_records_reading_and_never_touches_cash_session(): void
    {
        Queue::fake();
        $requester = $this->employee($this->companyA);
        Sanctum::actingAs($requester);
        [$station, $pump, $meter] = $this->fixture($this->companyA);

        // Session de caisse ouverte AVANT le traitement : l'OCR ne doit ni la
        // clôturer, ni la modifier (l'OCR ne clôture jamais seule une session).
        $session = FuelCashSession::query()->create([
            'company_id' => $this->companyA->id,
            'station_id' => (int) $station->getAttribute('id'),
            'opened_by' => (int) $requester->getAttribute('id'),
            'status' => FuelCashSession::STATUS_OPEN,
        ]);

        // Lecture haute confiance : 1234,56 l → 123456 unités mineures.
        $this->adapter->respondWithPayload([
            'value' => '1234.56',
            'unit' => 'l',
            'confidence' => 0.98,
        ]);

        $this->submitPhoto($station, $pump, $meter, 'ocr-feat-0020')->assertStatus(202);
        $this->runPushedJob();

        $row = FuelMeterOcrRequest::query()->where('correlation_id', 'ocr-feat-0020')->firstOrFail();

        $this->assertSame(FuelMeterOcrRequest::STATUS_SUCCEEDED, $row->status);
        $this->assertSame(123456, (int) $row->extracted_value_minor);
        $this->assertNotNull($row->reading_id);

        $this->assertDatabaseHas('fuel_meter_readings', [
            'company_id' => $this->companyA->id,
            'meter_id' => (int) $meter->getAttribute('id'),
            'reading_value_minor' => 123456,
            'reading_unit' => 'l',
            'device_reference' => 'ocr:'.(int) $row->id,
            'idempotency_key' => 'ocr-ocr-feat-0020',
            'status' => 'accepted',
        ]);

        // La session de caisse est intacte : toujours ouverte, aucun
        // mouvement, aucune clôture — l'OCR n'appelle que MeterReadingService.
        $this->assertDatabaseHas('fuel_cash_sessions', [
            'id' => (int) $session->id,
            'status' => FuelCashSession::STATUS_OPEN,
            'closed_at' => null,
        ]);
        $this->assertDatabaseCount('fuel_cash_session_movements', 0);
    }

    public function test_low_confidence_goes_to_needs_review_without_reading(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->employee($this->companyA));
        [$station, $pump, $meter] = $this->fixture($this->companyA);

        $this->adapter->respondWithPayload([
            'value' => '1200.00',
            'unit' => 'l',
            'confidence' => 0.5,
        ]);

        $this->submitPhoto($station, $pump, $meter, 'ocr-feat-0030')->assertStatus(202);
        $this->runPushedJob();

        // Aucun relevé auto-enregistré sous le seuil de confiance.
        $this->assertDatabaseCount('fuel_meter_readings', 0);

        $row = FuelMeterOcrRequest::query()->where('correlation_id', 'ocr-feat-0030')->firstOrFail();
        $this->assertSame(FuelMeterOcrRequest::STATUS_NEEDS_REVIEW, $row->status);
        $this->assertSame(['LOW_CONFIDENCE'], $row->anomalies);
        $this->assertSame(120000, (int) $row->extracted_value_minor);
        $this->assertNull($row->reading_id);

        // Consultation du suivi par l'employé (aucun chemin de photo exposé).
        Sanctum::actingAs($this->employee($this->companyA));
        $show = $this->getJson('/api/v1/fuel-station/meter-ocr-requests/'.(int) $row->id);
        $show->assertOk();
        $show->assertJsonPath('data.status', FuelMeterOcrRequest::STATUS_NEEDS_REVIEW);
        $show->assertJsonPath('data.anomalies.0', 'LOW_CONFIDENCE');
        $show->assertJsonPath('data.reading_id', null);
        $show->assertJsonMissing(['data.photo_path']);
    }

    public function test_provider_unavailable_marks_request_failed(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->employee($this->companyA));
        [$station, $pump, $meter] = $this->fixture($this->companyA);

        $this->adapter->queueStatus(ModelExecutionStatus::Unavailable);

        $this->submitPhoto($station, $pump, $meter, 'ocr-feat-0040')->assertStatus(202);

        // Le job RE-THROW (retry queue avec backoff) : la ligne est d'abord
        // passée en `failed` avec un code machine stable.
        Queue::assertPushed(ProcessMeterOcrJob::class, function (ProcessMeterOcrJob $job): bool {
            try {
                $job->handle(app(MeterOcrService::class));
                $this->fail('Une indisponibilité fournisseur doit re-throw pour retry.');
            } catch (RuntimeException $e) {
                $this->assertSame('ocr.provider_unavailable', $e->getMessage());
            }

            return true;
        });

        $row = FuelMeterOcrRequest::query()->where('correlation_id', 'ocr-feat-0040')->firstOrFail();
        $this->assertSame(FuelMeterOcrRequest::STATUS_FAILED, $row->status);
        $this->assertSame('PROVIDER_UNAVAILABLE', $row->error_code);
        $this->assertSame(1, (int) $row->attempts);
        $this->assertDatabaseCount('fuel_meter_readings', 0);
    }

    public function test_manager_review_accept_creates_reading(): void
    {
        Queue::fake();
        $requester = $this->employee($this->companyA);
        Sanctum::actingAs($requester);
        [$station, $pump, $meter] = $this->fixture($this->companyA);

        $this->adapter->respondWithPayload([
            'value' => '1200.00',
            'unit' => 'l',
            'confidence' => 0.5,
        ]);

        $this->submitPhoto($station, $pump, $meter, 'ocr-feat-0050')->assertStatus(202);
        $this->runPushedJob();

        $row = FuelMeterOcrRequest::query()->where('correlation_id', 'ocr-feat-0050')->firstOrFail();
        $this->assertSame(FuelMeterOcrRequest::STATUS_NEEDS_REVIEW, $row->status);

        // Revue par le manager : valeur corrigée (1200,10 l → 120010 mineures).
        $manager = $this->employee($this->companyA, 'manager');
        Sanctum::actingAs($manager);

        $review = $this->postJson('/api/v1/fuel-station/meter-ocr-requests/'.(int) $row->id.'/review', [
            'accepted' => true,
            'reading_value_minor' => 120010,
            'reading_unit' => 'l',
        ]);

        $review->assertOk();
        $review->assertJsonPath('data.status', FuelMeterOcrRequest::STATUS_SUCCEEDED);
        $review->assertJsonPath('data.review.decision', 'accepted');

        $this->assertDatabaseHas('fuel_meter_ocr_requests', [
            'id' => (int) $row->id,
            'status' => FuelMeterOcrRequest::STATUS_SUCCEEDED,
            'reading_id' => (int) $review->json('data.reading_id'),
            'reviewed_by_employee_id' => (int) $manager->getAttribute('id'),
        ]);

        $this->assertDatabaseHas('fuel_meter_readings', [
            'company_id' => $this->companyA->id,
            'meter_id' => (int) $meter->getAttribute('id'),
            'reading_value_minor' => 120010,
            'reading_unit' => 'l',
            'device_reference' => 'ocr-review:'.(int) $row->id,
            'idempotency_key' => 'ocr-review-ocr-feat-0050',
            'status' => 'accepted',
        ]);
    }

    public function test_manager_review_reject_marks_request_rejected(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->employee($this->companyA));
        [$station, $pump, $meter] = $this->fixture($this->companyA);

        $this->adapter->respondWithPayload([
            'value' => '1200.00',
            'unit' => 'l',
            'confidence' => 0.5,
        ]);

        $this->submitPhoto($station, $pump, $meter, 'ocr-feat-0060')->assertStatus(202);
        $this->runPushedJob();

        $row = FuelMeterOcrRequest::query()->where('correlation_id', 'ocr-feat-0060')->firstOrFail();

        Sanctum::actingAs($this->employee($this->companyA, 'manager'));
        $this->postJson('/api/v1/fuel-station/meter-ocr-requests/'.(int) $row->id.'/review', [
            'accepted' => false,
            'reason' => 'Photo illisible',
        ])->assertOk();

        $this->assertDatabaseHas('fuel_meter_ocr_requests', [
            'id' => (int) $row->id,
            'status' => FuelMeterOcrRequest::STATUS_REJECTED,
            'review_decision' => 'rejected',
            'error_code' => 'Photo illisible',
        ]);
        $this->assertDatabaseCount('fuel_meter_readings', 0);
    }

    public function test_employee_cannot_review(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->employee($this->companyA));
        [$station, $pump, $meter] = $this->fixture($this->companyA);

        $this->adapter->respondWithPayload(['value' => '1200.00', 'unit' => 'l', 'confidence' => 0.5]);
        $this->submitPhoto($station, $pump, $meter, 'ocr-feat-0070')->assertStatus(202);
        $this->runPushedJob();

        $row = FuelMeterOcrRequest::query()->where('correlation_id', 'ocr-feat-0070')->firstOrFail();

        // Employé ordinaire → 403 MANAGER_REQUIRED (middleware api.manager).
        Sanctum::actingAs($this->employee($this->companyA));
        $this->postJson('/api/v1/fuel-station/meter-ocr-requests/'.(int) $row->id.'/review', [
            'accepted' => true,
            'reading_value_minor' => 120000,
        ])->assertStatus(403);
    }

    public function test_cross_tenant_submit_returns_404(): void
    {
        Sanctum::actingAs($this->employee($this->companyA));
        [$station, $pump, $meter] = $this->fixture($this->companyB);

        $this->submitPhoto($station, $pump, $meter, 'ocr-feat-0080')->assertStatus(404);
        $this->assertDatabaseCount('fuel_meter_ocr_requests', 0);
    }

    public function test_cross_tenant_show_returns_404(): void
    {
        // La demande appartient au tenant B.
        Queue::fake();
        Sanctum::actingAs($this->employee($this->companyB));
        [$station, $pump, $meter] = $this->fixture($this->companyB, 'XYZ');
        $this->submitPhoto($station, $pump, $meter, 'ocr-feat-0090')->assertStatus(202);
        $row = FuelMeterOcrRequest::query()->where('correlation_id', 'ocr-feat-0090')->firstOrFail();

        // Un employé du tenant A ne doit pas la voir (404 fail-closed).
        Sanctum::actingAs($this->employee($this->companyA));
        $this->getJson('/api/v1/fuel-station/meter-ocr-requests/'.(int) $row->id)->assertStatus(404);

        // Un manager du tenant A ne doit pas pouvoir la revoir non plus :
        // le binding est résolu dans le tenant courant → 404 avant le contrôleur.
        Sanctum::actingAs($this->employee($this->companyA, 'manager'));
        $this->postJson('/api/v1/fuel-station/meter-ocr-requests/'.(int) $row->id.'/review', [
            'accepted' => true,
            'reading_value_minor' => 120000,
        ])->assertStatus(404);
    }

    public function test_validation_rejects_invalid_payloads(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->employee($this->companyA));
        [$station, $pump, $meter] = $this->fixture($this->companyA);

        // Clé d'idempotence trop courte → 422.
        $this->post($this->submitUrl($station, $pump, $meter), [
            'photo' => UploadedFile::fake()->create('compteur.jpg', 40, 'image/jpeg'),
            'idempotency_key' => 'short',
        ])->assertStatus(422);

        // Fichier non-image → 422.
        $this->post($this->submitUrl($station, $pump, $meter), [
            'photo' => UploadedFile::fake()->create('note.txt', 10, 'text/plain'),
            'idempotency_key' => 'ocr-feat-0100',
        ])->assertStatus(422);

        $this->assertDatabaseCount('fuel_meter_ocr_requests', 0);
    }

    public function test_inactive_solution_returns_403(): void
    {
        Queue::fake();

        /** @var Company $inactive */
        $inactive = Company::factory()->create([
            'country' => 'TN',
            'currency' => 'TND',
            'features' => [],
        ]);

        Sanctum::actingAs($this->employee($inactive));
        [$station, $pump, $meter] = $this->fixture($inactive);

        $this->submitPhoto($station, $pump, $meter, 'ocr-feat-0110')->assertStatus(403);
    }
}
