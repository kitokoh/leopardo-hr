<?php

declare(strict_types=1);

namespace Tests\Feature\FuelStation;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Enums\FuelReadingAnomalyReason;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use App\Modules\FuelStation\Infrastructure\Services\FuelMeterReadingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Relevés de compteur FuelStation — Issue #5798 (FUEL-004).
 *
 * Verrouille :
 *   1. deux relevés cohérents produisent un delta ;
 *   2. valeur décroissante → anomalie `decreasing_value` ;
 *   3. rollover explicite → pas d'anomalie, delta = valeur (cycle neuf) ;
 *   4. correction versionnée et auditée (nouvelle ligne liée, jamais UPDATE) ;
 *   5. zéro doublon (UNIQUE company_id/meter_id/read_at, retour de l'existant) ;
 *   6. zéro fuite tenant (scope BelongsToCompany + FK composite conditionnelle).
 */
class FuelMeterReadingServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private int $meterId;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;

        $this->meterId = (int) DB::table('fuel_meters')->insertGetId([
            'company_id' => $companyA->id,
            'code' => 'M-1',
            'name' => 'Compteur 1',
        ]);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function service(): FuelMeterReadingService
    {
        return app(FuelMeterReadingService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(float $value, string $at, array $extra = []): array
    {
        return array_merge([
            'meter_id' => $this->meterId,
            'reading_value' => $value,
            'read_at' => Carbon::parse($at),
        ], $extra);
    }

    public function test_two_coherent_readings_produce_a_delta(): void
    {
        $this->setTenant($this->companyA);

        $this->service()->record($this->payload(1000.000, '2026-08-28 08:00:00'));
        $second = $this->service()->record($this->payload(1050.500, '2026-08-28 16:00:00'));

        $this->assertSame('50.500', (string) $second->delta);
        $this->assertFalse($second->is_anomaly);
        $this->assertNull($second->anomaly_reason);
    }

    public function test_decreasing_value_becomes_anomaly(): void
    {
        $this->setTenant($this->companyA);

        $this->service()->record($this->payload(1050.500, '2026-08-28 08:00:00'));
        $anomaly = $this->service()->record($this->payload(1000.000, '2026-08-28 16:00:00'));

        $this->assertTrue($anomaly->is_anomaly);
        $this->assertSame(FuelReadingAnomalyReason::DecreasingValue->value, $anomaly->anomaly_reason);
        $this->assertSame('-50.500', (string) $anomaly->delta);
    }

    public function test_explicit_rollover_suppresses_anomaly_and_restarts_cycle(): void
    {
        $this->setTenant($this->companyA);

        $this->service()->record($this->payload(1000.000, '2026-08-28 08:00:00'));
        $rollover = $this->service()->record($this->payload(12.000, '2026-08-28 16:00:00', ['is_rollover' => true]));

        $this->assertFalse($rollover->is_anomaly);
        $this->assertNull($rollover->anomaly_reason);
        $this->assertSame('12.000', (string) $rollover->delta);
    }

    public function test_duplicate_reading_returns_existing_row(): void
    {
        $this->setTenant($this->companyA);

        $first = $this->service()->record($this->payload(1000.000, '2026-08-28 08:00:00'));
        $duplicate = $this->service()->record($this->payload(9999.000, '2026-08-28 08:00:00'));

        $this->assertSame($first->id, $duplicate->id);
        $this->assertSame(1, FuelMeterReading::query()->count());
    }

    public function test_correction_is_versioned_and_audited(): void
    {
        $this->setTenant($this->companyA);

        $original = $this->service()->record($this->payload(1000.000, '2026-08-28 08:00:00'));

        $correction = $this->service()->correct($original->id, ['reading_value' => 1005.000]);

        $this->assertSame($original->id, (int) $correction->corrects_reading_id);
        $this->assertSame('1005.000', (string) $correction->reading_value);

        // Append-only : la ligne d'origine est inchangée, une 2e ligne existe.
        $this->assertSame('1000.000', (string) $original->fresh()->reading_value);
        $this->assertSame(2, FuelMeterReading::query()->count());

        // Audit tracé (created ×2).
        $rows = DB::table('audit_logs')
            ->where('company_id', $this->companyA->id)
            ->where('auditable_type', FuelMeterReading::class)
            ->count();
        $this->assertSame(2, $rows);
    }

    public function test_duplicate_correction_is_refused(): void
    {
        $this->setTenant($this->companyA);

        $original = $this->service()->record($this->payload(1000.000, '2026-08-28 08:00:00'));
        $correction = $this->service()->correct($original->id, ['reading_value' => 1005.000]);

        $this->expectException(\App\Modules\FuelStation\Domain\Exceptions\FuelReadingException::class);

        $this->service()->correct($correction->id, ['reading_value' => 1010.000]);
    }

    public function test_cross_tenant_readings_are_invisible(): void
    {
        $this->setTenant($this->companyA);

        $reading = $this->service()->record($this->payload(1000.000, '2026-08-28 08:00:00'));

        // Depuis le tenant B, le relevé du tenant A est invisible (scope global).
        $this->setTenant($this->companyB);
        $this->assertNull(FuelMeterReading::query()->find($reading->id));
    }

    public function test_cross_tenant_meter_reference_rejected_when_fk_present(): void
    {
        if (! Schema::hasTable('fuel_meters')) {
            $this->markTestSkipped('dépendance #5797 (fuel_meters) non mergée');
        }

        $meterB = (int) DB::table('fuel_meters')->insertGetId([
            'company_id' => $this->companyB->id,
            'code' => 'M-B',
            'name' => 'Compteur B',
        ]);

        try {
            DB::table('fuel_meter_readings')->insert([
                'company_id' => $this->companyA->id,
                'meter_id' => $meterB,
                'reading_value' => 10,
                'read_at' => now(),
            ]);
            $this->fail('La FK composite fuel_meter_readings_meter_company_fk aurait dû rejeter la référence cross-tenant.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertStringContainsString('fuel_meter_readings_meter_company_fk', $exception->getMessage());
        }
    }

    private function setTenant(Company $company): void
    {
        app()->instance('current_company', $company);
    }
}
