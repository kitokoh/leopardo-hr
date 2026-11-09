<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Library\Application\Actions\RecordLibraryReadingProgress;
use App\Modules\Library\Domain\Models\LibraryReadingProgress;
use App\Modules\Library\Domain\Models\LibraryResource;
use App\Modules\Library\Domain\Policies\LibraryReadingProgressPolicy;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Leopardo Library — progression de lecture persistée côté serveur.
 *
 * Couvre : bornage de la page, idempotence (une seule ligne par employé et
 * par ressource), isolation tenant réelle (deux compagnies), et la policy
 * « propriétaire seul ».
 *
 * ⚠️ Les tests s'exécutent sous un VRAI contexte tenant (`current_company`
 * lié), comme en production : c'est ce qui fait fonctionner le scope
 * `BelongsToCompany` et le remplissage automatique de `company_id`.
 */
class LibraryReadingProgressTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $otherCompany;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->otherCompany = $other;

        app()->instance('current_company', $this->company);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current_company');
        app()->forgetInstance('tenant_scope_required');

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function resource(array $overrides = []): LibraryResource
    {
        /** @var LibraryResource $resource */
        $resource = LibraryResource::query()->create(array_merge([
            'title' => 'Ressource progression',
            'slug' => 'ressource-progression',
            'document_type' => 'book',
            'language' => 'fr',
            'access_model' => 'free',
            'status' => 'published',
            'license_id' => 'cc-by-4.0',
            'rights_holder' => 'Test',
            'allow_download' => true,
            'allow_redistribution' => true,
        ], $overrides));

        return $resource;
    }

    private function action(): RecordLibraryReadingProgress
    {
        return app(RecordLibraryReadingProgress::class);
    }

    public function test_recording_progress_creates_a_row_in_the_current_tenant(): void
    {
        $resource = $this->resource();

        $progress = $this->action()->execute($resource, 42, 7, 20);

        $this->assertSame(7, $progress->page);
        $this->assertSame(20, $progress->total_pages);
        $this->assertSame(42, $progress->employee_id);
        $this->assertSame((int) $resource->id, $progress->resource_id);
        // Le tenant est forcé depuis le contexte, jamais depuis le payload.
        $this->assertSame((string) $this->company->id, (string) $progress->company_id);
    }

    public function test_recording_progress_is_idempotent(): void
    {
        $resource = $this->resource();

        $this->action()->execute($resource, 42, 3, 20);
        $this->action()->execute($resource, 42, 11, 20);

        $rows = LibraryReadingProgress::query()
            ->where('employee_id', 42)
            ->where('resource_id', (int) $resource->id)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame(11, $rows->firstOrFail()->page);
    }

    public function test_page_is_clamped_to_the_document_bounds(): void
    {
        $resource = $this->resource();

        $this->assertSame(1, $this->action()->execute($resource, 1, 0, 20)->page);
        $this->assertSame(1, $this->action()->execute($resource, 2, -5, 20)->page);
        $this->assertSame(20, $this->action()->execute($resource, 3, 999, 20)->page);
        // Total inconnu : la page n'est pas plafonnée, seulement plancher à 1.
        $this->assertSame(999, $this->action()->execute($resource, 4, 999, null)->page);
    }

    public function test_progress_is_isolated_per_employee(): void
    {
        $resource = $this->resource();

        $this->action()->execute($resource, 1, 5, 20);
        $this->action()->execute($resource, 2, 15, 20);

        $this->assertSame(5, LibraryReadingProgress::query()->where('employee_id', 1)->firstOrFail()->page);
        $this->assertSame(15, LibraryReadingProgress::query()->where('employee_id', 2)->firstOrFail()->page);
    }

    public function test_progress_is_isolated_per_tenant(): void
    {
        $resource = $this->resource();
        $this->action()->execute($resource, 42, 7, 20);

        // Bascule sur l'autre compagnie : la progression du premier tenant
        // doit être invisible (scope `BelongsToCompany`).
        app()->instance('current_company', $this->otherCompany);

        $this->assertSame(0, LibraryReadingProgress::query()->count());
    }

    public function test_policy_allows_only_the_owner(): void
    {
        $resource = $this->resource();
        $progress = $this->action()->execute($resource, 42, 7, 20);

        $policy = new LibraryReadingProgressPolicy;

        $owner = new Employee;
        $owner->id = 42;
        $owner->company_id = $progress->company_id;

        $other = new Employee;
        $other->id = 43;
        $other->company_id = $progress->company_id;

        $this->assertTrue($policy->view($owner, $progress));
        $this->assertTrue($policy->update($owner, $progress));
        $this->assertFalse($policy->view($other, $progress));
        $this->assertFalse($policy->update($other, $progress));
    }

    public function test_policy_refuses_a_different_tenant(): void
    {
        $resource = $this->resource();
        $progress = $this->action()->execute($resource, 42, 7, 20);

        $policy = new LibraryReadingProgressPolicy;

        $foreigner = new Employee;
        $foreigner->id = 42; // même identifiant, autre tenant
        $foreigner->company_id = (string) $this->otherCompany->id;

        $this->assertFalse($policy->view($foreigner, $progress));
        $this->assertFalse($policy->update($foreigner, $progress));
    }
}
