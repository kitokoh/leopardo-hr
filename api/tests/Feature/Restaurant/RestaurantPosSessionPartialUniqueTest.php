<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Enums\PosSessionStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\Support\AssignsResourceAccess;
use Tests\TestCase;

/**
 * #7717 (BC-25) — Régression : l'unique (company, branche, statut) de #6173
 * interdisait deux sessions FERMÉES sur la même branche — la 2e clôture
 * violait la contrainte (23505) et cassait le POS au 2e jour d'exploitation.
 *
 * Depuis la migration 2026_09_19_000100_7717 (index unique PARTIEL Postgres
 * sur (company_id, branch_id) WHERE status = 'open') :
 *  - plusieurs sessions fermées coexistent sur la même branche (cycle
 *    ouverture → clôture rejouable jour après jour) ;
 *  - une seule session OUVERTE par branche reste garantie, à la fois par
 *    l'API (409) et par la base (violation d'unicité sur INSERT direct).
 */
class RestaurantPosSessionPartialUniqueTest extends TestCase
{
    use AssignsResourceAccess;
    use RefreshTenantDatabase;

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

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    private function makeBranch(Company $company): RestaurantBranch
    {
        /** @var RestaurantBranch $branch */
        $branch = app(TenantManager::class)->withinTenant($company, fn (): RestaurantBranch => RestaurantBranch::factory()->create());

        return $branch;
    }

    public function test_two_closed_sessions_coexist_on_the_same_branch(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $server = $this->server($company);
        $branch = $this->makeBranch($company);
        // #7599 — le serveur agit par assignation `operate` sur SA succursale.
        $this->assignResourceAccess($server, 'restaurant_branch', $branch->id, 'operate');

        // Jour 1 : ouverture puis clôture.
        $firstId = $this->postJson('/api/v1/restaurant/pos-sessions', [
            'branch_id' => $branch->id,
            'opening_cash_minor' => 10000,
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/restaurant/pos-sessions/{$firstId}/close", [
            'counted_cash_minor' => 10000,
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');

        // Jour 2 : nouveau cycle sur la MÊME branche — la clôture explosait
        // en 23505 sous l'unique (company, branch, status) de #6173.
        $secondId = $this->postJson('/api/v1/restaurant/pos-sessions', [
            'branch_id' => $branch->id,
            'opening_cash_minor' => 5000,
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/restaurant/pos-sessions/{$secondId}/close", [
            'counted_cash_minor' => 5000,
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');

        // Les deux sessions fermées coexistent en base sur la même branche.
        $closedCount = app(TenantManager::class)->withinTenant(
            $company,
            fn (): int => RestaurantPosSession::query()
                ->where('branch_id', $branch->id)
                ->where('status', PosSessionStatus::CLOSED->value)
                ->count()
        );

        $this->assertSame(2, $closedCount);
    }

    public function test_single_open_session_per_branch_is_still_enforced_by_the_database(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $branch = $this->makeBranch($company);

        app(TenantManager::class)->withinTenant($company, function () use ($branch): void {
            RestaurantPosSession::factory()->create([
                'branch_id' => $branch->id,
                'status' => PosSessionStatus::OPEN->value,
            ]);

            // L'index unique partiel (company_id, branch_id) WHERE status='open'
            // refuse une 2e session OUVERTE sur la même branche.
            //
            // #7729 (follow-up) : l'insertion fautive est isolée dans une
            // transaction IMBRIQUÉE (savepoint) — la violation 23505 y est
            // rollbackée, ce qui évite de laisser la transaction du test
            // (posée par RefreshDatabase) en état « aborted » — sinon le
            // SET search_path du tearDown échoue en 25P02 (constat #6954).
            // Pattern repo identique à AbsenceTypesTenantUniqueTest.
            $this->expectException(QueryException::class);

            DB::transaction(function () use ($branch): void {
                RestaurantPosSession::factory()->create([
                    'branch_id' => $branch->id,
                    'status' => PosSessionStatus::OPEN->value,
                ]);
            });
        });
    }

    public function test_second_open_for_same_branch_is_still_refused_409_via_api(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $server = $this->server($company);
        $branch = $this->makeBranch($company);
        // #7599 — le serveur agit par assignation `operate` sur SA succursale.
        $this->assignResourceAccess($server, 'restaurant_branch', $branch->id, 'operate');

        $this->postJson('/api/v1/restaurant/pos-sessions', [
            'branch_id' => $branch->id,
            'opening_cash_minor' => 10000,
        ])->assertStatus(201);

        $this->postJson('/api/v1/restaurant/pos-sessions', [
            'branch_id' => $branch->id,
            'opening_cash_minor' => 5000,
        ])->assertStatus(409);
    }
}
