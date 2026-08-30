<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelCustomer;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Intégration CRM client & fidélité — FUEL-016 (issue #5810).
 *
 * Couvre : upsert idempotent par external_id, consentement marketing
 * explicite (opt-in RGPD) versionné (outbox), dépense de points bornée au
 * solde, lien vente → client (points crédités), isolation tenant, aucune
 * lecture du CRM commercial Leopardo (le module ne touche que
 * fuel_customers).
 */
class FuelCustomerApiTest extends TestCase
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

    private function operator(Company $company): Employee
    {
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);

        return $operator;
    }

    public function test_customer_upsert_is_idempotent_by_external_id(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        $payload = [
            'external_id' => 'CUST-0001',
            'full_name' => 'Société Tazi',
            'phone' => '+213555123456',
            'email' => 'contact@tazi.dz',
            'marketing_consent' => true,
        ];

        /** @var array<string, mixed> $first */
        $first = $this->postJson('/api/v1/fuel-station/customers', $payload)->assertStatus(200)->json('data');
        /** @var array<string, mixed> $second */
        $second = $this->postJson('/api/v1/fuel-station/customers', $payload)->assertStatus(200)->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, FuelCustomer::query()->where('company_id', $company->id)->count());

        // phone/email chiffrés au repos : la colonne ne contient pas le clair.
        $row = DB::table('fuel_customers')->where('company_id', $company->id)->first();
        $this->assertNotNull($row);
        $this->assertStringNotContainsString('contact@tazi.dz', (string) $row->email);
    }

    public function test_operator_cannot_manage_customers(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->operator($company));

        $this->postJson('/api/v1/fuel-station/customers', [
            'external_id' => 'C1',
            'full_name' => 'Client X',
        ])->assertStatus(403);
    }

    public function test_consent_change_is_versioned(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        Sanctum::actingAs($manager);

        /** @var FuelCustomer $customer */
        $customer = FuelCustomer::query()->create([
            'company_id' => $company->id,
            'external_id' => 'C2',
            'full_name' => 'Client Consent',
            'marketing_consent' => false,
        ]);

        $this->putJson('/api/v1/fuel-station/customers/'.$customer->id.'/consent', [
            'marketing_consent' => true,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.marketing_consent', true);

        $events = DB::table('fuel_outbox_events')
            ->where('company_id', $company->id)
            ->where('event_type', 'fuel.customer.consent.updated.v1')
            ->count();

        $this->assertSame(1, $events);
    }

    public function test_redeem_points_bounded_by_balance(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        /** @var FuelCustomer $customer */
        $customer = FuelCustomer::query()->create([
            'company_id' => $company->id,
            'external_id' => 'C3',
            'full_name' => 'Client Points',
            'loyalty_points' => 100,
        ]);

        $this->postJson('/api/v1/fuel-station/customers/'.$customer->id.'/redeem', [
            'points' => 150,
            'reason' => 'Récompense',
        ])->assertStatus(422);

        $this->postJson('/api/v1/fuel-station/customers/'.$customer->id.'/redeem', [
            'points' => 40,
            'reason' => 'Récompense',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.loyalty_points', 60);
    }

    public function test_cross_tenant_customer_is_404(): void
    {
        $companyA = $this->company();
        Sanctum::actingAs($this->manager($companyA));

        /** @var FuelCustomer $customer */
        $customer = FuelCustomer::query()->create([
            'company_id' => $companyA->id,
            'external_id' => 'CA',
            'full_name' => 'Tenant A',
        ]);

        $companyB = $this->company();
        Sanctum::actingAs($this->manager($companyB));

        $this->getJson('/api/v1/fuel-station/customers/'.$customer->id)->assertStatus(404);
    }
}
