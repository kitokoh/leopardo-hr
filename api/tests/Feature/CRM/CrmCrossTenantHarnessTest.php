<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use Tests\RefreshTenantDatabase;
use Tests\Support\CRM\CrossTenantAssertions;
use Tests\Support\CRM\CrmTenantFixture;
use Tests\TestCase;

/**
 * #5738 (CRM PRE) — harness de fixtures et tests cross-tenant.
 *
 * Preuve que le harness fonctionne sur l'infrastructure tenant RÉELLE du
 * dépôt (lecture, mutation, relation indirecte, job, cache, export, webhook)
 * et qu'il détecte les absences de contrat (TenantManager, company_id).
 *
 * Les entités CRM synthétiques (`CrmTenantFixture::seedCrmDataIfAvailable`)
 * s'activent automatiquement quand les tables du socle V0 sont présentes ;
 * aujourd'hui le rapport `missing` les liste explicitement.
 */
class CrmCrossTenantHarnessTest extends TestCase
{
    use RefreshTenantDatabase;

    // ── fixtures : deux tenants + utilisateurs par rôle ─────────────────────

    public function test_fixture_creates_two_tenants_and_users_per_role(): void
    {
        [$tenantA, $tenantB] = CrmTenantFixture::createTwoTenants();

        $this->assertNotSame($tenantA->id, $tenantB->id);
        $this->assertSame('shared_tenants', $tenantA->schema_name);

        foreach ([$tenantA, $tenantB] as $tenant) {
            $users = CrmTenantFixture::usersByRole($tenant);

            $this->assertSame($tenant->id, (string) $users['principal']->company_id);
            $this->assertSame('principal', $users['principal']->manager_role);
            $this->assertSame($tenant->id, (string) $users['rh']->company_id);
            $this->assertSame('rh', $users['rh']->manager_role);
            $this->assertSame($tenant->id, (string) $users['employee']->company_id);
            $this->assertSame('employee', $users['employee']->role);
        }
    }

    // ── détection des absences de contrat ────────────────────────────────────

    public function test_harness_detects_tenant_manager_and_company_id(): void
    {
        [$tenantA] = CrmTenantFixture::createTwoTenants();
        $users = CrmTenantFixture::usersByRole($tenantA);

        // TenantManager résolvable (échec = harness invalide)…
        $manager = CrossTenantAssertions::assertTenantManagerResolvable($this);
        $this->assertInstanceOf(TenantManager::class, $manager);

        // …et company_id présent sur les modèles tenant.
        CrossTenantAssertions::assertCompanyIdPresent($this, $users['employee']);
    }

    // ── lecture / mutation / relation indirecte ─────────────────────────────

    public function test_cross_tenant_read_mutation_and_indirect_relation_are_safe(): void
    {
        [$tenantA, $tenantB] = CrmTenantFixture::createTwoTenants();
        $manager = app(TenantManager::class);

        // Données de A et de B (webhook_endpoints = modèle tenant réel).
        $manager->withinTenant($tenantA, function () use ($tenantA): void {
            CrossTenantAssertions::assertCreatedRowIsTenantScoped($this, WebhookEndpoint::class, [
                'url' => 'https://hooks.a.example/endpoint',
                'events' => ['crm.account.created'],
                'secret' => 'secret-A',
                'active' => true,
            ], (string) $tenantA->id);
        });

        $endpointB = $manager->withinTenant($tenantB, function () use ($tenantB) {
            return CrossTenantAssertions::assertCreatedRowIsTenantScoped($this, WebhookEndpoint::class, [
                'url' => 'https://hooks.b.example/endpoint',
                'events' => ['crm.account.created'],
                'secret' => 'secret-B',
                'active' => true,
            ], (string) $tenantB->id);
        });

        // Lecture : l'endpoint de B n'est pas résolu depuis A.
        $manager->withinTenant($tenantA, function () use ($endpointB): void {
            CrossTenantAssertions::assertScopedReadHidesCrossTenant(
                $this,
                WebhookEndpoint::class,
                (string) $endpointB->id
            );
        });

        // Relation indirecte : le propriétaire de l'autre tenant est invisible.
        $manager->withinTenant($tenantA, function () use ($endpointB): void {
            CrossTenantAssertions::assertIndirectRelationDoesNotLeak(
                $this,
                WebhookEndpoint::class,
                (string) $endpointB->id
            );
        });

        // Deux tenants simultanés : isolation stricte des lignes.
        CrossTenantAssertions::assertTwoTenantsAreIsolated($this, WebhookEndpoint::class, $tenantA, $tenantB);
    }

    // ── job : contexte tenant ────────────────────────────────────────────────

    public function test_job_dimension_establishes_and_restores_tenant_context(): void
    {
        [$tenantA] = CrmTenantFixture::createTwoTenants();
        $manager = app(TenantManager::class);
        $manager->clearTenant();

        $context = null;
        (new EnsureTenantContext)->handle(
            new HarnessTenantProbeJob($tenantA->id),
            function () use (&$context): void {
                $context = app(TenantManager::class)->current()?->id;
            }
        );

        $this->assertSame($tenantA->id, $context);
        $this->assertFalse($manager->hasTenant(), 'Contexte restauré après le job.');
    }

    // ── cache / export : clés tenant-scopées ─────────────────────────────────

    public function test_cache_and_export_artifacts_are_tenant_scoped(): void
    {
        [$tenantA, $tenantB] = CrmTenantFixture::createTwoTenants();

        CrossTenantAssertions::assertCacheTenantScoped(
            $this,
            'crm:pipeline-summary',
            (string) $tenantA->id,
            (string) $tenantB->id
        );

        // Convention export : l'artefact porte le tenant (jamais de nom nu).
        CrossTenantAssertions::assertArtifactNameTenantScoped(
            $this,
            "crm-export-{$tenantA->id}-2026-08.pdf",
            (string) $tenantA->id
        );
    }

    // ── webhook : réponse sûre cross-tenant ──────────────────────────────────

    public function test_webhook_endpoint_of_other_tenant_is_not_reachable(): void
    {
        [$tenantA, $tenantB] = CrmTenantFixture::createTwoTenants();
        $manager = app(TenantManager::class);

        $endpointB = $manager->withinTenant($tenantB, function () use ($tenantB) {
            return CrossTenantAssertions::assertCreatedRowIsTenantScoped($this, WebhookEndpoint::class, [
                'url' => 'https://hooks.b.example/webhook',
                'events' => ['crm.lead.converted'],
                'secret' => 'secret-B',
                'active' => true,
            ], (string) $tenantB->id);
        });

        // Depuis A, l'endpoint de B est invisible (404 au niveau modèle).
        $manager->withinTenant($tenantA, function () use ($endpointB): void {
            CrossTenantAssertions::assertScopedReadHidesCrossTenant(
                $this,
                WebhookEndpoint::class,
                (string) $endpointB->id
            );
        });

        // La ligne n'est jamais exposée hors de son tenant.
        $this->assertSame(0, WebhookEndpoint::query()->withoutGlobalScopes()
            ->where('company_id', (string) $tenantA->id)
            ->whereKey($endpointB->id)
            ->count());
    }

    // ── entités CRM : seed activable quand les tables V0 existent ───────────

    public function test_crm_entity_seed_reports_missing_tables_until_v0_lands(): void
    {
        [$tenantA] = CrmTenantFixture::createTwoTenants();

        $report = CrmTenantFixture::seedCrmDataIfAvailable($tenantA);

        $this->assertIsArray($report['created']);
        $this->assertIsArray($report['missing']);
        // Indépendant de l'ordre : created + missing partitionnent toujours
        // le contrat complet des tables CRM (aujourd'hui : created vide).
        $this->assertEqualsCanonicalizing(
            CrmTenantFixture::CRM_TABLES,
            array_merge($report['created'], $report['missing'])
        );
    }
}

/**
 * Fixture locale — job tenant-scopé pour la dimension « job » du harness
 * (issue #5738). Autonome : ne dépend d'aucune autre PR du programme.
 */
final class HarnessTenantProbeJob implements TenantScopedJob
{
    public function __construct(private readonly string $companyId) {}

    public function tenantCompanyId(): string
    {
        return $this->companyId;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
    }

    public function handle(): void {}
}
