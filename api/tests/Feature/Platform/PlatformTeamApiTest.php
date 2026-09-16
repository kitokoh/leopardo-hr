<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Core\Tenant\Domain\Enums\PlatformPermission;
use App\Core\Tenant\Domain\Enums\PlatformRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #7553 — rôles internes de la plateforme.
 *
 * Couvre le contrat HTTP de `/api/v1/platform/team/*`, l'application réelle
 * des permissions (`platform.permission`) sur les routes sensibles, et les
 * garde-fous d'auto-verrouillage (dernier super admin actif, auto-modification).
 */
final class PlatformTeamApiTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_super_admin_sees_team_with_roles_and_permission_matrix(): void
    {
        $owner = $this->platformAccount('owner@leopardo.test', PlatformRole::SuperAdmin);
        $support = $this->platformAccount('support@leopardo.test', PlatformRole::Support);

        Sanctum::actingAs($owner, ['*'], 'super_admin_api');

        $response = $this->getJson('/api/v1/platform/team');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 2);
        $response->assertJsonPath('meta.active_super_admins', 1);
        $response->assertJsonPath('data.0.email', 'owner@leopardo.test');
        $response->assertJsonPath('data.0.platform_role', 'super_admin');
        $response->assertJsonPath('data.0.is_self', true);
        $response->assertJsonPath('data.1.email', 'support@leopardo.test');
        $response->assertJsonPath('data.1.platform_role', 'support');
        $response->assertJsonPath('data.1.is_self', false);
        $response->assertJsonStructure([
            'meta' => ['roles' => ['super_admin', 'admin', 'support', 'finance', 'ops', 'marketing']],
        ]);
    }

    public function test_team_endpoints_are_refused_to_a_non_team_manage_role(): void
    {
        // `admin` a tous les droits SAUF la distribution des rôles : la
        // délégation reste la prérogative du propriétaire du SaaS.
        $admin = $this->platformAccount('admin@leopardo.test', PlatformRole::Admin);

        Sanctum::actingAs($admin, ['*'], 'super_admin_api');

        $this->getJson('/api/v1/platform/team')
            ->assertForbidden()
            ->assertJsonPath('error', 'PLATFORM_PERMISSION_REQUIRED')
            ->assertJsonPath('platform_role', 'admin');

        $this->postJson('/api/v1/platform/team', [
            'name' => 'New Ops',
            'email' => 'ops@leopardo.test',
            'password' => 'a-very-long-password',
            'platform_role' => 'ops',
        ])->assertForbidden();
    }

    public function test_super_admin_creates_an_internal_collaborator_with_a_role(): void
    {
        $owner = $this->platformAccount('owner@leopardo.test', PlatformRole::SuperAdmin);

        Sanctum::actingAs($owner, ['*'], 'super_admin_api');

        $response = $this->postJson('/api/v1/platform/team', [
            'name' => 'Amina Support',
            'email' => 'Amina.Support@leopardo.test',
            'password' => 'a-very-long-password',
            'platform_role' => 'support',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.platform_role', 'support');
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonPath('data.email', 'amina.support@leopardo.test');

        $created = SuperAdmin::query()->where('email', 'amina.support@leopardo.test')->firstOrFail();
        $this->assertSame('support', $created->platform_role);
        $this->assertNotNull($created->password_hash);
        $this->assertTrue($created->platformRole()->hasPermission(PlatformPermission::SupportManage));

        $this->assertSame(1, AuditLog::query()->where('action', 'platform_team_created')->count());
    }

    public function test_creating_a_collaborator_with_an_unknown_role_is_rejected(): void
    {
        $owner = $this->platformAccount('owner@leopardo.test', PlatformRole::SuperAdmin);

        Sanctum::actingAs($owner, ['*'], 'super_admin_api');

        $this->postJson('/api/v1/platform/team', [
            'name' => 'Rogue',
            'email' => 'rogue@leopardo.test',
            'password' => 'a-very-long-password',
            'platform_role' => 'root',
        ])->assertStatus(422)->assertJsonValidationErrors('platform_role');
    }

    public function test_role_change_is_applied_and_audited(): void
    {
        $owner = $this->platformAccount('owner@leopardo.test', PlatformRole::SuperAdmin);
        $member = $this->platformAccount('member@leopardo.test', PlatformRole::Support);

        Sanctum::actingAs($owner, ['*'], 'super_admin_api');

        $this->patchJson("/api/v1/platform/team/{$member->id}/role", ['platform_role' => 'finance'])
            ->assertOk()
            ->assertJsonPath('data.platform_role', 'finance');

        $this->assertSame('finance', $member->fresh()?->platform_role);
        $this->assertSame(1, AuditLog::query()->where('action', 'platform_team_role_changed')->count());
    }

    public function test_a_platform_account_cannot_change_its_own_role(): void
    {
        $owner = $this->platformAccount('owner@leopardo.test', PlatformRole::SuperAdmin);

        Sanctum::actingAs($owner, ['*'], 'super_admin_api');

        $this->patchJson("/api/v1/platform/team/{$owner->id}/role", ['platform_role' => 'admin'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'CANNOT_CHANGE_OWN_PLATFORM_ROLE');

        $this->assertSame('super_admin', $owner->fresh()?->platform_role);
    }

    public function test_the_last_active_super_admin_cannot_be_demoted_nor_deactivated(): void
    {
        $owner = $this->platformAccount('owner@leopardo.test', PlatformRole::SuperAdmin);
        $second = $this->platformAccount('second@leopardo.test', PlatformRole::SuperAdmin);

        Sanctum::actingAs($owner, ['*'], 'super_admin_api');

        // Deux super admins actifs : la rétrogradation de l'autre est permise.
        $this->patchJson("/api/v1/platform/team/{$second->id}/role", ['platform_role' => 'ops'])
            ->assertOk();

        // Il n'en reste qu'un (l'appelant) : impossible de le rétrograder.
        $this->patchJson("/api/v1/platform/team/{$owner->id}/role", ['platform_role' => 'admin'])
            ->assertStatus(422);

        // Et le dernier super admin actif ne peut pas être désactivé par un tiers
        // (ici l'appelant est le dernier : on vérifie la garde côté cible).
        $other = $this->platformAccount('other@leopardo.test', PlatformRole::SuperAdmin);
        Sanctum::actingAs($other, ['*'], 'super_admin_api');

        $this->postJson("/api/v1/platform/team/{$owner->id}/deactivate")->assertOk();
        $this->assertSame('deactivated', $owner->fresh()?->status);

        // `other` est désormais le dernier super admin actif : sa propre
        // désactivation est refusée, et un collègue ne peut pas le désactiver.
        $this->postJson("/api/v1/platform/team/{$other->id}/deactivate")
            ->assertStatus(422)
            ->assertJsonPath('error', 'CANNOT_DISABLE_OWN_ACCOUNT');

        $ops = $this->platformAccount('ops@leopardo.test', PlatformRole::Ops);
        Sanctum::actingAs($ops, ['*'], 'super_admin_api');
        $this->postJson("/api/v1/platform/team/{$other->id}/deactivate")->assertForbidden();
    }

    public function test_deactivating_a_collaborator_revokes_its_tokens(): void
    {
        $owner = $this->platformAccount('owner@leopardo.test', PlatformRole::SuperAdmin);
        $member = $this->platformAccount('member@leopardo.test', PlatformRole::Support);
        $member->createToken('platform-api');

        $this->assertSame(1, DB::table('personal_access_tokens')->count());

        Sanctum::actingAs($owner, ['*'], 'super_admin_api');

        $this->postJson("/api/v1/platform/team/{$member->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'deactivated');

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_a_deactivated_platform_account_cannot_log_in(): void
    {
        $member = $this->platformAccount('member@leopardo.test', PlatformRole::Support);
        $member->forceFill(['status' => 'deactivated'])->save();

        $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'member@leopardo.test',
            'password' => 'password123',
        ])->assertStatus(403)->assertJsonPath('error', 'ACCOUNT_SUSPENDED');
    }

    public function test_auth_me_exposes_the_platform_role_and_permissions_without_breaking_role(): void
    {
        $owner = $this->platformAccount('owner@leopardo.test', PlatformRole::SuperAdmin);
        $support = $this->platformAccount('support@leopardo.test', PlatformRole::Support);

        Sanctum::actingAs($support, ['*'], 'super_admin_api');

        $response = $this->getJson('/api/v1/platform/auth/me');

        $response->assertOk();
        // Rétrocompatibilité : la garde du SPA admin lit toujours `role`.
        $response->assertJsonPath('data.role', 'super_admin');
        $response->assertJsonPath('data.platform_role', 'support');

        $permissions = $response->json('data.permissions');
        $this->assertIsArray($permissions);
        $this->assertContains('support.manage', $permissions);
        $this->assertNotContains('team.manage', $permissions);
        $this->assertNotContains('killswitch.manage', $permissions);

        Sanctum::actingAs($owner, ['*'], 'super_admin_api');
        $ownerPermissions = $this->getJson('/api/v1/platform/auth/me')->json('data.permissions');
        $this->assertIsArray($ownerPermissions);
        $this->assertCount(count(PlatformPermission::cases()), $ownerPermissions);
    }

    /**
     * La permission est appliquée pour de vrai sur les routes sensibles : un
     * rôle qui ne la porte pas reçoit 403, un rôle qui la porte atteint le
     * contrôleur. Les endpoints « positifs » choisis sont ceux déjà couverts
     * par la suite Platform (plans, users, kill switches, CRM, support).
     */
    public function test_permissions_are_enforced_on_the_sensitive_platform_routes(): void
    {
        $support = $this->platformAccount('support@leopardo.test', PlatformRole::Support);
        Sanctum::actingAs($support, ['*'], 'super_admin_api');
        $this->getJson('/api/v1/platform/users')->assertOk();
        $this->postJson('/api/v1/platform/companies', ['name' => 'X'])->assertForbidden();
        $this->postJson('/api/v1/platform/feature-kill-switches', ['key' => 'ai'])->assertForbidden();
        $this->getJson('/api/v1/platform/plans')->assertForbidden();
        $this->getJson('/api/v1/platform/team')->assertForbidden();

        $finance = $this->platformAccount('finance@leopardo.test', PlatformRole::Finance);
        Sanctum::actingAs($finance, ['*'], 'super_admin_api');
        $this->getJson('/api/v1/platform/plans')->assertOk();
        $this->postJson('/api/v1/platform/companies', ['name' => 'X'])->assertForbidden();

        $ops = $this->platformAccount('ops@leopardo.test', PlatformRole::Ops);
        Sanctum::actingAs($ops, ['*'], 'super_admin_api');
        $this->getJson('/api/v1/platform/companies')->assertOk();
        $this->getJson('/api/v1/platform/support-tickets')->assertForbidden();

        $marketing = $this->platformAccount('marketing@leopardo.test', PlatformRole::Marketing);
        Sanctum::actingAs($marketing, ['*'], 'super_admin_api');
        $this->getJson('/api/v1/platform/companies')->assertOk();
        $this->getJson('/api/v1/platform/feature-kill-switches')->assertForbidden();
    }

    private function platformAccount(string $email, PlatformRole $role): SuperAdmin
    {
        $account = new SuperAdmin([
            'name' => 'Platform '.$role->value,
            'email' => $email,
        ]);
        $account->forceFill([
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
            'platform_role' => $role->value,
        ])->save();

        return $account;
    }
}
