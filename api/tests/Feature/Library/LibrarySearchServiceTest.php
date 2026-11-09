<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Modules\Library\Domain\Models\LibraryCategory;
use App\Modules\Library\Domain\Models\LibraryResource;
use App\Modules\Library\Infrastructure\Services\LibrarySearchService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Leopardo Library — recherche et découverte (BC-40 LIBRARY).
 *
 * Couvre : recherche plein texte (titre/description/extrait/éditeur), filtres
 * catégorie/langue/accès/type, gratuit uniquement, exclusion des brouillons et
 * des contenus d'un tenant privé, et recommandations par catégorie/langue.
 */
class LibrarySearchServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private function service(): LibrarySearchService
    {
        return $this->app->make(LibrarySearchService::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function resource(array $overrides = []): LibraryResource
    {
        /** @var LibraryResource $resource */
        $resource = LibraryResource::query()->create(array_merge([
            'title' => 'Ressource',
            'slug' => 'ressource-'.uniqid(),
            'document_type' => 'book',
            'language' => 'fr',
            'access_model' => 'free',
            'status' => 'published',
            'license_id' => 'cc-by-4.0',
            'rights_holder' => 'Auteur',
            'allow_download' => true,
            'allow_redistribution' => true,
        ], $overrides));

        return $resource;
    }

    public function test_search_matches_title_case_insensitively(): void
    {
        $this->resource(['title' => 'Agriculture Durable', 'slug' => 'agri-durable']);
        $this->resource(['title' => 'Comptabilité', 'slug' => 'compta']);

        $results = $this->service()->search(['query' => 'agriculture']);

        $this->assertCount(1, $results);
        $this->assertSame('agri-durable', $results[0]->slug);
    }

    public function test_search_matches_description_and_publisher(): void
    {
        $this->resource([
            'title' => 'Manuel',
            'slug' => 'manuel-x',
            'description' => 'Un guide sur la mécanique agricole.',
        ]);
        $this->resource(['title' => 'Autre', 'slug' => 'autre-y', 'publisher' => 'Éditions Solaire']);

        $this->assertCount(1, $this->service()->search(['query' => 'mécanique']));
        $this->assertCount(1, $this->service()->search(['query' => 'solaire']));
    }

    public function test_search_excludes_drafts_and_archived(): void
    {
        $this->resource(['title' => 'Brouillon', 'slug' => 'brouillon', 'status' => 'draft']);
        $this->resource(['title' => 'Archivé', 'slug' => 'archive', 'status' => 'archived']);
        $this->resource(['title' => 'Publié', 'slug' => 'publie', 'status' => 'published']);

        $results = $this->service()->search([]);

        $this->assertCount(1, $results);
        $this->assertSame('publie', $results[0]->slug);
    }

    public function test_search_never_returns_tenant_private_content(): void
    {
        $this->resource(['title' => 'Public', 'slug' => 'public-1']);
        $this->resource([
            'title' => 'Privé tenant',
            'slug' => 'prive-tenant',
            'company_id' => '22222222-2222-4222-8222-222222222222',
            'access_model' => 'private',
        ]);

        $results = $this->service()->search([]);

        $this->assertCount(1, $results);
        $this->assertSame('public-1', $results[0]->slug);
    }

    public function test_filters_by_language_and_access_model(): void
    {
        $this->resource(['title' => 'FR gratuit', 'slug' => 'fr-free', 'language' => 'fr', 'access_model' => 'free']);
        $this->resource(['title' => 'EN payant', 'slug' => 'en-paid', 'language' => 'en', 'access_model' => 'purchase']);

        $this->assertCount(1, $this->service()->search(['language' => 'en']));
        $this->assertCount(1, $this->service()->search(['access' => 'purchase']));
        $this->assertCount(1, $this->service()->search(['free_only' => true]));
    }

    public function test_filters_by_category_slug(): void
    {
        /** @var LibraryCategory $category */
        $category = LibraryCategory::query()->create([
            'name' => 'Informatique',
            'slug' => 'informatique',
            'position' => 0,
        ]);

        $this->resource(['title' => 'IA', 'slug' => 'ia-1', 'category_id' => $category->id]);
        $this->resource(['title' => 'Autre', 'slug' => 'autre-1']);

        $results = $this->service()->search(['category' => 'informatique']);

        $this->assertCount(1, $results);
        $this->assertSame('ia-1', $results[0]->slug);
    }

    public function test_related_prefers_same_category(): void
    {
        /** @var LibraryCategory $category */
        $category = LibraryCategory::query()->create([
            'name' => 'Sciences',
            'slug' => 'sciences',
            'position' => 0,
        ]);

        $current = $this->resource(['title' => 'Courant', 'slug' => 'courant', 'category_id' => $category->id]);
        $this->resource(['title' => 'Même catégorie', 'slug' => 'meme-cat', 'category_id' => $category->id]);
        $this->resource(['title' => 'Autre catégorie', 'slug' => 'autre-cat']);

        $related = $this->service()->related($current);

        $this->assertNotEmpty($related);
        $this->assertSame('meme-cat', $related[0]->slug);
    }

    public function test_short_query_is_ignored(): void
    {
        $this->resource(['title' => 'Alpha', 'slug' => 'alpha']);
        $this->resource(['title' => 'Beta', 'slug' => 'beta']);

        // Requête d'un caractère < search_min_length (2) : aucun filtrage texte.
        $results = $this->service()->search(['query' => 'a']);

        $this->assertCount(2, $results);
    }
}
