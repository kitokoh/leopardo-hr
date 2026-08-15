<?php

namespace Tests\Feature\Security;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #3727 — BelongsToCompany fail-closed sur la surface API tenant.
 *
 * Avant : sans `current_company` lié, le scope global sautait silencieusement
 * → requêtes toutes compagnies (fuite cross-tenant) pour un employé
 * `ordinary` sans compagnie passant TenantMiddleware.
 * Après : 403 TENANT_CONTEXT_MISSING. Les endpoints self-service sans modèle
 * scopé (/auth/me) restent fonctionnels, et le comportement console/jobs/
 * super-admin est inchangé (pas de marqueur → requêtes non scopées permises).
 */
class TenantScopeFailClosedTest extends TestCase
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

    public function test_scoped_query_without_company_context_fails_closed(): void
    {
        $orphan = Employee::factory()->create([
            'company_id' => null,
            'role' => 'ordinary',
            'status' => 'active',
        ]);

        Sanctum::actingAs($orphan);

        // /notifications interroge Notification (BelongsToCompany) : sans
        // compagnie liée, la requête doit échouer fermé au lieu de renvoyer
        // les notifications de TOUS les tenants.
        $response = $this->getJson('/api/v1/notifications');

        $response->assertStatus(403)
            ->assertJsonPath('error', 'TENANT_CONTEXT_MISSING');
    }

    public function test_self_service_endpoint_without_scoped_query_still_works(): void
    {
        $orphan = Employee::factory()->create([
            'company_id' => null,
            'role' => 'ordinary',
            'status' => 'active',
        ]);

        Sanctum::actingAs($orphan);

        // /auth/me n'interroge aucun modèle scopé : l'employé sans compagnie
        // conserve l'accès à son self-service (onboarding, langue, profil).
        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $orphan->id);
    }

    public function test_scoped_query_with_company_context_still_works(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'ordinary',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        // Compagnie liée → scope appliqué normalement, pas de faux positif.
        $this->getJson('/api/v1/notifications')->assertOk();
    }

    public function test_console_query_without_company_context_is_unaffected(): void
    {
        // Hors surface HTTP tenant (commandes, jobs, seeders) : pas de
        // marqueur → les requêtes cross-tenant légitimes restent possibles
        // via le comportement historique (opt-out explicite recommandé via
        // withoutGlobalScopes()).
        $company = Company::factory()->create(['status' => 'active']);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'ordinary',
            'status' => 'active',
        ]);

        \App\Modules\Notification\Domain\Models\Notification::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'test',
            'title' => 'Console context',
            'body' => 'Créé sans binding tenant (contexte console).',
        ]);

        $this->assertSame(
            1,
            \App\Modules\Notification\Domain\Models\Notification::query()->count()
        );
    }

    public function test_suspended_ordinary_user_without_company_is_rejected(): void
    {
        // Issue #3942 — les gardes de statut s'appliquent aussi aux comptes
        // ordinaires sans entreprise : avant, le branche `ordinary` sautait
        // les checks suspended/archived et laissait passer la requête.
        $orphan = Employee::factory()->create([
            'company_id' => null,
            'role' => 'ordinary',
            'status' => 'suspended',
        ]);

        Sanctum::actingAs($orphan);

        $this->getJson('/api/v1/notifications')
            ->assertStatus(403)
            ->assertJsonPath('error', 'EMPLOYEE_SUSPENDED');
    }

    public function test_archived_ordinary_user_without_company_is_rejected(): void
    {
        $orphan = Employee::factory()->create([
            'company_id' => null,
            'role' => 'ordinary',
            'status' => 'archived',
        ]);

        Sanctum::actingAs($orphan);

        $this->getJson('/api/v1/notifications')
            ->assertStatus(403)
            ->assertJsonPath('error', 'EMPLOYEE_ARCHIVED');
    }
}
