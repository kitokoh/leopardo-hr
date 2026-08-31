<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookSubscription;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-806 (#6097) — CRUD des abonnements webhook transporteurs.
 *
 * Tenant-scoped, écriture réservée aux rôles gestion, secret HMAC chiffré
 * jamais ré-exposé (une seule fois à la création), événements validés.
 */
class TravelWebhookApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function actingEmployee(Company $company, string $role = 'manager', string $managerRole = 'principal'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    public function test_store_creates_subscription_and_returns_secret_once(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->actingEmployee($company);

        $response = $this->postJson('/api/v1/travel/webhook-subscriptions', [
            'name' => 'Compagnie Express',
            'url' => 'https://carrier.example.test/hooks/travel',
            'events' => ['travel.booking.confirmed.v1', 'travel.ticket.issued.v1'],
        ])->assertStatus(201);

        $response->assertJsonStructure(['data' => ['id', 'name', 'url', 'events', 'active', 'has_secret', 'secret']]);
        self::assertTrue($response->json('data.has_secret'));
        self::assertNotEmpty($response->json('data.secret'), 'le secret est renvoyé une seule fois à la création');
    }

    public function test_show_never_exposes_the_secret(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->actingEmployee($company);

        $created = $this->postJson('/api/v1/travel/webhook-subscriptions', [
            'name' => 'Compagnie Express',
            'url' => 'https://carrier.example.test/hooks/travel',
            'events' => ['travel.booking.confirmed.v1'],
        ])->assertCreated()->json('data');

        $this->getJson("/api/v1/travel/webhook-subscriptions/{$created['id']}")
            ->assertOk()
            ->assertJsonPath('data.has_secret', true)
            ->assertJsonMissing(['secret' => $created['secret']])
            ->assertJsonMissingPath('data.secret');
    }

    public function test_store_rejects_invalid_event_and_url(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->actingEmployee($company);

        $this->postJson('/api/v1/travel/webhook-subscriptions', [
            'name' => 'Bad',
            'url' => 'not-a-url',
            'events' => ['travel.made.up.v1'],
        ])->assertStatus(422);
    }

    public function test_update_and_delete(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->actingEmployee($company);

        $created = $this->postJson('/api/v1/travel/webhook-subscriptions', [
            'name' => 'A',
            'url' => 'https://carrier.example.test/hooks/a',
            'events' => ['travel.booking.confirmed.v1'],
        ])->assertCreated()->json('data');

        $this->putJson("/api/v1/travel/webhook-subscriptions/{$created['id']}", [
            'name' => 'B',
            'active' => false,
        ])->assertOk()->assertJsonPath('data.name', 'B')->assertJsonPath('data.active', false);

        $this->deleteJson("/api/v1/travel/webhook-subscriptions/{$created['id']}")->assertStatus(204);
        $this->getJson("/api/v1/travel/webhook-subscriptions/{$created['id']}")->assertStatus(404);
    }

    public function test_write_requires_manager_role(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->actingEmployee($company, 'employee');

        $this->postJson('/api/v1/travel/webhook-subscriptions', [
            'name' => 'A',
            'url' => 'https://carrier.example.test/hooks/a',
            'events' => ['travel.booking.confirmed.v1'],
        ])->assertStatus(403);
    }

    public function test_isolation_per_tenant(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($companyA);
        $this->activateTravel($companyB);

        $this->actingEmployee($companyA);
        $created = $this->postJson('/api/v1/travel/webhook-subscriptions', [
            'name' => 'Tenant A',
            'url' => 'https://carrier.example.test/hooks/a',
            'events' => ['travel.booking.confirmed.v1'],
        ])->assertCreated()->json('data');

        $this->actingEmployee($companyB);
        $this->getJson("/api/v1/travel/webhook-subscriptions/{$created['id']}")->assertStatus(404);
        $this->putJson("/api/v1/travel/webhook-subscriptions/{$created['id']}", ['name' => 'X'])->assertStatus(404);
    }
}
