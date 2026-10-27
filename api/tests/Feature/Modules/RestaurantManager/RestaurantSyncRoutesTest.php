<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\RestaurantManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Permissions\RestaurantPermissions;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Restaurant OS — REST-OS-7 : les routes de synchronisation sont REELLEMENT
 * cablees (#8609).
 *
 * POURQUOI CE FICHIER EXISTE : le premier jet de ce lot declarait les routes
 * `RestaurantSyncController::class` dans `routes/modules/restaurantmanager.php`
 * SANS l'import `use` correspondant. Le fichier de routes parsait, les tests de
 * service passaient tous, et pourtant chaque appel HTTP aurait leve une erreur
 * de classe introuvable. Le gate `check-unrouted-controllers.sh` l'a attrape
 * (controleur « orphelin ») — ce test le rend impossible a reintroduire, en
 * exerçant les routes par HTTP plutot qu'en appelant le service directement.
 *
 * Un test de service ne prouve PAS qu'une route existe : seule une requete HTTP
 * le prouve.
 */
final class RestaurantSyncRoutesTest extends TestCase
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

    private function actor(Company $company, string $managerRole = 'principal'): Employee
    {
        return app(TenantManager::class)->withinTenant($company, fn (): Employee => Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]));
    }

    /**
     * La route de poussee existe et accepte un lot valide.
     */
    public function test_the_submit_route_is_wired(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);

        $response = $this->actingAs($actor)->postJson('/api/v1/restaurant/sync/operations', [
            'operations' => [[
                'client_operation_id' => 'route-op-1',
                'operation_type' => 'record_order',
                'payload' => ['total_minor' => 1200],
                'occurred_at' => '2026-10-27T10:00:00+00:00',
                'terminal_id' => 'terminal-1',
            ]],
        ]);

        $response->assertStatus(200);
        $this->assertSame('pending', $response->json('data.0.status'));
    }

    /**
     * Une operation hors registre est REFUSEE par la route, pas par le service :
     * c'est le contrat HTTP qui compte pour le terminal.
     */
    public function test_the_submit_route_rejects_an_unknown_operation(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);

        $response = $this->actingAs($actor)->postJson('/api/v1/restaurant/sync/operations', [
            'operations' => [[
                'client_operation_id' => 'route-op-unknown',
                'operation_type' => 'delete_everything',
                'payload' => ['whatever' => true],
                'occurred_at' => '2026-10-27T10:00:00+00:00',
            ]],
        ]);

        $response->assertStatus(200);
        $this->assertSame('rejected', $response->json('data.0.status'));
        $this->assertSame('RESTAURANT_SYNC_UNSUPPORTED_OPERATION', $response->json('data.0.error_code'));
    }

    /**
     * Les routes de lecture existent et renvoient la forme attendue.
     */
    public function test_the_status_and_errors_routes_are_wired(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);

        $this->actingAs($actor)
            ->getJson('/api/v1/restaurant/sync/status')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['pending', 'applied', 'conflict', 'rejected', 'failed', 'unresolved_conflicts']]);

        $this->actingAs($actor)
            ->getJson('/api/v1/restaurant/sync/errors')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    /**
     * La route des conflits exige la permission de PILOTAGE : un serveur ne peut
     * pas arbitrer un conflit financier.
     */
    public function test_the_conflicts_route_requires_the_reports_permission(): void
    {
        $company = $this->company();
        $server = $this->actor($company, 'server');

        $this->actingAs($server)
            ->getJson('/api/v1/restaurant/sync/conflicts')
            ->assertStatus(403);
    }

    /**
     * La route de resolution existe et refuse une strategie hors registre.
     */
    public function test_the_resolve_route_validates_the_strategy(): void
    {
        $company = $this->company();
        $actor = $this->actor($company);

        $this->actingAs($actor)
            ->postJson('/api/v1/restaurant/sync/conflicts/999999/resolve', [
                'resolution' => 'not_a_strategy',
            ])
            ->assertStatus(422);
    }

    /**
     * Un employe sans la permission d'operation de salle ne peut pas pousser
     * d'operations.
     */
    public function test_the_submit_route_requires_the_server_permission(): void
    {
        $company = $this->company();
        $outsider = $this->actor($company, 'comptable');

        $this->actingAs($outsider)
            ->postJson('/api/v1/restaurant/sync/operations', [
                'operations' => [[
                    'client_operation_id' => 'route-op-forbidden',
                    'operation_type' => 'record_order',
                    'payload' => ['total_minor' => 100],
                    'occurred_at' => '2026-10-27T10:00:00+00:00',
                ]],
            ])
            ->assertStatus(403);
    }

    /**
     * Le registre de permissions expose bien les deux capacites utilisees.
     */
    public function test_the_permissions_used_by_the_routes_exist(): void
    {
        $this->assertSame('restaurant.server', RestaurantPermissions::SERVER);
        $this->assertSame('restaurant.reports', RestaurantPermissions::REPORTS);
    }
}
