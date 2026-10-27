<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Permissions\RestaurantPermissions;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * REST-OS-8 (#8610) — Vue consolidée GROUPE.
 *
 * Critères d'acceptation couverts :
 *  - consolidation en LECTURE SEULE ;
 *  - ISOLATION par établissement respectée (aucun mélange d'opérations) ;
 *  - le périmètre vient de l'acteur, jamais de la requête.
 */
class RestaurantGroupConsolidationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        return $company;
    }

    private function branch(Company $company, string $name): RestaurantBranch
    {
        return app(TenantManager::class)->withinTenant($company, fn (): RestaurantBranch => RestaurantBranch::factory()->create([
            'name' => $name,
            'currency' => 'EUR',
        ]));
    }

    /**
     * Crée une commande encaissée sur une succursale donnée.
     */
    private function paidOrder(Company $company, RestaurantBranch $branch, int $totalMinor): void
    {
        app(TenantManager::class)->withinTenant($company, function () use ($branch, $totalMinor): void {
            RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'status' => 'paid',
                'currency' => 'EUR',
                'total_minor' => $totalMinor,
                'subtotal_minor' => $totalMinor,
                'tax_minor' => 0,
                'discount_minor' => 0,
            ]);
        });
    }

    private function actor(Company $company, string $managerRole = 'principal'): Employee
    {
        return app(TenantManager::class)->withinTenant($company, fn (): Employee => Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]));
    }

    /**
     * Le total groupe est la SOMME EXACTE des lignes par établissement — c'est
     * la définition de « sans mélange d'opérations ».
     */
    public function test_group_totals_equal_the_sum_of_branch_rows(): void
    {
        $company = $this->company();
        $a = $this->branch($company, 'Bastille');
        $b = $this->branch($company, 'Nation');

        $this->paidOrder($company, $a, 10000);
        $this->paidOrder($company, $a, 5000);
        $this->paidOrder($company, $b, 3000);

        $actor = $this->actor($company);

        $response = $this->actingAs($actor)
            ->getJson('/api/v1/restaurant/reports/group')
            ->assertStatus(200);

        $rows = $response->json('data.branches');
        $totals = $response->json('data.totals');

        $this->assertCount(2, $rows);
        $this->assertSame(18000, $totals['revenue_minor']);
        $this->assertSame(3, $totals['orders_count']);

        // La somme des lignes DOIT égaler le total : aucune opération n'est
        // attribuée à la mauvaise succursale.
        $this->assertSame(
            array_sum(array_column($rows, 'revenue_minor')),
            $totals['revenue_minor'],
        );
        $this->assertSame(
            array_sum(array_column($rows, 'orders_count')),
            $totals['orders_count'],
        );
    }

    /**
     * Chaque ligne ne contient QUE les opérations de son établissement.
     */
    public function test_each_branch_row_only_contains_its_own_operations(): void
    {
        $company = $this->company();
        $a = $this->branch($company, 'Bastille');
        $b = $this->branch($company, 'Nation');

        $this->paidOrder($company, $a, 10000);
        $this->paidOrder($company, $b, 3000);

        $actor = $this->actor($company);

        $rows = collect($this->actingAs($actor)
            ->getJson('/api/v1/restaurant/reports/group')
            ->assertStatus(200)
            ->json('data.branches'))
            ->keyBy('branch_name');

        $this->assertSame(10000, $rows['Bastille']['revenue_minor']);
        $this->assertSame(1, $rows['Bastille']['orders_count']);
        $this->assertSame(3000, $rows['Nation']['revenue_minor']);
        $this->assertSame(1, $rows['Nation']['orders_count']);
    }

    /**
     * Le panier moyen du groupe est recalculé sur les totaux, pas moyenné à
     * partir des paniers moyens par établissement (une moyenne de moyennes
     * pondère à tort une petite succursale comme une grande).
     */
    public function test_group_average_basket_is_weighted_by_volume(): void
    {
        $company = $this->company();
        $a = $this->branch($company, 'Bastille');
        $b = $this->branch($company, 'Nation');

        // Bastille : 10 commandes à 1000 = 10000 (panier moyen 1000).
        for ($i = 0; $i < 10; $i++) {
            $this->paidOrder($company, $a, 1000);
        }
        // Nation : 1 commande à 10000 (panier moyen 10000).
        $this->paidOrder($company, $b, 10000);

        $actor = $this->actor($company);

        $totals = $this->actingAs($actor)
            ->getJson('/api/v1/restaurant/reports/group')
            ->assertStatus(200)
            ->json('data.totals');

        // 20000 / 11 = 1818 — et NON (1000 + 10000) / 2 = 5500.
        $this->assertSame(1818, $totals['avg_basket_minor']);
    }

    /**
     * Un établissement sans activité est LISTÉ à zéro, pas omis : c'est
     * l'information la plus actionnable pour un responsable de groupe.
     */
    public function test_a_branch_without_activity_is_listed_at_zero(): void
    {
        $company = $this->company();
        $a = $this->branch($company, 'Bastille');
        $this->branch($company, 'Nation');

        $this->paidOrder($company, $a, 10000);

        $actor = $this->actor($company);

        $rows = collect($this->actingAs($actor)
            ->getJson('/api/v1/restaurant/reports/group')
            ->assertStatus(200)
            ->json('data.branches'))
            ->keyBy('branch_name');

        $this->assertCount(2, $rows);
        $this->assertSame(0, $rows['Nation']['revenue_minor']);
        $this->assertSame(0, $rows['Nation']['orders_count']);
    }

    /**
     * ISOLATION : un responsable scopé sur une seule succursale ne voit QUE
     * celle-ci — le total groupe ne doit pas inclure les autres.
     */
    public function test_a_scoped_manager_only_sees_assigned_branches(): void
    {
        $company = $this->company();
        $a = $this->branch($company, 'Bastille');
        $b = $this->branch($company, 'Nation');

        $this->paidOrder($company, $a, 10000);
        $this->paidOrder($company, $b, 3000);

        $actor = $this->actor($company, 'dept');

        app(TenantManager::class)->withinTenant($company, function () use ($company, $actor, $a): void {
            EmployeeResourceAssignment::query()->create([
                'company_id' => $company->id,
                'employee_id' => $actor->id,
                'resource_type' => RestaurantPermissions::RESOURCE_TYPE,
                'resource_id' => $a->id,
                'access_level' => EmployeeResourceAssignment::LEVEL_MANAGE,
            ]);
        });

        $data = $this->actingAs($actor->fresh())
            ->getJson('/api/v1/restaurant/reports/group')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $data['branches']);
        $this->assertSame('Bastille', $data['branches'][0]['branch_name']);
        // Le total ne contient PAS les 3000 de Nation.
        $this->assertSame(10000, $data['totals']['revenue_minor']);
    }

    /**
     * Le périmètre vient de l'ACTEUR : un paramètre de requête ne peut pas
     * l'élargir.
     */
    public function test_a_request_parameter_cannot_widen_the_scope(): void
    {
        $company = $this->company();
        $a = $this->branch($company, 'Bastille');
        $b = $this->branch($company, 'Nation');

        $this->paidOrder($company, $a, 10000);
        $this->paidOrder($company, $b, 3000);

        $actor = $this->actor($company, 'dept');

        app(TenantManager::class)->withinTenant($company, function () use ($company, $actor, $a): void {
            EmployeeResourceAssignment::query()->create([
                'company_id' => $company->id,
                'employee_id' => $actor->id,
                'resource_type' => RestaurantPermissions::RESOURCE_TYPE,
                'resource_id' => $a->id,
                'access_level' => EmployeeResourceAssignment::LEVEL_MANAGE,
            ]);
        });

        // Tentative d'élargissement explicite : ignorée.
        $data = $this->actingAs($actor->fresh())
            ->getJson('/api/v1/restaurant/reports/group?branch_ids[]='.$a->id.'&branch_ids[]='.$b->id)
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $data['branches']);
        $this->assertSame(10000, $data['totals']['revenue_minor']);
    }

    /**
     * Un employé sans la permission `restaurant.reports` est refusé.
     */
    public function test_an_employee_without_reports_permission_is_forbidden(): void
    {
        $company = $this->company();
        $this->branch($company, 'Bastille');

        $actor = app(TenantManager::class)->withinTenant($company, fn (): Employee => Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]));

        $this->actingAs($actor)
            ->getJson('/api/v1/restaurant/reports/group')
            ->assertStatus(403);
    }

    /**
     * La consolidation est en LECTURE SEULE : aucune écriture, aucun effet de
     * bord — les données sont identiques avant et après l'appel.
     */
    public function test_consolidation_is_read_only(): void
    {
        $company = $this->company();
        $a = $this->branch($company, 'Bastille');
        $this->paidOrder($company, $a, 10000);

        $actor = $this->actor($company);

        $before = app(TenantManager::class)->withinTenant(
            $company,
            fn () => RestaurantOrder::query()->orderBy('id')->pluck('total_minor')->all(),
        );

        $this->actingAs($actor)->getJson('/api/v1/restaurant/reports/group')->assertStatus(200);

        $after = app(TenantManager::class)->withinTenant(
            $company,
            fn () => RestaurantOrder::query()->orderBy('id')->pluck('total_minor')->all(),
        );

        $this->assertSame($before, $after);
    }

    /**
     * Le périmètre est borné au tenant : les succursales d'une autre société
     * n'apparaissent jamais.
     */
    public function test_the_scope_never_leaks_across_tenants(): void
    {
        $company = $this->company();
        $this->branch($company, 'Bastille');

        $other = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);
        $other->setFeature('restaurantmanager', true);
        $other->save();
        $this->branch($other, 'Succursale Etrangere');

        $actor = $this->actor($company);

        $rows = $this->actingAs($actor)
            ->getJson('/api/v1/restaurant/reports/group')
            ->assertStatus(200)
            ->json('data.branches');

        $this->assertCount(1, $rows);
        $this->assertSame('Bastille', $rows[0]['branch_name']);
    }
}
