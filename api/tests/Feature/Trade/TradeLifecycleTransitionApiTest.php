<?php

declare(strict_types=1);

namespace Tests\Feature\Trade;

use App\Modules\Trade\Domain\Models\TradeCounterparty;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Cycle de vie des consultations et des offres — #9779.
 *
 * ## Le defaut que ce test protege
 *
 * `TradeRfqStateMachine` et `TradeQuoteStateMachine` implementaient tout le
 * cycle, mais seuls trois sauts etaient branches : `draft -> open` (diffusion
 * RFQ), `draft -> submitted` (soumission d'offre) et `-> accepted`
 * (acceptation). Aucune route ne permettait d'ATTRIBUER une consultation, de
 * la CLORE, ni de REFUSER ou RETIRER une offre.
 *
 * Consequence pour un vendeur B2B : il demandait des prix, recevait des
 * offres, et ne pouvait pas designer le gagnant. Une offre refusee restait
 * `submitted` indefiniment et polluait son pipeline.
 *
 * ## Ce qui est verifie ici
 *
 * - `open -> awarded` passe et horodate la decision avec l'offre gagnante ;
 * - `awarded` sans `awarded_quote_id` est refuse (422) : un etat qui affirme
 *   une decision sans la nommer n'est pas une decision ;
 * - une offre d'une AUTRE RFQ ne peut pas etre designee ;
 * - `open -> closed` passe ;
 * - une transition interdite renvoie `422 INVALID_STATUS_TRANSITION` en
 *   nommant from/to ;
 * - une offre peut etre refusee et le motif est conserve ;
 * - `accepted` est refuse par cette route : accepter cree une commande.
 */
class TradeLifecycleTransitionApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_an_open_rfq_can_be_awarded_to_one_of_its_quotes(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $rfqId = $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'RFQ a adjuger',
            'lines' => [['description' => 'Cacao', 'quantity' => 100]],
        ])->json('data.id');

        $counterparty = TradeCounterparty::factory()->create();
        $this->postJson("/api/v1/trade/rfqs/{$rfqId}/send", [
            'counterparty_ids' => [$counterparty->id],
        ])->assertStatus(200);

        $quoteId = $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD',
            'trade_rfq_id' => $rfqId,
            'lines' => [['description' => 'Cacao', 'quantity' => 100, 'unit_price_minor' => 5_000]],
        ])->json('data.id');

        $this->postJson("/api/v1/trade/rfqs/{$rfqId}/transition", [
            'status' => 'awarded',
            'awarded_quote_id' => $quoteId,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'awarded')
            ->assertJsonPath('data.awarded_quote_id', $quoteId);

        // La decision est horodatee : sans date, on ne sait pas quand le
        // fournisseur a ete retenu.
        self::assertNotNull(
            $this->getJson("/api/v1/trade/rfqs/{$rfqId}")->json('data.awarded_at'),
        );
    }

    public function test_awarding_without_naming_the_winning_quote_is_refused(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $rfqId = $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'RFQ sans gagnant',
            'lines' => [['description' => 'Riz', 'quantity' => 10]],
        ])->json('data.id');

        $counterparty = TradeCounterparty::factory()->create();
        $this->postJson("/api/v1/trade/rfqs/{$rfqId}/send", [
            'counterparty_ids' => [$counterparty->id],
        ])->assertStatus(200);

        // Attribuer sans dire QUELLE offre a gagne produirait un etat qui
        // affirme une decision sans la nommer.
        $this->postJson("/api/v1/trade/rfqs/{$rfqId}/transition", ['status' => 'awarded'])
            ->assertStatus(422);
    }

    public function test_a_quote_from_another_rfq_cannot_be_awarded(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $rfqA = $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'RFQ A',
            'lines' => [['description' => 'Sucre', 'quantity' => 10]],
        ])->json('data.id');

        $rfqB = $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'RFQ B',
            'lines' => [['description' => 'Sel', 'quantity' => 10]],
        ])->json('data.id');

        $counterparty = TradeCounterparty::factory()->create();
        foreach ([$rfqA, $rfqB] as $id) {
            $this->postJson("/api/v1/trade/rfqs/{$id}/send", [
                'counterparty_ids' => [$counterparty->id],
            ])->assertStatus(200);
        }

        $quoteOfB = $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD',
            'trade_rfq_id' => $rfqB,
            'lines' => [['description' => 'Sel', 'quantity' => 10, 'unit_price_minor' => 1_000]],
        ])->json('data.id');

        // Attribuer A a l'offre de B melangerait deux consultations.
        $this->postJson("/api/v1/trade/rfqs/{$rfqA}/transition", [
            'status' => 'awarded',
            'awarded_quote_id' => $quoteOfB,
        ])->assertStatus(422);
    }

    public function test_an_open_rfq_can_be_closed(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $rfqId = $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'RFQ a clore',
            'lines' => [['description' => 'Cafe', 'quantity' => 20]],
        ])->json('data.id');

        $counterparty = TradeCounterparty::factory()->create();
        $this->postJson("/api/v1/trade/rfqs/{$rfqId}/send", [
            'counterparty_ids' => [$counterparty->id],
        ])->assertStatus(200);

        $this->postJson("/api/v1/trade/rfqs/{$rfqId}/transition", ['status' => 'closed'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_an_illegal_rfq_transition_names_from_and_to(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        // Une RFQ encore en brouillon ne peut pas etre attribuee : il n'y a
        // aucune offre, la consultation n'a jamais ete diffusee.
        $rfqId = $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'RFQ brouillon',
            'lines' => [['description' => 'The', 'quantity' => 5]],
        ])->json('data.id');

        $this->postJson("/api/v1/trade/rfqs/{$rfqId}/transition", ['status' => 'awarded'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_STATUS_TRANSITION')
            ->assertJsonPath('from', 'draft')
            ->assertJsonPath('to', 'awarded');
    }

    public function test_a_quote_can_be_rejected_and_the_reason_is_kept(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $quoteId = $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD',
            'lines' => [['description' => 'Poivre', 'quantity' => 3, 'unit_price_minor' => 2_000]],
        ])->json('data.id');

        // `POST /quotes` soumet par defaut (`submit: true`) : l'offre est deja
        // `submitted`, donc refusable.
        $this->postJson("/api/v1/trade/quotes/{$quoteId}/transition", [
            'status' => 'rejected',
            'reason' => 'Prix trop eleve face au concurrent.',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected');

        // Le motif est conserve : une offre refusee sans raison ne dit rien au
        // prochain commercial qui reprend le dossier.
        self::assertStringContainsString(
            'Prix trop eleve',
            (string) $this->getJson("/api/v1/trade/quotes/{$quoteId}")->json('data.notes'),
        );
    }

    public function test_accepting_through_the_transition_route_is_refused(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $quoteId = $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD',
            'lines' => [['description' => 'Girofle', 'quantity' => 1, 'unit_price_minor' => 900]],
        ])->json('data.id');

        // `accepted` cree une commande : l'autoriser ici produirait une offre
        // « acceptee » sans commande — un etat qui ment sur ce qui s'est passe.
        $this->postJson("/api/v1/trade/quotes/{$quoteId}/transition", ['status' => 'accepted'])
            ->assertStatus(422);
    }
}
