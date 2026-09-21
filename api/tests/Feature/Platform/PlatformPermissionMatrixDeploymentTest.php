<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Tenant\Domain\Enums\PlatformPermission;
use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
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
 * #8020 — suivi de #7973 : les routes /admin (et /platform/country-defaults)
 * restées sans garde sont armées :
 *
 *   - rapports RH + conversations IA + formations + alertes flotte
 *     (contenus tenant cross-tenant)                → companies.manage (admin)
 *   - stats de surveys solutions                    → crm.view (marketing+admin)
 *   - suivi IA + simulation paie (écrans Paramètres)→ settings.manage (admin)
 *   - country-defaults (référentiel pays)           → companies.view
 *
 * Contrat : un rôle délégué sans la permission reçoit 403 ; un rôle qui la
 * porte atteinte le contrôleur (la réponse peut être 200/404/422 selon la
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

    // ── #8020 — routes /admin & /platform résiduelles ───────────────────────

    /**
     * Route → permission attendue (matrice #8020).
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function residualRouteGuardProvider(): array
    {
        return [
            'country-defaults' => ['GET', 'api/v1/platform/country-defaults', PlatformPermission::CompaniesView->value],
            'ai conversations' => ['GET', 'api/v1/admin/ai/conversations', PlatformPermission::CompaniesManage->value],
            'ai conversation messages' => ['GET', 'api/v1/admin/ai/conversations/{conversation}/messages', PlatformPermission::CompaniesManage->value],
            'ai chat' => ['POST', 'api/v1/admin/ai/chat', PlatformPermission::CompaniesManage->value],
            'solutions survey-stats' => ['GET', 'api/v1/admin/solutions/survey-stats', PlatformPermission::CrmView->value],
            'fleet alerts' => ['GET', 'api/v1/admin/fleet/alerts', PlatformPermission::CompaniesManage->value],
            'hr reports' => ['GET', 'api/v1/admin/hr-reports', PlatformPermission::CompaniesManage->value],
            'training courses' => ['GET', 'api/v1/admin/training/courses', PlatformPermission::CompaniesManage->value],
            'training sessions' => ['GET', 'api/v1/admin/training/sessions', PlatformPermission::CompaniesManage->value],
            'training enrollments' => ['GET', 'api/v1/admin/training/enrollments', PlatformPermission::CompaniesManage->value],
            'ai monitoring' => ['GET', 'api/v1/admin/platform/ai/monitoring', PlatformPermission::SettingsManage->value],
            'ai health' => ['GET', 'api/v1/admin/platform/ai/health', PlatformPermission::SettingsManage->value],
            'payroll simulate' => ['POST', 'api/v1/admin/payroll/simulate', PlatformPermission::SettingsManage->value],
        ];
    }

    /**
     * Garde #8020 — la permission est portée par la DÉFINITION de la route.
     *
     * Indispensable pour `/platform/country-defaults` : tous les rôles de la
     * matrice #7553 portent `companies.view`, donc aucun jeton réel ne peut
     * prouver le 403 par le rôle. La route reste néanmoins armée (toute
     * permission retirée/renommée demain referme l'accès).
     */
    #[DataProvider('residualRouteGuardProvider')]
    public function test_residual_routes_carry_platform_permission(string $method, string $uri, string $permission): void
    {
        $route = null;

        foreach (Route::getRoutes() as $candidate) {
            if ($candidate->uri() === $uri && in_array($method, $candidate->methods(), true)) {
                $route = $candidate;
                break;
            }
        }

        self::assertNotNull($route, "Route introuvable : {$method} {$uri}");

        $guarded = false;

        foreach ($route->gatherMiddleware() as $middleware) {
            $middleware = (string) $middleware;

            if (str_contains($middleware, 'platform.permission:'.$permission)
                || str_contains($middleware, 'EnsurePlatformPermissionMiddleware:'.$permission)) {
                $guarded = true;
                break;
            }
        }

        self::assertTrue($guarded, "{$method} {$uri} doit porter platform.permission:{$permission}");
    }

    /**
     * #8020 — contenus tenant CROSS-TENANT : conversations IA (titres +
     * messages), assistant IA plateforme, rapports RH (dont résumé de paie),
     * formations (inscriptions nominatives) et alertes flotte.
     *
     * Toutes exigent `companies.manage` (admin seul) : le support (pourtant
     * porteur de `users.view`/`impersonate`), finance, ops et marketing en
     * sont exclus — c'est le cœur du durcissement demandé par #8005/#8020.
     */
    public function test_cross_tenant_admin_content_requires_companies_manage(): void
    {
        $hrReport = '/api/v1/admin/hr-reports?type=headcount&start_date=2026-01-01&end_date=2026-12-31';

        $crossTenantRoutes = [
            $hrReport,
            '/api/v1/admin/ai/conversations',
            '/api/v1/admin/ai/conversations/1/messages',
            '/api/v1/admin/fleet/alerts',
            '/api/v1/admin/training/courses',
            '/api/v1/admin/training/sessions',
            '/api/v1/admin/training/enrollments',
        ];

        foreach ([PlatformRole::Support, PlatformRole::Finance, PlatformRole::Ops, PlatformRole::Marketing] as $role) {
            $this->actingAsPlatform($role);

            foreach ($crossTenantRoutes as $uri) {
                $this->getJson($uri)->assertForbidden();
            }

            $this->postJson('/api/v1/admin/ai/chat', ['message' => 'Bonjour'])->assertForbidden();
        }

        foreach ([PlatformRole::Admin, PlatformRole::SuperAdmin] as $role) {
            $this->actingAsPlatform($role);

            foreach ($crossTenantRoutes as $uri) {
                $response = $this->getJson($uri);
                self::assertNotSame(403, $response->getStatusCode(), "{$role->value} (companies.manage) doit passer {$uri}");
            }

            $chat = $this->postJson('/api/v1/admin/ai/chat', ['message' => 'Bonjour']);
            self::assertNotSame(403, $chat->getStatusCode(), "{$role->value} (companies.manage) doit passer /admin/ai/chat");
        }
    }

    // ── #8020 — agrégats marketing, suivi IA, simulation paie ───────────────

    public function test_solution_survey_stats_requires_crm_view(): void
    {
        foreach ([PlatformRole::Support, PlatformRole::Finance, PlatformRole::Ops] as $role) {
            $this->actingAsPlatform($role);
            $this->getJson('/api/v1/admin/solutions/survey-stats')->assertForbidden();
        }

        $this->actingAsPlatform(PlatformRole::Marketing);
        $response = $this->getJson('/api/v1/admin/solutions/survey-stats');
        self::assertNotSame(403, $response->getStatusCode(), 'marketing (crm.view) doit passer');
    }

    public function test_ai_monitoring_and_payroll_simulation_require_settings_manage(): void
    {
        // Le support porte `observability.view` : la garde `settings.manage`
        // prouve que le suivi IA n'est plus ouvert aux rôles délégués.
        foreach ([PlatformRole::Support, PlatformRole::Ops, PlatformRole::Finance] as $role) {
            $this->actingAsPlatform($role);
            $this->getJson('/api/v1/admin/platform/ai/monitoring')->assertForbidden();
            $this->getJson('/api/v1/admin/platform/ai/health')->assertForbidden();
            $this->postJson('/api/v1/admin/payroll/simulate', [])->assertForbidden();
        }

        $this->actingAsPlatform(PlatformRole::Admin);

        $monitoring = $this->getJson('/api/v1/admin/platform/ai/monitoring');
        self::assertNotSame(403, $monitoring->getStatusCode(), 'admin (settings.manage) doit passer le suivi IA');

        $health = $this->getJson('/api/v1/admin/platform/ai/health');
        self::assertNotSame(403, $health->getStatusCode(), 'admin (settings.manage) doit passer le health IA');

        $simulate = $this->postJson('/api/v1/admin/payroll/simulate', []);
        self::assertNotSame(403, $simulate->getStatusCode(), 'admin (settings.manage) doit passer la simulation');
    }

    public function test_country_defaults_is_readable_by_every_platform_role(): void
    {
        // Référentiel pays non sensible : la garde `companies.view` est portée
        // par la définition de la route (test ci-dessus) et ne casse pas le
        // sélecteur de pays des écrans Entreprises.
        foreach ([PlatformRole::Support, PlatformRole::Marketing, PlatformRole::Ops] as $role) {
            $this->actingAsPlatform($role);
            $response = $this->getJson('/api/v1/platform/country-defaults');
            self::assertNotSame(403, $response->getStatusCode(), "{$role->value} (companies.view) doit passer");
        }
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
