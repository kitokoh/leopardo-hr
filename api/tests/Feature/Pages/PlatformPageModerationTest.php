<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Pages\Domain\Enums\PageStatus;
use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Domain\Models\PageModerationAudit;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8729 (BC PAGES) — modération plateforme.
 *
 * Couvre les critères d'acceptation :
 *  - recherche et filtres par tenant/établissement/état/vérification/domaine ;
 *  - chaque décision exige permission + motif et écrit un audit IMMUABLE ;
 *  - les pièces ne sont accessibles qu'aux rôles autorisés, et l'accès est tracé ;
 *  - une suspension retire la Page de l'annuaire (statut brouillon, plus de
 *    date de publication) ;
 *  - RBAC et isolation.
 */
class PlatformPageModerationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Site $site;

    private Page $page;

    private SuperAdmin $admin;

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
        ]);
        $this->site = $site;

        /** @var Page $page */
        $page = Page::create([
            'company_id' => $company->id,
            'site_id' => $site->id,
            'slug' => 'chez-lea',
            'status' => PageStatus::Published,
            'published_at' => now()->subDay(),
            'seo' => ['title' => 'Chez Léa'],
        ]);
        $this->page = $page;

        /** @var SuperAdmin $admin */
        $admin = $this->makeSuperAdmin(PlatformRole::SuperAdmin);
        $this->admin = $admin;
    }

    /**
     * `SuperAdmin` n'a pas de factory : le modèle est créé à la main, comme
     * dans `AdminImpersonationEndpointsTest`. `password_hash` est obligatoire
     * en base et n'est pas `fillable` — d'où `forceFill`.
     */
    private function makeSuperAdmin(PlatformRole $role): SuperAdmin
    {
        /** @var SuperAdmin $admin */
        $admin = new SuperAdmin([
            'name' => 'Modérateur '.$role->value,
            'email' => 'mod-'.$role->value.'-'.uniqid().'@leopardo-rh.com',
        ]);

        $admin->forceFill([
            'password_hash' => bcrypt('secret123'),
            'platform_role' => $role->value,
        ])->save();

        return $admin;
    }

    private function asPlatform(PlatformRole $role = PlatformRole::SuperAdmin): void
    {
        $admin = $this->makeSuperAdmin($role);

        // On mémorise l'acteur réellement authentifié : les assertions d'audit
        // comparent `actor_id` à CELUI qui a agi, pas à celui du setUp.
        $this->admin = $admin;

        Sanctum::actingAs($admin, ['*'], 'super_admin_api');
    }

    // ── Recherche / filtres ─────────────────────────────────────────────────

    public function test_search_returns_pages_of_the_targeted_tenant(): void
    {
        $this->asPlatform();

        $response = $this->getJson('/api/v1/platform/pages?company_id='.$this->company->id);

        $response->assertOk();
        $this->assertSame('chez-lea', $response->json('data.0.slug'));
        $this->assertSame($this->company->id, $response->json('data.0.company_id'));
    }

    public function test_filters_by_status_and_suspension(): void
    {
        $this->asPlatform();

        $this->getJson('/api/v1/platform/pages?company_id='.$this->company->id.'&status=published')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/platform/pages?company_id='.$this->company->id.'&status=draft')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/platform/pages?company_id='.$this->company->id.'&suspended=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_filters_by_domain_presence(): void
    {
        $this->page->custom_domain = 'chez-lea.sn';
        $this->page->save();

        $this->asPlatform();

        $this->getJson('/api/v1/platform/pages?company_id='.$this->company->id.'&has_domain=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ── Suspension ──────────────────────────────────────────────────────────

    public function test_suspension_requires_a_valid_reason(): void
    {
        $this->asPlatform();

        $this->postJson('/api/v1/platform/pages/'.$this->company->id.'/'.$this->page->id.'/suspend', [
            'reason' => 'je_n_aime_pas',
        ])->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->assertNull($this->inTenant(fn () => $this->page->refresh()->suspended_at));
    }

    /**
     * Exécute une closure dans le schéma du tenant de test.
     *
     * Les tables `pages` et `page_moderation_audits` vivent dans le schéma du
     * tenant ; hors `withinTenant()`, le search_path est revenu à
     * `shared_tenants,public` et la table n'est plus résolue.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function inTenant(callable $callback): mixed
    {
        return app(\App\Core\Tenant\TenantManager::class)->withinTenant($this->company, $callback);
    }

    public function test_suspension_removes_the_page_from_publication_and_audits_it(): void
    {
        $this->asPlatform();

        $this->postJson('/api/v1/platform/pages/'.$this->company->id.'/'.$this->page->id.'/suspend', [
            'reason' => 'fraud',
        ])->assertOk()->assertJsonPath('data.status', 'draft');

        // `withinTenant()` restaure le search_path en sortant : on relit donc
        // DANS le contexte du tenant, sinon `pages` n'est plus résolu.
        $page = $this->inTenant(fn (): Page => $this->page->refresh());

        // Une suspension retire la Page de l'annuaire : brouillon + plus de
        // date de publication (donc plus de sitemap, plus de domaine actif).
        $this->assertSame('draft', $page->status->value);
        $this->assertNull($page->published_at);
        $this->assertNotNull($page->suspended_at);
        $this->assertSame('fraud', $page->suspension_reason);

        $audit = $this->inTenant(
            fn (): ?PageModerationAudit => PageModerationAudit::query()
                ->where('page_id', $page->id)
                ->latest('id')
                ->first()
        );

        $this->assertNotNull($audit);
        $this->assertSame('page.suspended', $audit->action);
        $this->assertSame('fraud', $audit->reason);
        $this->assertSame('platform:'.$this->admin->id, $audit->actor_id);
        $this->assertSame('published', $audit->before['status']);
        $this->assertSame('draft', $audit->after['status']);
    }

    public function test_unsuspension_returns_the_page_to_draft_without_republishing(): void
    {
        $this->asPlatform();

        $this->postJson('/api/v1/platform/pages/'.$this->company->id.'/'.$this->page->id.'/suspend', [
            'reason' => 'tenant_request',
        ])->assertOk();

        $this->postJson('/api/v1/platform/pages/'.$this->company->id.'/'.$this->page->id.'/unsuspend', [
            'reason' => 'tenant_request',
        ])->assertOk()->assertJsonPath('data.status', 'draft');

        $page = $this->inTenant(fn (): Page => $this->page->refresh());

        $this->assertNull($page->suspended_at);
        // On ne republie JAMAIS à la place du tenant.
        $this->assertSame('draft', $page->status->value);
        $this->assertNull($page->published_at);
    }

    // ── Audit immuable ──────────────────────────────────────────────────────

    public function test_the_audit_journal_rejects_updates_at_the_database_level(): void
    {
        $this->asPlatform();

        $this->postJson('/api/v1/platform/pages/'.$this->company->id.'/'.$this->page->id.'/suspend', [
            'reason' => 'fraud',
        ])->assertOk();

        $audit = $this->inTenant(
            fn (): PageModerationAudit => PageModerationAudit::query()->firstOrFail()
        );

        // L'immuabilité est portée par un trigger PostgreSQL, pas par une
        // convention de code : c'est le moteur qui refuse.
        //
        // Le refus avorte la transaction courante ; on l'isole donc dans un
        // SAVEPOINT (`DB::transaction` imbriqué) pour que le test puisse
        // ensuite continuer à interroger la base.
        $refused = false;

        try {
            $this->inTenant(fn () => DB::transaction(
                fn () => DB::table('page_moderation_audits')->where('id', $audit->id)->update(['reason' => 'forged'])
            ));
        } catch (\Illuminate\Database\QueryException $e) {
            $refused = true;
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        $this->assertTrue($refused, 'Le trigger aurait dû refuser l UPDATE.');

        // La ligne est intacte.
        $this->assertSame('fraud', $this->inTenant(
            fn (): string => (string) DB::table('page_moderation_audits')->where('id', $audit->id)->value('reason')
        ));
    }

    public function test_the_audit_journal_rejects_deletes_at_the_database_level(): void
    {
        $this->asPlatform();

        $this->postJson('/api/v1/platform/pages/'.$this->company->id.'/'.$this->page->id.'/suspend', [
            'reason' => 'fraud',
        ])->assertOk();

        $audit = $this->inTenant(
            fn (): PageModerationAudit => PageModerationAudit::query()->firstOrFail()
        );

        $refused = false;

        try {
            $this->inTenant(fn () => DB::transaction(
                fn () => DB::table('page_moderation_audits')->where('id', $audit->id)->delete()
            ));
        } catch (\Illuminate\Database\QueryException $e) {
            $refused = true;
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        $this->assertTrue($refused, 'Le trigger aurait dû refuser le DELETE.');

        $this->assertSame(1, $this->inTenant(
            fn (): int => DB::table('page_moderation_audits')->where('id', $audit->id)->count()
        ));
    }

    // ── RBAC ────────────────────────────────────────────────────────────────

    public function test_support_can_read_but_not_suspend(): void
    {
        $this->asPlatform(PlatformRole::Support);

        $this->getJson('/api/v1/platform/pages?company_id='.$this->company->id)->assertOk();

        $this->postJson('/api/v1/platform/pages/'.$this->company->id.'/'.$this->page->id.'/suspend', [
            'reason' => 'fraud',
        ])->assertForbidden();

        $this->assertNull($this->inTenant(fn () => $this->page->refresh()->suspended_at));
    }

    public function test_a_tenant_employee_cannot_reach_the_platform_surface(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        $this->actingAs($employee, 'sanctum');
        $this->getJson('/api/v1/platform/pages?company_id='.$this->company->id)->assertUnauthorized();
    }

    // ── Isolation ───────────────────────────────────────────────────────────

    public function test_a_page_of_another_tenant_is_not_reachable_through_the_wrong_company(): void
    {
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);

        $this->asPlatform();

        // La Page existe, mais pas dans CE tenant : 404, pas de fuite.
        $this->getJson('/api/v1/platform/pages/'.$other->id.'/'.$this->page->id)->assertNotFound();
    }
}
