<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Sessions de caisse FuelStation — FUEL-007 (issue #5801).
 *
 * Couvre : ouverture, mouvements in/out, clôture idempotente avec écart
 * calculé serveur, approbation manager (403 pompiste), rejet des mouvements
 * sur session close, approbation avant clôture refusée, isolation tenant
 * 404, self-service pompiste.
 */
class FuelCashSessionApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_unauthenticated_gets_401(): void
    {
        $this->postJson('/api/v1/fuel-station/cash-sessions', ['opening_balance' => 0])->assertStatus(401);
    }

    public function test_operator_opens_session(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/fuel-station/cash-sessions', ['opening_balance' => 5000])
            ->assertStatus(201)
            ->assertJsonPath('data.opening_balance', 5000)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.opened_by', $operator->id);
    }

    public function test_operator_adds_movements_and_closes_with_server_computed_variance(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($operator);

        /** @var int $sessionId */
        $sessionId = $this->postJson('/api/v1/fuel-station/cash-sessions', ['opening_balance' => 1000])
            ->assertStatus(201)
            ->json('data.id');

        $this->postJson("/api/v1/fuel-station/cash-sessions/{$sessionId}/movements", [
            'type' => 'in',
            'amount' => 500,
            'reason' => 'Vente carburant',
        ])->assertStatus(201);
        $this->postJson("/api/v1/fuel-station/cash-sessions/{$sessionId}/movements", [
            'type' => 'out',
            'amount' => 200,
            'reason' => 'Ravitaillement caisse',
        ])->assertStatus(201);

        // expected = 1000 + 500 − 200 = 1300 ; compté 1250 → variance −50.
        $this->postJson("/api/v1/fuel-station/cash-sessions/{$sessionId}/close", ['closing_balance' => 1250])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.expected_balance', 1300)
            ->assertJsonPath('data.variance', -50)
            ->assertJsonPath('data.closed_by', $operator->id);

        // Mouvement après clôture → 422 SESSION_NOT_OPEN.
        $this->postJson("/api/v1/fuel-station/cash-sessions/{$sessionId}/movements", [
            'type' => 'in',
            'amount' => 100,
            'reason' => 'Trop tard',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'SESSION_NOT_OPEN');
    }

    public function test_close_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($operator);

        /** @var int $sessionId */
        $sessionId = $this->postJson('/api/v1/fuel-station/cash-sessions', ['opening_balance' => 0])
            ->json('data.id');

        $this->postJson("/api/v1/fuel-station/cash-sessions/{$sessionId}/close", ['closing_balance' => 800])
            ->assertStatus(200)
            ->assertJsonPath('data.expected_balance', 0)
            ->assertJsonPath('data.variance', 800);

        // Rejeu : état inchangé, aucun recalcul (idempotence stricte).
        $this->postJson("/api/v1/fuel-station/cash-sessions/{$sessionId}/close", ['closing_balance' => 99999])
            ->assertStatus(200)
            ->assertJsonPath('data.closing_balance', 800)
            ->assertJsonPath('data.variance', 800);

        $this->assertDatabaseHas('fuel_cash_sessions', [
            'id' => $sessionId,
            'closing_balance' => 800,
            'status' => 'closed',
        ]);
    }

    public function test_approval_requires_manager_and_closed_session(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($operator);

        /** @var int $sessionId */
        $sessionId = $this->postJson('/api/v1/fuel-station/cash-sessions', ['opening_balance' => 0])
            ->json('data.id');

        // Approbation par le pompiste → 403.
        $this->postJson("/api/v1/fuel-station/cash-sessions/{$sessionId}/approve")
            ->assertStatus(403);

        // Approbation avant clôture (manager) → 422 SESSION_NOT_CLOSED.
        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/fuel-station/cash-sessions/{$sessionId}/approve")
            ->assertStatus(422)
            ->assertJsonPath('error', 'SESSION_NOT_CLOSED');

        // Clôture puis approbation → 200 approved.
        Sanctum::actingAs($operator);
        $this->postJson("/api/v1/fuel-station/cash-sessions/{$sessionId}/close", ['closing_balance' => 300])
            ->assertStatus(200);

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/fuel-station/cash-sessions/{$sessionId}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', $manager->id);
    }

    public function test_operator_cannot_touch_another_operators_session(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $operatorA */
        $operatorA = Employee::factory()->create(['company_id' => $company->id]);
        /** @var Employee $operatorB */
        $operatorB = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($operatorA);

        /** @var int $sessionId */
        $sessionId = $this->postJson('/api/v1/fuel-station/cash-sessions', ['opening_balance' => 0])
            ->json('data.id');

        Sanctum::actingAs($operatorB);
        $this->postJson("/api/v1/fuel-station/cash-sessions/{$sessionId}/movements", [
            'type' => 'in',
            'amount' => 10,
            'reason' => 'Interdit',
        ])->assertStatus(403);
        $this->postJson("/api/v1/fuel-station/cash-sessions/{$sessionId}/close", ['closing_balance' => 10])
            ->assertStatus(403);
        $this->getJson("/api/v1/fuel-station/cash-sessions/{$sessionId}")->assertStatus(403);
    }

    public function test_cross_tenant_session_is_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $operatorA */
        $operatorA = Employee::factory()->create(['company_id' => $companyA->id]);
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        Sanctum::actingAs($operatorA);
        /** @var int $sessionId */
        $sessionId = $this->postJson('/api/v1/fuel-station/cash-sessions', ['opening_balance' => 0])
            ->json('data.id');

        Sanctum::actingAs($managerB);
        $this->getJson("/api/v1/fuel-station/cash-sessions/{$sessionId}")->assertStatus(404);
        $this->postJson("/api/v1/fuel-station/cash-sessions/{$sessionId}/approve")->assertStatus(404);
    }

    public function test_manager_lists_sessions_and_operator_self_service(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $operatorA */
        $operatorA = Employee::factory()->create(['company_id' => $company->id]);
        /** @var Employee $operatorB */
        $operatorB = Employee::factory()->create(['company_id' => $company->id]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($operatorA);
        $this->postJson('/api/v1/fuel-station/cash-sessions', ['opening_balance' => 100])->assertStatus(201);
        Sanctum::actingAs($operatorB);
        $this->postJson('/api/v1/fuel-station/cash-sessions', ['opening_balance' => 200])->assertStatus(201);

        Sanctum::actingAs($operatorA);
        $this->getJson('/api/v1/fuel-station/me/cash-sessions')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.opening_balance', 100);

        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/fuel-station/cash-sessions')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_invalid_movement_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($operator);

        /** @var int $sessionId */
        $sessionId = $this->postJson('/api/v1/fuel-station/cash-sessions', ['opening_balance' => 0])
            ->json('data.id');

        // Montant négatif / nul refusé ; type invalide refusé ; motif requis.
        $this->postJson("/api/v1/fuel-station/cash-sessions/{$sessionId}/movements", [
            'type' => 'in',
            'amount' => -5,
            'reason' => 'X',
        ])->assertStatus(422);
        $this->postJson("/api/v1/fuel-station/cash-sessions/{$sessionId}/movements", [
            'type' => 'transfer',
            'amount' => 10,
            'reason' => 'X',
        ])->assertStatus(422);
        $this->postJson("/api/v1/fuel-station/cash-sessions/{$sessionId}/movements", [
            'type' => 'in',
            'amount' => 10,
        ])->assertStatus(422);
    }
}
