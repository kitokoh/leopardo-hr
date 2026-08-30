<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-401 (#6188) — Sessions de caisse POS : ouverture / clôture.
 *
 * Couvre : une seule session ouverte par branche (409), totaux recalculés
 * serveur (expected = fonds + encaissements confirmés), écart + motif
 * obligatoire si écart non nul, clôture immuable (version) et RBAC
 * (ouverture serveur+, clôture gérant/RH/manager, refus employé lambda).
 */
class RestaurantPosSessionTest extends TestCase
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

    private function server(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'server',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function ordinaryEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    /**
     * Crée une branche dans le tenant (hors HTTP, contexte posé manuellement).
     */
    private function makeBranch(Company $company): RestaurantBranch
    {
        /** @var RestaurantBranch $branch */
        $branch = app(TenantManager::class)->withinTenant($company, fn (): RestaurantBranch => RestaurantBranch::factory()->create());

        return $branch;
    }

    public function test_server_can_open_a_pos_session(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        $branch = $this->makeBranch($company);

        $this->postJson('/api/v1/restaurant/pos-sessions', [
            'branch_id' => $branch->id,
            'opening_cash_minor' => 10000,
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.opening_cash_minor', 10000)
            ->assertJsonPath('data.version', 1);
    }

    public function test_second_open_for_same_branch_is_refused_409(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        $branch = $this->makeBranch($company);

        $this->postJson('/api/v1/restaurant/pos-sessions', [
            'branch_id' => $branch->id,
            'opening_cash_minor' => 10000,
        ])->assertStatus(201);

        $this->postJson('/api/v1/restaurant/pos-sessions', [
            'branch_id' => $branch->id,
            'opening_cash_minor' => 5000,
        ])->assertStatus(409);
    }

    public function test_ordinary_employee_cannot_open_a_pos_session(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->ordinaryEmployee($company);
        $branch = $this->makeBranch($company);

        $this->postJson('/api/v1/restaurant/pos-sessions', [
            'branch_id' => $branch->id,
            'opening_cash_minor' => 10000,
        ])->assertStatus(403);
    }

    public function test_close_with_exact_counted_cash_has_zero_variance(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        $branch = $this->makeBranch($company);

        $sessionId = $this->postJson('/api/v1/restaurant/pos-sessions', [
            'branch_id' => $branch->id,
            'opening_cash_minor' => 10000,
        ])->assertStatus(201)->json('data.id');

        // Encaissement confirmé de 2000 + pourboire 300 → expected = 12300.
        app(TenantManager::class)->withinTenant($company, function () use ($sessionId): void {
            \App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment::query()->create([
                'company_id' => $company->id,
                'order_id' => 1,
                'pos_session_id' => $sessionId,
                'provider_code' => 'cash',
                'amount_minor' => 2000,
                'currency' => 'DZD',
                'status' => 'confirmed',
                'tip_minor' => 300,
                'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            ]);
        });

        $this->postJson("/api/v1/restaurant/pos-sessions/{$sessionId}/close", [
            'counted_cash_minor' => 12300,
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.expected_cash_minor', 12300)
            ->assertJsonPath('data.variance_minor', 0);
    }

    public function test_close_with_variance_requires_reason(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        $branch = $this->makeBranch($company);

        $sessionId = $this->postJson('/api/v1/restaurant/pos-sessions', [
            'branch_id' => $branch->id,
            'opening_cash_minor' => 10000,
        ])->assertStatus(201)->json('data.id');

        // Écart sans motif → 422.
        $this->postJson("/api/v1/restaurant/pos-sessions/{$sessionId}/close", [
            'counted_cash_minor' => 9800,
        ])->assertStatus(422);

        // Écart justifié → 200.
        $this->postJson("/api/v1/restaurant/pos-sessions/{$sessionId}/close", [
            'counted_cash_minor' => 9800,
            'variance_reason' => 'Fausse monnaie rendue',
        ])->assertStatus(200)
            ->assertJsonPath('data.variance_minor', -200)
            ->assertJsonPath('data.variance_reason', 'Fausse monnaie rendue');
    }

    public function test_employee_cannot_close_a_session(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $branch = $this->makeBranch($company);

        /** @var RestaurantPosSession $session */
        $session = app(TenantManager::class)->withinTenant($company, fn (): RestaurantPosSession => RestaurantPosSession::factory()->create([
            'branch_id' => $branch->id,
            'opened_by_user_id' => 1,
            'status' => 'open',
        ]));

        $this->ordinaryEmployee($company);

        $this->postJson("/api/v1/restaurant/pos-sessions/{$session->id}/close", [
            'counted_cash_minor' => 10000,
        ])->assertStatus(403);
    }

    public function test_close_on_closed_session_is_refused_409(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        $branch = $this->makeBranch($company);

        $sessionId = $this->postJson('/api/v1/restaurant/pos-sessions', [
            'branch_id' => $branch->id,
            'opening_cash_minor' => 10000,
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/restaurant/pos-sessions/{$sessionId}/close", [
            'counted_cash_minor' => 10000,
        ])->assertStatus(200);

        $this->postJson("/api/v1/restaurant/pos-sessions/{$sessionId}/close", [
            'counted_cash_minor' => 10000,
        ])->assertStatus(409);
    }
}
