<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-409 (#6196) — Occupation des tables (open/close, table_sessions).
 *
 * Couvre : ouverture d'une session (201), table occupée → nouvelle ouverture
 * refusée (409 — critère d'acceptation), clôture immuable + événement
 * `restaurant.table.closed.v1`, refus employé lambda (403).
 */
class RestaurantTableSessionTest extends TestCase
{
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

    private function makeTable(Company $company): RestaurantTable
    {
        /** @var RestaurantTable $table */
        $table = app(TenantManager::class)->withinTenant($company, fn (): RestaurantTable => RestaurantTable::factory()->create());

        return $table;
    }

    public function test_server_can_open_and_close_a_table(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        $table = $this->makeTable($company);

        $this->postJson("/api/v1/restaurant/tables/{$table->id}/open", ['covers' => 4])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.covers', 4);

        $this->postJson("/api/v1/restaurant/tables/{$table->id}/close")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');

        $events = app(TenantManager::class)->withinTenant($company, fn (): int => \App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent::query()
            ->where('event_type', 'restaurant.table.closed.v1')
            ->count());

        $this->assertSame(1, $events);
    }

    public function test_occupied_table_cannot_be_reopened_409(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        $table = $this->makeTable($company);

        $this->postJson("/api/v1/restaurant/tables/{$table->id}/open", ['covers' => 2])->assertStatus(201);

        $this->postJson("/api/v1/restaurant/tables/{$table->id}/open", ['covers' => 3])->assertStatus(409);
    }

    public function test_close_without_open_session_returns_404(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        $table = $this->makeTable($company);

        $this->postJson("/api/v1/restaurant/tables/{$table->id}/close")->assertStatus(404);
    }

    public function test_ordinary_employee_cannot_open_table(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->ordinaryEmployee($company);
        $table = $this->makeTable($company);

        $this->postJson("/api/v1/restaurant/tables/{$table->id}/open", ['covers' => 2])->assertStatus(403);
    }

    public function test_table_session_of_other_tenant_returns_404(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);

        /** @var RestaurantTable $otherTable */
        $otherTable = app(TenantManager::class)->withinTenant(
            Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']),
            fn (): RestaurantTable => RestaurantTable::factory()->create()
        );

        $this->postJson("/api/v1/restaurant/tables/{$otherTable->id}/open", ['covers' => 2])->assertStatus(404);
    }
}
