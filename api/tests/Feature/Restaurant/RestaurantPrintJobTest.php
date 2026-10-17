<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Enums\PrintJobStatus;
use App\Modules\RestaurantManager\Domain\Enums\PrintTicketKind;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\Support\AssignsResourceAccess;
use Tests\TestCase;

/**
 * REST-OS-3/4 (Mission Leopardo Restaurant OS) — Surface AUTHENTIFIÉE des
 * travaux d'impression thermique.
 *
 * Couvre : mise en file idempotente (jamais de doublon sur double clic),
 * réimpression explicite = nouvelle révision, RBAC ressource-scopé (#7599),
 * et isolation cross-tenant (404, jamais de fuite).
 */
class RestaurantPrintJobTest extends TestCase
{
    use AssignsResourceAccess;
    use RefreshTenantDatabase;

    private function activate(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    private function actor(Company $company, string $managerRole = 'principal'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    /**
     * @return array{0: RestaurantBranch, 1: RestaurantOrder}
     */
    private function order(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company): array {
            $branch = RestaurantBranch::factory()->create();
            $taxRate = RestaurantTaxRate::factory()->create(['rate_bps' => 0]);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 1500,
                'currency' => $branch->currency,
                'tax_rate_id' => $taxRate->id,
                'is_available' => true,
            ]);

            $order = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'status' => 'in_preparation',
                'currency' => $branch->currency,
            ]);

            RestaurantOrderItem::query()->create([
                'company_id' => $company->id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price_minor' => 1500,
                'line_total_minor' => 3000,
                'tax_minor' => 0,
                'status' => 'active',
                'line_index' => 1,
            ]);

            return [$branch, $order->refresh()];
        });
    }

    public function test_enqueue_creates_a_pending_print_job(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activate($company);
        $this->actor($company);
        [$branch, $order] = $this->order($company);

        $response = $this->postJson("/api/v1/restaurant/orders/{$order->id}/print-jobs")
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.ticket_kind', 'kitchen')
            ->assertJsonPath('data.revision', 1);

        $this->assertNotEmpty($response->json('data.payload_hash'));
    }

    public function test_enqueue_is_idempotent_on_double_submit(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activate($company);
        $this->actor($company);
        [$branch, $order] = $this->order($company);

        $first = $this->postJson("/api/v1/restaurant/orders/{$order->id}/print-jobs")->assertStatus(201);
        $second = $this->postJson("/api/v1/restaurant/orders/{$order->id}/print-jobs")->assertStatus(201);

        // Même clé métier (commande, type, révision) ⇒ même travail, jamais de doublon.
        $this->assertSame($first->json('data.id'), $second->json('data.id'));

        $count = RestaurantPrintJob::query()
            ->where('company_id', $company->id)
            ->where('order_id', $order->id)
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_requeue_creates_a_new_revision(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activate($company);
        $this->actor($company);
        [$branch, $order] = $this->order($company);

        $jobId = $this->postJson("/api/v1/restaurant/orders/{$order->id}/print-jobs")
            ->assertStatus(201)
            ->json('data.id');

        $this->assertIsInt($jobId);

        $requeued = $this->postJson("/api/v1/restaurant/print-jobs/{$jobId}/requeue")
            ->assertStatus(201)
            ->assertJsonPath('data.revision', 2);

        $this->assertNotSame($jobId, $requeued->json('data.id'));
    }

    public function test_index_is_scoped_to_the_branch(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activate($company);
        $actor = $this->actor($company);
        [$branch, $order] = $this->order($company);
        $this->assignResourceAccess($actor, 'restaurant_branch', $branch->id, 'operate');

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/print-jobs")->assertStatus(201);

        $this->getJson("/api/v1/restaurant/print-jobs?branch_id={$branch->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_index_requires_branch_id(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activate($company);
        $this->actor($company);

        $this->getJson('/api/v1/restaurant/print-jobs')->assertStatus(422);
    }

    public function test_show_is_not_found_for_another_tenant_job(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activate($company);
        $this->actor($company);

        $foreignCompany = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        /** @var RestaurantPrintJob $foreignJob */
        $foreignJob = app(TenantManager::class)->withinTenant($foreignCompany, function () use ($foreignCompany): RestaurantPrintJob {
            $branch = RestaurantBranch::factory()->create();

            return RestaurantPrintJob::factory()->create([
                'company_id' => $foreignCompany->id,
                'branch_id' => $branch->id,
            ]);
        });

        $this->getJson("/api/v1/restaurant/print-jobs/{$foreignJob->id}")->assertStatus(404);
    }

    public function test_enqueue_is_forbidden_for_a_server_without_assignment(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activate($company);
        // Aucune assignation `restaurant_branch` ⇒ comportement historique :
        // l'écriture exige principal/rh → un serveur est refusé (fail-closed).
        $this->actor($company, 'server');
        [$branch, $order] = $this->order($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/print-jobs")->assertStatus(403);
    }
}
