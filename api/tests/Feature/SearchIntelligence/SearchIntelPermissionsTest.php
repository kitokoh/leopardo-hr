<?php

declare(strict_types=1);

namespace Tests\Feature\SearchIntelligence;

use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * GSI-1 (#8512) — garde de permissions du module SearchIntelligence.
 *
 * Trois profils sont vérifiés, conformément aux critères d'acceptation :
 *  1. un **tenant** (aucune identité sur le guard `super_admin_api`) → 401/403
 *     sur TOUTES les routes ;
 *  2. un **rôle sans la permission** (support) → 403 structuré
 *     `PLATFORM_PERMISSION_REQUIRED` (contrat consommé par le SPA admin) ;
 *  3. le **rôle marketing** → lecture ET écriture (`view` + `manage`).
 *
 * L'isolation tenant est structurelle : les routes vivent sous le guard
 * `super_admin_api` et les tables du module n'ont aucune colonne `company_id`.
 */
final class SearchIntelPermissionsTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_platform_guest_is_rejected_on_every_route(): void
    {
        $this->getJson('/api/v1/platform/search-intel/overview')
            ->assertStatus(401);

        $this->getJson('/api/v1/platform/search-intel/integrations')
            ->assertStatus(401);
    }

    public function test_support_role_is_denied_with_structured_payload(): void
    {
        $support = $this->superAdminWithRole(PlatformRole::Support);

        Sanctum::actingAs($support, ['*'], 'super_admin_api');

        $response = $this->getJson('/api/v1/platform/search-intel/overview');

        $response->assertStatus(403)
            ->assertJsonPath('error', 'PLATFORM_PERMISSION_REQUIRED')
            ->assertJsonPath('platform_role', PlatformRole::Support->value)
            ->assertJsonPath('required_permissions', ['search_intel.view']);
    }

    public function test_ops_role_can_read(): void
    {
        $ops = $this->superAdminWithRole(PlatformRole::Ops);

        Sanctum::actingAs($ops, ['*'], 'super_admin_api');

        $this->getJson('/api/v1/platform/search-intel/overview')
            ->assertOk()
            ->assertJsonPath('data.seo.pages_tracked', 0);
    }

    public function test_marketing_role_holds_view_and_manage(): void
    {
        $marketing = PlatformRole::Marketing;

        $this->assertTrue($marketing->hasPermission('search_intel.view'));
        $this->assertTrue($marketing->hasPermission('search_intel.manage'));
    }

    public function test_marketing_role_can_read_overview_and_integrations(): void
    {
        $marketing = $this->superAdminWithRole(PlatformRole::Marketing);

        Sanctum::actingAs($marketing, ['*'], 'super_admin_api');

        $this->getJson('/api/v1/platform/search-intel/overview')
            ->assertOk();

        $this->getJson('/api/v1/platform/search-intel/integrations')
            ->assertOk()
            // Une intégration jamais configurée est déclarée « non connecté »,
            // jamais omise : l'écran doit montrer les 4 connecteurs attendus.
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.provider', 'gsc')
            ->assertJsonPath('data.0.status', 'not_connected')
            ->assertJsonPath('data.0.has_credentials', false);
    }

    public function test_support_role_is_denied_on_integrations_route(): void
    {
        $support = $this->superAdminWithRole(PlatformRole::Support);

        Sanctum::actingAs($support, ['*'], 'super_admin_api');

        $this->getJson('/api/v1/platform/search-intel/integrations')
            ->assertStatus(403)
            ->assertJsonPath('error', 'PLATFORM_PERMISSION_REQUIRED');
    }

    private function superAdminWithRole(PlatformRole $role): SuperAdmin
    {
        $admin = new SuperAdmin([
            'name' => 'Test '.$role->value,
            'email' => $role->value.'-'.uniqid().'@leopardo.test',
        ]);

        // `platform_role` n'est jamais mass-assignable (#3597) : assignation
        // explicite, comme le fait PlatformTeamController.
        $admin->forceFill([
            'password_hash' => Hash::make('irrelevant-for-this-test'),
            'platform_role' => $role->value,
        ])->save();

        return $admin->refresh();
    }
}
