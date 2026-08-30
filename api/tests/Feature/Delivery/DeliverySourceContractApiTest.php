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
 * DELIVERY-208 (#6299) — Contrats sources (restaurant/retail/ecommerce/crm).
 *
 * - création 201 + **rejeu → même livraison (200)**, jamais de doublon ;
 * - deux sources différentes, même référence → deux livraisons distinctes ;
 * - `manual` sans référence → chaque appel crée une nouvelle livraison ;
 * - isolation tenant : même source_reference sur un autre tenant → distinct.
 */
class DeliverySourceContractApiTest extends TestCase
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

    /**
     * @return array<string, array{string, string}>
     */
    public static function sourceProviders(): array
    {
        return [
            'restaurant' => ['restaurant', 'RST-2026-0001'],
            'retail' => ['retail', 'POS-2026-0001'],
            'ecommerce' => ['ecommerce', 'ORD-AMZ-778899'],
            'crm' => ['crm', 'CRM-ORD-42'],
        ];
    }

    /**
     * @dataProvider sourceProviders
     */
    public function test_source_creation_and_replay_is_idempotent(string $source, string $reference): void
    {
        Sanctum::actingAs($this->manager());

        $body = [
            'source' => $source,
            'source_reference' => $reference,
            'type' => $source === 'restaurant' ? 'food' : 'parcel',
            'dropoff_contact' => 'Client',
            'dropoff_address' => 'Alger',
        ];

        $first = $this->postJson('/api/v1/delivery/deliveries', $body)->assertStatus(201);
        $second = $this->postJson('/api/v1/delivery/deliveries', $body)->assertStatus(200);

        self::assertSame($first->json('data.id'), $second->json('data.id'));
        self::assertSame($source, $second->json('data.source'));
        self::assertSame($reference, $second->json('data.source_reference'));

        self::assertSame(
            1,
            Delivery::query()->where('source', $source)->where('source_reference', $reference)->count(),
            'Rejeu → zéro doublon par commande source.',
        );
    }

    public function test_same_reference_across_sources_is_distinct(): void
    {
        Sanctum::actingAs($this->manager());

        foreach (['restaurant', 'retail'] as $source) {
            $this->postJson('/api/v1/delivery/deliveries', [
                'source' => $source,
                'source_reference' => 'SAME-REF-1',
                'type' => 'parcel',
                'dropoff_contact' => 'Client',
                'dropoff_address' => 'Alger',
            ])->assertStatus(201);
        }

        self::assertSame(
            2,
            Delivery::query()->where('source_reference', 'SAME-REF-1')->count(),
            'L\'unicité est (company, source, source_reference) — pas (company, source_reference).',
        );
    }

    public function test_manual_without_reference_creates_new_each_time(): void
    {
        Sanctum::actingAs($this->manager());

        $body = [
            'source' => 'manual',
            'type' => 'parcel',
            'dropoff_contact' => 'Client',
            'dropoff_address' => 'Alger',
        ];

        $first = $this->postJson('/api/v1/delivery/deliveries', $body)->assertStatus(201);
        $second = $this->postJson('/api/v1/delivery/deliveries', $body)->assertStatus(201);

        self::assertNotSame($first->json('data.id'), $second->json('data.id'));
    }

    public function test_source_reference_is_required_outside_manual(): void
    {
        Sanctum::actingAs($this->manager());

        $this->postJson('/api/v1/delivery/deliveries', [
            'source' => 'ecommerce',
            'type' => 'parcel',
            'dropoff_contact' => 'Client',
            'dropoff_address' => 'Alger',
        ])->assertStatus(422);
    }

    public function test_source_replay_is_tenant_scoped(): void
    {
        Sanctum::actingAs($this->manager());

        $this->postJson('/api/v1/delivery/deliveries', [
            'source' => 'restaurant',
            'source_reference' => 'RST-2026-T1',
            'type' => 'food',
            'dropoff_contact' => 'Client',
            'dropoff_address' => 'Alger',
        ])->assertStatus(201);

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

        // Même source_reference, autre tenant → NOUVELLE livraison (201).
        $this->postJson('/api/v1/delivery/deliveries', [
            'source' => 'restaurant',
            'source_reference' => 'RST-2026-T1',
            'type' => 'food',
            'dropoff_contact' => 'Client',
            'dropoff_address' => 'Alger',
        ])->assertStatus(201);
    }
}
