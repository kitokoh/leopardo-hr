<?php

declare(strict_types=1);

namespace Tests\Feature\Showcase;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-27 SHOWCASE — V-PUBLISH #6871 (+ V-SEO #6873, V-RGPD #6875).
 *
 * Workflow de publication (draft → published → unpublish), jeton d'aperçu
 * privé (`?token=`, X-Robots-Tag noindex), sitemap des vitrines publiées,
 * robots.txt, méta SEO et bloc légal/cookies du DTO public, invalidation du
 * cache public à chaque transition.
 */
class ShowcasePublishWorkflowTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $slug): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'slug' => $slug,
        ]);

        // Gate fail-closed `module.showcase` (#6866) : sans ce feature flag,
        // toutes les routes privées /showcase/* répondent 403.
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

    private function showcase(Company $company, CompanyShowcaseStatus $status = CompanyShowcaseStatus::Draft): CompanyShowcase
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $status): CompanyShowcase {
            /** @var CompanyShowcase $showcase */
            $showcase = CompanyShowcase::query()->create([
                'company_id' => $company->id,
                'slug' => $company->slug,
                'status' => $status,
                'theme' => 'industrie',
            ]);

            return $showcase;
        });
    }

    private function addHero(Company $company, CompanyShowcase $showcase, string $heading = 'Acme Industries'): void
    {
        app(TenantManager::class)->withinTenant($company, function () use ($company, $showcase, $heading): void {
            CompanyShowcaseSection::query()->create([
                'company_id' => $company->id,
                'showcase_id' => $showcase->id,
                'type' => 'hero',
                'content' => ['heading' => $heading, 'subheading' => 'Pilotez votre terrain'],
                'sort_order' => 10,
            ]);
        });
    }

    public function test_publish_requires_at_least_one_section(): void
    {
        $company = $this->company('publish-empty');
        $this->principal($company);
        $this->showcase($company);

        $this->postJson('/api/v1/showcase/publish')
            ->assertStatus(422)
            ->assertJsonValidationErrors('sections');
    }

    public function test_publish_then_unpublish_toggles_public_visibility(): void
    {
        $company = $this->company('publish-toggle');
        $this->principal($company);
        $showcase = $this->showcase($company);
        $this->addHero($company, $showcase);

        // Brouillon : jamais public.
        $this->getJson('/api/v1/public/vitrine/publish-toggle')->assertStatus(404);

        $this->postJson('/api/v1/showcase/publish')
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('company_showcases', [
            'slug' => 'publish-toggle',
            'status' => 'published',
        ]);

        // Audit de publication journalisé.
        app(TenantManager::class)->withinTenant($company, function (): void {
            $this->assertTrue(
                AuditLog::query()->where('action', 'showcase.published')->exists()
            );
        });

        // Publiée : lisible en public.
        $this->getJson('/api/v1/public/vitrine/publish-toggle')
            ->assertOk()
            ->assertJsonPath('data.slug', 'publish-toggle');

        // Dépublication : 404 public.
        $this->postJson('/api/v1/showcase/unpublish')
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->getJson('/api/v1/public/vitrine/publish-toggle')->assertStatus(404);
    }

    public function test_preview_token_serves_draft_with_noindex_and_invalid_token_404(): void
    {
        $company = $this->company('publish-preview');
        $this->principal($company);
        $showcase = $this->showcase($company, CompanyShowcaseStatus::Draft);
        $this->addHero($company, $showcase);

        $token = $this->postJson('/api/v1/showcase/preview-token')
            ->assertOk()
            ->json('data.preview_token');

        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));

        // Jeton valide → brouillon servi, non indexable.
        $response = $this->getJson('/api/v1/public/vitrine/publish-preview?token='.$token)->assertOk();
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $response->assertJsonPath('data.slug', 'publish-preview');

        // Jeton invalide → 404 (brouillon jamais exposé).
        $this->getJson('/api/v1/public/vitrine/publish-preview?token='.str_repeat('a', 64))
            ->assertStatus(404);
    }

    public function test_publish_revokes_the_preview_token(): void
    {
        $company = $this->company('publish-revoke');
        $this->principal($company);
        $showcase = $this->showcase($company);
        $this->addHero($company, $showcase);

        $token = $this->postJson('/api/v1/showcase/preview-token')->json('data.preview_token');

        $this->postJson('/api/v1/showcase/publish')->assertOk();

        app(TenantManager::class)->withinTenant($company, function () use ($showcase): void {
            $this->assertNull($showcase->refresh()->preview_token);
        });

        // Le jeton d'aperçu est mort (la vitrine reste publique sans lui).
        $this->getJson('/api/v1/public/vitrine/publish-revoke')->assertOk();
        $this->assertIsString($token);
    }

    public function test_public_payload_exposes_seo_meta_legal_and_cookie_policy(): void
    {
        $company = $this->company('publish-seo');
        $this->principal($company);
        $showcase = $this->showcase($company);
        $this->addHero($company, $showcase, 'Atelier du Sud');

        $this->postJson('/api/v1/showcase/publish')->assertOk();

        $this->getJson('/api/v1/public/vitrine/publish-seo')
            ->assertOk()
            ->assertJsonPath('data.meta.title', 'Atelier du Sud')
            ->assertJsonPath('data.meta.description', 'Pilotez votre terrain')
            ->assertJsonPath('data.meta.canonical_path', '/public/vitrine/publish-seo')
            ->assertJsonPath('data.meta.indexable', true)
            ->assertJsonPath('data.cookies.third_party', false)
            ->assertJsonPath('data.lang', 'fr')
            // Bloc légal de repli : mentions + confidentialité non vides.
            ->assertJsonStructure(['data' => ['legal' => ['notice', 'privacy', 'contact_email']]]);
    }

    public function test_sitemap_lists_published_showcases_only(): void
    {
        $published = $this->company('sitemap-published');
        $this->principal($published);
        $showcasePublished = $this->showcase($published);
        $this->addHero($published, $showcasePublished);
        $this->postJson('/api/v1/showcase/publish')->assertOk();

        $draft = $this->company('sitemap-draft');
        $this->principal($draft);
        $showcaseDraft = $this->showcase($draft);
        $this->addHero($draft, $showcaseDraft);

        Cache::forget('showcase:public:sitemap');

        $xml = $this->get('/api/v1/public/vitrine/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        $this->assertIsString($xml);
        $this->assertStringContainsString('/public/vitrine/sitemap-published', $xml);
        $this->assertStringNotContainsString('/public/vitrine/sitemap-draft', $xml);
    }

    /**
     * #8731 — le sitemap doit être un XML CONFORME et absolu.
     *
     * Trois défauts étaient invisibles : `xmlns:xhtml` utilisé mais jamais
     * déclaré (un parseur strict rejette le document entier), `<loc>` relatif
     * (ignoré par les moteurs), et `limit(500)` qui tronquait l'index sans
     * aucun signal. Ce test charge le XML réellement émis avec un parseur XML
     * — pas une recherche de sous-chaîne, qui passerait sur un document
     * malformé.
     */
    public function test_sitemap_is_well_formed_with_declared_namespaces_and_absolute_locations(): void
    {
        $company = $this->company('sitemap-xml');
        $this->principal($company);
        $showcase = $this->showcase($company);
        $this->addHero($company, $showcase);
        $this->postJson('/api/v1/showcase/publish')->assertOk();

        Cache::forget('showcase:public:sitemap');

        $xml = $this->get('/api/v1/public/vitrine/sitemap.xml')->assertOk()->getContent();
        $this->assertIsString($xml);

        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        $errors = array_map(static fn (\LibXMLError $e): string => trim($e->message), libxml_get_errors());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        // `assertNotFalse()` suffit à rétrécir le type : un `assertNotNull()`
        // supplémentaire serait toujours vrai (PHPStan strict le signale).
        $this->assertNotFalse($doc, 'Le sitemap doit être un XML bien formé : '.implode(' | ', $errors));

        // Le namespace xhtml DOIT être déclaré sur l'élément racine : sans lui
        // les `<xhtml:link>` sont dans un préfixe non lié et le document est
        // non conforme au protocole sitemap.
        $namespaces = $doc->getDocNamespaces();
        $this->assertIsArray($namespaces, 'le sitemap doit déclarer ses namespaces');
        $this->assertArrayHasKey('xhtml', $namespaces);
        $this->assertSame('http://www.w3.org/1999/xhtml', $namespaces['xhtml']);

        // `<loc>` exige une URL ABSOLUE : une valeur relative est ignorée.
        $urls = $doc->xpath('//*[local-name()="loc"]');
        $this->assertIsArray($urls, 'xpath doit renvoyer une liste');
        $this->assertNotEmpty($urls, 'le sitemap doit contenir au moins une entrée');
        foreach ($urls as $loc) {
            $this->assertMatchesRegularExpression(
                '#^https?://#',
                (string) $loc,
                'chaque <loc> doit être une URL absolue, reçu : '.(string) $loc
            );
        }

        // Les alternates hreflang sont eux aussi absolus (même règle).
        $alternates = $doc->xpath('//*[local-name()="link"]');
        $this->assertIsArray($alternates, 'xpath doit renvoyer une liste');
        $this->assertNotEmpty($alternates);
        foreach ($alternates as $link) {
            $this->assertMatchesRegularExpression('#^https?://#', (string) $link['href']);
        }
    }

    /**
     * #8731 — une société `expired` ne doit pas figurer au sitemap.
     *
     * Elle n'est plus servie en public (le resolver renvoie 404), donc l'y
     * inscrire ne produit que des URL mortes — et un signal de qualité
     * négatif pour le domaine.
     */
    public function test_sitemap_excludes_expired_companies(): void
    {
        $expired = $this->company('sitemap-expired');
        $this->principal($expired);
        $showcase = $this->showcase($expired);
        $this->addHero($expired, $showcase);
        $this->postJson('/api/v1/showcase/publish')->assertOk();

        $expired->forceFill(['status' => 'expired'])->save();

        Cache::forget('showcase:public:sitemap');

        $xml = $this->get('/api/v1/public/vitrine/sitemap.xml')->assertOk()->getContent();
        $this->assertIsString($xml);
        $this->assertStringNotContainsString('/public/vitrine/sitemap-expired', $xml);
    }

    public function test_robots_txt_allows_vitrine_and_excludes_preview_tokens(): void
    {
        $body = $this->get('/api/v1/public/vitrine/robots.txt')
            ->assertOk()
            ->getContent();

        $this->assertIsString($body);
        $this->assertStringContainsString('Allow: /public/vitrine/', $body);
        $this->assertStringContainsString('Disallow: /public/vitrine/*?token=', $body);
        $this->assertStringContainsString('Sitemap: /public/vitrine/sitemap.xml', $body);
    }

    public function test_unpublish_invalidates_the_public_cache(): void
    {
        $company = $this->company('publish-cache');
        $this->principal($company);
        $showcase = $this->showcase($company);
        $this->addHero($company, $showcase);
        $this->postJson('/api/v1/showcase/publish')->assertOk();

        $this->getJson('/api/v1/public/vitrine/publish-cache')->assertOk();

        // Le cache publie est chaud…
        Cache::put(ShowcasePublicCache::key('publish-cache', 'fr'), ['sentinel' => true], 60);
        $this->assertNotNull(Cache::get(ShowcasePublicCache::key('publish-cache', 'fr')));

        $this->postJson('/api/v1/showcase/unpublish')->assertOk();

        // …et purgé par la dépublication.
        $this->assertNull(Cache::get(ShowcasePublicCache::key('publish-cache', 'fr')));
    }
}
