<?php

declare(strict_types=1);

namespace Tests\Feature\Showcase;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\ShowcaseMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-27 SHOWCASE (V-MEDIA #6872) — API privée des médias (upload / liste /
 * suppression), service public (vitrine publiée uniquement) et gardes :
 * type/poids refusés, SVG actif refusé, isolation tenant, RBAC, feature flag
 * et non-fuite des champs internes (disk / path / company_id).
 */
class ShowcaseMediaApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $slug = 'acme-industries'): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'slug' => $slug]);
        $company->setFeature('company_showcase', true);
        $company->save();

        return $company;
    }

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function employee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function showcase(Company $company): CompanyShowcase
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company): CompanyShowcase {
            /** @var CompanyShowcase $showcase */
            $showcase = CompanyShowcase::query()->create([
                'company_id' => $company->id,
                'slug' => $company->slug,
                'status' => CompanyShowcaseStatus::Draft,
                'theme' => 'default',
            ]);

            return $showcase;
        });
    }

    /**
     * Fichier SVG réel avec un type MIME explicite (le contenu doit être
     * inspecté par la garde anti-contenu-actif avant stockage).
     */
    private function svgUpload(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'showcase_svg_');

        if ($path === false) {
            throw new \RuntimeException('Temporary file could not be created.');
        }

        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, 'image/svg+xml', null, true);
    }

    private function heroSectionId(): int
    {
        /** @var int $id */
        $id = $this->postJson('/api/v1/showcase/sections', [
            'type' => 'hero',
            'content' => ['heading' => 'Acme Industries'],
        ])->assertStatus(201)->json('data.id');

        return $id;
    }

    public function test_principal_uploads_logo_and_section_image_then_lists_and_deletes_them(): void
    {
        Storage::fake('local');

        $company = $this->company();
        $this->principal($company);

        $this->postJson('/api/v1/showcase')->assertStatus(201);
        $sectionId = $this->heroSectionId();

        $logo = $this->postJson('/api/v1/showcase/media', [
            'kind' => 'logo',
            'file' => UploadedFile::fake()->create('Logo Entreprise.PNG', 120, 'image/png'),
        ]);

        $logo->assertStatus(201)
            ->assertJsonPath('data.kind', 'logo')
            ->assertJsonPath('data.section_id', null)
            ->assertJsonPath('data.original_name', 'Logo-Entreprise.png');

        // Non-fuite : jamais de champ interne de stockage dans le DTO privé.
        $logoPayload = $logo->json('data');
        foreach (['disk', 'path', 'company_id'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $logoPayload);
        }

        $logoUrl = $logo->json('data.url');
        $this->assertIsString($logoUrl);
        $this->assertStringContainsString('/public/vitrine/'.$company->slug.'/media/', $logoUrl);
        $logoId = (int) $logo->json('data.id');

        // Image de section : lien par id stable (section existante).
        $this->postJson('/api/v1/showcase/media', [
            'kind' => 'section',
            'section_id' => $sectionId,
            'file' => UploadedFile::fake()->create('atelier.jpg', 240, 'image/jpeg'),
        ])->assertStatus(201)
            ->assertJsonPath('data.kind', 'section')
            ->assertJsonPath('data.section_id', $sectionId);

        // Logo référencé par id stable dans la vitrine (jamais un chemin).
        $this->getJson('/api/v1/showcase')
            ->assertOk()
            ->assertJsonPath('data.settings.logo_id', $logoId);

        // Liste complète puis filtrée (kind / section_id).
        $list = $this->getJson('/api/v1/showcase/media')->assertOk()->assertJsonCount(2, 'data')->json('data');
        foreach ($list as $item) {
            foreach (['disk', 'path', 'company_id'] as $forbidden) {
                $this->assertArrayNotHasKey($forbidden, $item);
            }
        }

        $this->getJson('/api/v1/showcase/media?kind=logo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $logoId);

        $this->getJson('/api/v1/showcase/media?kind=section&section_id='.$sectionId)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Le binaire est bien stocké hors webroot (disk existant `local`).
        $stored = app(TenantManager::class)->withinTenant($company, function () use ($logoId): ShowcaseMedia {
            /** @var ShowcaseMedia $media */
            $media = ShowcaseMedia::query()->whereKey($logoId)->firstOrFail();

            return $media;
        });
        Storage::disk('local')->assertExists($stored->path);

        // Suppression par id stable : ligne + référence logo retirées.
        $this->deleteJson('/api/v1/showcase/media/'.$logoId)->assertStatus(204);
        $this->getJson('/api/v1/showcase/media')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/showcase')->assertOk()->assertJsonMissingPath('data.settings.logo_id');

        $remaining = app(TenantManager::class)->withinTenant($company, function () use ($logoId): ?ShowcaseMedia {
            /** @var ShowcaseMedia|null $media */
            $media = ShowcaseMedia::query()->whereKey($logoId)->first();

            return $media;
        });
        $this->assertNull($remaining);
    }

    public function test_upload_rejects_invalid_type_size_and_section_target(): void
    {
        Storage::fake('local');

        $company = $this->company();
        $this->principal($company);
        $this->postJson('/api/v1/showcase')->assertStatus(201);
        $sectionId = $this->heroSectionId();

        // Type de fichier non autorisé pour un logo.
        $this->postJson('/api/v1/showcase/media', [
            'kind' => 'logo',
            'file' => UploadedFile::fake()->create('brochure.pdf', 40, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');

        // Le SVG n'est autorisé que pour le logo, jamais pour une section.
        $this->postJson('/api/v1/showcase/media', [
            'kind' => 'section',
            'section_id' => $sectionId,
            'file' => UploadedFile::fake()->create('schema.svg', 20, 'image/svg+xml'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');

        // Poids au-delà de la limite logo (2 Mo).
        $this->postJson('/api/v1/showcase/media', [
            'kind' => 'logo',
            'file' => UploadedFile::fake()->create('logo.png', 2049, 'image/png'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');

        // Type de média inconnu.
        $this->postJson('/api/v1/showcase/media', [
            'kind' => 'avatar',
            'file' => UploadedFile::fake()->create('logo.png', 10, 'image/png'),
        ])->assertStatus(422)->assertJsonValidationErrors('kind');

        // Image de section sans cible.
        $this->postJson('/api/v1/showcase/media', [
            'kind' => 'section',
            'file' => UploadedFile::fake()->create('photo.png', 10, 'image/png'),
        ])->assertStatus(422)->assertJsonValidationErrors('section_id');

        // Cible inconnue.
        $this->postJson('/api/v1/showcase/media', [
            'kind' => 'section',
            'section_id' => 999_999,
            'file' => UploadedFile::fake()->create('photo.png', 10, 'image/png'),
        ])->assertStatus(422)->assertJsonValidationErrors('section_id');

        // Le logo ne peut pas être rattaché à une section.
        $this->postJson('/api/v1/showcase/media', [
            'kind' => 'logo',
            'section_id' => $sectionId,
            'file' => UploadedFile::fake()->create('logo.png', 10, 'image/png'),
        ])->assertStatus(422)->assertJsonValidationErrors('section_id');
    }

    public function test_unsafe_svg_logo_is_rejected_and_safe_svg_is_served_sandboxed(): void
    {
        Storage::fake('local');

        $company = $this->company();
        $this->principal($company);
        $this->postJson('/api/v1/showcase')->assertStatus(201);
        $this->heroSectionId();

        $unsafe = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $this->postJson('/api/v1/showcase/media', [
            'kind' => 'logo',
            'file' => $this->svgUpload('logo.svg', $unsafe),
        ])->assertStatus(422)->assertJsonValidationErrors('file');

        $safe = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10" fill="#0af"/></svg>';

        $logoId = (int) $this->postJson('/api/v1/showcase/media', [
            'kind' => 'logo',
            'file' => $this->svgUpload('logo.svg', $safe),
        ])->assertStatus(201)->json('data.id');

        $this->postJson('/api/v1/showcase/publish')->assertOk();

        $this->get('/api/v1/public/vitrine/'.$company->slug.'/media/'.$logoId)
            ->assertOk()
            ->assertHeader('content-type', 'image/svg+xml')
            ->assertHeader('content-security-policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
    }

    public function test_media_is_tenant_scoped_and_role_gated(): void
    {
        Storage::fake('local');

        $companyA = $this->company('tenant-a');
        $companyB = $this->company('tenant-b');
        $this->showcase($companyA);

        // Tenant B : vitrine + section + média via le parcours réel.
        $this->principal($companyB);
        $this->postJson('/api/v1/showcase')->assertStatus(201);
        $sectionBId = $this->heroSectionId();
        $mediaBId = (int) $this->postJson('/api/v1/showcase/media', [
            'kind' => 'section',
            'section_id' => $sectionBId,
            'file' => UploadedFile::fake()->create('b.png', 10, 'image/png'),
        ])->assertStatus(201)->json('data.id');

        // Tenant A : le média de B est introuvable (liste vide, delete 404).
        $this->principal($companyA);
        $this->getJson('/api/v1/showcase/media')->assertOk()->assertJsonCount(0, 'data');
        $this->deleteJson('/api/v1/showcase/media/'.$mediaBId)->assertStatus(404);

        // Une section d'un autre tenant n'est jamais une cible d'upload.
        $this->postJson('/api/v1/showcase/media', [
            'kind' => 'section',
            'section_id' => $sectionBId,
            'file' => UploadedFile::fake()->create('a.png', 10, 'image/png'),
        ])->assertStatus(422)->assertJsonValidationErrors('section_id');

        // Rôle employé : gestion des médias refusée.
        $this->employee($companyA);
        $this->getJson('/api/v1/showcase/media')->assertStatus(403);
        $this->postJson('/api/v1/showcase/media', [
            'kind' => 'logo',
            'file' => UploadedFile::fake()->create('logo.png', 10, 'image/png'),
        ])->assertStatus(403);
    }

    public function test_feature_flag_disabled_blocks_media_routes(): void
    {
        Storage::fake('local');

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']); // flag absent
        $this->showcase($company);
        $this->principal($company);

        $this->getJson('/api/v1/showcase/media')->assertStatus(403)
            ->assertJsonPath('error', 'FEATURE_NOT_ENABLED');

        $this->postJson('/api/v1/showcase/media', [
            'kind' => 'logo',
            'file' => UploadedFile::fake()->create('logo.png', 10, 'image/png'),
        ])->assertStatus(403);
    }

    public function test_public_media_is_served_only_for_published_showcase(): void
    {
        Storage::fake('local');

        $company = $this->company();
        $this->principal($company);
        $this->postJson('/api/v1/showcase')->assertStatus(201);
        $this->heroSectionId();

        $logoId = (int) $this->postJson('/api/v1/showcase/media', [
            'kind' => 'logo',
            'file' => UploadedFile::fake()->create('logo.png', 32, 'image/png'),
        ])->assertStatus(201)->json('data.id');

        $publicPath = '/api/v1/public/vitrine/'.$company->slug.'/media/'.$logoId;

        // Brouillon sans jeton → jamais servi.
        $this->get($publicPath)->assertStatus(404);

        // Aperçu privé : jeton valide → servi sans cache partagé, non indexable.
        $token = (string) $this->postJson('/api/v1/showcase/preview-token')->assertOk()->json('data.preview_token');

        $this->get($publicPath.'?token='.$token)
            ->assertOk()
            ->assertHeader('cache-control', 'private, no-store, max-age=0')
            ->assertHeader('x-robots-tag', 'noindex, nofollow')
            ->assertHeader('content-type', 'image/png');

        // Publication → média public mis en cache longuement.
        $this->postJson('/api/v1/showcase/publish')->assertOk();

        $published = $this->get($publicPath);

        $published->assertOk()
            ->assertHeader('cache-control', 'public, max-age=86400')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('content-type', 'image/png');

        $this->assertNotSame('', $published->getContent());

        // Id inconnu / slug inconnu → 404.
        $this->get('/api/v1/public/vitrine/'.$company->slug.'/media/999999')->assertStatus(404);
        $this->get('/api/v1/public/vitrine/unknown-slug/media/'.$logoId)->assertStatus(404);
    }

    public function test_public_media_of_another_showcase_is_never_addressable(): void
    {
        Storage::fake('local');

        $companyA = $this->company('vitrine-a');
        $companyB = $this->company('vitrine-b');
        $this->showcase($companyA);

        // Tenant B publie sa vitrine avec un média.
        $this->principal($companyB);
        $this->postJson('/api/v1/showcase')->assertStatus(201);
        $this->heroSectionId();
        $mediaBId = (int) $this->postJson('/api/v1/showcase/media', [
            'kind' => 'logo',
            'file' => UploadedFile::fake()->create('logo.png', 32, 'image/png'),
        ])->assertStatus(201)->json('data.id');
        $this->postJson('/api/v1/showcase/publish')->assertOk();

        // Le média de B n'est pas servi sous le slug de A (publié aussi).
        $this->principal($companyA);
        $this->postJson('/api/v1/showcase')->assertStatus(200);
        $this->heroSectionId();
        $this->postJson('/api/v1/showcase/publish')->assertOk();

        $this->get('/api/v1/public/vitrine/'.$companyA->slug.'/media/'.$mediaBId)->assertStatus(404);
        $this->get('/api/v1/public/vitrine/'.$companyB->slug.'/media/'.$mediaBId)->assertOk();
    }
}
