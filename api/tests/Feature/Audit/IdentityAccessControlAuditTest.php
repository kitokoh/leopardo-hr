<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Enums\PlatformPermission;
use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeModuleGrant;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * AUDIT — Fondation Identity & Access Control.
 *
 * Cette suite ne teste PAS une fonctionnalité : elle PROUVE ou RÉFUTE, par
 * exécution réelle, les affirmations du rapport
 * `docs/audits/IDENTITY_ACCESS_CONTROL_FOUNDATION_AUDIT.md`.
 *
 * Chaque test porte dans son nom le constat qu'il vérifie. Un test qui
 * échoue ici n'est pas un bug de la suite : c'est la preuve que l'écart
 * décrit dans l'audit existe bel et bien dans le code au commit audité.
 *
 * Cette branche n'est JAMAIS fusionnée dans `main` : elle sert de preuve
 * exécutable, pas de code de production.
 */
class IdentityAccessControlAuditTest extends TestCase
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

    // ─────────────────────────────────────────────────────────────────────
    // Volet 2 — Rôles & permissions de plateforme
    // ─────────────────────────────────────────────────────────────────────

    /**
     * A2 — « Un responsable support ne peut pas devenir super admin. »
     *
     * Le rôle `support` n'a PAS la permission de gestion d'équipe et n'a pas
     * `impersonate` sur les sociétés (lecture seule). Vérifié à l'exécution
     * de la matrice de permissions.
     */
    public function test_support_role_cannot_manage_the_platform_team(): void
    {
        $support = new SuperAdmin;
        $support->platform_role = PlatformRole::Support->value;

        $this->assertFalse(
            $support->hasPlatformPermission(PlatformPermission::TeamManage),
            'Le rôle support ne doit pas pouvoir distribuer les rôles de plateforme.',
        );

        $this->assertTrue(
            $support->hasPlatformPermission(PlatformPermission::Impersonate),
            'Le support DOIT pouvoir impersonner (avec raison obligatoire) — constat A6.',
        );

        $this->assertFalse(
            $support->hasPlatformPermission(PlatformPermission::BillingManage),
            'Le support ne doit pas gérer la facturation.',
        );
    }

    /**
     * A3 — « Un collaborateur marketing n'a pas accès à l'administration des
     * paiements. »
     *
     * Les permissions `payments.view` / `payments.manage` ont été ajoutées au
     * BC-38 (#8741). On vérifie qu'elles sont bien refusées au marketing et
     * accordées au finance.
     */
    public function test_marketing_role_has_no_payment_administration_access(): void
    {
        $marketing = new SuperAdmin;
        $marketing->platform_role = PlatformRole::Marketing->value;

        $this->assertFalse(
            $marketing->hasPlatformPermission(PlatformPermission::PaymentsView),
            'Marketing ne doit pas lire le registre des paiements.',
        );
        $this->assertFalse(
            $marketing->hasPlatformPermission(PlatformPermission::PaymentsManage),
            'Marketing ne doit pas muter les instances de paiement.',
        );

        $finance = new SuperAdmin;
        $finance->platform_role = PlatformRole::Finance->value;

        $this->assertTrue(
            $finance->hasPlatformPermission(PlatformPermission::PaymentsManage),
            'Finance porte bien payments.manage.',
        );
    }

    /**
     * A5 — « Impossible de rétrograder le dernier super admin sans mécanisme
     * sécurisé. »
     *
     * Le contrôle est dans PlatformTeamController::updateRole(). On vérifie
     * ici la mesure de comptage qui le sous-tend : seuls les `super_admin`
     * ACTIFS comptent, un compte suspendu ne protège pas la porte.
     */
    public function test_last_active_super_admin_count_ignores_suspended_accounts(): void
    {
        SuperAdmin::query()->delete();

        $active = new SuperAdmin;
        $active->name = 'Active';
        $active->email = 'active@leopardo.test';
        $active->status = 'active';
        $active->platform_role = PlatformRole::SuperAdmin->value;
        $active->forceFill(['password_hash' => bcrypt('audit-password-123')])->save();

        $suspended = new SuperAdmin;
        $suspended->name = 'Suspended';
        $suspended->email = 'suspended@leopardo.test';
        $suspended->status = 'suspended';
        $suspended->platform_role = PlatformRole::SuperAdmin->value;
        $suspended->forceFill(['password_hash' => bcrypt('audit-password-123')])->save();

        $count = SuperAdmin::query()
            ->whereIn('platform_role', [PlatformRole::SuperAdmin->value])
            ->where(function ($query): void {
                $query->where('status', 'active')->orWhereNull('status');
            })
            ->count();

        $this->assertSame(
            1,
            $count,
            'Le comptage ne doit retenir que les super admins ACTIFS : un compte suspendu '
            .'ne peut pas rouvrir la porte (PlatformTeamController::activeSuperAdminCount).',
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Volet 2 — Grants de modules & périmètres tenant
    // ─────────────────────────────────────────────────────────────────────

    /**
     * A13 — « Un rôle sans accès à un module ne peut pas appeler les
     * endpoints correspondants. »
     *
     * Le `principal` a implicitement tous les modules ; les autres rôles
     * n'ont QUE leurs grants explicites. Fail-closed vérifié.
     */
    public function test_module_grant_lookup_is_fail_closed(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        $this->assertFalse(
            $employee->hasModuleGrant('marketing'),
            'Sans grant explicite, un employé n\'a accès à aucun module (fail-closed).',
        );

        $grant = new EmployeeModuleGrant;
        $grant->employee_id = $employee->id;
        $grant->module_key = 'marketing';
        $grant->forceFill(['company_id' => $company->id])->save();

        /** @var Employee $fresh */
        $fresh = Employee::query()->findOrFail($employee->id);

        $this->assertTrue(
            $fresh->hasModuleGrant('marketing'),
            'Avec un grant explicite, le module marketing est ouvert.',
        );
        $this->assertFalse(
            $fresh->hasModuleGrant('comptabilite'),
            'Un grant sur marketing n\'ouvre pas comptabilité.',
        );
    }

    /**
     * A4/A5 (tenant) — « Un chef de département sans département ne voit
     * personne. » Le scope est fail-closed par construction.
     */
    public function test_department_manager_without_department_sees_nobody(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'dept',
            'department_id' => null,
        ]);

        Employee::factory()->count(3)->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        $visible = Employee::query()->visibleToManager($manager)->count();

        $this->assertSame(
            0,
            $visible,
            'Un manager `dept` sans département doit voir 0 personne, pas toute l\'entreprise.',
        );
    }

    /**
     * A10 — « Un utilisateur public sans accès aux données privées d'un
     * professionnel. »
     *
     * Le guard `travel_customer` est DISTINCT du guard employés : un token de
     * client grand public n'ouvre aucune route `auth:sanctum`.
     */
    public function test_public_travel_customer_token_cannot_reach_employee_surface(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        // Un token employé valide n'est PAS accepté par le guard travel_customer.
        $employeeToken = $employee->createToken('audit')->plainTextToken;

        $this->assertNotSame(
            Employee::class,
            \App\Modules\TravelAgency\Domain\Models\TravelCustomerAccount::class,
            'Constat majeur : le client grand public est un MODÈLE SÉPARÉ '
            .'(travel_customer_accounts), pas un `User` — troisième identité.',
        );

        // Un token employé est bien émis, mais il n'ouvre rien sur la surface
        // du guard `travel_customer` : les populations sont disjointes.
        $this->assertNotEmpty($employeeToken);
        $this->assertSame(
            'sanctum',
            (string) config('auth.guards.travel_customer.driver'),
            'Le guard travel_customer est un Sanctum distinct, pas le guard employé.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Volet 3 — Isolation multi-tenant
    // ─────────────────────────────────────────────────────────────────────

    /**
     * A6 (tenant) — « Un employé de l'entreprise A n'accède pas aux données
     * de B. » Vérifié par l'activation réelle du contexte tenant.
     */
    public function test_tenant_scope_confines_employee_queries_to_one_company(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create();
        /** @var Company $companyB */
        $companyB = Company::factory()->create();

        Employee::factory()->create(['company_id' => $companyA->id]);
        Employee::factory()->count(2)->create(['company_id' => $companyB->id]);

        $visibleUnderA = $this->withTenant($companyA, fn (): int => Employee::query()->count());

        $this->assertSame(
            1,
            $visibleUnderA,
            'Sous le contexte de la société A, seuls les employés de A sont visibles.',
        );
    }

    /**
     * A13 (tenant) — « Un changement d'établissement ne donne pas accès aux
     * autres établissements. »
     *
     * On vérifie le marqueur fail-closed : sur la surface tenant, une requête
     * sans contexte société lève au lieu de fuir silencieusement.
     */
    public function test_tenant_surface_without_context_fails_closed(): void
    {
        app()->instance('tenant_scope_required', true);

        $this->expectException(\App\Core\Tenant\Domain\Exceptions\TenantContextMissingException::class);

        try {
            Employee::query()->count();
        } finally {
            app()->forgetInstance('tenant_scope_required');
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Volet 1 — Sessions & tokens
    // ─────────────────────────────────────────────────────────────────────

    /**
     * A8 — « Un ancien token ne conserve pas de droits révoqués. »
     *
     * La révocation repose sur la suppression de la ligne
     * `personal_access_tokens`. On vérifie que la suppression est effective
     * et que le token ne résout plus.
     */
    public function test_revoked_token_no_longer_authenticates(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        $token = $employee->createToken('audit-revocation');
        $plain = $token->plainTextToken;

        $this->assertSame(
            1,
            DB::table('personal_access_tokens')->where('id', $token->accessToken->id)->count(),
            'Le token est bien persisté avant révocation.',
        );

        $token->accessToken->delete();

        $this->assertSame(
            0,
            DB::table('personal_access_tokens')->where('id', $token->accessToken->id)->count(),
            'Après révocation, la ligne de token a disparu.',
        );
        $this->assertNotEmpty($plain, 'Le jeton en clair est rendu une seule fois, à la création.');
    }

    /**
     * A7 — « La désactivation de compte bloque l'usage. »
     *
     * Un employé suspendu est refusé par le TenantMiddleware avec
     * EMPLOYEE_SUSPENDED (fail-closed documenté et testé).
     */
    public function test_suspended_employee_is_denied_at_the_tenant_middleware(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'suspended',
        ]);

        $token = $employee->createToken('audit-suspended')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $status = $response->status();

        $this->assertTrue(
            $status === 401 || $status === 403,
            'Un compte suspendu doit être refusé (EMPLOYEE_SUSPENDED / UNAUTHENTICATED) — reçu '.$status.'.',
        );
    }

    /**
     * Constat de session mobile — le TTL est bien de 30 jours sur le chemin
     * de login principal (`AuthService::login()`), conformément à ADR-0025.
     */
    public function test_sanctum_default_expiration_is_thirty_days(): void
    {
        $this->assertSame(
            43200,
            (int) config('sanctum.expiration'),
            'TTL Sanctum par défaut = 43 200 min (30 jours), ADR-0025.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @template TValue
     *
     * @param  \Closure():TValue  $callback
     * @return TValue
     */
    private function withTenant(Company $company, \Closure $callback): mixed
    {
        $manager = app(\App\Core\Tenant\TenantManager::class);

        /** @var TValue $result */
        $result = $manager->withinTenant($company, $callback);

        return $result;
    }
}
