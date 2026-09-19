<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelDistributorKey;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7641 (TRAVEL-DISTRIBUTION) — clés API de lecture par distributeur.
 *
 * Couvre : émission/rotation/révocation par le gérant (RBAC principal/rh,
 * employé simple → 403), catalogue des voyages PUBLIÉS consommable par clé
 * scopée (X-Distributor-Key), enforcement des scopes (fail-closed), clé
 * révoquée → 401, ancien token invalide après rotation, stats d'usage, et
 * isolation cross-tenant (le catalogue/booking d'un autre tenant est
 * invisible).
 */
class TravelDistributorKeyTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('travelagency', true);
        $company->save();

        return $company;
    }

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

    /**
     * @param  list<string>  $scopes
     * @return array{key: TravelDistributorKey, token: string}
     */
    private function issueKey(Company $company, array $scopes = ['catalog.read']): array
    {
        $token = 'dsk_test_'.bin2hex(random_bytes(16));

        /** @var TravelDistributorKey $key */
        $key = app(TenantManager::class)->withinTenant($company, function () use ($token, $scopes): TravelDistributorKey {
            return TravelDistributorKey::factory()->create([
                'api_key_hash' => hash('sha256', $token),
                'scopes' => $scopes,
            ]);
        });

        return ['key' => $key, 'token' => $token];
    }

    private function publishedTrip(Company $company): TravelTrip
    {
        return app(TenantManager::class)->withinTenant($company, function (): TravelTrip {
            /** @var TravelTrip $trip */
            $trip = TravelTrip::factory()->create([
                'status' => TripStatus::PUBLISHED,
                'published_at' => now(),
            ]);

            return $trip;
        });
    }

    public function test_principal_creates_key_and_token_is_shown_once(): void
    {
        $company = $this->company();
        $this->principal($company);

        $response = $this->postJson('/api/v1/travel/distributor-keys', [
            'name' => 'Plateforme Wasili',
            'scopes' => ['catalog.read', 'bookings.read'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Plateforme Wasili');

        $token = $response->json('data.api_key');
        $this->assertIsString($token);
        $this->assertStringStartsWith('dsk_', $token);

        // La liste n'expose JAMAIS le token ni le hash.
        $list = $this->getJson('/api/v1/travel/distributor-keys');
        $list->assertOk();
        $this->assertArrayNotHasKey('api_key', (array) $list->json('data.0'));
        $this->assertArrayNotHasKey('api_key_hash', (array) $list->json('data.0'));
    }

    public function test_unknown_scope_is_rejected_and_self_service_employee_forbidden(): void
    {
        $company = $this->company();
        $this->principal($company);

        $this->postJson('/api/v1/travel/distributor-keys', [
            'name' => 'Scope pirate',
            'scopes' => ['catalog.write'],
        ])->assertStatus(422);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'manager_role' => null,
        ]);
        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/travel/distributor-keys', [
            'name' => 'Interdit',
            'scopes' => ['catalog.read'],
        ])->assertStatus(403);
    }

    public function test_catalog_is_readable_with_scoped_key_and_only_published_trips(): void
    {
        $company = $this->company();
        $published = $this->publishedTrip($company);
        app(TenantManager::class)->withinTenant($company, function (): void {
            TravelTrip::factory()->create(['status' => TripStatus::DRAFT]);
        });

        ['token' => $token] = $this->issueKey($company, ['catalog.read']);

        $response = $this->getJson('/api/v1/travel/distributor/catalog', [
            'X-Distributor-Key' => $token,
        ]);

        $response->assertOk();
        $ids = array_column((array) $response->json('data'), 'id');
        $this->assertContains($published->id, $ids);
        $this->assertCount(1, $ids, 'Seuls les voyages PUBLIÉS sont exposés au distributeur.');
    }

    public function test_scope_enforcement_revocation_and_rotation(): void
    {
        $company = $this->company();
        $this->publishedTrip($company);

        // Scope manquant → 403 fail-closed.
        ['token' => $bookingOnly] = $this->issueKey($company, ['bookings.read']);
        $this->getJson('/api/v1/travel/distributor/catalog', [
            'X-Distributor-Key' => $bookingOnly,
        ])->assertStatus(403);

        // Clé sans header / clé inconnue → 401.
        $this->getJson('/api/v1/travel/distributor/catalog')->assertStatus(401);
        $this->getJson('/api/v1/travel/distributor/catalog', [
            'X-Distributor-Key' => 'dsk_inconnu',
        ])->assertStatus(401);

        ['key' => $key, 'token' => $token] = $this->issueKey($company, ['catalog.read']);
        $this->getJson('/api/v1/travel/distributor/catalog', [
            'X-Distributor-Key' => $token,
        ])->assertOk();

        // Rotation par le gérant : l'ancien token est immédiatement invalide.
        $this->principal($company);
        $rotate = $this->postJson("/api/v1/travel/distributor-keys/{$key->id}/rotate");
        $rotate->assertOk();
        $newToken = $rotate->json('data.api_key');
        $this->assertIsString($newToken);
        $this->assertNotSame($token, $newToken);

        $this->getJson('/api/v1/travel/distributor/catalog', [
            'X-Distributor-Key' => $token,
        ])->assertStatus(401);
        $this->getJson('/api/v1/travel/distributor/catalog', [
            'X-Distributor-Key' => $newToken,
        ])->assertOk();

        // Révocation : la clé cesse d'authentifier, la ligne reste (stats).
        $this->postJson("/api/v1/travel/distributor-keys/{$key->id}/revoke")
            ->assertOk()
            ->assertJsonPath('data.enabled', false);
        $this->getJson('/api/v1/travel/distributor/catalog', [
            'X-Distributor-Key' => $newToken,
        ])->assertStatus(401);
    }

    public function test_usage_stats_are_tracked_per_key(): void
    {
        $company = $this->company();
        $this->publishedTrip($company);
        ['key' => $key, 'token' => $token] = $this->issueKey($company, ['catalog.read']);

        $this->getJson('/api/v1/travel/distributor/catalog', ['X-Distributor-Key' => $token])->assertOk();
        $this->getJson('/api/v1/travel/distributor/catalog', ['X-Distributor-Key' => $token])->assertOk();

        $fresh = app(TenantManager::class)->withinTenant(
            $company,
            fn (): ?TravelDistributorKey => TravelDistributorKey::query()->find($key->id)
        );
        $this->assertNotNull($fresh);
        $this->assertSame(2, (int) $fresh->usage_count);
        $this->assertNotNull($fresh->last_used_at);
    }

    public function test_cross_tenant_isolation_on_catalog_bookings_and_key_management(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();

        $tripA = $this->publishedTrip($companyA);
        $tripB = $this->publishedTrip($companyB);

        ['key' => $keyA, 'token' => $tokenA] = $this->issueKey($companyA, ['catalog.read', 'bookings.read']);

        // Le catalogue de la clé A ne contient QUE les voyages du tenant A.
        $response = $this->getJson('/api/v1/travel/distributor/catalog', [
            'X-Distributor-Key' => $tokenA,
        ]);
        $response->assertOk();
        $ids = array_column((array) $response->json('data'), 'id');
        $this->assertContains($tripA->id, $ids);
        $this->assertNotContains($tripB->id, $ids);

        // Une réservation du tenant B est invisible pour la clé A (404).
        $referenceB = app(TenantManager::class)->withinTenant($companyB, function () use ($tripB): string {
            /** @var TravelBooking $booking */
            $booking = TravelBooking::factory()->create([
                'trip_id' => $tripB->id,
                'reference' => 'GV-B-0001',
            ]);

            return (string) $booking->reference;
        });

        $this->getJson('/api/v1/travel/distributor/bookings/'.$referenceB, [
            'X-Distributor-Key' => $tokenA,
        ])->assertStatus(404);

        // Le gérant du tenant B ne peut ni révoquer ni faire tourner la clé A.
        $this->principal($companyB);
        $this->postJson("/api/v1/travel/distributor-keys/{$keyA->id}/revoke")->assertStatus(404);
        $this->postJson("/api/v1/travel/distributor-keys/{$keyA->id}/rotate")->assertStatus(404);
    }

    public function test_booking_tracking_returns_minimal_payload(): void
    {
        $company = $this->company();
        $trip = $this->publishedTrip($company);

        $reference = app(TenantManager::class)->withinTenant($company, function () use ($trip): string {
            /** @var TravelBooking $booking */
            $booking = TravelBooking::factory()->create([
                'trip_id' => $trip->id,
                'reference' => 'GV-2026-0042',
            ]);

            return (string) $booking->reference;
        });

        ['token' => $token] = $this->issueKey($company, ['bookings.read']);

        $response = $this->getJson('/api/v1/travel/distributor/bookings/'.$reference, [
            'X-Distributor-Key' => $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.reference', 'GV-2026-0042')
            ->assertJsonPath('data.trip.code', $trip->code);

        // Jamais de PII passager ni de détail interne.
        $this->assertArrayNotHasKey('passengers', (array) $response->json('data'));
        $this->assertArrayNotHasKey('contact_email', (array) $response->json('data'));
    }
}
