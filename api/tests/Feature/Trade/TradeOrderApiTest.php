<?php

declare(strict_types=1);

namespace Tests\Feature\Trade;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Commandes du vendeur B2B — #9779.
 *
 * ## Le defaut que ce test protege
 *
 * `AcceptQuoteAction` cree une commande en `confirmed`, `TradeOrderResource`
 * existait, `TradeOrderStateMachine` implementait tout le cycle — et AUCUNE
 * route ne servait la ressource. Le vendeur acceptait une offre et la commande
 * disparaissait de son espace.
 *
 * ## Ce qui est verifie ici
 *
 * - la commande nee d'une acceptation est VISIBLE dans la liste du tenant ;
 * - `?direction=` distingue achats et ventes ;
 * - une transition valide passe, une transition interdite renvoie
 *   `422 INVALID_STATUS_TRANSITION` en NOMMANT from/to ;
 * - l'annulation exige un motif ;
 * - une commande d'un autre tenant est introuvable.
 */
class TradeOrderApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_an_accepted_quote_produces_a_visible_order(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        // Chemins reels : RFQ -> offre (liee a la RFQ, donc vue acheteur) ->
        // acceptation -> commande.
        $rfqId = $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'RFQ a adjuger',
            'lines' => [['description' => 'Cacao', 'quantity' => 100]],
        ])->json('data.id');

        $quoteId = $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD',
            'trade_rfq_id' => $rfqId,
            'lines' => [['description' => 'Cacao', 'quantity' => 100, 'unit_price_minor' => 5_000]],
        ])->json('data.id');

        $orderId = $this->postJson("/api/v1/trade/quotes/{$quoteId}/accept")
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'confirmed')
            ->json('data.id');

        // Le point de tout #9779 : la commande est desormais LISIBLE.
        $this->getJson('/api/v1/trade/orders')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $orderId)
            ->assertJsonPath('data.0.total_minor', 500_000);
    }

    public function test_orders_can_be_split_by_direction(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        // Offre EMISE (sans RFQ) -> acceptation -> commande de VENTE.
        $outgoingQuoteId = $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD',
            'lines' => [['description' => 'Emise', 'quantity' => 1, 'unit_price_minor' => 100]],
        ])->json('data.id');
        $this->postJson("/api/v1/trade/quotes/{$outgoingQuoteId}/accept")->assertStatus(201);

        // Offre RECUE (liee a une RFQ) -> acceptation -> commande d'ACHAT.
        $rfqId = $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'RFQ recue',
            'lines' => [['description' => 'Recue', 'quantity' => 1]],
        ])->json('data.id');
        $incomingQuoteId = $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD',
            'trade_rfq_id' => $rfqId,
            'lines' => [['description' => 'Recue', 'quantity' => 1, 'unit_price_minor' => 200]],
        ])->json('data.id');
        $this->postJson("/api/v1/trade/quotes/{$incomingQuoteId}/accept")->assertStatus(201);

        $this->getJson('/api/v1/trade/orders?direction=sale')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.direction', 'sale');

        $this->getJson('/api/v1/trade/orders?direction=purchase')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.direction', 'purchase');
    }

    public function test_a_valid_transition_advances_the_order(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $orderId = $this->createConfirmedOrder();

        $this->postJson("/api/v1/trade/orders/{$orderId}/transition", ['status' => 'preparing'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'preparing');

        $this->postJson("/api/v1/trade/orders/{$orderId}/transition", ['status' => 'ready'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'ready');

        // L'horodatage d'expedition n'est pose QUE sur `shipped` : le poser
        // d'avance ferait croire a une expedition qui n'a pas eu lieu.
        $shipped = $this->postJson("/api/v1/trade/orders/{$orderId}/transition", ['status' => 'shipped'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'shipped');
        self::assertNotNull($shipped->json('data.shipped_at'));
    }

    public function test_an_illegal_transition_is_a_422_naming_both_states(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $orderId = $this->createConfirmedOrder();

        // confirmed -> delivered saute preparing/ready/shipped : la machine a
        // etats doit refuser, et la reponse doit NOMMER les deux etats pour
        // que l'ecran puisse expliquer le refus.
        $this->postJson("/api/v1/trade/orders/{$orderId}/transition", ['status' => 'delivered'])
            ->assertStatus(422)
            // Format reel d'`ApiError::response()` : `error` est le CODE et
            // `message` l'explication ; les details sont ajoutes a la RACINE,
            // pas sous `error.details`. Le test precedent attendait une forme
            // qui n'existe nulle part dans l'API (la CI l'a revele).
            ->assertJsonPath('error', 'INVALID_STATUS_TRANSITION')
            ->assertJsonPath('from', 'confirmed')
            ->assertJsonPath('to', 'delivered');
    }

    public function test_an_unknown_status_is_a_validation_error_not_a_transition_error(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $orderId = $this->createConfirmedOrder();

        // Deux causes DISTINCTES : « ce statut n'existe pas » (validation) et
        // « ce saut est interdit » (transition). Les confondre rendrait
        // l'ecran incapable de dire laquelle s'applique.
        $this->postJson("/api/v1/trade/orders/{$orderId}/transition", ['status' => 'teleported'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_cancelling_requires_a_reason(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $orderId = $this->createConfirmedOrder();

        // Une annulation sans motif est inexploitable six mois plus tard.
        $this->postJson("/api/v1/trade/orders/{$orderId}/transition", ['status' => 'cancelled'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cancel_reason']);

        $this->postJson("/api/v1/trade/orders/{$orderId}/transition", [
            'status' => 'cancelled',
            'cancel_reason' => 'Rupture de stock fournisseur',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancel_reason', 'Rupture de stock fournisseur');
    }

    public function test_another_tenants_order_is_not_found(): void
    {
        $companyA = $this->createCompany();
        $this->actingAsEmployee($companyA);

        $orderId = $this->createConfirmedOrder();

        $companyB = $this->createCompany();
        $this->actingAsEmployee($companyB);

        $this->getJson("/api/v1/trade/orders/{$orderId}")->assertStatus(404);
        $this->postJson("/api/v1/trade/orders/{$orderId}/transition", ['status' => 'preparing'])
            ->assertStatus(404);

        $this->getJson('/api/v1/trade/orders')->assertStatus(200)->assertJsonCount(0, 'data');
    }

    // ── Fixtures ──────────────────────────────────────────────────────────

    /**
     * Cree une commande `confirmed` par le VRAI chemin (offre emise + accept).
     */
    private function createConfirmedOrder(): int
    {
        $quoteId = $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD',
            'lines' => [['description' => 'Marchandise', 'quantity' => 10, 'unit_price_minor' => 1_000]],
        ])->assertStatus(201)->json('data.id');

        return (int) $this->postJson("/api/v1/trade/quotes/{$quoteId}/accept")
            ->assertStatus(201)
            ->json('data.id');
    }

    private function createCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('trade', true);
        $company->save();

        app()->instance('current_company', $company);

        return $company;
    }

    private function actingAsEmployee(Company $company, string $managerRole = 'principal'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        $this->actingAs($employee, 'sanctum');

        return $employee;
    }
}
