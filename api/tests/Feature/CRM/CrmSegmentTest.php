<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Contracts\SegmentContactSourceInterface;
use App\Modules\CRM\Domain\Models\CrmSegment;
use App\Modules\CRM\Domain\Models\CrmSegmentMember;
use App\Modules\CRM\Domain\Models\CrmSegmentVersion;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5723 — segments CRM tenant simples.
 *
 * Couvre : CRUD versionné (snapshot reproductible), rebuild depuis la source
 * (members computed remplacés, manual préservés), source indisponible → 422,
 * RBAC (lecture manager, écriture principal/marketing), isolation tenant via
 * l'API (404 cross-tenant), audit des mutations.
 */
class CrmSegmentTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(Company $company, string $managerRole = 'principal'): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        return $manager;
    }

    private function ordinaryEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $employee;
    }

    /**
     * @param  list<int>  $ids
     */
    private function fakeSource(array $ids, bool $available = true): SegmentContactSourceInterface
    {
        return new class($ids, $available) implements SegmentContactSourceInterface
        {
            /**
             * @param  list<int>  $ids
             */
            public function __construct(private readonly array $ids, private readonly bool $available) {}

            public function matchingContactIds(array $definition): array
            {
                return $this->ids;
            }

            public function supports(array $definition): bool
            {
                return $this->available;
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function validDefinition(): array
    {
        return [
            'operator' => 'and',
            'conditions' => [
                ['field' => 'crm_contacts.status', 'op' => 'eq', 'value' => 'active'],
            ],
        ];
    }

    // ─── CRUD versionné ─────────────────────────────────────────────────────

    public function test_principal_can_create_segment(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/crm/segments', [
            'name' => 'Clients actifs DZ',
            'description' => 'Prospects actifs en Algérie',
            'definition' => $this->validDefinition(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Clients actifs DZ');
        $response->assertJsonPath('data.version', 1);
        $response->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('crm_segments', [
            'company_id' => $this->companyA->id,
            'name' => 'Clients actifs DZ',
            'version' => 1,
        ]);

        // Snapshot version 1 figé.
        $this->assertDatabaseHas('crm_segment_versions', [
            'segment_id' => $response->json('data.id'),
            'version' => 1,
        ]);
    }

    public function test_create_with_invalid_definition_is_rejected(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/crm/segments', [
            'name' => 'Segment cassé',
            'definition' => [
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'crm_contacts.salary', 'op' => 'eq', 'value' => 5000],
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('definition');
        $this->assertDatabaseMissing('crm_segments', ['name' => 'Segment cassé']);
    }

    public function test_update_bumps_version_and_freezes_previous(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        // Création via l'API : la version 1 est figée par le service.
        $create = $this->postJson('/api/v1/crm/segments', [
            'name' => 'Segment v1',
            'definition' => $this->validDefinition(),
        ]);
        $create->assertStatus(201);

        $segmentId = $create->json('data.id');

        $newDefinition = [
            'operator' => 'and',
            'conditions' => [
                ['field' => 'crm_contacts.country', 'op' => 'eq', 'value' => 'FR'],
            ],
        ];

        $response = $this->putJson("/api/v1/crm/segments/{$segmentId}", [
            'name' => 'Segment v2',
            'definition' => $newDefinition,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.version', 2);

        $this->assertDatabaseHas('crm_segment_versions', [
            'segment_id' => $segmentId,
            'version' => 1,
            'definition' => json_encode($this->validDefinition()),
        ]);
        $this->assertDatabaseHas('crm_segment_versions', [
            'segment_id' => $segmentId,
            'version' => 2,
        ]);
    }

    // ─── Rebuild / snapshot ─────────────────────────────────────────────────

    public function test_rebuild_replaces_computed_members_and_preserves_manual(): void
    {
        app()->instance('current_company', $this->companyA);

        /** @var CrmSegment $segment */
        $segment = CrmSegment::query()->create([
            'name' => 'Segment rebuild',
            'definition' => $this->validDefinition(),
            'version' => 1,
            'is_active' => true,
        ]);

        CrmSegmentMember::query()->create([
            'segment_id' => $segment->id,
            'contact_id' => 1,
            'source' => 'computed',
            'built_at' => now(),
        ]);
        CrmSegmentMember::query()->create([
            'segment_id' => $segment->id,
            'contact_id' => 99,
            'source' => 'manual',
            'built_at' => now(),
        ]);

        $this->app->instance(SegmentContactSourceInterface::class, $this->fakeSource([2, 3, 4]));

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson("/api/v1/crm/segments/{$segment->id}/rebuild");
        $response->assertStatus(200);

        // computed remplacés (1 → 2,3,4), manual (99) préservé.
        $this->assertDatabaseMissing('crm_segment_members', [
            'segment_id' => $segment->id,
            'contact_id' => 1,
        ]);
        foreach ([2, 3, 4] as $contactId) {
            $this->assertDatabaseHas('crm_segment_members', [
                'segment_id' => $segment->id,
                'contact_id' => $contactId,
                'source' => 'computed',
            ]);
        }
        $this->assertDatabaseHas('crm_segment_members', [
            'segment_id' => $segment->id,
            'contact_id' => 99,
            'source' => 'manual',
        ]);

        // Rebuild audité.
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'action' => 'segment.rebuilt',
            'auditable_type' => CrmSegment::class,
            'auditable_id' => $segment->id,
        ]);
    }

    public function test_rebuild_with_unavailable_source_returns_422(): void
    {
        app()->instance('current_company', $this->companyA);

        /** @var CrmSegment $segment */
        $segment = CrmSegment::query()->create([
            'name' => 'Segment sans source',
            'definition' => $this->validDefinition(),
            'version' => 1,
            'is_active' => true,
        ]);

        $this->app->instance(SegmentContactSourceInterface::class, $this->fakeSource([], false));

        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson("/api/v1/crm/segments/{$segment->id}/rebuild")->assertStatus(422);
    }

    public function test_members_endpoint_returns_paginated_members(): void
    {
        app()->instance('current_company', $this->companyA);

        /** @var CrmSegment $segment */
        $segment = CrmSegment::query()->create([
            'name' => 'Segment membres',
            'definition' => $this->validDefinition(),
            'version' => 1,
            'is_active' => true,
        ]);

        CrmSegmentMember::query()->create([
            'segment_id' => $segment->id,
            'contact_id' => 11,
            'source' => 'computed',
            'built_at' => now(),
        ]);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson("/api/v1/crm/segments/{$segment->id}/members");
        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.contact_id', 11);
        $response->assertJsonPath('data.0.source', 'computed');
    }

    // ─── RBAC ───────────────────────────────────────────────────────────────

    public function test_ordinary_employee_is_forbidden(): void
    {
        Sanctum::actingAs($this->ordinaryEmployee($this->companyA));

        $this->getJson('/api/v1/crm/segments')->assertStatus(403);
    }

    public function test_comptable_cannot_write_segments(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'comptable'));

        $this->postJson('/api/v1/crm/segments', [
            'name' => 'Interdit',
            'definition' => $this->validDefinition(),
        ])->assertStatus(403);
    }

    // ─── Isolation tenant ───────────────────────────────────────────────────

    public function test_cross_tenant_segment_is_404(): void
    {
        app()->instance('current_company', $this->companyB);

        /** @var CrmSegment $segmentB */
        $segmentB = CrmSegment::query()->create([
            'name' => 'Segment tenant B',
            'definition' => $this->validDefinition(),
            'version' => 1,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->manager($this->companyA));

        $this->getJson("/api/v1/crm/segments/{$segmentB->id}")->assertStatus(404);
        $this->putJson("/api/v1/crm/segments/{$segmentB->id}", [
            'name' => 'Piratage',
        ])->assertStatus(404);
        $this->deleteJson("/api/v1/crm/segments/{$segmentB->id}")->assertStatus(404);
    }

    public function test_destroy_removes_members_and_versions(): void
    {
        app()->instance('current_company', $this->companyA);

        /** @var CrmSegment $segment */
        $segment = CrmSegment::query()->create([
            'name' => 'Segment à supprimer',
            'definition' => $this->validDefinition(),
            'version' => 1,
            'is_active' => true,
        ]);
        CrmSegmentVersion::query()->create([
            'segment_id' => $segment->id,
            'version' => 1,
            'definition' => $this->validDefinition(),
            'changed_at' => now(),
        ]);
        CrmSegmentMember::query()->create([
            'segment_id' => $segment->id,
            'contact_id' => 5,
            'source' => 'manual',
            'built_at' => now(),
        ]);

        Sanctum::actingAs($this->manager($this->companyA));

        $this->deleteJson("/api/v1/crm/segments/{$segment->id}")->assertStatus(204);

        $this->assertDatabaseMissing('crm_segments', ['id' => $segment->id]);
        $this->assertDatabaseMissing('crm_segment_versions', ['segment_id' => $segment->id]);
        $this->assertDatabaseMissing('crm_segment_members', ['segment_id' => $segment->id]);
    }

    public function test_mutations_are_audited(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $create = $this->postJson('/api/v1/crm/segments', [
            'name' => 'Segment audité',
            'definition' => $this->validDefinition(),
        ]);
        $create->assertStatus(201);

        $segmentId = $create->json('data.id');

        $actions = AuditLog::query()
            ->where('module', 'crm')
            ->where('auditable_type', CrmSegment::class)
            ->where('auditable_id', $segmentId)
            ->orderBy('id')
            ->pluck('action')
            ->all();

        $this->assertSame(['segment.created'], $actions);
    }
}
