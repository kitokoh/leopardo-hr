<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use App\Modules\TravelAgency\Domain\Models\TravelCarrierToken;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-807 (#6086) — Synchronisation des trajets transporteurs
 * (API entrante).
 *
 * Couvre : l'upsert idempotent (un trajet synchronisé 2× n'est jamais
 * dupliqué — critère d'acceptation), l'autorisation par jeton (401 sans
 * jeton / jeton invalide), les bornes (total_seats ≤ 200) et la
 * synchronisation des tarifs par classe.
 */
class TravelCarrierSyncApiTest extends TestCase
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
     * Transporteur + jeton valide.
     */
    private function carrierWithToken(Company $company, string $plainToken = 'tok-test-123'): array
    {
        $ids = app(TenantManager::class)->withinTenant($company, function () use ($company, $plainToken): array {
            $carrier = TravelCarrier::factory()->create();

            $token = TravelCarrierToken::query()->create([
                'company_id' => $company->id,
                'carrier_id' => $carrier->id,
                'name' => 'Test',
                'token_hash' => TravelCarrierToken::hash($plainToken),
                'active' => true,
            ]);

            $class = TravelClass::factory()->create();

            $origin = TravelCity::factory()->create();
            $destination = TravelCity::factory()->create();

            return [
                'carrier' => $carrier->id,
                'token' => $token->id,
                'class' => $class->id,
                'origin_city' => $origin->id,
                'destination_city' => $destination->id,
            ];
        });

        return $ids;
    }

    public function test_sync_creates_trip_and_replay_does_not_duplicate(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $ids = $this->carrierWithToken($company);

        $payload = [
            'external_id' => 'TRP-EXT-001',
            'code' => 'EXT-001',
            'route' => [
                'external_id' => 'RTE-EXT-001',
                'origin_city_id' => $ids['origin_city'],
                'destination_city_id' => $ids['destination_city'],
            ],
            'departure_date' => now()->addDays(3)->toDateString(),
            'departure_time' => '08:00:00',
            'arrival_date' => now()->addDays(3)->toDateString(),
            'arrival_time' => '12:00:00',
            'means_of_transport' => 'bus',
            'total_seats' => 40,
            'status' => 'scheduled',
            'prices' => [[
                'class_id' => $ids['class'],
                'adult_price_minor' => 12000,
                'child_price_minor' => 8000,
                'currency' => 'XAF',
            ]],
        ];

        $headers = ['X-Carrier-Token' => 'tok-test-123'];

        $this->postJson('/api/v1/travel/carrier-sync/trips', $payload, $headers)
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'EXT-001');

        // Rejeu : upsert, pas de doublon.
        $this->postJson('/api/v1/travel/carrier-sync/trips', $payload, $headers)
            ->assertStatus(201);

        $this->assertSame(1, TravelTrip::query()
            ->where('company_id', $company->id)
            ->where('external_id', 'TRP-EXT-001')
            ->count());

        // Tarif synchronisé.
        $trip = TravelTrip::query()->where('external_id', 'TRP-EXT-001')->firstOrFail();
        $this->assertSame(12000, $trip->prices()->first()?->adult_price_minor);
    }

    public function test_sync_requires_valid_token(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->carrierWithToken($company, 'tok-test-123');

        // Payload valide (route optionnelle) : la validation passe et c'est
        // bien l'autorisation par jeton qui est testée (401 avant tout upsert).
        $payload = [
            'external_id' => 'TRP-EXT-002',
            'departure_date' => now()->addDays(3)->toDateString(),
            'departure_time' => '09:00:00',
            'total_seats' => 30,
        ];

        // Sans jeton → 401.
        $this->postJson('/api/v1/travel/carrier-sync/trips', $payload)->assertStatus(401);

        // Jeton invalide → 401.
        $this->postJson('/api/v1/travel/carrier-sync/trips', $payload, ['X-Carrier-Token' => 'mauvais-token'])
            ->assertStatus(401);
    }

    public function test_sync_enforces_seat_bounds(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->carrierWithToken($company);

        $this->postJson('/api/v1/travel/carrier-sync/trips', [
            'external_id' => 'TRP-EXT-003',
            'departure_date' => now()->addDays(3)->toDateString(),
            'total_seats' => 500,
        ], ['X-Carrier-Token' => 'tok-test-123'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('total_seats');
    }
}
