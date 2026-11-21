<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReview;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * MAT-009 / #9724 — API observabilité RestaurantManager.
 *
 * Couvre : 401 non authentifié, 403 employé opérationnel, métriques
 * tenant-scoped (outbox, dead-letter, avis en attente, commandes bloquées),
 * isolation cross-tenant et cohérence des seuils exposés.
 */
class RestaurantObservabilityApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $companyA->setFeature('restaurantmanager', true);
        $companyA->save();
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $companyB->setFeature('restaurantmanager', true);
        $companyB->save();
        $this->companyB = $companyB;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $employee;
    }

    private function operator(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'manager_role' => null,
            'status' => 'active',
        ]);

        return $employee;
    }

    private function branch(Company $company): RestaurantBranch
    {
        return app(TenantManager::class)->withinTenant(
            $company,
            fn (): RestaurantBranch => RestaurantBranch::factory()->create([
                'status' => 'active',
                'currency' => 'XAF',
            ])
        );
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/restaurant/health/metrics')->assertStatus(401);
    }

    public function test_operator_cannot_read_metrics(): void
    {
        Sanctum::actingAs($this->operator($this->companyA));

        $this->getJson('/api/v1/restaurant/health/metrics')->assertStatus(403);
    }

    public function test_metrics_reflect_tenant_state(): void
    {
        $branch = $this->branch($this->companyA);

        app(TenantManager::class)->withinTenant($this->companyA, function () use ($branch): void {
            // Avis en attente de modération.
            RestaurantReview::query()->create([
                'company_id' => $this->companyA->id,
                'branch_id' => $branch->id,
                'order_reference' => 'RST-OBS0001',
                'rating' => 4,
                'author_name' => 'Awa',
                'status' => 'pending',
            ]);

            // Outbox : un événement en échec non épuisé, un en dead-letter.
            RestaurantOutboxEvent::query()->create([
                'company_id' => $this->companyA->id,
                'event_type' => 'order.created',
                'payload_redacted' => ['order_id' => 1],
                'status' => RestaurantOutboxEvent::STATUS_FAILED,
                'attempts' => 2,
                'available_at' => now(),
                'last_error' => 'transient: test',
                'idempotency_key' => 'obs-failed-1',
            ]);

            RestaurantOutboxEvent::query()->create([
                'company_id' => $this->companyA->id,
                'event_type' => 'order.created',
                'payload_redacted' => ['order_id' => 2],
                'status' => RestaurantOutboxEvent::STATUS_FAILED,
                'attempts' => RestaurantOutboxEvent::MAX_ATTEMPTS,
                'available_at' => now(),
                'last_error' => 'permanent: test',
                'idempotency_key' => 'obs-dead-1',
            ]);

            // Commande en cuisine depuis 2 h → bloquée. `created_at` est posé
            // après l'insertion : Eloquent écrase la valeur passée à create().
            $stuck = RestaurantOrder::query()->create([
                'company_id' => $this->companyA->id,
                'branch_id' => $branch->id,
                'order_type' => 'dine_in',
                'status' => 'in_preparation',
                'currency' => 'XAF',
                'source' => 'pos',
                'total_minor' => 8000,
            ]);
            $stuck->forceFill(['created_at' => now()->subHours(2)])->save();
        });

        Sanctum::actingAs($this->principal($this->companyA));

        $this->getJson('/api/v1/restaurant/health/metrics')
            ->assertOk()
            ->assertJsonPath('data.reviews_pending', 1)
            ->assertJsonPath('data.outbox_failed', 2)
            ->assertJsonPath('data.outbox_dead_letter', 1)
            ->assertJsonPath('data.outbox_pending', 0)
            ->assertJsonPath('data.orders_in_kitchen', 1)
            ->assertJsonPath('data.orders_stuck_kitchen', 1)
            ->assertJsonPath('data.orders_paid_unserved', 0)
            ->assertJsonPath('data.thresholds.outbox_max_attempts', RestaurantOutboxEvent::MAX_ATTEMPTS)
            ->assertJsonPath('data.generated_at', fn ($v): bool => is_string($v));
    }

    public function test_metrics_are_tenant_isolated(): void
    {
        $branchA = $this->branch($this->companyA);

        app(TenantManager::class)->withinTenant($this->companyA, function () use ($branchA): void {
            RestaurantReview::query()->create([
                'company_id' => $this->companyA->id,
                'branch_id' => $branchA->id,
                'order_reference' => 'RST-OBS0002',
                'rating' => 5,
                'author_name' => 'Awa',
                'status' => 'pending',
            ]);
        });

        // Le gérant du tenant B ne voit rien du tenant A.
        Sanctum::actingAs($this->principal($this->companyB));

        $this->getJson('/api/v1/restaurant/health/metrics')
            ->assertOk()
            ->assertJsonPath('data.reviews_pending', 0)
            ->assertJsonPath('data.outbox_failed', 0)
            ->assertJsonPath('data.orders_in_kitchen', 0);
    }
}
