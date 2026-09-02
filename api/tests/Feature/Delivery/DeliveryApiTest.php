<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Delivery\Domain\Models\Delivery;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DELIVERY-201 (#6285) — API des livraisons (CRUD).
 *
 * - 401 sans authentification ; 403 employé non-manager ; 403 flag inactif ;
 * - création avec référence DLV-… générée + validation stricte ;
 * - isolation tenant : l'id d'une livraison du tenant A est 404 depuis B.
 */
class DeliveryApiTest extends TestCase
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

    private function employee(): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $employee;
    }

    public function test_list_requires_authentication(): void
    {
        $this->getJson('/api/v1/delivery/deliveries')->assertStatus(401);
    }

    public function test_list_rejects_non_manager(): void
    {
        Sanctum::actingAs($this->employee());

        $this->getJson('/api/v1/delivery/deliveries')
            ->assertStatus(403)
            ->assertJson(['error' => 'MANAGER_REQUIRED']);
    }

    public function test_list_rejects_disabled_feature_flag(): void
    {
        $this->company->setFeature('delivery', false);
        $this->company->save();

        Sanctum::actingAs($this->manager());

        $this->getJson('/api/v1/delivery/deliveries')
            ->assertStatus(403)
            ->assertJson(['error' => 'FEATURE_NOT_ENABLED']);
    }

    public function test_store_creates_delivery_with_generated_reference(): void
    {
        Sanctum::actingAs($this->manager());

        $response = $this->postJson('/api/v1/delivery/deliveries', [
            'source' => 'manual',
            'type' => 'parcel',
            'cod_amount_minor' => 12000,
            'dropoff_contact' => 'Client Alpha',
            'dropoff_address' => '12 Rue Didouche, Alger',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.source', 'manual');
        $response->assertJsonPath('data.status', 'created');
        $response->assertJsonPath('data.cod_amount_minor', 12000);

        $reference = $response->json('data.reference');
        self::assertMatchesRegularExpression('/^DLV-\d{4}-\d{6}$/', $reference);
        self::assertDatabaseHas('delivery_deliveries', ['reference' => $reference]);
    }

    public function test_store_rejects_invalid_source_and_missing_dropoff(): void
    {
        Sanctum::actingAs($this->manager());

        $this->postJson('/api/v1/delivery/deliveries', [
            'source' => 'teleport',
            'type' => 'parcel',
        ])->assertStatus(422);

        $this->postJson('/api/v1/delivery/deliveries', [
            'source' => 'manual',
            'type' => 'parcel',
            'dropoff_contact' => 'Client',
            // dropoff_address manquante
        ])->assertStatus(422);
    }

    public function test_show_is_tenant_scoped(): void
    {
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $other->setFeature('delivery', true);
        $other->save();

        Sanctum::actingAs($this->manager());

        $created = $this->postJson('/api/v1/delivery/deliveries', [
            'source' => 'manual',
            'type' => 'parcel',
            'dropoff_contact' => 'Client',
            'dropoff_address' => 'Alger',
        ])->assertStatus(201)->json('data');

        // Le même employé (tenant A) voit sa livraison.
        $this->getJson('/api/v1/delivery/deliveries/'.$created['id'])
            ->assertOk()
            ->assertJsonPath('data.reference', $created['reference']);

        // Un manager du tenant B ne doit PAS voir cette livraison (404 sûr).
        /** @var Employee $managerB */
        $managerB = Employee::factory()->create([
            'company_id' => $other->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($managerB);

        $this->getJson('/api/v1/delivery/deliveries/'.$created['id'])->assertStatus(404);
    }

    public function test_list_is_paginated_and_filtered(): void
    {
        Sanctum::actingAs($this->manager());

        foreach (['DLV-2026-000001', 'DLV-2026-000002'] as $i => $reference) {
            Delivery::query()->create([
                'company_id' => $this->company->id,
                'reference' => $reference,
                'source' => $i === 0 ? 'manual' : 'restaurant',
                'source_reference' => $i === 0 ? null : 'RST-2026-0001',
                'type' => 'parcel',
                'status' => 'created',
                'dropoff_contact' => 'Client',
                'dropoff_address' => 'Alger',
            ]);
        }

        $this->getJson('/api/v1/delivery/deliveries?source=manual')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/delivery/deliveries?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.last_page', 2);
    }
}
