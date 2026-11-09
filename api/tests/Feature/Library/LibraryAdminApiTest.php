<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Library\Domain\Models\LibraryResource;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Leopardo Library — API d'administration (BC-40 LIBRARY).
 *
 * Couvre la surface TENANT du back-office :
 *  - pagination et métadonnées (`meta`) ;
 *  - filtres allowlistés (statut, modèle d'accès, type, langue, recherche) ;
 *  - refus d'un filtre hors registre (422) ;
 *  - RBAC : un employé ne peut pas créer / modifier / publier ;
 *  - isolation tenant : un manager ne voit ni ne modifie la ressource d'un
 *    autre tenant (404 sur la route scopée, jamais de fuite) ;
 *  - transitions éditoriales (publish / unpublish / archive) ;
 *  - upload : 501 quand le stockage n'est pas configuré, 422 sans fichier.
 *
 * Les assertions portent sur le STATUT HTTP et la forme de la réponse, pas sur
 * des messages localisés (instables).
 */
class LibraryAdminApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $slug): Company
    {
        return Company::query()->create([
            'name' => 'Company '.$slug,
            'slug' => $slug,
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => $slug.'@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'plan_id' => 1,
            'subscription_start' => now()->subMonth()->toDateString(),
            'subscription_end' => now()->addYear()->toDateString(),
            'language' => 'fr',
            'timezone' => 'UTC',
            'currency' => 'DZD',
        ]);
    }

    private function manager(Company $company): Employee
    {
        return Employee::factory()->manager()->create(['company_id' => $company->id]);
    }

    private function employee(Company $company): Employee
    {
        return Employee::factory()->create(['company_id' => $company->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function resource(Company $company, array $overrides = []): LibraryResource
    {
        /** @var LibraryResource $resource */
        $resource = LibraryResource::query()->create(array_merge([
            'company_id' => $company->id,
            'title' => 'Ressource '.uniqid(),
            'slug' => 'ressource-'.uniqid(),
            'document_type' => 'book',
            'language' => 'fr',
            'access_model' => 'free',
            'status' => 'draft',
            'license_id' => 'cc-by-4.0',
            'rights_holder' => 'Test',
            'allow_download' => true,
            'allow_redistribution' => true,
        ], $overrides));

        return $resource;
    }

    public function test_index_returns_paginated_payload_with_meta(): void
    {
        $company = $this->company('lib-page');
        $manager = $this->manager($company);

        for ($i = 0; $i < 3; $i++) {
            $this->resource($company, ['title' => 'Livre '.$i, 'slug' => 'livre-'.$i]);
        }

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/library/resources?per_page=2&page=1');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.per_page', 2);
        $response->assertJsonPath('meta.total', 3);
        $response->assertJsonPath('meta.last_page', 2);
    }

    public function test_index_filters_by_status(): void
    {
        $company = $this->company('lib-status');
        $manager = $this->manager($company);

        $this->resource($company, ['status' => 'draft', 'slug' => 'draft-1']);
        $this->resource($company, ['status' => 'published', 'slug' => 'pub-1']);
        $this->resource($company, ['status' => 'published', 'slug' => 'pub-2']);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/library/resources?status=published');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.total', 2);
    }

    public function test_index_filters_by_access_model_and_document_type(): void
    {
        $company = $this->company('lib-access');
        $manager = $this->manager($company);

        $this->resource($company, ['access_model' => 'purchase', 'document_type' => 'ebook', 'slug' => 'a']);
        $this->resource($company, ['access_model' => 'free', 'document_type' => 'book', 'slug' => 'b']);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/library/resources?access_model=purchase')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/library/resources?document_type=book')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_search_matches_title(): void
    {
        $company = $this->company('lib-search');
        $manager = $this->manager($company);

        $this->resource($company, ['title' => 'Guide du développeur', 'slug' => 'guide-dev']);
        $this->resource($company, ['title' => 'Cuisine rapide', 'slug' => 'cuisine']);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/library/resources?q=développeur');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Guide du développeur');
    }

    public function test_index_rejects_unknown_status_filter(): void
    {
        $company = $this->company('lib-badfilter');
        $manager = $this->manager($company);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/library/resources?status=not_a_status')
            ->assertStatus(422);
    }

    public function test_index_never_leaks_another_tenant_resources(): void
    {
        $companyA = $this->company('lib-tenant-a');
        $companyB = $this->company('lib-tenant-b');

        $this->resource($companyA, ['title' => 'Secret A', 'slug' => 'secret-a']);
        $this->resource($companyB, ['title' => 'Secret B', 'slug' => 'secret-b']);

        Sanctum::actingAs($this->manager($companyA));

        $response = $this->getJson('/api/v1/library/resources');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Secret A');
    }

    public function test_employee_cannot_create_resource(): void
    {
        $company = $this->company('lib-rbac-create');
        Sanctum::actingAs($this->employee($company));

        $this->postJson('/api/v1/library/resources', [
            'title' => 'Tentative',
            'document_type' => 'book',
            'language' => 'fr',
            'access_model' => 'free',
        ])->assertStatus(403);
    }

    public function test_manager_can_create_draft_resource(): void
    {
        $company = $this->company('lib-create-ok');
        Sanctum::actingAs($this->manager($company));

        $response = $this->postJson('/api/v1/library/resources', [
            'title' => 'Nouvelle ressource',
            'document_type' => 'guide',
            'language' => 'fr',
            'access_model' => 'free',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.title', 'Nouvelle ressource');
    }

    public function test_manager_cannot_update_another_tenant_resource(): void
    {
        $companyA = $this->company('lib-upd-a');
        $companyB = $this->company('lib-upd-b');

        $foreign = $this->resource($companyB, ['slug' => 'foreign-res']);

        Sanctum::actingAs($this->manager($companyA));

        // Refus sûr : la ressource d'un autre tenant n'est jamais modifiable.
        // La route est scopée par `BelongsToCompany`, donc la ressource d'un
        // autre tenant n'est pas résolue : 404 déterministe (garde #5585).
        $this->putJson('/api/v1/library/resources/'.$foreign->id, ['title' => 'Piraté'])
            ->assertStatus(404);

        $this->assertSame(
            $foreign->title,
            $foreign->fresh()?->title,
            'La ressource d\'un autre tenant ne doit jamais être modifiée.',
        );
    }

    public function test_publish_transition_sets_status_and_timestamp(): void
    {
        $company = $this->company('lib-publish');
        $manager = $this->manager($company);
        $resource = $this->resource($company, ['slug' => 'to-publish']);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/library/resources/'.$resource->id.'/publish');

        $response->assertOk();
        $response->assertJsonPath('data.status', 'published');
        $this->assertNotNull($response->json('data.published_at'));
    }

    public function test_unpublish_and_archive_transitions(): void
    {
        $company = $this->company('lib-transitions');
        $manager = $this->manager($company);
        $resource = $this->resource($company, ['status' => 'published', 'slug' => 'trans']);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/library/resources/'.$resource->id.'/unpublish')
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->postJson('/api/v1/library/resources/'.$resource->id.'/archive')
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');
    }

    public function test_employee_cannot_publish(): void
    {
        $company = $this->company('lib-rbac-publish');
        $resource = $this->resource($company, ['slug' => 'no-publish']);

        Sanctum::actingAs($this->employee($company));

        $this->postJson('/api/v1/library/resources/'.$resource->id.'/publish')
            ->assertStatus(403);
    }

    public function test_upload_returns_501_when_storage_not_configured(): void
    {
        $company = $this->company('lib-upload-501');
        $manager = $this->manager($company);
        $resource = $this->resource($company, ['slug' => 'upload-501']);

        // Stockage objet déclaré mais SANS bucket ni credentials : c'est la
        // définition exacte de « non configuré » pour `ObjectStorageLibraryFiles`
        // (un disque local, lui, est toujours disponible). Le service doit
        // refuser explicitement plutôt que d'écrire un faux fichier. On envoie
        // un fichier VALIDE pour franchir la FormRequest et atteindre la garde.
        config([
            'library.storage_driver' => 's3',
            'filesystems.disks.library' => [
                'driver' => 's3',
                'bucket' => '',
                'key' => '',
                'secret' => '',
                'region' => 'eu-west-3',
            ],
        ]);

        Sanctum::actingAs($manager);

        $file = UploadedFile::fake()->createWithContent('doc.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");

        $this->postJson('/api/v1/library/resources/'.$resource->id.'/file', ['file' => $file])
            ->assertStatus(501)
            ->assertJsonPath('error', 'storage_not_configured');
    }

    public function test_upload_returns_422_without_file(): void
    {
        $company = $this->company('lib-upload-422');
        $manager = $this->manager($company);
        $resource = $this->resource($company, ['slug' => 'upload-422']);

        // Stockage « configuré » pour atteindre la validation du fichier.
        config([
            'filesystems.disks.library' => [
                'driver' => 'local',
                'root' => storage_path('app/library-test'),
            ],
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/library/resources/'.$resource->id.'/file')
            ->assertStatus(422);
    }
}
