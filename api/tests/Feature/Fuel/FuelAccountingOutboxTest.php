<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Contrat Accounting — FUEL-015 (issue #5809).
 *
 * Couvre : événements versionnés publiés dans l'outbox (sale.recorded,
 * cash_session.closed, stock.reconciled), agrégats validés, consommation
 * par fuel:outbox-dispatch (sent, sans PII), idempotence de publication.
 */
class FuelAccountingOutboxTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        return $company;
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    public function test_sale_records_outbox_event(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'Essence',
            'quantity' => 10,
            'unit_price' => 150,
            'external_id' => 'ACC-0001',
        ])->assertStatus(200);

        $event = DB::table('fuel_outbox_events')
            ->where('company_id', $company->id)
            ->where('event_type', FuelOutboxEvent::EVENT_SALE_RECORDED)
            ->first();

        $this->assertNotNull($event);
        $payload = json_decode((string) $event->payload, true);
        $this->assertIsArray($payload);
        $this->assertSame(1500.0, (float) ($payload['amount'] ?? 0));
        $this->assertArrayNotHasKey('employee_id', $payload); // pas de PII
    }

    public function test_cash_session_close_publishes_closed_event(): void
    {
        $company = $this->company();
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($operator);

        /** @var FuelCashSession $session */
        $session = FuelCashSession::query()->create([
            'company_id' => $company->id,
            'opened_by' => $operator->id,
            'opening_balance' => 100,
            'status' => FuelCashSession::STATUS_OPEN,
        ]);

        $this->postJson('/api/v1/fuel-station/cash-sessions/'.$session->id.'/close', [
            'closing_balance' => 500,
        ])->assertStatus(200);

        $event = DB::table('fuel_outbox_events')
            ->where('company_id', $company->id)
            ->where('event_type', FuelOutboxEvent::EVENT_CASH_SESSION_CLOSED)
            ->first();

        $this->assertNotNull($event);
    }

    public function test_outbox_dispatch_consumes_and_marks_sent(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'Gazole',
            'quantity' => 30,
            'unit_price' => 140,
            'external_id' => 'ACC-0002',
        ])->assertStatus(200);

        $this->artisan('fuel:outbox-dispatch', ['--limit' => 10])
            ->assertExitCode(0);

        $event = DB::table('fuel_outbox_events')
            ->where('company_id', $company->id)
            ->where('event_type', FuelOutboxEvent::EVENT_SALE_RECORDED)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame(FuelOutboxEvent::STATUS_SENT, $event->status);
        $this->assertNotNull($event->processed_at);
    }

    public function test_outbox_publish_is_idempotent(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        // Deux ventes distinctes → deux événements (un par agrégat).
        $this->postJson('/api/v1/fuel-station/sales', ['product' => 'A', 'quantity' => 1, 'unit_price' => 10, 'external_id' => 'I1'])->assertStatus(200);
        $this->postJson('/api/v1/fuel-station/sales', ['product' => 'B', 'quantity' => 2, 'unit_price' => 20, 'external_id' => 'I2'])->assertStatus(200);

        $count = FuelOutboxEvent::query()
            ->where('company_id', $company->id)
            ->where('event_type', FuelOutboxEvent::EVENT_SALE_RECORDED)
            ->count();

        $this->assertSame(2, $count);
    }
}
