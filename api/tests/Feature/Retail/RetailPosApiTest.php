<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Models\RetailInventoryMovement;
use App\Modules\Retail\Domain\Models\RetailStockLevel;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-17 RETAIL (#7674) — POS v1 du module vendeur : sessions de caisse
 * (une seule session ouverte par emplacement, cloture avec attendu/ecart),
 * commandes de vente (totaux serveur, idempotence), paiements multi-moyens
 * (completion → decrement de stock trace, survente auto-ajustee, annulation
 * → mouvements return), RBAC deny-by-default, isolation tenant et gate
 * feature flag retail.
 */
class RetailPosApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Company $companyNoFlag;

    private Employee $principalA;

    private Employee $employeeA;

    private Employee $principalB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $companyA->setFeature('retail', true);
        $companyA->save();
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $companyB->setFeature('retail', true);
        $companyB->save();
        $this->companyB = $companyB;

        /** @var Company $companyNoFlag */
        $companyNoFlag = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyNoFlag = $companyNoFlag;

        $this->principalA = $this->employee($this->companyA, 'principal');
        $this->employeeA = $this->employee($this->companyA, 'employee');
        $this->principalB = $this->employee($this->companyB, 'principal');
    }

    private function employee(Company $company, string $managerRole = 'employee'): Employee
    {
        $attributes = [
            'company_id' => $company->id,
            'status' => 'active',
        ];

        if ($managerRole === 'employee') {
            $attributes['role'] = 'employee';
        } else {
            $attributes['role'] = 'manager';
            $attributes['manager_role'] = $managerRole;
        }

        /** @var Employee $employee */
        $employee = Employee::factory()->create($attributes);

        return $employee;
    }

    private function actingAsUser(Employee $employee): void
    {
        Sanctum::actingAs($employee);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storeLocation(Employee $actor, array $overrides = []): array
    {
        $this->actingAsUser($actor);

        return $this->postJson('/api/v1/retail/locations', array_merge([
            'name' => 'Boutique centre-ville',
            'code' => 'STORE-01',
        ], $overrides))
            ->assertStatus(201)
            ->json('data');
    }

    /**
     * Cree un produit PUBLIE (vendable en caisse).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storePublishedProduct(Employee $actor, array $overrides = []): array
    {
        $this->actingAsUser($actor);

        $product = $this->postJson('/api/v1/retail/products', array_merge([
            'name' => 'Jus de bissap 50cl',
            'sku' => 'SKU-BISSAP-50',
            'price_minor' => 1_500,
            'currency' => 'XOF',
        ], $overrides))
            ->assertStatus(201)
            ->json('data');

        return $this->postJson("/api/v1/retail/products/{$product['id']}/publish")
            ->assertStatus(200)
            ->json('data');
    }

    /**
     * @return array<string, mixed>
     */
    private function openSession(Employee $actor, int $locationId, int $openingCashMinor = 0): array
    {
        $this->actingAsUser($actor);

        return $this->postJson('/api/v1/retail/pos/sessions', [
            'location_id' => $locationId,
            'opening_cash_minor' => $openingCashMinor,
        ])
            ->assertStatus(201)
            ->json('data');
    }

    /**
     * Applique un mouvement de stock d'inventaire (mise en place des tests).
     */
    private function seedStock(Employee $actor, int $locationId, int $productId, float $quantity): void
    {
        $this->actingAsUser($actor);

        $this->postJson('/api/v1/retail/stock/movements', [
            'location_id' => $locationId,
            'product_id' => $productId,
            'quantity_delta' => $quantity,
            'reason_code' => 'purchase',
        ])->assertStatus(201);
    }

    private function levelQuantity(int $locationId, int $productId): string
    {
        /** @var RetailStockLevel $level */
        $level = RetailStockLevel::query()
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->firstOrFail();

        return (string) $level->quantity;
    }

    public function test_open_session_and_reject_second_open_on_same_location(): void
    {
        $location = $this->storeLocation($this->principalA);

        $session = $this->openSession($this->principalA, (int) $location['id'], 10_000);
        $this->assertSame('open', $session['status']);
        $this->assertSame(10_000, $session['opening_cash_minor']);
        $this->assertSame((int) $this->principalA->id, $session['opened_by_user_id']);

        // Deuxieme ouverture sur le meme emplacement → 422.
        $this->postJson('/api/v1/retail/pos/sessions', ['location_id' => (int) $location['id']])
            ->assertStatus(422);

        // Un AUTRE emplacement du meme tenant peut ouvrir sa session.
        $other = $this->storeLocation($this->principalA, ['code' => 'STORE-02']);
        $this->openSession($this->principalA, (int) $other['id']);
    }

    public function test_create_order_with_two_lines_computes_totals_server_side(): void
    {
        $location = $this->storeLocation($this->principalA);
        $bissap = $this->storePublishedProduct($this->principalA);
        $the = $this->storePublishedProduct($this->principalA, [
            'name' => 'The a la menthe',
            'sku' => 'SKU-THE-01',
            'price_minor' => 700,
        ]);
        $session = $this->openSession($this->principalA, (int) $location['id']);

        $order = $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $session['id'],
            'lines' => [
                ['product_id' => (int) $bissap['id'], 'quantity' => 2],
                ['product_id' => (int) $the['id'], 'quantity' => 3],
            ],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.source', 'pos')
            ->assertJsonPath('data.currency', 'XOF')
            ->json('data');

        // Totaux serveur : 2*1500 + 3*700 = 5100.
        $this->assertSame(5_100, $order['subtotal_minor']);
        $this->assertSame(5_100, $order['total_minor']);
        $this->assertStringStartsWith('POS-', $order['reference']);
        $this->assertCount(2, $order['items']);
        $this->assertSame('Jus de bissap 50cl', $order['items'][0]['product_name']);
        $this->assertSame(1_500, $order['items'][0]['unit_price_minor']);
        $this->assertSame(3_000, $order['items'][0]['line_total_minor']);
        $this->assertSame(2_100, $order['items'][1]['line_total_minor']);
    }

    public function test_full_cash_payment_completes_order_and_decrements_stock(): void
    {
        $location = $this->storeLocation($this->principalA);
        $product = $this->storePublishedProduct($this->principalA);
        $this->seedStock($this->principalA, (int) $location['id'], (int) $product['id'], 10);
        $session = $this->openSession($this->principalA, (int) $location['id']);

        $order = $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $session['id'],
            'lines' => [['product_id' => (int) $product['id'], 'quantity' => 4]],
        ])->assertStatus(201)->json('data');

        $paid = $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/payments", [
            'method' => 'cash',
            'amount_minor' => 6_000,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'completed')
            ->json('data');

        $this->assertCount(1, $paid['payments']);
        $this->assertSame('captured', $paid['payments'][0]['status']);

        // Stock decremente : 10 - 4 = 6, mouvement `sale` trace.
        $this->assertSame('6.000', $this->levelQuantity((int) $location['id'], (int) $product['id']));

        $sale = RetailInventoryMovement::query()
            ->where('company_id', $this->companyA->id)
            ->where('reason_code', 'sale')
            ->where('reference_type', 'retail_order')
            ->where('reference_id', (int) $order['id'])
            ->sole();
        $this->assertSame('-4.000', (string) $sale->quantity_delta);
        $this->assertSame((int) $this->principalA->id, $sale->user_id);
    }

    public function test_oversell_creates_traced_auto_adjustment_and_completes_sale(): void
    {
        $location = $this->storeLocation($this->principalA);
        $product = $this->storePublishedProduct($this->principalA);
        // Stock theorique de 1 seulement, mais 3 vendus en caisse.
        $this->seedStock($this->principalA, (int) $location['id'], (int) $product['id'], 1);
        $session = $this->openSession($this->principalA, (int) $location['id']);

        $order = $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $session['id'],
            'lines' => [['product_id' => (int) $product['id'], 'quantity' => 3]],
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/payments", [
            'method' => 'card',
            'amount_minor' => 4_500,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'completed');

        // La vente n'est PAS bloquee : ajustement trace +2 puis vente -3 → 0.
        $this->assertSame('0.000', $this->levelQuantity((int) $location['id'], (int) $product['id']));

        $movements = RetailInventoryMovement::query()
            ->where('company_id', $this->companyA->id)
            ->where('reference_type', 'retail_order')
            ->where('reference_id', (int) $order['id'])
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $movements);

        $adjustment = RetailInventoryMovement::query()
            ->where('company_id', $this->companyA->id)
            ->where('reference_type', 'retail_order')
            ->where('reference_id', (int) $order['id'])
            ->where('reason_code', 'adjustment')
            ->sole();
        $this->assertSame('2.000', (string) $adjustment->quantity_delta);
        $this->assertStringContainsString('Auto adjustment on oversell', (string) $adjustment->note);

        $sale = RetailInventoryMovement::query()
            ->where('company_id', $this->companyA->id)
            ->where('reference_type', 'retail_order')
            ->where('reference_id', (int) $order['id'])
            ->where('reason_code', 'sale')
            ->sole();
        $this->assertSame('-3.000', (string) $sale->quantity_delta);
        $this->assertGreaterThan($adjustment->id, $sale->id);
    }

    public function test_partial_payment_keeps_draft_then_second_payment_completes(): void
    {
        $location = $this->storeLocation($this->principalA);
        $product = $this->storePublishedProduct($this->principalA);
        $this->seedStock($this->principalA, (int) $location['id'], (int) $product['id'], 5);
        $session = $this->openSession($this->principalA, (int) $location['id']);

        $order = $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $session['id'],
            'lines' => [['product_id' => (int) $product['id'], 'quantity' => 2]],
        ])->assertStatus(201)->json('data');

        // 1er paiement partiel (cash 1000 / 3000) : la commande reste draft.
        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/payments", [
            'method' => 'cash',
            'amount_minor' => 1_000,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');

        // Aucun decrement de stock tant que la commande n'est pas completee.
        $this->assertSame('5.000', $this->levelQuantity((int) $location['id'], (int) $product['id']));

        // 2e paiement (mobile 2000) : total atteint → completed + stock -2.
        $completed = $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/payments", [
            'method' => 'mobile',
            'amount_minor' => 2_000,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'completed')
            ->json('data');

        $this->assertCount(2, $completed['payments']);
        $this->assertSame('3.000', $this->levelQuantity((int) $location['id'], (int) $product['id']));
    }

    public function test_payment_idempotency_key_prevents_duplicate_payment(): void
    {
        $location = $this->storeLocation($this->principalA);
        $product = $this->storePublishedProduct($this->principalA);
        $this->seedStock($this->principalA, (int) $location['id'], (int) $product['id'], 5);
        $session = $this->openSession($this->principalA, (int) $location['id']);

        $order = $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $session['id'],
            'lines' => [['product_id' => (int) $product['id'], 'quantity' => 1]],
        ])->assertStatus(201)->json('data');

        $payload = [
            'method' => 'cash',
            'amount_minor' => 1_500,
            'idempotency_key' => 'pay-key-0001',
        ];

        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/payments", $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'completed');

        // Rejeu avec la meme cle : AUCUN paiement supplementaire.
        $replay = $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/payments", $payload)
            ->assertStatus(201)
            ->json('data');

        $this->assertCount(1, $replay['payments']);
        $this->assertSame('completed', $replay['status']);

        // Idempotence de creation de commande : meme cle → meme commande.
        $orderPayload = [
            'pos_session_id' => (int) $session['id'],
            'lines' => [['product_id' => (int) $product['id'], 'quantity' => 1]],
            'idempotency_key' => 'order-key-0001',
        ];
        $first = $this->postJson('/api/v1/retail/pos/orders', $orderPayload)
            ->assertStatus(201)->json('data');
        $second = $this->postJson('/api/v1/retail/pos/orders', $orderPayload)
            ->assertStatus(201)->json('data');
        $this->assertSame($first['id'], $second['id']);
    }

    public function test_cancel_completed_order_creates_return_movements_restoring_stock(): void
    {
        $location = $this->storeLocation($this->principalA);
        $product = $this->storePublishedProduct($this->principalA);
        $this->seedStock($this->principalA, (int) $location['id'], (int) $product['id'], 8);
        $session = $this->openSession($this->principalA, (int) $location['id']);

        $order = $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $session['id'],
            'lines' => [['product_id' => (int) $product['id'], 'quantity' => 3]],
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/payments", [
            'method' => 'cash',
            'amount_minor' => 4_500,
        ])->assertStatus(201);

        $this->assertSame('5.000', $this->levelQuantity((int) $location['id'], (int) $product['id']));

        // Annulation de la commande completee → mouvement return +3.
        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/cancel", [
            'note' => 'Client refund',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('8.000', $this->levelQuantity((int) $location['id'], (int) $product['id']));

        $return = RetailInventoryMovement::query()
            ->where('company_id', $this->companyA->id)
            ->where('reason_code', 'return')
            ->where('reference_type', 'retail_order')
            ->where('reference_id', (int) $order['id'])
            ->sole();
        $this->assertSame('3.000', (string) $return->quantity_delta);

        // Annulation d'une commande DRAFT : pas de mouvement de stock.
        $draft = $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $session['id'],
            'lines' => [['product_id' => (int) $product['id'], 'quantity' => 1]],
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/retail/pos/orders/{$draft['id']}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
        $this->assertSame('8.000', $this->levelQuantity((int) $location['id'], (int) $product['id']));
    }

    public function test_close_session_computes_expected_cash_and_variance(): void
    {
        $location = $this->storeLocation($this->principalA);
        $product = $this->storePublishedProduct($this->principalA);
        $this->seedStock($this->principalA, (int) $location['id'], (int) $product['id'], 10);
        $session = $this->openSession($this->principalA, (int) $location['id'], 10_000);

        $order = $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $session['id'],
            'lines' => [['product_id' => (int) $product['id'], 'quantity' => 2]],
        ])->assertStatus(201)->json('data');

        // 1000 en cash + 2000 en carte : seul le CASH compte dans l'attendu.
        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/payments", [
            'method' => 'cash',
            'amount_minor' => 1_000,
        ])->assertStatus(201);
        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/payments", [
            'method' => 'card',
            'amount_minor' => 2_000,
        ])->assertStatus(201);

        // Attendu = 10000 + 1000 = 11000 ; compte 10500 → ecart -500.
        $closed = $this->postJson("/api/v1/retail/pos/sessions/{$session['id']}/close", [
            'counted_cash_minor' => 10_500,
            'variance_reason' => 'Missing coins',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed')
            ->json('data');

        $this->assertSame(11_000, $closed['expected_cash_minor']);
        $this->assertSame(10_500, $closed['counted_cash_minor']);
        $this->assertSame(-500, $closed['variance_minor']);
        $this->assertSame('Missing coins', $closed['variance_reason']);
        $this->assertSame((int) $this->principalA->id, $closed['closed_by_user_id']);
        $this->assertSame(2, $closed['version']);

        // Re-cloture d'une session fermee → 422.
        $this->postJson("/api/v1/retail/pos/sessions/{$session['id']}/close", [
            'counted_cash_minor' => 0,
        ])->assertStatus(422);
    }

    public function test_two_closed_sessions_can_coexist_on_same_location(): void
    {
        // Regression de la deviation #7674 : l'unique partiel `WHERE status
        // = open` (contrairement au pattern restaurant #6173) doit permettre
        // plusieurs sessions FERMEES sur le meme emplacement.
        $location = $this->storeLocation($this->principalA);

        $first = $this->openSession($this->principalA, (int) $location['id']);
        $this->postJson("/api/v1/retail/pos/sessions/{$first['id']}/close", [
            'counted_cash_minor' => 0,
        ])->assertStatus(200);

        $second = $this->openSession($this->principalA, (int) $location['id']);
        $this->postJson("/api/v1/retail/pos/sessions/{$second['id']}/close", [
            'counted_cash_minor' => 0,
        ])->assertStatus(200);

        $this->getJson('/api/v1/retail/pos/sessions?status=closed&location_id='.$location['id'])
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_orders_require_open_session_and_published_products(): void
    {
        $location = $this->storeLocation($this->principalA);
        $product = $this->storePublishedProduct($this->principalA);
        $session = $this->openSession($this->principalA, (int) $location['id']);

        // Produit non publie (draft) → 422.
        $this->actingAsUser($this->principalA);
        $draftProduct = $this->postJson('/api/v1/retail/products', [
            'name' => 'Produit brouillon',
            'sku' => 'SKU-DRAFT-01',
            'price_minor' => 500,
            'currency' => 'XOF',
        ])->assertStatus(201)->json('data');

        $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $session['id'],
            'lines' => [['product_id' => (int) $draftProduct['id'], 'quantity' => 1]],
        ])->assertStatus(422);

        // Session fermee → 422.
        $this->postJson("/api/v1/retail/pos/sessions/{$session['id']}/close", [
            'counted_cash_minor' => 0,
        ])->assertStatus(200);

        $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $session['id'],
            'lines' => [['product_id' => (int) $product['id'], 'quantity' => 1]],
        ])->assertStatus(422);

        // Lignes vides / quantite invalide → 422 (FormRequest).
        $newSession = $this->openSession($this->principalA, (int) $location['id']);
        $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $newSession['id'],
            'lines' => [],
        ])->assertStatus(422);
        $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $newSession['id'],
            'lines' => [['product_id' => (int) $product['id'], 'quantity' => 0]],
        ])->assertStatus(422);
        $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $newSession['id'],
            'lines' => [['product_id' => (int) $product['id'], 'quantity' => 1.5555]],
        ])->assertStatus(422);
    }

    public function test_employee_cannot_operate_pos_but_can_read(): void
    {
        $location = $this->storeLocation($this->principalA);
        $product = $this->storePublishedProduct($this->principalA);
        $session = $this->openSession($this->principalA, (int) $location['id']);

        $order = $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $session['id'],
            'lines' => [['product_id' => (int) $product['id'], 'quantity' => 1]],
        ])->assertStatus(201)->json('data');

        $this->actingAsUser($this->employeeA);

        // Ecritures : 403 (RBAC deny-by-default, miroir restaurant/retail).
        $this->postJson('/api/v1/retail/pos/sessions', ['location_id' => (int) $location['id']])
            ->assertStatus(403);
        $this->postJson("/api/v1/retail/pos/sessions/{$session['id']}/close", ['counted_cash_minor' => 0])
            ->assertStatus(403);
        $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $session['id'],
            'lines' => [['product_id' => (int) $product['id'], 'quantity' => 1]],
        ])->assertStatus(403);
        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/payments", [
            'method' => 'cash',
            'amount_minor' => 100,
        ])->assertStatus(403);
        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/cancel")
            ->assertStatus(403);

        // Lectures : autorisees aux membres du tenant.
        $this->getJson('/api/v1/retail/pos/sessions')->assertStatus(200);
        $this->getJson("/api/v1/retail/pos/sessions/{$session['id']}")->assertStatus(200);
        $this->getJson('/api/v1/retail/pos/orders')->assertStatus(200);
        $this->getJson("/api/v1/retail/pos/orders/{$order['id']}")
            ->assertStatus(200)
            ->assertJsonPath('data.reference', $order['reference']);
    }

    public function test_cross_tenant_pos_resources_return_404(): void
    {
        $location = $this->storeLocation($this->principalA);
        $product = $this->storePublishedProduct($this->principalA);
        $session = $this->openSession($this->principalA, (int) $location['id']);

        $order = $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $session['id'],
            'lines' => [['product_id' => (int) $product['id'], 'quantity' => 1]],
        ])->assertStatus(201)->json('data');

        $this->actingAsUser($this->principalB);

        $this->getJson("/api/v1/retail/pos/sessions/{$session['id']}")->assertStatus(404);
        $this->postJson("/api/v1/retail/pos/sessions/{$session['id']}/close", ['counted_cash_minor' => 0])
            ->assertStatus(404);
        $this->getJson("/api/v1/retail/pos/orders/{$order['id']}")->assertStatus(404);
        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/payments", [
            'method' => 'cash',
            'amount_minor' => 100,
        ])->assertStatus(404);
        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/cancel")->assertStatus(404);

        // Session d'un autre tenant dans le payload → 422 (Rule::exists scoped).
        $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $session['id'],
            'lines' => [['product_id' => (int) $product['id'], 'quantity' => 1]],
        ])->assertStatus(422);
    }

    public function test_feature_flag_gate_returns_403_when_disabled(): void
    {
        /** @var Employee $manager */
        $manager = $this->employee($this->companyNoFlag, 'principal');
        $this->actingAsUser($manager);

        $this->getJson('/api/v1/retail/pos/sessions')
            ->assertStatus(403)
            ->assertJsonPath('error', 'FEATURE_NOT_ENABLED');

        $this->postJson('/api/v1/retail/pos/sessions', ['location_id' => 1])
            ->assertStatus(403)
            ->assertJsonPath('error', 'FEATURE_NOT_ENABLED');

        $this->getJson('/api/v1/retail/pos/orders')
            ->assertStatus(403)
            ->assertJsonPath('error', 'FEATURE_NOT_ENABLED');
    }
}
