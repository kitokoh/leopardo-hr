<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Modules\Library\Domain\Enums\LibraryAccessModel;
use App\Modules\Library\Domain\Enums\LibraryResourceStatus;
use App\Modules\Library\Domain\Models\LibraryCategory;
use App\Modules\Library\Domain\Models\LibraryResource;
use App\Modules\Library\Domain\Support\LibraryAccessPolicy;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Leopardo Library — politique d'accès (VÉRIFICATION CÔTÉ SERVEUR).
 *
 * Couvre : lecture/téléchargement par modèle d'accès, refus de téléchargement
 * non autorisé, achat, abonnement, isolation tenant (contenu privé).
 */
class LibraryAccessPolicyTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function resource(array $overrides = []): LibraryResource
    {
        /** @var LibraryResource $resource */
        $resource = new LibraryResource(array_merge([
            'title' => 'Ressource test',
            'slug' => 'ressource-test',
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

    public function test_free_resource_is_readable_and_downloadable(): void
    {
        $policy = new LibraryAccessPolicy;
        $resource = $this->resource(['access_model' => 'free']);

        $this->assertTrue($policy->canRead($resource));
        $this->assertTrue($policy->canDownload($resource));
        $this->assertTrue($policy->isVisible($resource));
    }

    public function test_free_resource_without_download_license_cannot_download(): void
    {
        $policy = new LibraryAccessPolicy;
        $resource = $this->resource(['access_model' => 'free', 'allow_download' => false]);

        $this->assertTrue($policy->canRead($resource));
        $this->assertFalse($policy->canDownload($resource));
    }

    public function test_read_only_resource_can_never_download(): void
    {
        $policy = new LibraryAccessPolicy;
        $resource = $this->resource(['access_model' => 'read_only']);

        $this->assertTrue($policy->canRead($resource));
        $this->assertFalse($policy->canDownload($resource));
    }

    public function test_purchase_requires_purchase_before_reading(): void
    {
        $resource = $this->resource(['access_model' => 'purchase']);

        $this->assertFalse((new LibraryAccessPolicy)->canRead($resource));
        $this->assertTrue((new LibraryAccessPolicy(purchased: true))->canRead($resource));
        $this->assertTrue((new LibraryAccessPolicy(purchased: true))->canDownload($resource));
    }

    public function test_subscription_requires_active_subscription(): void
    {
        $resource = $this->resource(['access_model' => 'subscription']);

        $this->assertFalse((new LibraryAccessPolicy)->canRead($resource));
        $this->assertTrue((new LibraryAccessPolicy(subscribed: true))->canRead($resource));
    }

    public function test_private_resource_is_isolated_to_its_tenant(): void
    {
        $resource = $this->resource(['access_model' => 'private', 'company_id' => 'tenant-a']);

        // Anonyme : invisible.
        $this->assertFalse((new LibraryAccessPolicy)->isVisible($resource));
        // Autre tenant : invisible.
        $this->assertFalse((new LibraryAccessPolicy('tenant-b'))->isVisible($resource));
        // Tenant propriétaire : visible et lisible.
        $this->assertTrue((new LibraryAccessPolicy('tenant-a'))->isVisible($resource));
        $this->assertTrue((new LibraryAccessPolicy('tenant-a'))->canRead($resource));
    }

    public function test_unpublished_resource_is_never_visible(): void
    {
        $policy = new LibraryAccessPolicy;
        $resource = $this->resource(['status' => 'draft']);

        $this->assertFalse($policy->isVisible($resource));
    }

    public function test_access_model_enum_publicly_readable(): void
    {
        $this->assertTrue(LibraryAccessModel::Free->isPubliclyReadable());
        $this->assertTrue(LibraryAccessModel::ReadOnly->isPubliclyReadable());
        $this->assertFalse(LibraryAccessModel::Purchase->isPubliclyReadable());
        $this->assertFalse(LibraryAccessModel::Private->isPubliclyReadable());
    }

    public function test_status_enum_publicly_visible(): void
    {
        $this->assertTrue(LibraryResourceStatus::Published->isPubliclyVisible());
        $this->assertFalse(LibraryResourceStatus::Draft->isPubliclyVisible());
        $this->assertFalse(LibraryResourceStatus::Archived->isPubliclyVisible());
    }
}
