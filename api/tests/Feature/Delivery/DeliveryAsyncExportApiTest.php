<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\GenerateDeliveryExportJob;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryExport;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-26-D07 (#6295) — Export CSV async (pattern GenerateBankExportJob).
 *
 * - POST → 202 pending + job dispatché (file documents) ;
 * - job : pending → generating → done (fichier + URL de téléchargement) ;
 * - observabilité : status + error_message ; retry borné (tries 3) ;
 * - RBAC manager|admin + isolation tenant (404).
 */
class DeliveryAsyncExportApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $company->setFeature('delivery', true);
        $company->save();
        $this->company = $company;
    }

    private function manager(): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $manager;
    }

    public function test_store_dispatches_job_and_returns_202(): void
    {
        Queue::fake();

        Sanctum::actingAs($this->manager());

        $response = $this->postJson('/api/v1/delivery/deliveries/reports/async-export', [
            'from' => '2026-08-01',
            'to' => '2026-08-31',
        ])->assertStatus(202);

        $exportId = $response->json('data.id');
        self::assertSame('pending', $response->json('data.status'));

        Queue::assertPushed(GenerateDeliveryExportJob::class, fn ($job) => $job->exportId === $exportId);
    }

    public function test_job_generates_csv_and_marks_done(): void
    {
        Sanctum::actingAs($this->manager());

        Delivery::query()->create([
            'company_id' => $this->company->id,
            'reference' => 'DLV-2026-111001',
            'source' => 'manual',
            'type' => 'parcel',
            'status' => 'delivered',
            'cod_amount_minor' => 5000,
            'dropoff_contact' => 'Client',
            'dropoff_address' => 'Alger',
        ]);

        $export = DeliveryExport::query()->create([
            'company_id' => $this->company->id,
            'status' => 'pending',
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-31',
            'requested_by' => $this->manager()->id,
        ]);

        // Exécution synchrone du job (pattern BankExport).
        (new GenerateDeliveryExportJob((int) $export->id))->handle();

        $export->refresh();
        self::assertSame('done', $export->status);
        self::assertNotNull($export->filename);
        self::assertNotNull($export->completed_at);

        // Observation + téléchargement.
        $this->getJson(sprintf('/api/v1/delivery/deliveries/reports/async-export/%d', $export->id))
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.download_url', fn ($v) => str_contains((string) $v, '/download'));

        $download = $this->get(sprintf('/api/v1/delivery/deliveries/reports/async-export/%d/download', $export->id));
        $download->assertOk();
        self::assertStringContainsString('DLV-2026-111001', $download->streamedContent());
    }

    public function test_export_is_tenant_scoped(): void
    {
        Sanctum::actingAs($this->manager());

        $export = DeliveryExport::query()->create([
            'company_id' => $this->company->id,
            'status' => 'done',
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-31',
            'filename' => 'delivery_exports/x.csv',
        ]);

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $other->setFeature('delivery', true);
        $other->save();

        /** @var Employee $managerB */
        $managerB = Employee::factory()->create([
            'company_id' => $other->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($managerB);

        $this->getJson(sprintf('/api/v1/delivery/deliveries/reports/async-export/%d', $export->id))->assertStatus(404);
        $this->get(sprintf('/api/v1/delivery/deliveries/reports/async-export/%d/download', $export->id))->assertStatus(404);
    }
}
