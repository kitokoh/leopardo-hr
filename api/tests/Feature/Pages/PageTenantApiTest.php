<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use App\Modules\Pages\Domain\Enums\PageStatus;
use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Domain\Models\PageBlockModel;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8728 (BC PAGES) — surface tenant : CRUD, blocs, publication, aperçu.
 *
 * Couvre les critères d'acceptation de l'issue :
 *  - CRUD accessible uniquement aux acteurs autorisés et au tenant propriétaire ;
 *  - l'éditeur applique les schémas de blocs et affiche les erreurs
 *    contextualisées (toutes, indexées par bloc) ;
 *  - l'aperçu n'est pas indexable ; la publication exige les prérequis ;
 *  - isolation multi-tenant : la Page d'un autre tenant répond 404.
 */
class PageTenantApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $otherCompany;

    private Site $site;

    private Site $otherSite;

    private Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->company = $company;

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);
        $this->otherCompany = $other;

        $this->site = $this->makeSite($company, 'Boutique Dakar', 'DK-01');
        $this->otherSite = $this->makeSite($other, 'Boutique Lyon', 'LY-01');

        /** @var Employee $principal */
        $principal = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principal = $principal;
    }

    private function makeSite(Company $company, string $name, string $code): Site
    {
        /** @var Site $site */
        $site = Site::create([
            'company_id' => $company->id,
            'name' => $name,
            'code' => $code,
            'site_type' => 'store',
            'status' => Site::STATUS_ACTIVE,
        ]);

        return $site;
    }

    private function makePage(Company $company, Site $site, string $slug): Page
    {
        /** @var Page $page */
        $page = Page::create([
            'company_id' => $company->id,
            'site_id' => $site->id,
            'slug' => $slug,
            'status' => PageStatus::Draft,
            'seo' => ['title' => 'Chez Léa'],
        ]);

        return $page;
    }

    // ── Création ────────────────────────────────────────────────────────────

    public function test_principal_creates_a_page_for_its_own_site(): void
    {
        Sanctum::actingAs($this->principal);

        $response = $this->postJson('/api/v1/pages', [
            'site_id' => $this->site->id,
            'title' => 'Chez Léa & Fils',
            'seo' => ['title' => 'Chez Léa', 'description' => 'Épicerie de quartier'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'chez-lea-fils')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.verification_status', 'unverified');

        $this->assertDatabaseHas('pages', [
            'company_id' => $this->company->id,
            'site_id' => $this->site->id,
            'slug' => 'chez-lea-fils',
        ]);
    }

    public function test_creation_refuses_a_site_belonging_to_another_tenant(): void
    {
        Sanctum::actingAs($this->principal);

        $this->postJson('/api/v1/pages', [
            'site_id' => $this->otherSite->id,
            'title' => 'Page volée',
        ])->assertStatus(422)->assertJsonValidationErrors('site_id');

        $this->assertDatabaseCount('pages', 0);
    }

    public function test_creation_refuses_a_reserved_slug(): void
    {
        Sanctum::actingAs($this->principal);

        $this->postJson('/api/v1/pages', [
            'site_id' => $this->site->id,
            'slug' => 'dashboard',
        ])->assertStatus(422)->assertJsonPath('error', 'PAGE_SLUG_RESERVED');
    }

    public function test_creation_refuses_a_slug_with_nothing_usable(): void
    {
        Sanctum::actingAs($this->principal);

        $this->postJson('/api/v1/pages', [
            'site_id' => $this->site->id,
            'slug' => '---',
        ])->assertStatus(422)->assertJsonPath('error', 'PAGE_SLUG_UNUSABLE');
    }

    public function test_a_second_page_for_the_same_site_is_refused_by_the_database(): void
    {
        $this->makePage($this->company, $this->site, 'premiere');

        // L'unicité `site_id` est un invariant de SCHÉMA, pas une règle
        // applicative : on vérifie l'index lui-même. Provoquer la violation
        // pour l'attraper avorterait la transaction du test (PostgreSQL refuse
        // toute commande suivante) et masquerait les assertions suivantes.
        $indexes = DB::select(
            "SELECT indexdef FROM pg_indexes WHERE tablename = 'pages' AND indexdef LIKE '%UNIQUE%'"
        );

        $definitions = array_map(static fn (object $row): string => (string) $row->indexdef, $indexes);

        $this->assertNotEmpty(
            array_filter($definitions, static fn (string $d): bool => str_contains($d, '(site_id)')),
            'Un index UNIQUE sur pages.site_id doit exister : deux Pages pour un même établissement rendraient le rendu public non déterministe.',
        );
    }

    // ── Lecture / isolation ─────────────────────────────────────────────────

    public function test_listing_only_returns_pages_of_the_current_tenant(): void
    {
        $this->makePage($this->company, $this->site, 'la-mienne');
        $this->makePage($this->otherCompany, $this->otherSite, 'celle-de-lautre');

        Sanctum::actingAs($this->principal);

        $response = $this->getJson('/api/v1/pages');

        $response->assertOk();
        $slugs = array_column($response->json('data'), 'slug');

        $this->assertContains('la-mienne', $slugs);
        $this->assertNotContains('celle-de-lautre', $slugs);
    }

    public function test_a_page_of_another_tenant_answers_404_not_403(): void
    {
        $foreign = $this->makePage($this->otherCompany, $this->otherSite, 'etrangere');

        Sanctum::actingAs($this->principal);

        // 404 et non 403 : un 403 confirmerait l'existence de la ressource et
        // permettrait d'énumérer le parc client.
        $this->getJson('/api/v1/pages/'.$foreign->id)->assertNotFound();
    }

    // ── Mise à jour ─────────────────────────────────────────────────────────

    public function test_partial_update_does_not_require_creation_fields(): void
    {
        $page = $this->makePage($this->company, $this->site, 'chez-lea');

        Sanctum::actingAs($this->principal);

        $this->putJson('/api/v1/pages/'.$page->id, ['theme' => 'dark'])
            ->assertOk()
            ->assertJsonPath('data.theme', 'dark');
    }

    public function test_update_cannot_write_verification_status(): void
    {
        $page = $this->makePage($this->company, $this->site, 'chez-lea');

        Sanctum::actingAs($this->principal);

        $this->putJson('/api/v1/pages/'.$page->id, [
            'verification_status' => 'verified',
            'status' => 'published',
        ])->assertOk();

        $page->refresh();

        // Les champs d'état ne sont pas dans les règles : ils sont ignorés.
        $this->assertSame('unverified', $page->verification_status->value);
        $this->assertSame('draft', $page->status->value);
    }

    public function test_update_refuses_a_slug_already_taken_by_another_page(): void
    {
        $this->makePage($this->company, $this->site, 'deja-pris');
        $other = $this->makePage($this->otherCompany, $this->otherSite, 'autre-page');

        Sanctum::actingAs($this->principal);

        $this->putJson('/api/v1/pages/'.$other->id, ['slug' => 'deja-pris'])
            ->assertNotFound();
    }

    // ── Blocs ───────────────────────────────────────────────────────────────

    public function test_blocks_are_stored_in_explicit_order(): void
    {
        $page = $this->makePage($this->company, $this->site, 'chez-lea');

        Sanctum::actingAs($this->principal);

        $this->putJson('/api/v1/pages/'.$page->id.'/blocks', [
            'blocks' => [
                ['id' => 'b_2', 'type' => 'core.hero', 'position' => 5, 'content' => ['heading' => 'Bienvenue']],
                ['id' => 'b_1', 'type' => 'core.footer', 'position' => 1, 'content' => ['text' => 'Pied']],
            ],
        ])->assertOk();

        $blocks = PageBlockModel::query()->where('page_id', $page->id)->orderBy('position')->get();

        $this->assertCount(2, $blocks);
        // Positions renumérotées 0..n-1, ordre explicite respecté.
        $this->assertSame(['b_1', 'b_2'], $blocks->pluck('block_uid')->all());
        $this->assertSame([0, 1], $blocks->pluck('position')->all());
    }

    public function test_invalid_block_content_is_reported_with_all_errors(): void
    {
        $page = $this->makePage($this->company, $this->site, 'chez-lea');

        Sanctum::actingAs($this->principal);

        $response = $this->putJson('/api/v1/pages/'.$page->id.'/blocks', [
            'blocks' => [
                ['id' => 'b_1', 'type' => 'core.hero', 'content' => ['heading' => 'OK']],
                ['id' => 'b_2', 'type' => 'inconnu.total', 'content' => ['x' => 1]],
            ],
        ]);

        $response->assertStatus(422)->assertJsonPath('error', 'PAGE_BLOCKS_INVALID');

        /** @var array<int, list<string>> $errors */
        $errors = $response->json('errors');

        $this->assertArrayHasKey(1, $errors);
        $this->assertStringContainsString('inconnu.total', $errors[1][0]);

        // Rien n'a été écrit : la validation précède l'écriture.
        $this->assertDatabaseCount('page_blocks', 0);
    }

    public function test_replacing_blocks_removes_the_previous_list(): void
    {
        $page = $this->makePage($this->company, $this->site, 'chez-lea');

        Sanctum::actingAs($this->principal);

        $this->putJson('/api/v1/pages/'.$page->id.'/blocks', [
            'blocks' => [['id' => 'b_1', 'type' => 'core.hero', 'content' => ['heading' => 'A']]],
        ])->assertOk();

        $this->putJson('/api/v1/pages/'.$page->id.'/blocks', [
            'blocks' => [['id' => 'b_9', 'type' => 'core.footer', 'content' => ['text' => 'B']]],
        ])->assertOk();

        $this->assertSame(
            ['b_9'],
            PageBlockModel::query()->where('page_id', $page->id)->pluck('block_uid')->all(),
        );
    }

    // ── Publication ─────────────────────────────────────────────────────────

    public function test_publishing_requires_a_seo_title(): void
    {
        /** @var Page $page */
        $page = Page::create([
            'company_id' => $this->company->id,
            'site_id' => $this->site->id,
            'slug' => 'sans-titre',
            'status' => PageStatus::Draft,
        ]);

        Sanctum::actingAs($this->principal);

        $this->postJson('/api/v1/pages/'.$page->id.'/publish')->assertStatus(422);

        $this->assertSame('draft', $page->refresh()->status->value);
    }

    public function test_publishing_then_unpublishing_moves_the_page_between_states(): void
    {
        $page = $this->makePage($this->company, $this->site, 'chez-lea');

        Sanctum::actingAs($this->principal);

        $this->postJson('/api/v1/pages/'.$page->id.'/publish')
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertNotNull($page->refresh()->published_at);

        $this->postJson('/api/v1/pages/'.$page->id.'/unpublish')
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertNull($page->refresh()->published_at);
    }

    // ── Aperçu ──────────────────────────────────────────────────────────────

    public function test_preview_is_served_for_a_draft_and_is_not_indexable(): void
    {
        $page = $this->makePage($this->company, $this->site, 'chez-lea');

        Sanctum::actingAs($this->principal);

        $response = $this->getJson('/api/v1/pages/'.$page->id.'/preview');

        $response->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    // ── RBAC ────────────────────────────────────────────────────────────────

    public function test_a_non_manager_cannot_reach_the_tenant_surface(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'manager_role' => null,
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/pages')->assertForbidden();
    }
}
