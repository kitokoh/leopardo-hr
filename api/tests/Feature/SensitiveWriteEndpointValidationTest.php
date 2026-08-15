<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * QA wave 2026-08-14 — T007 (#2232).
 *
 * Validations FormRequest sur les endpoints d'écriture sensibles :
 * CalendarSync (sync/disconnect), EdgeNode (sync/forceSync/revokeNode),
 * Zkteco (destroy). Chaque entrée invalide doit produire un 422 explicite.
 */
class SensitiveWriteEndpointValidationTest extends TestCase
{
    use CreatesMvpSchema;

    protected Company $company;

    protected Company $otherCompany;

    protected Employee $manager;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        /** @var Company $this->company */
        $this->company = Company::factory()->create();
        /** @var Company $this->otherCompany */
        $this->otherCompany = Company::factory()->create();
        $this->manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        /** @var Employee $this->employee */
        $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /** @test */
    public function calendar_disconnect_rejects_unknown_provider(): void
    {
        Sanctum::actingAs($this->employee);

        $this->deleteJson('/api/v1/calendar/disconnect/slack')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['provider']);
    }

    /** @test */
    public function calendar_disconnect_accepts_known_provider(): void
    {
        Sanctum::actingAs($this->employee);

        // Aucune connexion : le service n'a rien à déconnecter, mais la
        // validation du provider passe (200, pas de 422).
        $this->deleteJson('/api/v1/calendar/disconnect/google')
            ->assertOk();
    }

    /** @test */
    public function calendar_sync_still_works_with_form_request(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/calendar/sync')
            ->assertOk();
    }

    /** @test */
    public function edge_force_sync_rejects_non_uuid_node(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/platform/edge/nodes/not-a-uuid/sync')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nodeId']);
    }

    /** @test */
    public function edge_revoke_rejects_non_uuid_node(): void
    {
        Sanctum::actingAs($this->manager);

        $this->deleteJson('/api/v1/platform/edge/nodes/12345')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nodeId']);
    }

    /** @test */
    public function edge_admin_sync_alias_rejects_non_uuid_node(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/admin/edge-nodes/xyz/sync')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nodeId']);
    }

    /** @test */
    public function zkteco_destroy_is_guarded_by_router_constraint(): void
    {
        // La route DELETE /zkteco/devices/{id} porte `whereNumber('id')` :
        // un id non numérique ne matche pas la route → 404 (validation au
        // niveau routeur, pas besoin de FormRequest).
        Sanctum::actingAs($this->manager);

        $this->deleteJson('/api/v1/zkteco/devices/abc')
            ->assertNotFound();

        $this->deleteJson('/api/v1/zkteco/devices/999999')
            ->assertNotFound();
    }
}
