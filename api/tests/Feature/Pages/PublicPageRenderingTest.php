<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use App\Modules\Pages\Domain\Enums\PageStatus;
use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Domain\Models\PageBlockModel;
use App\Modules\Pages\Domain\Models\PageCustomDomain;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8730 (BC PAGES) — rendu public SSR.
 *
 * Couvre les critères d'acceptation :
 *  - résolution par slug ET par hostname vers le bon tenant ;
 *  - 404 UNIFORME pour contenu privé/suspendu (jamais 403) ;
 *  - le DTO public n'expose que les champs explicitement publics ;
 *  - les URL sont validées avant rendu (injection de lien) ;
 *  - RTL/LTR corrects ;
 *  - isolation multi-tenant.
 */
class PublicPageRenderingTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Site $site;

    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->company = $company;

        /** @var Site $site */
        $site = Site::create([
            'company_id' => $company->id,
            'name' => 'Boutique Dakar',
            'code' => 'DK-01',
            'site_type' => 'store',
            'status' => Site::STATUS_ACTIVE,
            'gps_lat' => 14.6928,
            'gps_lng' => -17.4467,
        ]);
        $this->site = $site;

        /** @var Page $page */
        $page = Page::create([
            'company_id' => $company->id,
            'site_id' => $site->id,
            'slug' => 'chez-lea',
            'status' => PageStatus::Published,
            'published_at' => now()->subDay(),
            'seo' => ['title' => 'Chez Léa', 'description' => 'Epicerie fine à Dakar'],
        ]);
        $this->page = $page;

        $this->addBlock('b_hero', 'core.hero', 0, [
            'heading' => 'Bienvenue chez Léa',
            'subheading' => 'Produits frais du marché',
        ]);

        $this->addBlock('b_contact', 'core.contact', 1, [
            'title' => 'Nous joindre',
            'phone' => '+221 33 000 00 00',
            'email' => 'bonjour@chez-lea.sn',
        ]);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function addBlock(string $uid, string $type, int $position, array $content): void
    {
        PageBlockModel::create([
            'company_id' => $this->company->id,
            'page_id' => $this->page->id,
            'block_uid' => $uid,
            'type' => $type,
            'schema_version' => 1,
            'position' => $position,
            'content' => $content,
        ]);
    }

    // ── Rendu nominal ───────────────────────────────────────────────────────

    public function test_a_published_page_is_rendered_as_html(): void
    {
        $response = $this->get('/api/v1/p/chez-lea');

        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $response->assertSee('Chez Léa', false);
        $response->assertSee('Bienvenue chez Léa', false);
        $response->assertSee('Epicerie fine à Dakar', false);
    }

    public function test_the_rendering_is_server_side(): void
    {
        $html = $this->get('/api/v1/p/chez-lea')->getContent();

        // Le contenu des blocs est présent dans le HTML BRUT : un moteur de
        // recherche ou un aperçu de lien le voit sans exécuter de JavaScript.
        $this->assertIsString($html);
        $this->assertStringContainsString('Bienvenue chez Léa', $html);
        $this->assertStringContainsString('+221 33 000 00 00', $html);
    }

    public function test_the_page_declares_ltr_for_french(): void
    {
        // L'en-tête est EXPLICITE : le client de test de Symfony injecte
        // `Accept-Language: en-us,en;q=0.5` par défaut, donc un test qui ne
        // l'envoie pas ne teste pas « le français » — il teste la négociation
        // avec `en` et se contredirait lui-même.
        $this->get('/api/v1/p/chez-lea', ['Accept-Language' => 'fr-FR,fr;q=0.9'])
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('lang="fr"', false);
    }

    public function test_the_page_default_locale_is_used_when_no_header_matches(): void
    {
        // Une Page ouverte en turc ne doit pas retomber en français : le repli
        // est `default_locale` de la PAGE, pas une constante du produit.
        $this->page->update(['default_locale' => 'tr']);

        $this->get('/api/v1/p/chez-lea', ['Accept-Language' => 'ja-JP,ja;q=0.9'])
            ->assertOk()
            ->assertSee('lang="tr"', false);
    }

    public function test_a_locale_the_page_does_not_publish_is_not_announced(): void
    {
        // La Page restreint ses locales publiées à `fr` : ni l'en-tête ni
        // `?lang=` ne doivent faire annoncer un `lang` que le contenu ne tient
        // pas.
        $this->page->update(['locales' => ['fr']]);

        $this->get('/api/v1/p/chez-lea?lang=ar')
            ->assertOk()
            ->assertSee('lang="fr"', false);
    }

    public function test_the_page_declares_rtl_for_arabic(): void
    {
        $this->get('/api/v1/p/chez-lea?lang=ar')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('lang="ar"', false);
    }

    public function test_accept_language_header_selects_the_locale(): void
    {
        $this->get('/api/v1/p/chez-lea', ['Accept-Language' => 'ar-DZ,ar;q=0.9'])
            ->assertOk()
            ->assertSee('dir="rtl"', false);
    }

    // ── 404 uniforme ────────────────────────────────────────────────────────

    public function test_an_unknown_slug_returns_404(): void
    {
        $this->get('/api/v1/p/inconnu')->assertNotFound();
    }

    public function test_a_draft_page_returns_404(): void
    {
        $this->page->unpublish();
        $this->page->save();

        $this->get('/api/v1/p/chez-lea')->assertNotFound();
    }

    public function test_a_suspended_page_returns_404(): void
    {
        $this->page->suspend('fraud');
        $this->page->save();

        $this->get('/api/v1/p/chez-lea')->assertNotFound();
    }

    public function test_a_suspended_page_returns_404_even_if_status_was_forced_back(): void
    {
        // Défense en profondeur : même si un chemin de code remettait le
        // statut à `published` sans effacer `suspended_at`, la Page ne doit
        // pas redevenir publique.
        //
        // `status=published` exige une `published_at` (contrainte
        // `pages_published_requires_date`) : on écrit donc les deux champs
        // ensemble. Le forçage qu'on simule est celui d'un code qui republie
        // sans passer par `publish()`, pas celui d'une ligne SQL invalide —
        // sans quoi le test échouerait sur la contrainte avant d'atteindre la
        // règle qu'il prétend vérifier.
        $this->page->suspend('fraud');
        $this->page->status = PageStatus::Published;
        $this->page->published_at = now();
        $this->page->save();

        $this->get('/api/v1/p/chez-lea')->assertNotFound();
    }

    public function test_a_page_of_a_suspended_company_returns_404(): void
    {
        $this->company->update(['status' => 'suspended']);

        $this->get('/api/v1/p/chez-lea')->assertNotFound();
    }

    // ── Fuite de données ────────────────────────────────────────────────────

    public function test_the_public_html_never_leaks_internal_fields(): void
    {
        $html = (string) $this->get('/api/v1/p/chez-lea')->getContent();

        // Aucun identifiant interne, aucun état de modération.
        $this->assertStringNotContainsString((string) $this->company->id, $html);
        $this->assertStringNotContainsString('suspension_reason', $html);
        $this->assertStringNotContainsString('verification_status', $html);
        $this->assertStringNotContainsString('company_id', $html);
    }

    // ── Injection de liens ──────────────────────────────────────────────────

    public function test_a_javascript_url_in_a_block_is_not_rendered(): void
    {
        // Écriture directe en base : le validateur de blocs (#8724) ne
        // protège que les écritures passées par l'API. Le rendu doit résister
        // à une ligne insérée autrement.
        PageBlockModel::query()->where('block_uid', 'b_hero')->update([
            'content' => json_encode([
                'heading' => 'Promo',
                'cta_label' => 'Cliquez',
                'cta_url' => 'javascript:alert(1)',
            ]),
        ]);

        $html = (string) $this->get('/api/v1/p/chez-lea')->getContent();

        $this->assertStringNotContainsString('javascript:alert(1)', $html);
        $this->assertStringNotContainsString('Cliquez', $html);
    }

    public function test_a_data_url_in_a_block_is_not_rendered(): void
    {
        PageBlockModel::query()->where('block_uid', 'b_hero')->update([
            'content' => json_encode([
                'heading' => 'Promo',
                'cta_label' => 'Cliquez',
                'cta_url' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
            ]),
        ]);

        $html = (string) $this->get('/api/v1/p/chez-lea')->getContent();

        $this->assertStringNotContainsString('data:text/html', $html);
    }

    public function test_html_in_block_content_is_escaped(): void
    {
        PageBlockModel::query()->where('block_uid', 'b_hero')->update([
            'content' => json_encode([
                'heading' => '<script>alert(1)</script>',
            ]),
        ]);

        $html = (string) $this->get('/api/v1/p/chez-lea')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // ── Actions providers ───────────────────────────────────────────────────

    public function test_directions_action_is_rendered_when_gps_is_known(): void
    {
        $this->get('/api/v1/p/chez-lea')
            ->assertOk()
            ->assertSee('google.com/maps/dir', false);
    }

    public function test_directions_action_is_absent_without_gps(): void
    {
        $this->site->update(['gps_lat' => null, 'gps_lng' => null]);

        $html = (string) $this->get('/api/v1/p/chez-lea')->getContent();

        $this->assertStringNotContainsString('google.com/maps/dir', $html);
    }

    // ── Résolution par hostname ─────────────────────────────────────────────

    public function test_a_page_is_resolved_by_its_custom_domain(): void
    {
        PageCustomDomain::create([
            'company_id' => $this->company->id,
            'page_id' => $this->page->id,
            'host' => 'chez-lea.sn',
            'status' => PageCustomDomain::STATUS_ACTIVE,
            'certificate_status' => 'active',
            'certificate_expires_at' => now()->addMonths(2),
            'verified_at' => now()->subDay(),
        ]);

        // URL ABSOLUE : `prepareUrlForRequest()` reconstruit l'URL via `url()`
        // et Symfony en dérive `HTTP_HOST` — un `withServerVariables(
        // ['HTTP_HOST' => ...])` serait silencieusement écrasé, et le test
        // passerait 404 sans jamais tester la résolution par domaine.
        $this->get('http://chez-lea.sn/api/v1/pages/by-host')
            ->assertOk()
            ->assertSee('Bienvenue chez Léa', false);
    }

    public function test_a_pending_domain_does_not_serve(): void
    {
        PageCustomDomain::create([
            'company_id' => $this->company->id,
            'page_id' => $this->page->id,
            'host' => 'chez-lea.sn',
            'status' => PageCustomDomain::STATUS_PENDING_DNS,
        ]);

        $this->get('http://chez-lea.sn/api/v1/pages/by-host')->assertNotFound();
    }

    public function test_a_domain_serving_a_draft_page_returns_404(): void
    {
        // Le domaine est actif mais la Page ne l'est plus : la visibilité de
        // la Page prime, un domaine vérifié n'est pas un droit de publier.
        PageCustomDomain::create([
            'company_id' => $this->company->id,
            'page_id' => $this->page->id,
            'host' => 'chez-lea.sn',
            'status' => PageCustomDomain::STATUS_ACTIVE,
            'certificate_status' => 'active',
            'certificate_expires_at' => now()->addMonths(2),
            'verified_at' => now()->subDay(),
        ]);

        $this->page->unpublish();
        $this->page->save();

        $this->get('http://chez-lea.sn/api/v1/pages/by-host')->assertNotFound();
    }

    public function test_an_unknown_host_returns_404(): void
    {
        $this->get('http://inconnu.example/api/v1/pages/by-host')->assertNotFound();
    }

    // ── Isolation multi-tenant ──────────────────────────────────────────────

    public function test_a_page_of_another_tenant_is_not_served_under_this_tenant(): void
    {
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);

        /** @var Site $otherSite */
        $otherSite = Site::create([
            'company_id' => $other->id,
            'name' => 'Boutique Paris',
            'code' => 'PA-01',
            'site_type' => 'store',
            'status' => Site::STATUS_ACTIVE,
        ]);

        Page::create([
            'company_id' => $other->id,
            'site_id' => $otherSite->id,
            'slug' => 'chez-paul',
            'status' => PageStatus::Published,
            'published_at' => now(),
            'seo' => ['title' => 'Chez Paul'],
        ]);

        // Chaque Page reste servie dans SON tenant, jamais dans l'autre.
        $this->get('/api/v1/p/chez-paul')->assertOk()->assertSee('Chez Paul', false);
        $this->get('/api/v1/p/chez-lea')->assertOk()->assertSee('Chez Léa', false);
    }
}
