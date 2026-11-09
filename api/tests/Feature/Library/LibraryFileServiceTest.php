<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Modules\Library\Domain\Contracts\LibraryFileStorage;
use App\Modules\Library\Domain\Models\LibraryResource;
use App\Modules\Library\Infrastructure\Services\LibraryFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Leopardo Library — stockage des fichiers (BC-40 LIBRARY).
 *
 * Couvre : validation MIME réelle, limite de taille, clé de stockage isolée
 * par tenant (anti path-traversal), remplacement d'un fichier existant,
 * résolution (URL présignée ou flux), et refus explicite quand le stockage
 * n'est pas configuré.
 *
 * Le disque `library` est remplacé par un disque local en mémoire
 * (`Storage::fake`) : aucun fichier réel n'est écrit sur le disque du runner.
 */
class LibraryFileServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function resource(array $overrides = []): LibraryResource
    {
        /** @var LibraryResource $resource */
        $resource = LibraryResource::query()->create(array_merge([
            'title' => 'Ressource fichier',
            'slug' => 'ressource-fichier',
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

    private function service(): LibraryFileService
    {
        return $this->app->make(LibraryFileService::class);
    }

    public function test_upload_stores_file_with_tenant_isolated_key(): void
    {
        Storage::fake('library');
        config(['library.storage_driver' => 'local', 'library.storage_disk' => 'library']);

        $resource = $this->resource();
        $file = UploadedFile::fake()->create('livre.pdf', 120, 'application/pdf');

        $updated = $this->service()->attach($resource, $file);

        $this->assertNotNull($updated->file_key);
        $this->assertSame('pdf', $updated->file_format);
        // Clé isolée : library/public/{id}/{uuid}.pdf (catalogue public).
        $this->assertStringStartsWith('library/public/'.$resource->id.'/', (string) $updated->file_key);
        $this->assertStringEndsWith('.pdf', (string) $updated->file_key);
        Storage::disk('library')->assertExists((string) $updated->file_key);
    }

    public function test_upload_key_is_isolated_per_tenant(): void
    {
        Storage::fake('library');
        config(['library.storage_driver' => 'local', 'library.storage_disk' => 'library']);

        $resource = $this->resource(['company_id' => '11111111-1111-4111-8111-111111111111']);
        $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');

        $updated = $this->service()->attach($resource, $file);

        $this->assertStringStartsWith(
            'library/11111111-1111-4111-8111-111111111111/'.$resource->id.'/',
            (string) $updated->file_key,
        );
    }

    public function test_upload_rejects_disallowed_mime_type(): void
    {
        Storage::fake('library');
        config(['library.storage_driver' => 'local', 'library.storage_disk' => 'library']);

        $resource = $this->resource();
        // Un exécutable déguisé : le MIME réel n'est pas dans la liste blanche.
        $file = UploadedFile::fake()->create('payload.exe', 10, 'application/x-msdownload');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('library_file_type_not_allowed');

        $this->service()->attach($resource, $file);
    }

    public function test_upload_rejects_file_over_size_limit(): void
    {
        Storage::fake('library');
        config([
            'library.storage_driver' => 'local',
            'library.storage_disk' => 'library',
            'library.max_upload_mb' => 1,
        ]);

        $resource = $this->resource();
        // 2 Mo > limite de 1 Mo.
        $file = UploadedFile::fake()->create('gros.pdf', 2048, 'application/pdf');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('library_file_too_large');

        $this->service()->attach($resource, $file);
    }

    public function test_replacing_a_file_deletes_the_previous_one(): void
    {
        Storage::fake('library');
        config(['library.storage_driver' => 'local', 'library.storage_disk' => 'library']);

        $resource = $this->resource();
        $first = $this->service()->attach(
            $resource,
            UploadedFile::fake()->create('v1.pdf', 10, 'application/pdf'),
        );
        $firstKey = (string) $first->file_key;

        $second = $this->service()->attach(
            $first,
            UploadedFile::fake()->create('v2.pdf', 10, 'application/pdf'),
        );

        $this->assertNotSame($firstKey, $second->file_key);
        Storage::disk('library')->assertMissing($firstKey);
        Storage::disk('library')->assertExists((string) $second->file_key);
    }

    public function test_resolve_returns_stream_for_local_driver(): void
    {
        Storage::fake('library');
        config(['library.storage_driver' => 'local', 'library.storage_disk' => 'library']);

        $resource = $this->resource();
        $updated = $this->service()->attach(
            $resource,
            UploadedFile::fake()->create('livre.pdf', 10, 'application/pdf'),
        );

        $resolved = $this->service()->resolve($updated, 60);

        // Disque local : pas d'URL présignée, mais un flux lisible.
        $this->assertNull($resolved['url']);
        $this->assertIsResource($resolved['stream']);
        $this->assertSame('pdf', $resolved['format']);
    }

    public function test_resolve_returns_nothing_when_no_file(): void
    {
        $resource = $this->resource();

        $resolved = $this->service()->resolve($resource, 60);

        $this->assertNull($resolved['url']);
        $this->assertNull($resolved['stream']);
    }

    public function test_storage_contract_is_bound_in_container(): void
    {
        $this->assertInstanceOf(
            LibraryFileStorage::class,
            $this->app->make(LibraryFileStorage::class),
        );
    }
}
