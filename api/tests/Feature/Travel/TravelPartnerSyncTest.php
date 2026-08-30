<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use App\Modules\TravelAgency\Domain\Models\TravelCarrierApiKey;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-807 (#6086) — API entrante transporteurs.
 *
 * Émission/révocation de clés (travel.manage) + synchro idempotente par clé
 * externe : un trajet synchronisé 2× est mis à jour, jamais dupliqué.
 */
class TravelPartnerSyncTest extends TestCase
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

    /**
     * @return array{company: Company, carrier: TravelCarrier, cityA: TravelCity, cityB: TravelCity, class: TravelClass}
     */
    private function setupTenant(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $carrier = app(TenantManager::class)->withinTenant($company, fn (): TravelCarrier => TravelCarrier::factory()->create(['code' => 'CARR-1', 'status' => 'active']));
        $cityA = app(TenantManager::class)->withinTenant($company, fn (): TravelCity => TravelCity::factory()->create(['name' => 'Ville A']));
        $cityB = app(TenantManager::class)->withinTenant($company, fn (): TravelCity => TravelCity::factory()->create(['name' => 'Ville B']));
        $class = app(TenantManager::class)->withinTenant($company, fn (): TravelClass => TravelClass::factory()->create(['code' => 'STANDARD']));

        return ['company' => $company, 'carrier' => $carrier, 'cityA' => $cityA, 'cityB' => $cityB, 'class' => $class];
    }

    /**
     * @return array{token: string, key: TravelCarrierApiKey}
     */
    private function issueKey(Company $company, TravelCarrier $carrier): array
    {
        $response = $this->postJson('/api/v1/travel/partner-keys', [
            'carrier_id' => $carrier->id,
            'label' => 'Transporteur Test',
        ])->assertStatus(201);

        $token = (string) $response->json('data.api_key');
        $this->assertStringStartsWith('ptk_', $token);

        $key = app(TenantManager::class)->withinTenant($company, function () use ($token): TravelCarrierApiKey {
            return TravelCarrierApiKey::query()->where('api_key_hash', hash('sha256', $token))->firstOrFail();
        });

        return ['token' => $token, 'key' => $key];
    }

    /**
     * @return array{routes: list<array<string, mixed>>, trips: list<array<string, mixed>>}
     */
    private function syncPayload(TravelCity $cityA, TravelCity $cityB, TravelClass $class): array
    {
        return [
            'routes' => [
                [
                    'external_ref' => 'EXT-ROUTE-1',
                    'code' => 'ROUTE-1',
                    'origin_city_id' => $cityA->id,
                    'destination_city_id' => $cityB->id,
                    'distance_km' => 250,
                    'duration_min' => 180,
                    'status' => 'active',
                ],
            ],
            'trips' => [
                [
                    'external_ref' => 'EXT-TRIP-1',
                    'route_external_ref' => 'EXT-ROUTE-1',
                    'code' => 'TRIP-1',
                    'departure_date' => '2026-10-01',
                    'departure_time' => '08:00',
                    'arrival_date' => '2026-10-01',
                    'arrival_time' => '11:00',
                    'means_of_transport' => 'bus',
                    'total_seats' => 40,
                    'status' => 'scheduled',
                    'prices' => [
                        [
                            'class_code' => $class->code,
                            'adult_price_minor' => 15000,
                            'child_price_minor' => 7500,
                            'currency' => 'XAF',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_principal_issues_partner_key(): void
    {
        $setup = $this->setupTenant();

        $result = $this->issueKey($setup['company'], $setup['carrier']);

        $this->assertTrue($result['key']->enabled);
        $this->assertSame($setup['carrier']->id, $result['key']->carrier_id);
    }

    public function test_sync_creates_routes_and_trips_then_updates_on_replay(): void
    {
        $setup = $this->setupTenant();
        $key = $this->issueKey($setup['company'], $setup['carrier']);
        $payload = $this->syncPayload($setup['cityA'], $setup['cityB'], $setup['class']);

        // 1er appel : création.
        $this->postJson('/api/v1/travel/partner/sync', $payload, [
            'X-Partner-Key' => $key['token'],
        ])->assertOk()
            ->assertJsonPath('data.routes_created', 1)
            ->assertJsonPath('data.trips_created', 1)
            ->assertJsonPath('data.trips_updated', 0)
            ->assertJsonPath('data.errors', []);

        // Rejeu : mise à jour, aucune duplication (acceptance TRAVEL-807).
        $this->postJson('/api/v1/travel/partner/sync', $payload, [
            'X-Partner-Key' => $key['token'],
        ])->assertOk()
            ->assertJsonPath('data.routes_created', 0)
            ->assertJsonPath('data.routes_updated', 1)
            ->assertJsonPath('data.trips_created', 0)
            ->assertJsonPath('data.trips_updated', 1);

        $counts = app(TenantManager::class)->withinTenant($setup['company'], function (): array {
            return [
                'routes' => TravelRoute::query()->count(),
                'trips' => TravelTrip::query()->count(),
            ];
        });

        $this->assertSame(['routes' => 1, 'trips' => 1], $counts);
    }

    public function test_sync_with_invalid_key_is_rejected(): void
    {
        $this->setupTenant();

        $this->postJson('/api/v1/travel/partner/sync', [
            'routes' => [],
            'trips' => [],
        ], [
            'X-Partner-Key' => 'ptk_invalid',
        ])->assertStatus(401);
    }

    public function test_sync_without_key_is_rejected(): void
    {
        $this->setupTenant();

        $this->postJson('/api/v1/travel/partner/sync', [
            'routes' => [],
            'trips' => [],
        ])->assertStatus(401);
    }

    public function test_revoked_key_is_rejected(): void
    {
        $setup = $this->setupTenant();
        $key = $this->issueKey($setup['company'], $setup['carrier']);

        $this->deleteJson('/api/v1/travel/partner-keys/'.$key['key']->id)
            ->assertOk();

        $this->postJson('/api/v1/travel/partner/sync', [
            'routes' => [],
            'trips' => [],
        ], [
            'X-Partner-Key' => $key['token'],
        ])->assertStatus(401);
    }

    public function test_sync_unknown_route_is_reported_per_item(): void
    {
        $setup = $this->setupTenant();
        $key = $this->issueKey($setup['company'], $setup['carrier']);

        $payload = $this->syncPayload($setup['cityA'], $setup['cityB'], $setup['class']);
        $payload['trips'][0]['route_external_ref'] = 'EXT-INCONNUE';

        $this->postJson('/api/v1/travel/partner/sync', $payload, [
            'X-Partner-Key' => $key['token'],
        ])->assertOk()
            ->assertJsonPath('data.trips_created', 0)
            ->assertJsonPath('data.routes_created', 1)
            ->assertJsonCount(1, 'data.errors');
    }

    public function test_sync_rejects_oversized_batch(): void
    {
        $setup = $this->setupTenant();
        $key = $this->issueKey($setup['company'], $setup['carrier']);

        $routes = [];
        for ($i = 0; $i < 201; $i++) {
            $routes[] = ['external_ref' => "R-{$i}"];
        }

        $this->postJson('/api/v1/travel/partner/sync', [
            'routes' => $routes,
            'trips' => [],
        ], [
            'X-Partner-Key' => $key['token'],
        ])->assertStatus(422);
    }
}
