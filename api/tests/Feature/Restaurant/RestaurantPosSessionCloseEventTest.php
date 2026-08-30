<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-412 (#6199) — Clôture de caisse → événement restaurant.pos.closed.v1.
 *
 * Couvre : contenu de l'événement (totaux serveur, écart, période), clôture
 * rejouable sans doublon (seconde clôture → 409, un seul événement — critère
 * d'acceptation).
 */
class RestaurantPosSessionCloseEventTest extends TestCase
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

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    public function test_close_publishes_pos_closed_event_with_totals(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);

        $branch = app(TenantManager::class)->withinTenant($company, fn (): RestaurantBranch => RestaurantBranch::factory()->create());
        $sessionId = $this->postJson('/api/v1/restaurant/pos-sessions', [
            'branch_id' => $branch->id,
            'opening_cash_minor' => 10000,
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/restaurant/pos-sessions/{$sessionId}/close", [
            'counted_cash_minor' => 9600,
            'variance_reason' => 'Erreur de caisse',
        ])->assertStatus(200);

        $event = app(TenantManager::class)->withinTenant($company, fn () => \App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent::query()
            ->where('event_type', 'restaurant.pos.closed.v1')
            ->first());

        $this->assertNotNull($event);
        $this->assertSame($sessionId, $event->payload_redacted['pos_session_id']);
        $this->assertSame(10000, $event->payload_redacted['opening_cash_minor']);
        $this->assertSame(10000, $event->payload_redacted['expected_cash_minor']);
        $this->assertSame(-400, $event->payload_redacted['variance_minor']);
        $this->assertSame('Erreur de caisse', $event->payload_redacted['variance_reason']);
        $this->assertArrayHasKey('closed_at', $event->payload_redacted);
        $this->assertArrayHasKey('opened_at', $event->payload_redacted);
    }

    public function test_reclose_is_refused_and_event_not_duplicated(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);

        $branch = app(TenantManager::class)->withinTenant($company, fn (): RestaurantBranch => RestaurantBranch::factory()->create());
        $sessionId = $this->postJson('/api/v1/restaurant/pos-sessions', [
            'branch_id' => $branch->id,
            'opening_cash_minor' => 10000,
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/restaurant/pos-sessions/{$sessionId}/close", ['counted_cash_minor' => 10000])->assertStatus(200);

        // Seconde clôture : refusée (immuable) — pas de second événement.
        $this->postJson("/api/v1/restaurant/pos-sessions/{$sessionId}/close", ['counted_cash_minor' => 10000])->assertStatus(409);

        $count = app(TenantManager::class)->withinTenant($company, fn (): int => \App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent::query()
            ->where('event_type', 'restaurant.pos.closed.v1')
            ->count());

        $this->assertSame(1, $count);
    }
}
