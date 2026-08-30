<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmContact;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * FUEL-016 (#5810) — Intégration CRM client (tenant).
 *
 * Couvre la référence client par valeur (validation tenant-scopée, jamais
 * les leads plateforme), l'opt-in marketing requis pour l'événement
 * d'activité, et l'absence de PII dans le payload outbox.
 */
class FuelCrmIntegrationTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_sale_with_consented_contact_publishes_activity_event(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $this->actingAs($company);

        $contactId = DB::table('crm_contacts')->insertGetId([
            'company_id' => $company->id,
            'first_name' => 'Ali',
            'last_name' => 'Bensalem',
            'email' => 'ali@example.dz',
            'phone' => '+213600000000',
        ]);

        $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'Essence',
            'quantity' => 10,
            'unit_price' => 150,
            'customer_contact_id' => $contactId,
            'marketing_consent' => true,
        ])->assertOk();

        $event = FuelOutboxEvent::query()->where('event_type', 'fuel.customer.activity.v1')->firstOrFail();

        // Payload sans PII : référence contact_id uniquement.
        $payload = $event->payload_redacted ?? [];
        $this->assertSame($contactId, $payload['customer_contact_id'] ?? null);
        $this->assertArrayNotHasKey('email', $payload);
        $this->assertArrayNotHasKey('phone', $payload);
        $this->assertArrayNotHasKey('first_name', $payload);
    }

    public function test_sale_without_consent_publishes_no_event(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $this->actingAs($company);

        $contactId = DB::table('crm_contacts')->insertGetId([
            'company_id' => $company->id,
            'first_name' => 'Sara',
            'last_name' => 'Meziane',
            'email' => 'sara@example.dz',
            'phone' => '+213600000001',
        ]);

        $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'Gazole',
            'quantity' => 5,
            'unit_price' => 200,
            'customer_contact_id' => $contactId,
            'marketing_consent' => false,
        ])->assertOk();

        $this->assertSame(0, FuelOutboxEvent::query()->where('event_type', 'fuel.customer.activity.v1')->count());
    }

    public function test_contact_from_another_tenant_is_rejected(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $this->actingAs($companyA);

        $foreignContact = DB::table('crm_contacts')->insertGetId([
            'company_id' => $companyB->id,
            'first_name' => 'X',
            'last_name' => 'Y',
            'email' => 'x@example.dz',
        ]);

        $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'Essence',
            'quantity' => 1,
            'unit_price' => 100,
            'customer_contact_id' => $foreignContact,
            'marketing_consent' => true,
        ])->assertStatus(422);
    }

    private function actingAs(Company $company): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($employee);
    }
}
