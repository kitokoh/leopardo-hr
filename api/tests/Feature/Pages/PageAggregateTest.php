<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use App\Modules\Pages\Domain\Enums\PageStatus;
use App\Modules\Pages\Domain\Enums\PageVerificationStatus;
use App\Modules\Pages\Domain\Exceptions\InvalidPageVerificationTransitionException;
use App\Modules\Pages\Domain\Exceptions\PageTenantMismatchException;
use App\Modules\Pages\Domain\Models\Page;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8723 (BC PAGES) — agrégat public `Page` lié à un établissement.
 *
 * Ce fichier couvre les critères d'acceptation de l'issue, un par un :
 *  - isolation : une Page ne peut pas associer l'établissement d'un autre
 *    tenant (contrainte applicative ET lecture ;
 *  - transitions `draft`/`published`/`verified`/`suspended` définies et
 *    testées, publication et vérification SÉPARÉES ;
 *  - slugs normalisés, globalement uniques, réservés refusés ;
 *  - réponses publiques excluant brouillons et Pages suspendues ;
 *  - une seule Page par établissement (contrainte de base).
 */
class PageAggregateTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Site $siteA;

    private Site $siteB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);
        $this->companyB = $companyB;

        $this->siteA = $this->makeSite($companyA, 'Boutique Dakar', 'DK-01');
        $this->siteB = $this->makeSite($companyB, 'Boutique Lyon', 'LY-01');
    }

    // ── Slug ────────────────────────────────────────────────────────────────

    public function test_slug_is_normalized_from_accented_and_punctuated_input(): void
    {
        $this->assertSame('chez-lea-fils', Page::normalizeSlug('Chez Léa & Fils'));
        $this->assertSame('restaurant-le-dakar', Page::normalizeSlug('  Restaurant  Le   Dakar  '));
        $this->assertSame('cafe-de-la-gare', Page::normalizeSlug('Café de la Gare'));
        // Apostrophe turque et point médian : réduits au même séparateur.
        $this->assertSame('kebab-istanbul', Page::normalizeSlug('Kebab · İstanbul'));
    }

    public function test_slug_rejects_empty_and_unusable_input(): void
    {
        $this->assertNull(Page::normalizeSlug(''), 'Un slug vide ne doit pas être accepté.');
        $this->assertNull(Page::normalizeSlug('   '), 'Un slug blanc ne doit pas être accepté.');
        $this->assertNull(Page::normalizeSlug('---'), 'Un slug sans caractère alphanumérique ne doit pas être accepté.');
        $this->assertNull(Page::normalizeSlug(str_repeat('a', 161)), 'Un slug > 160 caractères doit être refusé.');
    }

    public function test_reserved_slugs_are_flagged(): void
    {
        $this->assertTrue(Page::isReservedSlug('api'));
        $this->assertTrue(Page::isReservedSlug('dashboard'));
        $this->assertTrue(Page::isReservedSlug('vitrine'));
        $this->assertFalse(Page::isReservedSlug('boutique-dakar'));
    }

    public function test_slug_is_globally_unique_across_tenants(): void
    {
        $this->makePage($this->companyA, $this->siteA, 'boutique-dakar');

        // Un second tenant qui prétend le même slug public : la contrainte de
        // base doit refuser, sans quoi deux sociétés se disputeraient l'URL.
        // Savepoint (#4978) : sans lui, le PostgreSQL de test reste en
        // transaction avortée et les assertions suivantes échouent en cascade.
        $this->expectException(QueryException::class);
        DB::transaction(function (): void {
            $this->makePage($this->companyB, $this->siteB, 'boutique-dakar');
        });
    }

    // ── Isolation tenant ────────────────────────────────────────────────────

    public function test_page_cannot_be_attached_to_another_tenants_establishment(): void
    {
        $page = new Page;
        $page->company_id = (string) $this->companyA->id;
        $page->site_id = (int) $this->siteB->id; // établissement du tenant B
        $page->slug = 'tentative-cross-tenant';
        $page->status = PageStatus::Draft;

        $this->expectException(PageTenantMismatchException::class);

        $page->assertSameTenantAsSite();
    }

    public function test_cross_tenant_attempt_is_reported_as_forbidden_not_as_a_404(): void
    {
        $page = new Page;
        $page->company_id = (string) $this->companyA->id;
        $page->site_id = (int) $this->siteB->id;
        $page->slug = 'tentative-cross-tenant-2';

        try {
            $page->assertSameTenantAsSite();
            $this->fail('Le rattachement cross-tenant aurait dû être refusé.');
        } catch (PageTenantMismatchException $e) {
            // 403 et non 404 : ce n'est pas une référence invalide, c'est une
            // tentative d'accès à une ressource d'un autre tenant.
            $this->assertSame(403, $e->statusCode());
            $this->assertSame(PageTenantMismatchException::CODE_CROSS_TENANT, $e->errorCode());
        }
    }

    public function test_missing_site_is_a_validation_error(): void
    {
        $page = new Page;
        $page->company_id = (string) $this->companyA->id;
        $page->site_id = 999_999_999;
        $page->slug = 'site-inexistant';

        try {
            $page->assertSameTenantAsSite();
            $this->fail('Un site_id inexistant aurait dû être refusé.');
        } catch (PageTenantMismatchException $e) {
            $this->assertSame(422, $e->statusCode());
            $this->assertSame(PageTenantMismatchException::CODE_MISSING_SITE, $e->errorCode());
        }
    }

    public function test_one_page_per_establishment_is_enforced_by_the_database(): void
    {
        $this->makePage($this->companyA, $this->siteA, 'boutique-dakar');

        // Même établissement, slug différent : la contrainte d'unicité sur
        // `site_id` doit refuser (une seule Page par établissement en V1).
        // Savepoint (#4978) pour ne pas avorter la transaction du test.
        $this->expectException(QueryException::class);
        DB::transaction(function (): void {
            $this->makePage($this->companyA, $this->siteA, 'boutique-dakar-bis');
        });
    }

    // ── Publication ─────────────────────────────────────────────────────────

    public function test_publishing_sets_the_date_and_makes_the_page_visible(): void
    {
        $page = $this->makePage($this->companyA, $this->siteA, 'boutique-dakar');

        $this->assertFalse($page->isPubliclyVisible(), 'Une Page en brouillon ne doit pas être visible.');

        $page->publish(Carbon::parse('2026-10-01 09:00:00'));
        $page->save();

        $fresh = $page->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(PageStatus::Published, $fresh->status);
        $this->assertNotNull($fresh->published_at);
        $this->assertTrue($fresh->isPubliclyVisible());
    }

    public function test_republishing_does_not_move_the_publication_date(): void
    {
        $page = $this->makePage($this->companyA, $this->siteA, 'boutique-dakar');
        $page->publish(Carbon::parse('2026-10-01 09:00:00'));
        $page->save();

        $originalDate = $page->published_at;
        $this->assertNotNull($originalDate);

        $page->publish(Carbon::parse('2026-11-20 12:00:00'));

        $this->assertEquals($originalDate, $page->published_at, 'Republier ne doit pas remonter la Page en tête du sitemap.');
    }

    public function test_unpublishing_clears_the_publication_date(): void
    {
        $page = $this->makePage($this->companyA, $this->siteA, 'boutique-dakar');
        $page->publish();
        $page->save();

        $page->unpublish();
        $page->save();

        $fresh = $page->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(PageStatus::Draft, $fresh->status);
        $this->assertNull($fresh->published_at, 'Dépublier doit effacer la date (contrainte pages_draft_has_no_published_date).');
        $this->assertFalse($fresh->isPubliclyVisible());
    }

    public function test_published_scope_excludes_drafts_and_suspended_pages(): void
    {
        $published = $this->makePage($this->companyA, $this->siteA, 'boutique-publiee');
        $published->publish();
        $published->save();

        $draft = $this->makePage($this->companyB, $this->siteB, 'boutique-brouillon');

        $suspendedSite = $this->makeSite($this->companyA, 'Boutique suspendue', 'DK-02');
        $suspended = $this->makePage($this->companyA, $suspendedSite, 'boutique-suspendue');
        $suspended->publish();
        $suspended->suspend('fraud');
        $suspended->save();

        $ids = Page::query()->published()->pluck('id')->all();

        $this->assertContains($published->id, $ids);
        $this->assertNotContains($draft->id, $ids);
        $this->assertNotContains($suspended->id, $ids, 'Une Page suspendue ne doit jamais réapparaître dans une lecture publique.');
    }

    // ── Vérification : axe indépendant ──────────────────────────────────────

    public function test_verification_and_publication_are_independent_axes(): void
    {
        $page = $this->makePage($this->companyA, $this->siteA, 'boutique-dakar');

        // Vérifiée mais NON publiée : les preuves sont validées avant l'ouverture.
        $page->transitionVerification(PageVerificationStatus::Pending);
        $page->transitionVerification(PageVerificationStatus::Verified, 'document');
        $page->save();

        $fresh = $page->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(PageVerificationStatus::Verified, $fresh->verification_status);
        $this->assertSame(PageStatus::Draft, $fresh->status);
        $this->assertFalse($fresh->isPubliclyVisible(), 'Vérifier ne publie pas.');

        // Publiée puis vérifiée : l'inverse doit aussi être possible.
        $other = $this->makePage($this->companyB, $this->siteB, 'boutique-lyon');
        $other->publish();
        $other->save();

        $freshOther = $other->fresh();
        $this->assertNotNull($freshOther);
        $this->assertTrue($freshOther->isPubliclyVisible());
        $this->assertSame(PageVerificationStatus::Unverified, $freshOther->verification_status, 'Publier ne vérifie pas.');
    }

    public function test_illegal_verification_transitions_are_rejected(): void
    {
        $page = $this->makePage($this->companyA, $this->siteA, 'boutique-dakar');

        // unverified -> verified sans dossier : interdit (il faut passer par
        // `pending`, sinon on blanchit une Page sans avoir examiné de preuve).
        $this->expectException(InvalidPageVerificationTransitionException::class);

        $page->transitionVerification(PageVerificationStatus::Verified, 'document');
    }

    public function test_verified_requires_a_known_method(): void
    {
        $page = $this->makePage($this->companyA, $this->siteA, 'boutique-dakar');
        $page->transitionVerification(PageVerificationStatus::Pending);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('PAGE_VERIFICATION_METHOD_REQUIRED');

        $page->transitionVerification(PageVerificationStatus::Verified, 'carrier-pigeon');
    }

    public function test_leaving_verified_clears_the_proof_columns(): void
    {
        $page = $this->makePage($this->companyA, $this->siteA, 'boutique-dakar');
        $page->transitionVerification(PageVerificationStatus::Pending);
        $page->transitionVerification(PageVerificationStatus::Verified, 'sms');
        $page->save();

        $page->transitionVerification(PageVerificationStatus::Revoked);
        $page->save();

        $fresh = $page->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(PageVerificationStatus::Revoked, $fresh->verification_status);
        $this->assertNull($fresh->verified_at, 'Une preuve révoquée ne doit pas conserver sa date.');
        $this->assertNull($fresh->verification_method);
    }

    public function test_verified_scope_only_returns_verified_pages(): void
    {
        $verified = $this->makePage($this->companyA, $this->siteA, 'boutique-verifiee');
        $verified->transitionVerification(PageVerificationStatus::Pending);
        $verified->transitionVerification(PageVerificationStatus::Verified, 'superadmin');
        $verified->save();

        $pending = $this->makePage($this->companyB, $this->siteB, 'boutique-en-attente');
        $pending->transitionVerification(PageVerificationStatus::Pending);
        $pending->save();

        $ids = Page::query()->verified()->pluck('id')->all();

        $this->assertContains($verified->id, $ids);
        $this->assertNotContains($pending->id, $ids);
    }

    // ── Suspension ──────────────────────────────────────────────────────────

    public function test_suspension_unpublishes_the_page_and_keeps_the_reason(): void
    {
        $page = $this->makePage($this->companyA, $this->siteA, 'boutique-dakar');
        $page->publish();
        $page->save();

        $page->suspend('impersonation');
        $page->save();

        $fresh = $page->fresh();
        $this->assertNotNull($fresh);
        $this->assertTrue($fresh->isSuspended());
        $this->assertSame('impersonation', $fresh->suspension_reason);
        $this->assertSame(PageStatus::Draft, $fresh->status);
        $this->assertNull($fresh->published_at);
        $this->assertFalse($fresh->isPubliclyVisible());
    }

    public function test_suspension_requires_a_known_reason(): void
    {
        $page = $this->makePage($this->companyA, $this->siteA, 'boutique-dakar');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('PAGE_SUSPENSION_REASON_INVALID');

        $page->suspend('because');
    }

    public function test_suspended_page_cannot_be_published_again(): void
    {
        $page = $this->makePage($this->companyA, $this->siteA, 'boutique-dakar');
        $page->suspend('policy_violation');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('SUSPENDED_PAGE_CANNOT_BE_PUBLISHED');

        $page->publish();
    }

    public function test_unsuspending_returns_the_page_to_draft_without_republishing(): void
    {
        $page = $this->makePage($this->companyA, $this->siteA, 'boutique-dakar');
        $page->publish();
        $page->suspend('tenant_request');
        $page->save();

        $page->unsuspend();
        $page->save();

        $fresh = $page->fresh();
        $this->assertNotNull($fresh);
        $this->assertFalse($fresh->isSuspended());
        $this->assertNull($fresh->suspension_reason);
        $this->assertSame(PageStatus::Draft, $fresh->status, 'Lever une suspension ne republie jamais à la place du tenant.');
        $this->assertFalse($fresh->isPubliclyVisible());
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

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
            'verification_status' => PageVerificationStatus::Unverified,
        ]);

        return $page;
    }
}
