<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelExportAsset;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-505 (#6075) — Export CSV idempotent + URL signée éphémère.
 * Rejeu même clé → MÊME asset (aucun doublon), contenu CSV conforme.
 */
class TravelExportTest extends TestCase
{
    use RefreshTenantDatabase;

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    public function test_export_generated_once_and_replayable(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $booking = app(TenantManager::class)->withinTenant($company, fn (): TravelBooking => TravelBooking::factory()->create([
            'status' => 'confirmed',
            'passenger_count' => 2,
            'total_amount_minor' => 45000,
        ]));

        $payload = [
            'report_type' => 'sales',
            'from' => now()->subDays(1)->toIso8601String(),
            'to' => now()->toIso8601String(),
            'idempotency_key' => 'travel-export-1',
        ];

        $first = $this->postJson('/api/v1/travel/reports/export', $payload)
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'generated');

        $assetId = (int) $first->json('data.id');

        /** @var TravelExportAsset $asset */
        $asset = app(TenantManager::class)->withinTenant($company, fn (): TravelExportAsset => TravelExportAsset::query()->findOrFail($assetId));

        $csv = Storage::disk('local')->get((string) $asset->file_path);

        $this->assertStringStartsWith("\xEF\xBB\xBF", (string) $csv);
        $this->assertStringContainsString('reference;created_at;trip_id;booking_source;status;passenger_count;total_amount_minor;currency', (string) $csv);
        $this->assertStringContainsString((string) $booking->reference, (string) $csv);

        // Rejeu : même asset, aucun doublon.
        $replay = $this->postJson('/api/v1/travel/reports/export', $payload)
            ->assertStatus(202);

        $this->assertSame($assetId, (int) $replay->json('data.id'));

        $count = app(TenantManager::class)->withinTenant($company, fn (): int => TravelExportAsset::query()->where('company_id', $company->id)->count());
        $this->assertSame(1, $count);

        // Lecture : URL signée éphémère.
        $this->getJson("/api/v1/travel/reports/export/{$assetId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'generated');
    }
}
