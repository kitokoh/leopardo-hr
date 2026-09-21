<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Tenant\Domain\Enums\PlatformPermission;
use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7973 — la matrice `platform.permission` (#7553) est réellement déployée
 * sur les blocs qui l'avaient perdue :
 *
 *   - CRUD offres (/platform/plans)                 → plans.manage (finance)
 *   - purge de tenant (/platform/companies DELETE)  → companies.manage
 *   - webhooks sortants (/admin/webhooks*)          → webhooks.manage (admin+ops)
 *   - réglages /admin (e-mails, IA, barèmes, fériés)→ settings.manage (admin)
 *   - audit paie cross-tenant (/admin/payroll/audit)→ payroll.view (finance+ops)
 *   - utilisateurs plateforme (/admin/users)        → users.view / users.manage
 *   - alias /admin/edge-nodes                       → edge.manage
 *
 * Contrat : un rôle délégué sans la permission reçoit 403 ; un rôle qui la
 * porte atteint le contrôleur (la réponse peut être 200/404/422 selon la
 * fixture — tout sauf 403, le middleware est passé).
 */
class PlatformPermissionMatrixDeploymentTest extends TestCase
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

    // ── Matrice rôle → nouvelles permissions ────────────────────────────────

    public function test_matrix_carries_the_new_permissions_on_the_expected_roles(): void
    {
        self::assertTrue(PlatformRole::SuperAdmin->hasPermission(PlatformPermission::PlansManage));
        self::assertTrue(PlatformRole::SuperAdmin->hasPermission(PlatformPermission::WebhooksManage));
        self::assertTrue(PlatformRole::SuperAdmin->hasPermission(PlatformPermission::SettingsManage));
        self::assertTrue(PlatformRole::SuperAdmin->hasPermission(PlatformPermission::PayrollView));

        self::assertTrue(PlatformRole::Admin->hasPermission(PlatformPermission::PlansManage));
        self::assertTrue(PlatformRole::Admin->hasPermission(PlatformPermission::WebhooksManage));
        self::assertTrue(PlatformRole::Admin->hasPermission(PlatformPermission::SettingsManage));
        self::assertTrue(PlatformRole::Admin->hasPermission(PlatformPermission::PayrollView));

        self::assertTrue(PlatformRole::Finance->hasPermission(PlatformPermission::PlansManage));
        self::assertTrue(PlatformRole::Finance->hasPermission(PlatformPermission::PayrollView));
        self::assertFalse(PlatformRole::Finance->hasPermission(PlatformPermission::WebhooksManage));
        self::assertFalse(PlatformRole::Finance->hasPermission(PlatformPermission::SettingsManage));

        self::assertTrue(PlatformRole::Ops->hasPermission(PlatformPermission::WebhooksManage));
        self::assertTrue(PlatformRole::Ops->hasPermission(PlatformPermission::PayrollView));
        self::assertFalse(PlatformRole::Ops->hasPermission(PlatformPermission::PlansManage));
        self::assertFalse(PlatformRole::Ops->hasPermission(PlatformPermission::SettingsManage));

        foreach ([PlatformRole::Support, PlatformRole::Marketing] as $role) {
            self::assertFalse($role->hasPermission(PlatformPermission::PlansManage), $role->value);
            self::assertFalse($role->hasPermission(PlatformPermission::WebhooksManage), $role->value);
            self::assertFalse($role->hasPermission(PlatformPermission::SettingsManage), $role->value);
            self::assertFalse($role->hasPermission(PlatformPermission::PayrollView), $role->value);
        }
    }

    // ── Plans ────────────────────────────────────────────────────────────────

    public function test_plans_write_requires_plans_manage(): void
    {
        $this->actingAsPlatform(PlatformRole::Support);
        $this->postJson('/api/v1/platform/plans', [])->assertForbidden();
        $this->patchJson('/api/v1/platform/plans/1', [])->assertForbidden();
        $this->postJson('/api/v1/platform/plans/1/duplicate')->assertForbidden();
        $this->postJson('/api/v1/platform/plans/1/archive')->assertForbidden();
        $this->deleteJson('/api/v1/platform/plans/1')->assertForbidden();

        $this->actingAsPlatform(PlatformRole::Finance);
        $response = $this->postJson('/api/v1/platform/plans', []);
        // 422 de validation attendu (payload vide) : le middleware est passé.
        self::assertNotSame(403, $response->getStatusCode(), 'finance (plans.manage) doit passer le middleware');
    }

    // ── Purge de tenant ──────────────────────────────────────────────────────

    public function test_tenant_deletion_requires_companies_manage(): void
    {
        $companyId = '00000000-0000-0000-0000-000000000001';

        $this->actingAsPlatform(PlatformRole::Support);
        $this->getJson("/api/v1/platform/companies/{$companyId}/deletion-inventory")->assertForbidden();
        $this->deleteJson("/api/v1/platform/companies/{$companyId}")->assertForbidden();

        // La lecture de l'historique reste en companies.view (support l'a).
        $history = $this->getJson("/api/v1/platform/companies/{$companyId}/deletion-audits");
        self::assertNotSame(403, $history->getStatusCode());

        $this->actingAsPlatform(PlatformRole::SuperAdmin);
        $response = $this->deleteJson("/api/v1/platform/companies/{$companyId}");
        self::assertNotSame(403, $response->getStatusCode());
    }

    // ── Webhooks sortants ────────────────────────────────────────────────────

    public function test_webhooks_require_webhooks_manage(): void
    {
        $this->actingAsPlatform(PlatformRole::Support);
        $this->getJson('/api/v1/admin/webhooks')->assertForbidden();
        $this->postJson('/api/v1/admin/webhooks', [])->assertForbidden();
        $this->deleteJson('/api/v1/admin/webhooks/1')->assertForbidden();

        $this->actingAsPlatform(PlatformRole::Marketing);
        $this->getJson('/api/v1/admin/webhooks')->assertForbidden();

        $this->actingAsPlatform(PlatformRole::Finance);
        $this->getJson('/api/v1/admin/webhooks')->assertForbidden();

        $this->actingAsPlatform(PlatformRole::Ops);
        $response = $this->getJson('/api/v1/admin/webhooks');
        self::assertNotSame(403, $response->getStatusCode(), 'ops (webhooks.manage) doit passer');
    }

    // ── Réglages /admin ──────────────────────────────────────────────────────

    public function test_admin_settings_require_settings_manage(): void
    {
        $this->actingAsPlatform(PlatformRole::Support);
        $this->getJson('/api/v1/admin/email-templates')->assertForbidden();
        $this->putJson('/api/v1/admin/email-templates', [])->assertForbidden();
        $this->getJson('/api/v1/admin/tax-slabs')->assertForbidden();
        $this->postJson('/api/v1/admin/tax-slabs/reset-defaults')->assertForbidden();
        $this->getJson('/api/v1/admin/social-contributions')->assertForbidden();
        $this->getJson('/api/v1/admin/public-holidays')->assertForbidden();
        $this->getJson('/api/v1/admin/rate-validation/pending')->assertForbidden();
        $this->putJson('/api/v1/admin/platform/ai/settings', [])->assertForbidden();
        $this->getJson('/api/v1/admin/platform/marketing/oauth-config')->assertForbidden();

        $this->actingAsPlatform(PlatformRole::Finance);
        $this->getJson('/api/v1/admin/tax-slabs')->assertForbidden();

        $this->actingAsPlatform(PlatformRole::Ops);
        $this->getJson('/api/v1/admin/email-templates')->assertForbidden();

        $this->actingAsPlatform(PlatformRole::Admin);
        $response = $this->getJson('/api/v1/admin/email-templates');
        self::assertNotSame(403, $response->getStatusCode(), 'admin (settings.manage) doit passer');
    }

    // ── Audit paie cross-tenant ──────────────────────────────────────────────

    public function test_payroll_audit_requires_payroll_view(): void
    {
        // Le support est volontairement exclu (traces salariales cross-tenant).
        $this->actingAsPlatform(PlatformRole::Support);
        $this->getJson('/api/v1/admin/payroll/audit')->assertForbidden();

        $this->actingAsPlatform(PlatformRole::Marketing);
        $this->getJson('/api/v1/admin/payroll/audit')->assertForbidden();

        $this->actingAsPlatform(PlatformRole::Finance);
        $response = $this->getJson('/api/v1/admin/payroll/audit');
        self::assertNotSame(403, $response->getStatusCode(), 'finance (payroll.view) doit passer');

        $this->actingAsPlatform(PlatformRole::Ops);
        $response = $this->getJson('/api/v1/admin/payroll/audit');
        self::assertNotSame(403, $response->getStatusCode(), 'ops (payroll.view) doit passer');
    }

    // ── Utilisateurs plateforme ──────────────────────────────────────────────

    public function test_platform_users_require_users_permissions(): void
    {
        $this->actingAsPlatform(PlatformRole::Marketing);
        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->patchJson('/api/v1/admin/users/1', [])->assertForbidden();

        $this->actingAsPlatform(PlatformRole::Support);
        $response = $this->getJson('/api/v1/admin/users');
        self::assertNotSame(403, $response->getStatusCode(), 'support (users.view) doit passer en lecture');
    }

    // ── Alias /admin/edge-nodes ──────────────────────────────────────────────

    public function test_edge_nodes_alias_keeps_edge_manage(): void
    {
        $this->actingAsPlatform(PlatformRole::Support);
        $this->getJson('/api/v1/admin/edge-nodes')->assertForbidden();

        $this->actingAsPlatform(PlatformRole::Ops);
        $response = $this->getJson('/api/v1/admin/edge-nodes');
        self::assertNotSame(403, $response->getStatusCode(), 'ops (edge.manage) doit passer');
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    private function actingAsPlatform(PlatformRole $role): SuperAdmin
    {
        $account = new SuperAdmin([
            'name' => 'Platform '.$role->value,
            'email' => $role->value.'-matrix@leopardo.test',
        ]);
        $account->forceFill([
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
            'platform_role' => $role->value,
        ])->save();

        Sanctum::actingAs($account, ['*'], 'super_admin_api');

        return $account;
    }
}
