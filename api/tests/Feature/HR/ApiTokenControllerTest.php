<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #4320 — ApiTokenController (tokens API développeur, manager-only) :
 * happy path + RBAC 403 employé + isolation.
 */
class ApiTokenControllerTest extends TestCase
{
    use RefreshTenantDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var Company $company */
        $company = Company::factory()->create();
        $this->company = $company;
    }

    public function test_manager_can_create_list_and_delete_api_token(): void
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        Sanctum::actingAs($manager);

        // Création → 201 + token en clair (seule fois).
        $created = $this->postJson('/api/v1/api-tokens', ['name' => 'CI integration']);
        $created->assertStatus(201)
            ->assertJsonPath('data.name', 'CI integration')
            ->assertJsonStructure(['data' => ['id', 'name', 'token', 'created_at']]);

        $tokenId = $created->json('data.id');

        // Liste → le token apparaît.
        $this->getJson('/api/v1/api-tokens')
            ->assertOk()
            ->assertJsonPath('data.0.id', $tokenId);

        // Suppression → 204 puis liste vide.
        $this->deleteJson("/api/v1/api-tokens/{$tokenId}")->assertStatus(204);
        $this->getJson('/api/v1/api-tokens')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_plain_employee_cannot_manage_api_tokens(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/api-tokens')->assertStatus(403);
        $this->postJson('/api/v1/api-tokens', ['name' => 'x'])->assertStatus(403);
        $this->deleteJson('/api/v1/api-tokens/1')->assertStatus(403);
    }

    public function test_create_token_requires_name(): void
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/api-tokens', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/v1/api-tokens')->assertStatus(401);
    }
}
