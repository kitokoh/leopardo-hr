<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Modules\Retail\Domain\Models\RetailCashMovement;
use App\Modules\Retail\Domain\Models\RetailPosRefund;
use App\Modules\Retail\Domain\Models\RetailStockLevel;
use App\Modules\Retail\Domain\Permissions\RetailPermissions;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\Support\AssignsResourceAccess;
use Tests\TestCase;

/**
 * #9207 / #9208 / #9209 / #9212 — POS Retail : RBAC ressource-scope
 * (`retail_location`), remboursement en caisse, mouvements de tiroir-caisse
 * et rapports de vente.
 *
 * Le point central de #9207 : un VENDEUR (employe non-manager, assigne
 * `operate` sur une boutique) doit pouvoir tenir la caisse — ouvrir une
 * session, encaisser, creer une commande — sans recevoir le role `principal`
 * (qui lui ouvrirait la paie et les RH). Avant ce correctif, toutes les
 * policies Retail exigeaient `hasManagerRole('principal','rh')`.
 */
class RetailPosRbacAndReportsTest extends TestCase
{
    use AssignsResourceAccess;
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $principal;

    private Employee $seller;

    private Employee $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $company->setFeature('retail', true);
        $company->save();
        $this->company = $company;

        $this->principal = $this->employee('principal');
        $this->seller = $this->employee('employee');
        $this->outsider = $this->employee('employee');
    }

    private function employee(string $managerRole): Employee
    {
        $attributes = [
            'company_id' => $this->company->id,
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
            'cost_minor' => 900,
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

    /**
     * @return array<string, mixed>
     */
    private function completedOrder(Employee $actor, int $locationId, int $productId, float $quantity = 2): array
    {
        $session = $this->openSession($actor, $locationId);

        $order = $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $session['id'],
            'lines' => [['product_id' => $productId, 'quantity' => $quantity]],
        ])->assertStatus(201)->json('data');

        return $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/payments", [
            'method' => 'cash',
            'amount_minor' => (int) $order['total_minor'],
        ])->assertStatus(201)->json('data');
    }

    // ── #9207 : RBAC ressource-scope ────────────────────────────────────────

    public function test_seller_assigned_operate_can_operate_the_pos(): void
    {
        $location = $this->storeLocation($this->principal);
        $product = $this->storePublishedProduct($this->principal);
        $this->seedStock($this->principal, (int) $location['id'], (int) $product['id'], 10);

        // Le vendeur est assigne `operate` sur CETTE boutique.
        $this->assignResourceAccess(
            $this->seller,
            RetailPermissions::RESOURCE_TYPE,
            (int) $location['id'],
            EmployeeResourceAssignment::LEVEL_OPERATE,
            $this->principal,
        );

        // Il ouvre la caisse, cree une commande et encaisse — sans role principal.
        $session = $this->openSession($this->seller, (int) $location['id'], 5_000);
        $this->assertSame('open', $session['status']);

        $order = $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $session['id'],
            'lines' => [['product_id' => (int) $product['id'], 'quantity' => 2]],
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/payments", [
            'method' => 'cash',
            'amount_minor' => 3_000,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'completed');

        // Le stock est decremente : 10 - 2 = 8.
        $this->assertSame('8.000', $this->levelQuantity((int) $location['id'], (int) $product['id']));
    }

    public function test_seller_cannot_manage_settings_but_can_manage_catalog(): void
    {
        $location = $this->storeLocation($this->principal);

        $this->assignResourceAccess(
            $this->seller,
            RetailPermissions::RESOURCE_TYPE,
            (int) $location['id'],
            EmployeeResourceAssignment::LEVEL_OPERATE,
            $this->principal,
        );

        $this->actingAsUser($this->seller);

        // `retail.settings` reste direction seule : ouvrir une boutique → 403.
        $this->postJson('/api/v1/retail/locations', ['name' => 'Nouvelle', 'code' => 'STORE-99'])
            ->assertStatus(403);

        // `retail.catalog` est ouvert au vendeur (corriger un prix en rayon).
        $this->postJson('/api/v1/retail/products', [
            'name' => 'The a la menthe',
            'sku' => 'SKU-THE-01',
            'price_minor' => 700,
            'currency' => 'XOF',
        ])->assertStatus(201);
    }

    public function test_employee_without_assignment_is_denied_when_scoping_is_active(): void
    {
        $location = $this->storeLocation($this->principal);

        // Le scoping devient actif des qu'une assignation existe pour le type.
        $this->assignResourceAccess(
            $this->seller,
            RetailPermissions::RESOURCE_TYPE,
            (int) $location['id'],
            EmployeeResourceAssignment::LEVEL_OPERATE,
            $this->principal,
        );

        $this->actingAsUser($this->outsider);

        // Non assigne → fail-closed : 403 sur l'ouverture de caisse.
        $this->postJson('/api/v1/retail/pos/sessions', ['location_id' => (int) $location['id']])
            ->assertStatus(403);

        // La lecture reste ouverte aux membres du tenant.
        $this->getJson('/api/v1/retail/pos/sessions')->assertStatus(200);
    }

    public function test_seller_cannot_close_a_session_of_another_location(): void
    {
        $locationA = $this->storeLocation($this->principal);
        $locationB = $this->storeLocation($this->principal, ['code' => 'STORE-02']);

        $sessionB = $this->openSession($this->principal, (int) $locationB['id']);

        // Le vendeur n'est assigne que sur la boutique A.
        $this->assignResourceAccess(
            $this->seller,
            RetailPermissions::RESOURCE_TYPE,
            (int) $locationA['id'],
            EmployeeResourceAssignment::LEVEL_OPERATE,
            $this->principal,
        );

        $this->actingAsUser($this->seller);

        $this->postJson("/api/v1/retail/pos/sessions/{$sessionB['id']}/close", [
            'counted_cash_minor' => 0,
        ])->assertStatus(403);
    }

    public function test_principal_keeps_full_access_without_any_assignment(): void
    {
        $location = $this->storeLocation($this->principal);

        // Aucune assignation : comportement historique, direction seule.
        $this->actingAsUser($this->principal);

        $this->postJson('/api/v1/retail/pos/sessions', ['location_id' => (int) $location['id']])
            ->assertStatus(201);
    }

    // ── #9208 : remboursement en caisse ─────────────────────────────────────

    public function test_full_refund_restores_stock_and_is_traced(): void
    {
        $location = $this->storeLocation($this->principal);
        $product = $this->storePublishedProduct($this->principal);
        $this->seedStock($this->principal, (int) $location['id'], (int) $product['id'], 10);

        $order = $this->completedOrder($this->principal, (int) $location['id'], (int) $product['id'], 2);
        $this->assertSame('8.000', $this->levelQuantity((int) $location['id'], (int) $product['id']));

        $this->actingAsUser($this->principal);

        $refunded = $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/refund", [
            'amount_minor' => 3_000,
            'reason' => 'Client a rapporte l article',
            'method' => 'cash',
        ])
            ->assertStatus(201)
            ->json('data');

        $this->assertCount(1, $refunded['refunds']);
        $this->assertSame(3_000, $refunded['refunds'][0]['amount_minor']);
        $this->assertSame('Client a rapporte l article', $refunded['refunds'][0]['reason']);

        // Remboursement total → le stock revient a 10.
        $this->assertSame('10.000', $this->levelQuantity((int) $location['id'], (int) $product['id']));
    }

    public function test_partial_refund_does_not_touch_stock_without_restock_lines(): void
    {
        $location = $this->storeLocation($this->principal);
        $product = $this->storePublishedProduct($this->principal);
        $this->seedStock($this->principal, (int) $location['id'], (int) $product['id'], 10);

        $order = $this->completedOrder($this->principal, (int) $location['id'], (int) $product['id'], 2);

        $this->actingAsUser($this->principal);

        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/refund", [
            'amount_minor' => 1_000,
            'reason' => 'Geste commercial',
            'method' => 'cash',
        ])->assertStatus(201);

        // Remboursement partiel sans detail de lignes : stock inchange.
        $this->assertSame('8.000', $this->levelQuantity((int) $location['id'], (int) $product['id']));
    }

    public function test_cumulative_refunds_cannot_exceed_order_total(): void
    {
        $location = $this->storeLocation($this->principal);
        $product = $this->storePublishedProduct($this->principal);
        $this->seedStock($this->principal, (int) $location['id'], (int) $product['id'], 10);

        $order = $this->completedOrder($this->principal, (int) $location['id'], (int) $product['id'], 2);

        $this->actingAsUser($this->principal);

        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/refund", [
            'amount_minor' => 2_000,
            'reason' => 'Premier remboursement',
            'method' => 'cash',
        ])->assertStatus(201);

        // 2000 + 2000 > 3000 → 422.
        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/refund", [
            'amount_minor' => 2_000,
            'reason' => 'Trop',
            'method' => 'cash',
        ])->assertStatus(422);
    }

    public function test_refund_requires_reason_and_completed_order(): void
    {
        $location = $this->storeLocation($this->principal);
        $product = $this->storePublishedProduct($this->principal);
        $this->seedStock($this->principal, (int) $location['id'], (int) $product['id'], 10);

        $order = $this->completedOrder($this->principal, (int) $location['id'], (int) $product['id'], 1);

        $this->actingAsUser($this->principal);

        // Motif manquant → 422 (FormRequest).
        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/refund", [
            'amount_minor' => 500,
            'method' => 'cash',
        ])->assertStatus(422);

        // Commande brouillon → 422 (service).
        $session = $this->openSession($this->principal, (int) $location['id']);
        $draft = $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $session['id'],
            'lines' => [['product_id' => (int) $product['id'], 'quantity' => 1]],
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/retail/pos/orders/{$draft['id']}/refund", [
            'amount_minor' => 500,
            'reason' => 'Trop tot',
            'method' => 'cash',
        ])->assertStatus(422);
    }

    public function test_refund_idempotency_key_prevents_double_refund(): void
    {
        $location = $this->storeLocation($this->principal);
        $product = $this->storePublishedProduct($this->principal);
        $this->seedStock($this->principal, (int) $location['id'], (int) $product['id'], 10);

        $order = $this->completedOrder($this->principal, (int) $location['id'], (int) $product['id'], 2);

        $this->actingAsUser($this->principal);

        $payload = [
            'amount_minor' => 1_000,
            'reason' => 'Rejeu hors-ligne',
            'method' => 'cash',
            'idempotency_key' => 'refund-key-0001',
        ];

        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/refund", $payload)->assertStatus(201);
        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/refund", $payload)->assertStatus(201);

        $this->assertSame(1, RetailPosRefund::query()
            ->where('company_id', $this->company->id)
            ->where('order_id', (int) $order['id'])
            ->count());
    }

    public function test_cash_refund_reduces_expected_cash_at_close(): void
    {
        $location = $this->storeLocation($this->principal);
        $product = $this->storePublishedProduct($this->principal);
        $this->seedStock($this->principal, (int) $location['id'], (int) $product['id'], 10);

        $session = $this->openSession($this->principal, (int) $location['id'], 10_000);

        $order = $this->postJson('/api/v1/retail/pos/orders', [
            'pos_session_id' => (int) $session['id'],
            'lines' => [['product_id' => (int) $product['id'], 'quantity' => 2]],
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/payments", [
            'method' => 'cash',
            'amount_minor' => 3_000,
        ])->assertStatus(201);

        // Remboursement cash de 1000 : l'attendu passe de 13000 a 12000.
        $this->postJson("/api/v1/retail/pos/orders/{$order['id']}/refund", [
            'amount_minor' => 1_000,
            'reason' => 'Retour partiel',
            'method' => 'cash',
        ])->assertStatus(201);

        $closed = $this->postJson("/api/v1/retail/pos/sessions/{$session['id']}/close", [
            'counted_cash_minor' => 12_000,
        ])
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(12_000, $closed['expected_cash_minor']);
        $this->assertSame(0, $closed['variance_minor']);
    }

    // ── #9212 : mouvements de tiroir-caisse ─────────────────────────────────

    public function test_cash_movements_are_traced_and_affect_expected_cash(): void
    {
        $location = $this->storeLocation($this->principal);
        $session = $this->openSession($this->principal, (int) $location['id'], 10_000);

        $this->actingAsUser($this->principal);

        // Entree de monnaie +2000.
        $this->postJson("/api/v1/retail/pos/sessions/{$session['id']}/cash-movements", [
            'type' => 'in',
            'amount_minor' => 2_000,
            'reason' => 'Appoint de monnaie',
        ])->assertStatus(201);

        // Prelevement banque -5000.
        $this->postJson("/api/v1/retail/pos/sessions/{$session['id']}/cash-movements", [
            'type' => 'drop',
            'amount_minor' => 5_000,
            'reason' => 'Remise en banque',
        ])->assertStatus(201);

        // Attendu = 10000 + 2000 - 5000 = 7000.
        $closed = $this->postJson("/api/v1/retail/pos/sessions/{$session['id']}/close", [
            'counted_cash_minor' => 7_000,
        ])
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(7_000, $closed['expected_cash_minor']);
        $this->assertSame(0, $closed['variance_minor']);

        $this->assertSame(2, RetailCashMovement::query()
            ->where('company_id', $this->company->id)
            ->where('pos_session_id', (int) $session['id'])
            ->count());
    }

    public function test_cash_movement_requires_reason_and_open_session(): void
    {
        $location = $this->storeLocation($this->principal);
        $session = $this->openSession($this->principal, (int) $location['id']);

        $this->actingAsUser($this->principal);

        // Motif manquant → 422.
        $this->postJson("/api/v1/retail/pos/sessions/{$session['id']}/cash-movements", [
            'type' => 'in',
            'amount_minor' => 1_000,
        ])->assertStatus(422);

        // Type inconnu → 422.
        $this->postJson("/api/v1/retail/pos/sessions/{$session['id']}/cash-movements", [
            'type' => 'teleport',
            'amount_minor' => 1_000,
            'reason' => 'N importe quoi',
        ])->assertStatus(422);

        // Session fermee → 422.
        $this->postJson("/api/v1/retail/pos/sessions/{$session['id']}/close", [
            'counted_cash_minor' => 0,
        ])->assertStatus(200);

        $this->postJson("/api/v1/retail/pos/sessions/{$session['id']}/cash-movements", [
            'type' => 'in',
            'amount_minor' => 1_000,
            'reason' => 'Trop tard',
        ])->assertStatus(422);
    }

    // ── #9209 : rapports ────────────────────────────────────────────────────

    public function test_sales_report_aggregates_server_side(): void
    {
        $location = $this->storeLocation($this->principal);
        $bissap = $this->storePublishedProduct($this->principal);
        $the = $this->storePublishedProduct($this->principal, [
            'name' => 'The a la menthe',
            'sku' => 'SKU-THE-01',
            'price_minor' => 700,
            'cost_minor' => 300,
        ]);
        $this->seedStock($this->principal, (int) $location['id'], (int) $bissap['id'], 20);
        $this->seedStock($this->principal, (int) $location['id'], (int) $the['id'], 20);

        $this->completedOrder($this->principal, (int) $location['id'], (int) $bissap['id'], 2);
        $this->completedOrder($this->principal, (int) $location['id'], (int) $the['id'], 3);

        $this->actingAsUser($this->principal);

        $report = $this->getJson('/api/v1/retail/reports/sales')
            ->assertStatus(200)
            ->json('data');

        // 2*1500 + 3*700 = 5100 sur 2 tickets → panier moyen 2550.
        $this->assertSame(5_100, $report['gross_sales_minor']);
        $this->assertSame(2, $report['ticket_count']);
        $this->assertSame(2_550, $report['average_basket_minor']);
        $this->assertSame(5_100, $report['by_payment_method']['cash']);
    }

    public function test_top_products_report_orders_by_revenue(): void
    {
        $location = $this->storeLocation($this->principal);
        $bissap = $this->storePublishedProduct($this->principal);
        $the = $this->storePublishedProduct($this->principal, [
            'name' => 'The a la menthe',
            'sku' => 'SKU-THE-01',
            'price_minor' => 700,
        ]);
        $this->seedStock($this->principal, (int) $location['id'], (int) $bissap['id'], 20);
        $this->seedStock($this->principal, (int) $location['id'], (int) $the['id'], 20);

        $this->completedOrder($this->principal, (int) $location['id'], (int) $bissap['id'], 2);
        $this->completedOrder($this->principal, (int) $location['id'], (int) $the['id'], 3);

        $this->actingAsUser($this->principal);

        $rows = $this->getJson('/api/v1/retail/reports/top-products')
            ->assertStatus(200)
            ->json('data.lines');

        // Bissap 3000 > The 2100.
        $this->assertSame('Jus de bissap 50cl', $rows[0]['product_name']);
        $this->assertSame(3_000, $rows[0]['revenue_minor']);
        $this->assertSame('The a la menthe', $rows[1]['product_name']);
    }

    public function test_stock_valuation_report_computes_cost_and_retail_value(): void
    {
        $location = $this->storeLocation($this->principal);
        $product = $this->storePublishedProduct($this->principal);
        $this->seedStock($this->principal, (int) $location['id'], (int) $product['id'], 10);

        $this->actingAsUser($this->principal);

        $report = $this->getJson('/api/v1/retail/reports/stock-valuation')
            ->assertStatus(200)
            ->json('data');

        // 10 * 900 = 9000 au cout ; 10 * 1500 = 15000 au prix ; marge 6000.
        $this->assertSame(9_000, $report['cost_value_minor']);
        $this->assertSame(15_000, $report['retail_value_minor']);
        $this->assertSame(6_000, $report['margin_minor']);
    }

    public function test_reports_export_csv(): void
    {
        $location = $this->storeLocation($this->principal);
        $product = $this->storePublishedProduct($this->principal);
        $this->seedStock($this->principal, (int) $location['id'], (int) $product['id'], 10);
        $this->completedOrder($this->principal, (int) $location['id'], (int) $product['id'], 1);

        $this->actingAsUser($this->principal);

        $response = $this->get('/api/v1/retail/reports/top-products?format=csv')
            ->assertStatus(200);

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('product_name', (string) $response->getContent());
    }

    public function test_seller_without_manage_cannot_read_reports(): void
    {
        $location = $this->storeLocation($this->principal);

        // `operate` ne suffit pas pour les rapports (niveau `manage` exige).
        $this->assignResourceAccess(
            $this->seller,
            RetailPermissions::RESOURCE_TYPE,
            (int) $location['id'],
            EmployeeResourceAssignment::LEVEL_OPERATE,
            $this->principal,
        );

        $this->actingAsUser($this->seller);

        $this->getJson('/api/v1/retail/reports/sales')->assertStatus(403);
    }

    public function test_manager_assigned_manage_can_read_reports(): void
    {
        $location = $this->storeLocation($this->principal);

        $this->assignResourceAccess(
            $this->seller,
            RetailPermissions::RESOURCE_TYPE,
            (int) $location['id'],
            EmployeeResourceAssignment::LEVEL_MANAGE,
            $this->principal,
        );

        $this->actingAsUser($this->seller);

        $this->getJson('/api/v1/retail/reports/sales')->assertStatus(200);
    }

    public function test_reports_are_tenant_scoped(): void
    {
        $location = $this->storeLocation($this->principal);
        $product = $this->storePublishedProduct($this->principal);
        $this->seedStock($this->principal, (int) $location['id'], (int) $product['id'], 10);
        $this->completedOrder($this->principal, (int) $location['id'], (int) $product['id'], 2);

        // Un autre tenant ne voit rien.
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $other->setFeature('retail', true);
        $other->save();

        /** @var Employee $otherPrincipal */
        $otherPrincipal = Employee::factory()->create([
            'company_id' => $other->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $this->actingAsUser($otherPrincipal);

        $report = $this->getJson('/api/v1/retail/reports/sales')
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(0, $report['gross_sales_minor']);
        $this->assertSame(0, $report['ticket_count']);
    }
}
