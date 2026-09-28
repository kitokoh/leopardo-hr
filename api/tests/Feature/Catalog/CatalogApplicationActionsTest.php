<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Core\Tenant\Domain\Models\Company;
use App\Events\CatalogInquiryErased;
use App\Events\CatalogInquiryReceived;
use App\Modules\Catalog\Application\Actions\CreateCatalogCategoryAction;
use App\Modules\Catalog\Application\Actions\CreateCatalogProductAction;
use App\Modules\Catalog\Application\Actions\DeleteCatalogCategoryAction;
use App\Modules\Catalog\Application\Actions\DeleteCatalogProductAction;
use App\Modules\Catalog\Application\Actions\EraseCatalogInquiryAction;
use App\Modules\Catalog\Application\Actions\ExportCatalogInquiriesAction;
use App\Modules\Catalog\Application\Actions\SubmitCatalogInquiryAction;
use App\Modules\Catalog\Application\Actions\TransitionCatalogInquiryStatusAction;
use App\Modules\Catalog\Application\Actions\TransitionCatalogProductStatusAction;
use App\Modules\Catalog\Application\Actions\UpdateCatalogCategoryAction;
use App\Modules\Catalog\Application\Actions\UpdateCatalogProductAction;
use App\Modules\Catalog\Domain\Enums\CatalogInquiryStatus;
use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use App\Modules\Catalog\Domain\Exceptions\InvalidInquiryStatusTransitionException;
use App\Modules\Catalog\Domain\Models\CatalogCategory;
use App\Modules\Catalog\Domain\Models\CatalogInquiry;
use App\Modules\Catalog\Domain\Models\CatalogProduct;
use App\Modules\Catalog\Infrastructure\Services\CatalogPublicCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Tests des Actions de la couche Application Catalog — BOS-024f
 * (#8217, BC-28).
 *
 * Chaque Action est éprouvée directement (conteneur) sur ses
 * responsabilités propres : dérivations serveur (slug unique par tenant,
 * devise/unité par défaut), invariants (matrice de transitions des
 * demandes de devis), invalidation du cache public (snapshot + fiches),
 * émission des événements cross-BC (lead CRM, effacement RGPD) et
 * isolation tenant. La parité HTTP de bout en bout reste couverte par les
 * suites de contrat existantes (CatalogApiTest, CatalogPublicApiTest,
 * CatalogInquiryBackofficeTest, CatalogRgpdTest, CatalogPublicInquiryApiTest),
 * vertes sans modification — preuve d'absence de changement d'API.
 */
class CatalogApplicationActionsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $companyA->setFeature('b2b_catalog', true);
        $companyA->save();
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $companyB->setFeature('b2b_catalog', true);
        $companyB->save();
        $this->companyB = $companyB;
    }

    // ── Fixtures ────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Fraiseuse CNC',
            'price_minor' => 125000,
            'description' => 'Fraiseuse 3 axes',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function categoryPayload(array $overrides = []): array
    {
        return array_merge(['name' => 'Machines'], $overrides);
    }

    private function inquiry(Company $company, CatalogInquiryStatus $status, string $email = 'achat@exemple.dz'): CatalogInquiry
    {
        /** @var CatalogInquiry $inquiry */
        $inquiry = CatalogInquiry::query()->create([
            'company_id' => $company->id,
            'product_slug' => 'fraiseuse-cnc',
            'product_name' => 'Fraiseuse CNC',
            'quantity' => 2,
            'company_name' => 'Ateliers Boussaid SARL',
            'email' => $email,
            'message' => 'Demande de devis',
            'status' => $status->value,
            'consent_at' => now(),
            'retention_until' => now()->addDays(90)->toDateString(),
        ]);

        return $inquiry;
    }

    private function publishedProduct(Company $company): CatalogProduct
    {
        /** @var CatalogProduct $product */
        $product = CatalogProduct::query()->create([
            'company_id' => $company->id,
            'name' => 'Fraiseuse CNC',
            'slug' => 'fraiseuse-cnc',
            'price_minor' => 125000,
            'currency' => 'DZD',
            'unit' => 'piece',
            'status' => CatalogProductStatus::Published->value,
        ]);

        return $product;
    }

    // ── Produits ────────────────────────────────────────────────────────

    public function test_create_product_action_derives_tenant_currency_unit_and_draft_status(): void
    {
        $product = app(CreateCatalogProductAction::class)
            ->execute((string) $this->companyA->id, $this->productPayload(), 'DZD');

        $this->assertSame((string) $this->companyA->id, $product->company_id);
        $this->assertSame('fraiseuse-cnc', $product->slug);
        $this->assertSame('DZD', $product->currency);
        $this->assertSame('piece', $product->unit);
        $this->assertSame(CatalogProductStatus::Draft, $product->status);
        $this->assertSame(125000, $product->price_minor);
    }

    public function test_create_product_action_uses_explicit_currency_and_unit_when_provided(): void
    {
        $product = app(CreateCatalogProductAction::class)->execute(
            (string) $this->companyA->id,
            $this->productPayload(['currency' => 'EUR', 'unit' => 'kg', 'status' => 'published']),
            'DZD',
        );

        $this->assertSame('EUR', $product->currency);
        $this->assertSame('kg', $product->unit);
        $this->assertSame(CatalogProductStatus::Published, $product->status);
    }

    public function test_create_product_action_resolves_slug_collisions_with_numeric_suffix(): void
    {
        $action = app(CreateCatalogProductAction::class);

        $first = $action->execute((string) $this->companyA->id, $this->productPayload(), 'DZD');
        $second = $action->execute((string) $this->companyA->id, $this->productPayload(), 'DZD');
        $third = $action->execute((string) $this->companyA->id, $this->productPayload(), 'DZD');

        $this->assertSame('fraiseuse-cnc', $first->slug);
        $this->assertSame('fraiseuse-cnc-2', $second->slug);
        $this->assertSame('fraiseuse-cnc-3', $third->slug);
    }

    public function test_create_product_action_keeps_slugs_independent_per_tenant(): void
    {
        $action = app(CreateCatalogProductAction::class);

        $productA = $action->execute((string) $this->companyA->id, $this->productPayload(), 'DZD');
        $productB = $action->execute((string) $this->companyB->id, $this->productPayload(), 'MAD');

        $this->assertSame('fraiseuse-cnc', $productA->slug);
        $this->assertSame('fraiseuse-cnc', $productB->slug);
    }

    public function test_create_product_action_purges_public_snapshot(): void
    {
        $key = CatalogPublicCache::snapshotKey((string) $this->companyA->id);
        Cache::put($key, ['stale' => true], 60);

        app(CreateCatalogProductAction::class)
            ->execute((string) $this->companyA->id, $this->productPayload(), 'DZD');

        $this->assertFalse(Cache::has($key));
    }

    public function test_update_product_action_ignores_self_on_slug_collision(): void
    {
        $product = $this->publishedProduct($this->companyA);

        $updated = app(UpdateCatalogProductAction::class)->execute($product, [
            'name' => 'Fraiseuse CNC',
            'price_minor' => 130000,
        ]);

        $this->assertSame('fraiseuse-cnc', $updated->slug);
        $this->assertSame(130000, $updated->price_minor);
        $this->assertSame('DZD', $updated->currency);
        $this->assertSame('piece', $updated->unit);
        $this->assertSame(CatalogProductStatus::Published, $updated->status);
    }

    public function test_update_product_action_invalidates_both_old_and_new_slug_keys(): void
    {
        $product = $this->publishedProduct($this->companyA);
        $companyId = (string) $this->companyA->id;

        $oldKey = CatalogPublicCache::productKey($companyId, 'fraiseuse-cnc');
        Cache::put($oldKey, ['stale' => true], 60);

        $updated = app(UpdateCatalogProductAction::class)->execute($product, [
            'name' => 'Fraiseuse CNC XL',
            'price_minor' => 150000,
            'slug' => 'fraiseuse-cnc-xl',
        ]);

        $newKey = CatalogPublicCache::productKey($companyId, 'fraiseuse-cnc-xl');

        $this->assertSame('fraiseuse-cnc-xl', $updated->slug);
        $this->assertFalse(Cache::has($oldKey));
        $this->assertFalse(Cache::has($newKey));
    }

    public function test_update_product_action_keeps_slug_of_other_tenant_untouched(): void
    {
        $productA = $this->publishedProduct($this->companyA);
        $productB = $this->publishedProduct($this->companyB);

        $updated = app(UpdateCatalogProductAction::class)->execute($productA, [
            'name' => 'Fraiseuse CNC XL',
            'price_minor' => 150000,
        ]);

        $this->assertSame('fraiseuse-cnc-xl', $updated->slug);
        $this->assertSame('fraiseuse-cnc', $productB->refresh()->slug);
    }

    public function test_delete_product_action_removes_product_and_purges_slug_key(): void
    {
        $product = $this->publishedProduct($this->companyA);
        $companyId = (string) $this->companyA->id;
        $key = CatalogPublicCache::productKey($companyId, 'fraiseuse-cnc');
        Cache::put($key, ['stale' => true], 60);

        app(DeleteCatalogProductAction::class)->execute($product);

        $this->assertDatabaseMissing('catalog_products', ['id' => $product->id]);
        $this->assertFalse(Cache::has($key));
    }

    public function test_transition_product_status_action_publishes_and_unpublishes(): void
    {
        $action = app(TransitionCatalogProductStatusAction::class);

        $product = $action->execute($this->publishedProduct($this->companyA), CatalogProductStatus::Draft);
        $this->assertSame(CatalogProductStatus::Draft, $product->status);

        $companyId = (string) $this->companyA->id;
        $key = CatalogPublicCache::productKey($companyId, 'fraiseuse-cnc');
        Cache::put($key, ['stale' => true], 60);

        $product = $action->execute($product, CatalogProductStatus::Published);

        $this->assertSame(CatalogProductStatus::Published, $product->status);
        $this->assertFalse(Cache::has($key));
    }

    // ── Catégories ──────────────────────────────────────────────────────

    public function test_create_category_action_derives_slug_and_position_default(): void
    {
        $category = app(CreateCatalogCategoryAction::class)
            ->execute((string) $this->companyA->id, $this->categoryPayload());

        $this->assertSame('machines', $category->slug);
        $this->assertSame(0, $category->position);
        $this->assertNull($category->parent_id);
        $this->assertDatabaseHas('catalog_categories', [
            'id' => $category->id,
            'company_id' => (string) $this->companyA->id,
        ]);
    }

    public function test_create_category_action_resolves_slug_collision_and_purges_snapshot(): void
    {
        $action = app(CreateCatalogCategoryAction::class);
        $key = CatalogPublicCache::snapshotKey((string) $this->companyA->id);

        $first = $action->execute((string) $this->companyA->id, $this->categoryPayload());
        Cache::put($key, ['stale' => true], 60);
        $second = $action->execute((string) $this->companyA->id, $this->categoryPayload(['position' => 3]));

        $this->assertSame('machines', $first->slug);
        $this->assertSame('machines-2', $second->slug);
        $this->assertSame(3, $second->position);
        $this->assertFalse(Cache::has($key));
    }

    public function test_update_category_action_ignores_self_and_keeps_position_when_absent(): void
    {
        /** @var CatalogCategory $category */
        $category = CatalogCategory::query()->create([
            'company_id' => (string) $this->companyA->id,
            'name' => 'Machines',
            'slug' => 'machines',
            'position' => 7,
        ]);

        $updated = app(UpdateCatalogCategoryAction::class)->execute($category, ['name' => 'Machines']);

        $this->assertSame('machines', $updated->slug);
        $this->assertSame(7, $updated->position);
    }

    public function test_delete_category_action_purges_snapshot_and_removes_row(): void
    {
        /** @var CatalogCategory $category */
        $category = CatalogCategory::query()->create([
            'company_id' => (string) $this->companyA->id,
            'name' => 'Machines',
            'slug' => 'machines',
            'position' => 0,
        ]);

        $key = CatalogPublicCache::snapshotKey((string) $this->companyA->id);
        Cache::put($key, ['stale' => true], 60);

        app(DeleteCatalogCategoryAction::class)->execute($category);

        $this->assertDatabaseMissing('catalog_categories', ['id' => $category->id]);
        $this->assertFalse(Cache::has($key));
    }

    // ── Demandes de devis : transitions ─────────────────────────────────

    public function test_transition_inquiry_action_applies_allowed_transition_and_stamps_note(): void
    {
        $inquiry = $this->inquiry($this->companyA, CatalogInquiryStatus::New);

        $updated = app(TransitionCatalogInquiryStatusAction::class)
            ->execute($inquiry, 'contacted', 'Appel telephonique du 28/09');

        $this->assertSame(CatalogInquiryStatus::Contacted, $updated->status);
        $this->assertNotNull($updated->notes);
        $this->assertStringContainsString('Appel telephonique du 28/09', (string) $updated->notes);
        $this->assertStringStartsWith('[', (string) $updated->notes);
    }

    public function test_transition_inquiry_action_appends_note_to_existing_history(): void
    {
        $inquiry = $this->inquiry($this->companyA, CatalogInquiryStatus::New);
        $action = app(TransitionCatalogInquiryStatusAction::class);

        $action->execute($inquiry, 'contacted', 'Premier contact');
        $updated = $action->execute($inquiry, 'quote_sent', 'Devis envoye');

        $notes = (string) $updated->notes;
        $this->assertStringContainsString('Premier contact', $notes);
        $this->assertStringContainsString('Devis envoye', $notes);
        $this->assertSame(2, substr_count($notes, "\n") + 1);
    }

    public function test_transition_inquiry_action_ignores_blank_note(): void
    {
        $inquiry = $this->inquiry($this->companyA, CatalogInquiryStatus::New);

        $updated = app(TransitionCatalogInquiryStatusAction::class)->execute($inquiry, 'contacted', '   ');

        $this->assertNull($updated->notes);
    }

    public function test_transition_inquiry_action_rejects_terminal_status(): void
    {
        $inquiry = $this->inquiry($this->companyA, CatalogInquiryStatus::Closed);

        try {
            app(TransitionCatalogInquiryStatusAction::class)->execute($inquiry, 'contacted');
            $this->fail('Une transition depuis un statut terminal doit être refusée.');
        } catch (InvalidInquiryStatusTransitionException $e) {
            $this->assertSame('closed', $e->currentStatus());
            $this->assertSame('INVALID_INQUIRY_STATUS_TRANSITION', $e->getMessage());
        }

        $this->assertSame(CatalogInquiryStatus::Closed, $inquiry->refresh()->status);
    }

    public function test_transition_inquiry_action_rejects_transition_outside_matrix(): void
    {
        $inquiry = $this->inquiry($this->companyA, CatalogInquiryStatus::New);

        $this->expectException(InvalidInquiryStatusTransitionException::class);

        app(TransitionCatalogInquiryStatusAction::class)->execute($inquiry, 'quote_sent');
    }

    public function test_transition_inquiry_action_rejects_unknown_status(): void
    {
        $inquiry = $this->inquiry($this->companyA, CatalogInquiryStatus::New);

        $this->expectException(InvalidInquiryStatusTransitionException::class);

        app(TransitionCatalogInquiryStatusAction::class)->execute($inquiry, 'archived');
    }

    // ── Demandes de devis : effacement et export ────────────────────────

    public function test_erase_inquiry_action_deletes_and_dispatches_erasure_event(): void
    {
        Event::fake([CatalogInquiryErased::class]);

        $inquiry = $this->inquiry($this->companyA, CatalogInquiryStatus::New, 'acheteur@exemple.dz');

        app(EraseCatalogInquiryAction::class)->execute($inquiry, (string) $this->companyA->id);

        $this->assertDatabaseMissing('catalog_inquiries', ['id' => $inquiry->id]);

        Event::assertDispatched(
            CatalogInquiryErased::class,
            fn (CatalogInquiryErased $event): bool => $event->companyId === (string) $this->companyA->id
                && $event->buyerEmail === 'acheteur@exemple.dz'
                && $event->inquiryId === (int) $inquiry->id
        );
    }

    public function test_export_action_filters_by_status_and_orders_most_recent_first(): void
    {
        $this->inquiry($this->companyA, CatalogInquiryStatus::New, 'un@exemple.dz');
        $this->inquiry($this->companyA, CatalogInquiryStatus::Contacted, 'deux@exemple.dz');
        $closed = $this->inquiry($this->companyA, CatalogInquiryStatus::Closed, 'trois@exemple.dz');
        $otherTenant = $this->inquiry($this->companyB, CatalogInquiryStatus::New, 'autre@exemple.ma');

        $action = app(ExportCatalogInquiriesAction::class);

        $all = $action->execute((string) $this->companyA->id);
        $contacted = $action->execute((string) $this->companyA->id, 'contacted');

        $this->assertCount(3, $all);
        $this->assertSame((int) $closed->id, (int) $all->first()->id);
        $this->assertCount(1, $contacted);
        $this->assertSame('deux@exemple.dz', $contacted->first()->email);
        $this->assertNotContains((int) $otherTenant->id, $all->pluck('id')->all());
    }

    public function test_export_action_honours_limit(): void
    {
        $this->inquiry($this->companyA, CatalogInquiryStatus::New, 'un@exemple.dz');
        $this->inquiry($this->companyA, CatalogInquiryStatus::New, 'deux@exemple.dz');
        $this->inquiry($this->companyA, CatalogInquiryStatus::New, 'trois@exemple.dz');

        $exported = app(ExportCatalogInquiriesAction::class)->execute((string) $this->companyA->id, null, 2);

        $this->assertCount(2, $exported);
    }

    // ── Réception publique d'une demande ───────────────────────────────

    public function test_submit_inquiry_action_creates_minimised_inquiry_and_dispatches_event(): void
    {
        Event::fake([CatalogInquiryReceived::class]);
        $this->publishedProduct($this->companyA);

        $inquiry = app(SubmitCatalogInquiryAction::class)->execute(
            (string) $this->companyA->id,
            [
                'product_slug' => 'fraiseuse-cnc',
                'quantity' => 4,
                'company_name' => 'Ateliers Boussaid SARL',
                'email' => 'achat@boussaid.dz',
                'message' => 'Demande de devis',
            ],
            '203.0.113.7',
        );

        $this->assertInstanceOf(CatalogInquiry::class, $inquiry);
        $this->assertSame((string) $this->companyA->id, $inquiry->company_id);
        $this->assertSame('fraiseuse-cnc', $inquiry->product_slug);
        $this->assertSame('Fraiseuse CNC', $inquiry->product_name);
        $this->assertSame(CatalogInquiryStatus::New, $inquiry->status);
        $this->assertNotNull($inquiry->consent_at);
        $this->assertSame(
            $inquiry->consent_at?->copy()->addDays(90)->toDateString(),
            $inquiry->retention_until?->toDateString()
        );
        $this->assertSame(hash('sha256', '203.0.113.7'), $inquiry->ip_hash);

        Event::assertDispatched(
            CatalogInquiryReceived::class,
            fn (CatalogInquiryReceived $event): bool => $event->companyId === (string) $this->companyA->id
        );
    }

    public function test_submit_inquiry_action_skips_ip_hash_for_local_or_absent_ip(): void
    {
        $this->publishedProduct($this->companyA);
        $action = app(SubmitCatalogInquiryAction::class);

        $payload = [
            'product_slug' => 'fraiseuse-cnc',
            'company_name' => 'Ateliers Boussaid SARL',
            'email' => 'achat@boussaid.dz',
        ];

        $withoutIp = $action->execute((string) $this->companyA->id, $payload, null);
        $localIp = $action->execute((string) $this->companyA->id, $payload, '127.0.0.1');

        $this->assertNull($withoutIp?->ip_hash);
        $this->assertNull($localIp?->ip_hash);
    }

    public function test_submit_inquiry_action_returns_null_when_product_is_not_published(): void
    {
        /** @var CatalogProduct $draft */
        $draft = CatalogProduct::query()->create([
            'company_id' => (string) $this->companyA->id,
            'name' => 'Fraiseuse CNC',
            'slug' => 'fraiseuse-cnc',
            'price_minor' => 125000,
            'currency' => 'DZD',
            'unit' => 'piece',
            'status' => CatalogProductStatus::Draft->value,
        ]);

        $result = app(SubmitCatalogInquiryAction::class)->execute(
            (string) $this->companyA->id,
            [
                'product_slug' => (string) $draft->slug,
                'company_name' => 'Ateliers Boussaid SARL',
                'email' => 'achat@boussaid.dz',
            ],
            null,
        );

        $this->assertNull($result);
        $this->assertDatabaseCount('catalog_inquiries', 0);
    }

    public function test_submit_inquiry_action_does_not_leak_across_tenants(): void
    {
        $this->publishedProduct($this->companyB);

        $result = app(SubmitCatalogInquiryAction::class)->execute(
            (string) $this->companyA->id,
            [
                'product_slug' => 'fraiseuse-cnc',
                'company_name' => 'Ateliers Boussaid SARL',
                'email' => 'achat@boussaid.dz',
            ],
            null,
        );

        // Le produit n'appartient pas au tenant appelant : rien n'est écrit.
        $this->assertNull($result);
        $this->assertDatabaseCount('catalog_inquiries', 0);
    }
}
