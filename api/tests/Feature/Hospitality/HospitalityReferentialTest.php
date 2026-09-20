<?php

declare(strict_types=1);

namespace Tests\Feature\Hospitality;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Models\HospitalityRoomType;
use App\Modules\HospitalityManager\Domain\Models\HospitalityUnit;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-32 HOSPITALITY (HOSP-002, #7944) — référentiel multi-établissements :
 * CRUD établissements / types de chambres / unités, publication vitrine
 * (slug unique global), isolation cross-tenant (404), flag inactif (403
 * HOSPITALITY_SOLUTION_INACTIVE), RBAC deny-by-default (employé lambda 403).
 */
class HospitalityReferentialTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $otherCompany;

    private Employee $admin;

    private Employee $lambda;

    private Employee $otherAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);
        $company->setFeature('hospitality', true);
        $company->save();
        $this->company = $company;

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $otherCompany->setFeature('hospitality', true);
        $otherCompany->save();
        $this->otherCompany = $otherCompany;

        $this->admin = $this->manager($this->company);
        $this->lambda = $this->employee($this->company);
        $this->otherAdmin = $this->manager($this->otherCompany);
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        return $employee;
    }

    private function employee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'role' => 'employee',
        ]);

        return $employee;
    }

    /** @return array<string, mixed> */
    private function propertyPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Hôtel du Centre',
            'code' => 'HTL-001',
            'type' => 'hotel',
            'country' => 'FR',
            'currency' => 'EUR',
            'city' => 'Lyon',
            'star_rating' => 4,
        ], $overrides);
    }

    private function createProperty(Company $company, array $overrides = []): HospitalityProperty
    {
        /** @var HospitalityProperty $property */
        $property = HospitalityProperty::query()->withoutGlobalScopes()->create(array_merge([
            'company_id' => $company->id,
            'name' => 'Résidence Les Pins',
            'code' => 'RES-'.fake()->unique()->numerify('###'),
            'type' => 'residence',
            'country' => 'FR',
            'currency' => 'EUR',
        ], $overrides));

        return $property;
    }

    // ── Gate & RBAC ────────────────────────────────────────────────────

    public function test_properties_require_authentication(): void
    {
        $this->getJson('/api/v1/hospitality/properties')->assertStatus(401);
    }

    public function test_properties_return_403_when_solution_disabled(): void
    {
        /** @var Company $disabled */
        $disabled = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        Sanctum::actingAs($this->manager($disabled));

        $this->getJson('/api/v1/hospitality/properties')
            ->assertStatus(403)
            ->assertJsonPath('error', 'HOSPITALITY_SOLUTION_INACTIVE');
    }

    public function test_lambda_employee_cannot_manage_properties(): void
    {
        Sanctum::actingAs($this->lambda);

        $this->getJson('/api/v1/hospitality/properties')->assertStatus(403);
        $this->postJson('/api/v1/hospitality/properties', $this->propertyPayload())->assertStatus(403);
    }

    // ── CRUD établissements ────────────────────────────────────────────

    public function test_admin_can_create_and_read_back_a_property(): void
    {
        Sanctum::actingAs($this->admin);

        $created = $this->postJson('/api/v1/hospitality/properties', $this->propertyPayload())
            ->assertStatus(201)
            ->json('data');

        $this->assertSame('HTL-001', $created['code']);
        $this->assertFalse($created['is_public']);
        $this->assertNull($created['public_slug']);

        $this->getJson('/api/v1/hospitality/properties')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'HTL-001');
    }

    public function test_property_code_is_unique_per_tenant_but_reusable_across_tenants(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/hospitality/properties', $this->propertyPayload())->assertStatus(201);
        // Doublon dans le même tenant → 422.
        $this->postJson('/api/v1/hospitality/properties', $this->propertyPayload())->assertStatus(422);

        // Même code chez un AUTRE tenant → 201.
        Sanctum::actingAs($this->otherAdmin);
        $this->postJson('/api/v1/hospitality/properties', $this->propertyPayload(['country' => 'CM', 'currency' => 'XAF']))
            ->assertStatus(201);
    }

    public function test_property_type_is_bounded(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/hospitality/properties', $this->propertyPayload(['type' => 'castle']))
            ->assertStatus(422);
    }

    public function test_cross_tenant_property_is_a_404_on_show_update_and_delete(): void
    {
        $foreign = $this->createProperty($this->otherCompany);

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/hospitality/properties/{$foreign->getKey()}")->assertStatus(404);
        $this->patchJson("/api/v1/hospitality/properties/{$foreign->getKey()}", ['name' => 'Hack'])
            ->assertStatus(404);
        $this->deleteJson("/api/v1/hospitality/properties/{$foreign->getKey()}")->assertStatus(404);
        $this->postJson("/api/v1/hospitality/properties/{$foreign->getKey()}/publish")->assertStatus(404);
    }

    public function test_update_and_delete_property(): void
    {
        $property = $this->createProperty($this->company);

        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/v1/hospitality/properties/{$property->getKey()}", [
            'name' => 'Résidence Les Pins (rénovée)',
            'status' => 'inactive',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Résidence Les Pins (rénovée)')
            ->assertJsonPath('data.status', 'inactive');

        $this->deleteJson("/api/v1/hospitality/properties/{$property->getKey()}")->assertStatus(204);
    }

    public function test_property_with_inventory_cannot_be_deleted(): void
    {
        $property = $this->createProperty($this->company);
        HospitalityRoomType::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'property_id' => $property->getKey(),
            'code' => 'STD',
            'name' => 'Standard',
            'currency' => 'EUR',
        ]);

        Sanctum::actingAs($this->admin);

        $this->deleteJson("/api/v1/hospitality/properties/{$property->getKey()}")->assertStatus(422);
    }

    // ── Publication vitrine ────────────────────────────────────────────

    public function test_publish_generates_a_globally_unique_slug_and_unpublish_keeps_it(): void
    {
        $property = $this->createProperty($this->company, ['name' => 'Hôtel Bellevue']);

        Sanctum::actingAs($this->admin);

        $data = $this->postJson("/api/v1/hospitality/properties/{$property->getKey()}/publish")
            ->assertStatus(200)
            ->json('data');

        $this->assertTrue($data['is_public']);
        $this->assertNotNull($data['public_slug']);
        $this->assertStringStartsWith('hotel-bellevue-', $data['public_slug']);
        $this->assertSame('/stay/'.$data['public_slug'], $data['public_url']);

        $slug = $data['public_slug'];

        $unpublished = $this->postJson("/api/v1/hospitality/properties/{$property->getKey()}/unpublish")
            ->assertStatus(200)
            ->json('data');

        $this->assertFalse($unpublished['is_public']);
        $this->assertSame($slug, $unpublished['public_slug']);
        $this->assertNull($unpublished['public_url']);

        // Re-publication : le MÊME slug est conservé (URL stable).
        $republished = $this->postJson("/api/v1/hospitality/properties/{$property->getKey()}/publish")
            ->assertStatus(200)
            ->json('data');

        $this->assertSame($slug, $republished['public_slug']);
    }

    // ── Types de chambres ──────────────────────────────────────────────

    public function test_room_type_crud_scoped_to_property(): void
    {
        $property = $this->createProperty($this->company);

        Sanctum::actingAs($this->admin);

        $created = $this->postJson("/api/v1/hospitality/properties/{$property->getKey()}/room-types", [
            'name' => 'Suite Familiale',
            'code' => 'SUITE-FAM',
            'capacity_adults' => 2,
            'capacity_children' => 2,
            'base_price_minor' => 18500,
            'currency' => 'EUR',
        ])->assertStatus(201)->json('data');

        $this->assertSame($property->getKey(), $created['property_id']);

        // Doublon de code dans le MÊME établissement → 422.
        $this->postJson("/api/v1/hospitality/properties/{$property->getKey()}/room-types", [
            'name' => 'Suite bis',
            'code' => 'SUITE-FAM',
            'currency' => 'EUR',
        ])->assertStatus(422);

        $this->getJson("/api/v1/hospitality/properties/{$property->getKey()}/room-types")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->patchJson("/api/v1/hospitality/room-types/{$created['id']}", ['base_price_minor' => 19900])
            ->assertStatus(200)
            ->assertJsonPath('data.base_price_minor', 19900);

        $this->deleteJson("/api/v1/hospitality/room-types/{$created['id']}")->assertStatus(204);
    }

    public function test_room_type_of_other_tenant_is_a_404(): void
    {
        $foreignProperty = $this->createProperty($this->otherCompany);
        /** @var HospitalityRoomType $foreignType */
        $foreignType = HospitalityRoomType::query()->withoutGlobalScopes()->create([
            'company_id' => $this->otherCompany->id,
            'property_id' => $foreignProperty->getKey(),
            'code' => 'STD',
            'name' => 'Standard',
            'currency' => 'XAF',
        ]);

        Sanctum::actingAs($this->admin);

        // Route imbriquée sous un établissement étranger → 404.
        $this->getJson("/api/v1/hospitality/properties/{$foreignProperty->getKey()}/room-types")->assertStatus(404);
        $this->postJson("/api/v1/hospitality/properties/{$foreignProperty->getKey()}/room-types", [
            'name' => 'Hack', 'code' => 'HCK', 'currency' => 'EUR',
        ])->assertStatus(404);

        // Route shallow sur un type étranger → 404.
        $this->patchJson("/api/v1/hospitality/room-types/{$foreignType->getKey()}", ['name' => 'Hack'])->assertStatus(404);
        $this->deleteJson("/api/v1/hospitality/room-types/{$foreignType->getKey()}")->assertStatus(404);
    }

    // ── Unités physiques ───────────────────────────────────────────────

    public function test_unit_crud_and_room_type_attachment_rules(): void
    {
        $property = $this->createProperty($this->company);
        $otherProperty = $this->createProperty($this->company);
        /** @var HospitalityRoomType $roomType */
        $roomType = HospitalityRoomType::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'property_id' => $property->getKey(),
            'code' => 'STD',
            'name' => 'Standard',
            'currency' => 'EUR',
        ]);
        /** @var HospitalityRoomType $otherPropertyType */
        $otherPropertyType = HospitalityRoomType::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'property_id' => $otherProperty->getKey(),
            'code' => 'STD',
            'name' => 'Standard',
            'currency' => 'EUR',
        ]);

        Sanctum::actingAs($this->admin);

        // Unité rattachée à un type du MÊME établissement → 201.
        $created = $this->postJson("/api/v1/hospitality/properties/{$property->getKey()}/units", [
            'room_type_id' => $roomType->getKey(),
            'code' => 'CH-101',
            'floor' => '1',
        ])->assertStatus(201)->json('data');

        $this->assertSame('available', $created['status']);

        // Unité SANS type (appartement locatif hors typologie) → 201.
        $this->postJson("/api/v1/hospitality/properties/{$property->getKey()}/units", [
            'code' => 'APT-B2',
        ])->assertStatus(201);

        // Type d'un AUTRE établissement du même tenant → 422.
        $this->postJson("/api/v1/hospitality/properties/{$property->getKey()}/units", [
            'room_type_id' => $otherPropertyType->getKey(),
            'code' => 'CH-102',
        ])->assertStatus(422);

        // Doublon de code dans le même établissement → 422.
        $this->postJson("/api/v1/hospitality/properties/{$property->getKey()}/units", [
            'code' => 'CH-101',
        ])->assertStatus(422);

        // Statut borné.
        $this->patchJson("/api/v1/hospitality/units/{$created['id']}", ['status' => 'cleaning'])
            ->assertStatus(422);

        $this->patchJson("/api/v1/hospitality/units/{$created['id']}", ['status' => 'maintenance'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'maintenance');

        // Un type rattaché à une unité n'est plus supprimable.
        $this->deleteJson("/api/v1/hospitality/room-types/{$roomType->getKey()}")->assertStatus(422);

        $this->deleteJson("/api/v1/hospitality/units/{$created['id']}")->assertStatus(204);
    }

    public function test_unit_of_other_tenant_is_a_404(): void
    {
        $foreignProperty = $this->createProperty($this->otherCompany);
        /** @var HospitalityUnit $foreignUnit */
        $foreignUnit = HospitalityUnit::query()->withoutGlobalScopes()->create([
            'company_id' => $this->otherCompany->id,
            'property_id' => $foreignProperty->getKey(),
            'code' => 'CH-1',
        ]);

        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/v1/hospitality/units/{$foreignUnit->getKey()}", ['status' => 'occupied'])->assertStatus(404);
        $this->deleteJson("/api/v1/hospitality/units/{$foreignUnit->getKey()}")->assertStatus(404);
    }
}
