<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-902 (#7747) — Commande en ligne PUBLIQUE depuis la page par slug.
 *
 * Couvre : happy path (création par product_code publié, prix + TVA serveur,
 * 201), produit non publié en ligne refusé (422), branche non publique →
 * 404 fail-closed, idempotence par `idempotency_key` (rejeu → même commande,
 * 200), suivi par référence sans PII (note interne jamais renvoyée), suivi
 * borné à LA branche du slug, et paiement public EN LIGNE uniquement (#7728)
 * (PSP carte refusé).
 */
class RestaurantPublicSlugOrderTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeTenant(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        return $company;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makePublicBranch(Company $company, array $attributes = []): RestaurantBranch
    {
        return app(TenantManager::class)->withinTenant(
            $company,
            fn (): RestaurantBranch => RestaurantBranch::factory()->create(array_merge([
                'is_public' => true,
                'status' => 'active',
                'currency' => 'XAF',
            ], $attributes))
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makePublishedProduct(Company $company, array $attributes = []): RestaurantProduct
    {
        return app(TenantManager::class)->withinTenant(
            $company,
            fn (): RestaurantProduct => RestaurantProduct::factory()->create(array_merge([
                'branch_id' => null,
                'is_published_online' => true,
                'is_available' => true,
                'status' => 'active',
                'currency' => 'XAF',
                'tax_rate_id' => null,
                'price_minor' => 2500,
            ], $attributes))
        );
    }

    public function test_creates_public_order_by_slug_with_server_side_prices(): void
    {
        $company = $this->makeTenant();
        $this->makePublicBranch($company, ['public_slug' => 'chez-leo']);
        $product = $this->makePublishedProduct($company, ['code' => 'PLAT-01', 'price_minor' => 2500]);

        $response = $this->postJson('/api/v1/public/restaurants/chez-leo/orders', [
            'customer_name' => 'Awa Ndiaye',
            'customer_phone' => '+237690000001',
            'order_type' => 'pickup',
            'items' => [
                ['product_code' => $product->code, 'quantity' => 2, 'note' => 'sans piment'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.order_type', 'takeaway')
            ->assertJsonPath('data.total_minor', 5000)
            ->assertJsonPath('data.currency', 'XAF')
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.created', true);

        $reference = $response->json('data.reference');
        $this->assertIsString($reference);
        $this->assertStringStartsWith('RST-', $reference);

        // Aucun montant client accepté / aucune PII renvoyée.
        $this->assertArrayNotHasKey('note_redacted', (array) $response->json('data'));
    }

    public function test_rejects_product_not_published_online(): void
    {
        $company = $this->makeTenant();
        $this->makePublicBranch($company, ['public_slug' => 'chez-leo']);
        $product = $this->makePublishedProduct($company, [
            'code' => 'PRIVE-01',
            'is_published_online' => false,
        ]);

        $this->postJson('/api/v1/public/restaurants/chez-leo/orders', [
            'customer_name' => 'Awa Ndiaye',
            'customer_phone' => '+237690000001',
            'items' => [
                ['product_code' => $product->code, 'quantity' => 1],
            ],
        ])->assertStatus(422);
    }

    public function test_rejects_product_of_another_branch(): void
    {
        $company = $this->makeTenant();
        $branch = $this->makePublicBranch($company, ['public_slug' => 'chez-leo']);
        $other = $this->makePublicBranch($company, ['public_slug' => 'chez-ali']);
        $product = $this->makePublishedProduct($company, [
            'code' => 'AUTRE-01',
            'branch_id' => $other->id,
        ]);

        $this->postJson('/api/v1/public/restaurants/chez-leo/orders', [
            'customer_name' => 'Awa Ndiaye',
            'customer_phone' => '+237690000001',
            'items' => [
                ['product_code' => $product->code, 'quantity' => 1],
            ],
        ])->assertStatus(422);

        $this->assertNotSame($branch->id, $product->branch_id);
    }

    public function test_returns_404_when_branch_is_not_public(): void
    {
        $company = $this->makeTenant();
        $this->makePublicBranch($company, ['public_slug' => 'chez-cache', 'is_public' => false]);
        $product = $this->makePublishedProduct($company, ['code' => 'PLAT-02']);

        $this->postJson('/api/v1/public/restaurants/chez-cache/orders', [
            'customer_name' => 'Awa Ndiaye',
            'customer_phone' => '+237690000001',
            'items' => [
                ['product_code' => $product->code, 'quantity' => 1],
            ],
        ])->assertNotFound();
    }

    public function test_idempotency_key_replays_the_same_order(): void
    {
        $company = $this->makeTenant();
        $this->makePublicBranch($company, ['public_slug' => 'chez-leo']);
        $product = $this->makePublishedProduct($company, ['code' => 'PLAT-03']);

        $payload = [
            'customer_name' => 'Awa Ndiaye',
            'customer_phone' => '+237690000001',
            'idempotency_key' => 'test-idem-7747',
            'items' => [
                ['product_code' => $product->code, 'quantity' => 1],
            ],
        ];

        $first = $this->postJson('/api/v1/public/restaurants/chez-leo/orders', $payload)
            ->assertCreated();

        $second = $this->postJson('/api/v1/public/restaurants/chez-leo/orders', $payload)
            ->assertOk()
            ->assertJsonPath('data.created', false);

        $this->assertSame($first->json('data.reference'), $second->json('data.reference'));
    }

    public function test_tracks_order_by_reference_without_pii(): void
    {
        $company = $this->makeTenant();
        $this->makePublicBranch($company, ['public_slug' => 'chez-leo']);
        $product = $this->makePublishedProduct($company, ['code' => 'PLAT-04', 'name' => 'Poulet DG']);

        $reference = $this->postJson('/api/v1/public/restaurants/chez-leo/orders', [
            'customer_name' => 'Awa Ndiaye',
            'customer_phone' => '+237690000001',
            'items' => [
                ['product_code' => $product->code, 'quantity' => 1],
            ],
        ])->assertCreated()->json('data.reference');

        $this->assertIsString($reference);

        $track = $this->getJson('/api/v1/public/restaurants/chez-leo/orders/'.$reference)
            ->assertOk()
            ->assertJsonPath('data.reference', $reference)
            ->assertJsonPath('data.items.0.name', 'Poulet DG');

        // Jamais de PII (note interne « Client: … ») dans la réponse publique.
        $data = (array) $track->json('data');
        $this->assertArrayNotHasKey('note_redacted', $data);
        $content = $track->getContent();
        $this->assertIsString($content);
        $this->assertStringNotContainsString('Awa', $content);
    }

    public function test_tracking_is_scoped_to_the_slug_branch(): void
    {
        $company = $this->makeTenant();
        $this->makePublicBranch($company, ['public_slug' => 'chez-leo']);
        $this->makePublicBranch($company, ['public_slug' => 'chez-ali']);
        $product = $this->makePublishedProduct($company, ['code' => 'PLAT-05']);

        $reference = $this->postJson('/api/v1/public/restaurants/chez-leo/orders', [
            'customer_name' => 'Awa Ndiaye',
            'customer_phone' => '+237690000001',
            'items' => [
                ['product_code' => $product->code, 'quantity' => 1],
            ],
        ])->assertCreated()->json('data.reference');

        $this->assertIsString($reference);

        // La commande appartient à chez-leo : introuvable depuis chez-ali.
        $this->getJson('/api/v1/public/restaurants/chez-ali/orders/'.$reference)
            ->assertNotFound();
    }

    public function test_public_slug_pay_is_online_only_and_fails_closed(): void
    {
        $company = $this->makeTenant();
        $this->makePublicBranch($company, ['public_slug' => 'chez-leo']);
        $product = $this->makePublishedProduct($company, ['code' => 'PLAT-06', 'price_minor' => 3000]);

        $reference = $this->postJson('/api/v1/public/restaurants/chez-leo/orders', [
            'customer_name' => 'Awa Ndiaye',
            'customer_phone' => '+237690000001',
            'items' => [
                ['product_code' => $product->code, 'quantity' => 1],
            ],
        ])->assertCreated()->json('data.reference');

        $this->assertIsString($reference);

        // #7728 — la surface publique n'accepte que les providers EN LIGNE
        // (carte via profil tenant / mobile money) : providers guichet refusés.
        $this->postJson('/api/v1/public/restaurants/chez-leo/orders/'.$reference.'/pay', [
            'provider_code' => 'card',
        ])->assertStatus(422);

        $this->postJson('/api/v1/public/restaurants/chez-leo/orders/'.$reference.'/pay', [
            'provider_code' => 'cash_on_delivery',
        ])->assertStatus(422);

        // Fail-closed : sans profil de paiement tenant actif, même le défaut
        // (provider omis) répond 422 `online_payment_not_configured` — jamais
        // un 500, et le paiement sur place reste proposé en repli.
        $this->postJson('/api/v1/public/restaurants/chez-leo/orders/'.$reference.'/pay')
            ->assertStatus(422)
            ->assertJsonPath('error', 'online_payment_not_configured');
    }
}
