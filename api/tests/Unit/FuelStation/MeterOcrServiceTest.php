<?php

declare(strict_types=1);

namespace Tests\Unit\FuelStation;

use App\Core\AI\Domain\Contracts\ModelInferencePort;
use App\Core\AI\Domain\Enums\ModelExecutionStatus;
use App\Core\AI\Infrastructure\Adapters\FakeModelInferenceAdapter;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Exceptions\FuelOcrNotReviewableException;
use App\Modules\FuelStation\Domain\Exceptions\FuelOcrReviewValueRejectedException;
use App\Modules\FuelStation\Domain\Models\FuelMeterOcrRequest;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Infrastructure\Services\MeterOcrService;
use App\Modules\FuelStation\Infrastructure\Services\MeterReadingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * AI-002 (#6771) — OCR des compteurs FuelStation : logique de service.
 *
 * Couvre : soumission (ligne queued persistée AVANT dispatch, rejeu
 * idempotent sans doublon de photo) ; auto-enregistrement haute confiance ;
 * basse confiance / unité inconnue / valeur décroissante → needs_review SANS
 * relevé ; valeurs invalides → failed ; fournisseur indisponible/timeout →
 * failed + rethrow (retry queue) ; revue accept/reject ; gardes de revue.
 */
final class MeterOcrServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    private Employee $manager;

    private FakeModelInferenceAdapter $adapter;

    private MeterOcrService $service;

    private MeterReadingService $readings;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['fuel_station' => true],
        ]);
        $this->company = $company;

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        $this->employee = $employee;

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $this->manager = $manager;

        $this->adapter = new FakeModelInferenceAdapter;
        $this->app->instance(ModelInferencePort::class, $this->adapter);

        config(['ai.meter_ocr.confidence_threshold' => 0.92]);

        Storage::fake('local');

        $this->service = app(MeterOcrService::class);
        $this->readings = app(MeterReadingService::class);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    /** @return array{FuelStation, FuelPump, FuelMeterRegister} */
    private function fixture(): array
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $this->company->id,
            'code' => 'ST-UNIT',
            'name' => 'Station unitaire',
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        /** @var FuelPump $pump */
        $pump = FuelPump::query()->create([
            'company_id' => $this->company->id,
            'station_id' => (int) $station->getAttribute('id'),
            'code' => 'P-UNIT',
            'product_types' => ['essence'],
            'status' => FuelPump::STATUS_ACTIVE,
        ]);

        /** @var FuelMeterRegister $meter */
        $meter = FuelMeterRegister::query()->create([
            'company_id' => $this->company->id,
            'station_id' => (int) $station->getAttribute('id'),
            'pump_id' => (int) $pump->getAttribute('id'),
            'meter_code' => 'C-UNIT',
            'meter_type' => FuelMeterRegister::TYPE_ELECTRONIC,
            'product_code' => 'essence',
            'unit_code' => 'l',
            'precision_scale' => 2,
            'status' => FuelMeterRegister::STATUS_ACTIVE,
        ]);

        return [$station, $pump, $meter];
    }

    private function submit(string $key): FuelMeterOcrRequest
    {
        [$station, $pump, $meter] = $this->fixture();

        $row = $this->service->submit(
            $station,
            $pump,
            $meter,
            UploadedFile::fake()->create('compteur.jpg', 40, 'image/jpeg'),
            $this->employee,
            null,
            $key,
        );

        return $row->refresh();
    }

    public function test_submit_persists_queued_row_before_dispatch_and_replays(): void
    {
        Queue::fake();

        [$station, $pump, $meter] = $this->fixture();
        $photo = UploadedFile::fake()->create('compteur.jpg', 40, 'image/jpeg');

        $row = $this->service->submit($station, $pump, $meter, $photo, $this->employee, null, 'ocr-unit-0001');

        $this->assertTrue($row->isQueued());
        $this->assertSame('ocr-unit-0001', (string) $row->correlation_id);
        $this->assertSame(0, (int) $row->attempts);
        $this->assertStringStartsWith('ocr/'.$this->company->id.'/', (string) $row->photo_path);
        Storage::disk('local')->assertExists((string) $row->photo_path);

        // Rejeu idempotent : même ligne, aucune photo supplémentaire.
        $replay = $this->service->submit(
            $station,
            $pump,
            $meter,
            UploadedFile::fake()->create('autre.jpg', 40, 'image/jpeg'),
            $this->employee,
            null,
            'ocr-unit-0001',
        );

        $this->assertSame((int) $row->id, (int) $replay->id);
        $this->assertDatabaseCount('fuel_meter_ocr_requests', 1);
        $this->assertCount(1, Storage::disk('local')->files('ocr/'.$this->company->id));
        $this->assertDatabaseCount('fuel_meter_readings', 0);
    }

    public function test_process_auto_records_high_confidence_reading(): void
    {
        Queue::fake();

        [$station, $pump, $meter] = $this->fixture();
        $this->adapter->respondWithPayload([
            'value' => '1234.56',
            'unit' => 'l',
            'confidence' => 0.98,
        ]);

        $row = $this->service->submit($station, $pump, $meter, UploadedFile::fake()->create('compteur.jpg', 40, 'image/jpeg'), $this->employee, null, 'ocr-unit-0010');
        $processed = $this->service->process($row);

        $this->assertTrue($processed->isSucceeded());
        $this->assertSame(123456, (int) $processed->extracted_value_minor);
        $this->assertSame('l', (string) $processed->extracted_unit);
        $this->assertSame('model-inference:fake', (string) $processed->model_version);
        $this->assertNotNull($processed->reading_id);
        $this->assertSame(1, (int) $processed->attempts);

        $this->assertDatabaseHas('fuel_meter_readings', [
            'company_id' => $this->company->id,
            'meter_id' => (int) $meter->getAttribute('id'),
            'reading_value_minor' => 123456,
            'reading_unit' => 'l',
            'captured_by_employee_id' => (int) $this->employee->id,
            'device_reference' => 'ocr:'.(int) $processed->id,
            'idempotency_key' => 'ocr-ocr-unit-0010',
            'status' => 'accepted',
        ]);
    }

    public function test_process_low_confidence_requires_review_without_reading(): void
    {
        Queue::fake();

        [$station, $pump, $meter] = $this->fixture();
        $this->adapter->respondWithPayload([
            'value' => '1234.56',
            'unit' => 'l',
            'confidence' => 0.5,
        ]);

        $row = $this->service->submit($station, $pump, $meter, UploadedFile::fake()->create('compteur.jpg', 40, 'image/jpeg'), $this->employee, null, 'ocr-unit-0020');
        $processed = $this->service->process($row);

        $this->assertTrue($processed->isNeedsReview());
        $this->assertSame(['LOW_CONFIDENCE'], $processed->anomalies);
        $this->assertSame(123456, (int) $processed->extracted_value_minor);
        $this->assertNull($processed->reading_id);
        $this->assertDatabaseCount('fuel_meter_readings', 0);
    }

    public function test_process_unit_mismatch_requires_review_without_reading(): void
    {
        Queue::fake();

        [$station, $pump, $meter] = $this->fixture();
        $this->adapter->respondWithPayload([
            'value' => '1234.56',
            'unit' => 'gal',
            'confidence' => 0.98,
        ]);

        $row = $this->service->submit($station, $pump, $meter, UploadedFile::fake()->create('compteur.jpg', 40, 'image/jpeg'), $this->employee, null, 'ocr-unit-0030');
        $processed = $this->service->process($row);

        $this->assertTrue($processed->isNeedsReview());
        $this->assertSame(['UNIT_MISMATCH'], $processed->anomalies);
        $this->assertSame(123456, (int) $processed->extracted_value_minor);
        $this->assertNull($processed->reading_id);
        $this->assertDatabaseCount('fuel_meter_readings', 0);
    }

    public function test_process_decreasing_value_requires_review_without_auto_record(): void
    {
        Queue::fake();

        [$station, $pump, $meter] = $this->fixture();

        // Relevé manuel antérieur : 125 430,20 l → 12 543 020 unités mineures.
        $this->readings->record($station, $pump, $meter, [
            'reading_value_minor' => 12543020,
            'captured_at' => '2026-08-28T08:00:00+01:00',
            'idempotency_key' => 'seed-unit-0001',
        ], $this->employee);

        // L'OCR lit 125 000,00 l → 12 500 000 unités mineures : DÉCROISSANT.
        $this->adapter->respondWithPayload([
            'value' => '125000.00',
            'unit' => 'l',
            'confidence' => 0.98,
        ]);

        $row = $this->service->submit($station, $pump, $meter, UploadedFile::fake()->create('compteur.jpg', 40, 'image/jpeg'), $this->employee, null, 'ocr-unit-0040');
        $processed = $this->service->process($row);

        $this->assertTrue($processed->isNeedsReview());
        $this->assertSame(['DECREASING_READING'], $processed->anomalies);
        $this->assertNull($processed->reading_id);

        // Uniquement le relevé manuel seed — l'OCR n'a rien auto-enregistré.
        $this->assertDatabaseCount('fuel_meter_readings', 1);
        $this->assertDatabaseMissing('fuel_meter_readings', [
            'idempotency_key' => 'ocr-ocr-unit-0040',
        ]);
    }

    public function test_process_invalid_value_marks_failed(): void
    {
        Queue::fake();

        [$station, $pump, $meter] = $this->fixture();
        $this->adapter->respondWithPayload([
            'value' => 'abc',
            'unit' => 'l',
            'confidence' => 0.99,
        ]);

        $row = $this->service->submit($station, $pump, $meter, UploadedFile::fake()->create('compteur.jpg', 40, 'image/jpeg'), $this->employee, null, 'ocr-unit-0050');
        $processed = $this->service->process($row);

        $this->assertTrue($processed->isFailed());
        $this->assertSame('INVALID_OCR_VALUE', (string) $processed->error_code);
        $this->assertDatabaseCount('fuel_meter_readings', 0);
    }

    public function test_process_provider_unavailable_marks_failed_and_rethrows_for_retry(): void
    {
        Queue::fake();

        [$station, $pump, $meter] = $this->fixture();
        $this->adapter->queueStatus(ModelExecutionStatus::Unavailable);

        $row = $this->service->submit($station, $pump, $meter, UploadedFile::fake()->create('compteur.jpg', 40, 'image/jpeg'), $this->employee, null, 'ocr-unit-0060');

        try {
            $this->service->process($row);
            $this->fail('Un fournisseur indisponible doit re-throw pour le retry queue.');
        } catch (RuntimeException $e) {
            $this->assertSame('ocr.provider_unavailable', $e->getMessage());
        }

        $failed = $row->refresh();
        $this->assertTrue($failed->isFailed());
        $this->assertSame('PROVIDER_UNAVAILABLE', (string) $failed->error_code);
        $this->assertSame(1, (int) $failed->attempts);
        $this->assertDatabaseCount('fuel_meter_readings', 0);
    }

    public function test_process_timeout_marks_failed_and_rethrows_for_retry(): void
    {
        Queue::fake();

        [$station, $pump, $meter] = $this->fixture();
        $this->adapter->queueStatus(ModelExecutionStatus::Timeout);

        $row = $this->service->submit($station, $pump, $meter, UploadedFile::fake()->create('compteur.jpg', 40, 'image/jpeg'), $this->employee, null, 'ocr-unit-0065');

        try {
            $this->service->process($row);
            $this->fail('Un timeout fournisseur doit re-throw pour le retry queue.');
        } catch (RuntimeException $e) {
            $this->assertSame('ocr.provider_unavailable', $e->getMessage());
        }

        $failed = $row->refresh();
        $this->assertTrue($failed->isFailed());
        $this->assertSame('PROVIDER_TIMEOUT', (string) $failed->error_code);
        $this->assertDatabaseCount('fuel_meter_readings', 0);
    }

    public function test_process_invalid_input_marks_failed_without_rethrow(): void
    {
        Queue::fake();

        [$station, $pump, $meter] = $this->fixture();
        $this->adapter->queueStatus(ModelExecutionStatus::InvalidInput);

        $row = $this->service->submit($station, $pump, $meter, UploadedFile::fake()->create('compteur.jpg', 40, 'image/jpeg'), $this->employee, null, 'ocr-unit-0070');
        $processed = $this->service->process($row);

        $this->assertTrue($processed->isFailed());
        $this->assertSame('INVALID_INPUT', (string) $processed->error_code);
    }

    public function test_process_never_retries_a_terminal_row(): void
    {
        Queue::fake();

        [$station, $pump, $meter] = $this->fixture();
        $this->adapter->respondWithPayload(['value' => '1234.56', 'unit' => 'l', 'confidence' => 0.98]);

        $row = $this->service->submit($station, $pump, $meter, UploadedFile::fake()->create('compteur.jpg', 40, 'image/jpeg'), $this->employee, null, 'ocr-unit-0080');
        $processed = $this->service->process($row);
        $this->assertTrue($processed->isSucceeded());

        // Un second appel (doublon de job) ne re-traite pas : statut inchangé,
        // tentative non incrémentée, aucun relevé en double.
        $again = $this->service->process($processed);
        $this->assertSame(1, (int) $again->attempts);
        $this->assertDatabaseCount('fuel_meter_readings', 1);
    }

    public function test_review_accept_records_reading_with_reviewer_as_actor(): void
    {
        Queue::fake();

        [$station, $pump, $meter] = $this->fixture();
        $this->adapter->respondWithPayload(['value' => '1200.00', 'unit' => 'l', 'confidence' => 0.5]);

        $row = $this->service->submit($station, $pump, $meter, UploadedFile::fake()->create('compteur.jpg', 40, 'image/jpeg'), $this->employee, null, 'ocr-unit-0090');
        $pending = $this->service->process($row);
        $this->assertTrue($pending->isNeedsReview());

        // Acceptation sans correction → valeur extraite (120 000 mineures).
        $reviewed = $this->service->review($pending, $this->manager, true, null, null);

        $this->assertTrue($reviewed->isSucceeded());
        $this->assertSame('accepted', (string) $reviewed->review_decision);
        $this->assertSame((int) $this->manager->id, (int) $reviewed->reviewed_by_employee_id);
        $this->assertNotNull($reviewed->reviewed_at);
        $this->assertNotNull($reviewed->reading_id);

        $this->assertDatabaseHas('fuel_meter_readings', [
            'company_id' => $this->company->id,
            'reading_value_minor' => 120000,
            'captured_by_employee_id' => (int) $this->manager->id,
            'device_reference' => 'ocr-review:'.(int) $pending->id,
            'idempotency_key' => 'ocr-review-ocr-unit-0090',
        ]);
    }

    public function test_review_accept_with_corrected_value_records_correction(): void
    {
        Queue::fake();

        [$station, $pump, $meter] = $this->fixture();
        $this->adapter->respondWithPayload(['value' => '1200.00', 'unit' => 'l', 'confidence' => 0.5]);

        $row = $this->service->submit($station, $pump, $meter, UploadedFile::fake()->create('compteur.jpg', 40, 'image/jpeg'), $this->employee, null, 'ocr-unit-0095');
        $pending = $this->service->process($row);

        // Le manager corrige la valeur lue (1200,10 l → 120 010 mineures).
        $reviewed = $this->service->review($pending, $this->manager, true, 120010, null);

        $this->assertTrue($reviewed->isSucceeded());
        $this->assertDatabaseHas('fuel_meter_readings', [
            'company_id' => $this->company->id,
            'reading_value_minor' => 120010,
            'device_reference' => 'ocr-review:'.(int) $pending->id,
        ]);
    }

    public function test_review_reject_marks_request_rejected(): void
    {
        Queue::fake();

        [$station, $pump, $meter] = $this->fixture();
        $this->adapter->respondWithPayload(['value' => '1200.00', 'unit' => 'l', 'confidence' => 0.5]);

        $row = $this->service->submit($station, $pump, $meter, UploadedFile::fake()->create('compteur.jpg', 40, 'image/jpeg'), $this->employee, null, 'ocr-unit-0100');
        $pending = $this->service->process($row);

        $rejected = $this->service->review($pending, $this->manager, false, null, 'photo floue');

        $this->assertTrue($rejected->isRejected());
        $this->assertSame('rejected', (string) $rejected->review_decision);
        $this->assertSame('photo floue', (string) $rejected->error_code);
        $this->assertSame((int) $this->manager->id, (int) $rejected->reviewed_by_employee_id);
        $this->assertDatabaseCount('fuel_meter_readings', 0);
    }

    public function test_review_reject_without_reason_uses_stable_code(): void
    {
        Queue::fake();

        [$station, $pump, $meter] = $this->fixture();
        $this->adapter->respondWithPayload(['value' => '1200.00', 'unit' => 'l', 'confidence' => 0.5]);

        $row = $this->service->submit($station, $pump, $meter, UploadedFile::fake()->create('compteur.jpg', 40, 'image/jpeg'), $this->employee, null, 'ocr-unit-0105');
        $pending = $this->service->process($row);

        $rejected = $this->service->review($pending, $this->manager, false, null, null);

        $this->assertTrue($rejected->isRejected());
        $this->assertSame('REJECTED_BY_MANAGER', (string) $rejected->error_code);
    }

    public function test_review_of_non_reviewable_request_throws(): void
    {
        Queue::fake();

        $row = $this->submit('ocr-unit-0110');

        try {
            $this->service->review($row, $this->manager, true, 120000, null);
            $this->fail('Une demande queued n\'est pas revoyable.');
        } catch (FuelOcrNotReviewableException $e) {
            $this->assertSame('OCR_REQUEST_NOT_REVIEWABLE', $e->errorCode());
            $this->assertSame(409, $e->statusCode());
        }
    }

    public function test_review_accept_with_negative_value_is_rejected(): void
    {
        Queue::fake();

        [$station, $pump, $meter] = $this->fixture();
        $this->adapter->respondWithPayload(['value' => '1200.00', 'unit' => 'l', 'confidence' => 0.5]);

        $row = $this->service->submit($station, $pump, $meter, UploadedFile::fake()->create('compteur.jpg', 40, 'image/jpeg'), $this->employee, null, 'ocr-unit-0120');
        $pending = $this->service->process($row);

        try {
            $this->service->review($pending, $this->manager, true, -5, null);
            $this->fail('Une valeur négative doit être refusée par MeterReadingService.');
        } catch (FuelOcrReviewValueRejectedException $e) {
            $this->assertSame('REVIEW_VALUE_REJECTED', $e->errorCode());
            $this->assertSame(422, $e->statusCode());
        }

        // Aucun relevé créé, la demande reste en attente de revue.
        $this->assertDatabaseCount('fuel_meter_readings', 0);
        $this->assertTrue($pending->refresh()->isNeedsReview());
    }
}
