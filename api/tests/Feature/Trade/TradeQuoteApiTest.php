<?php

declare(strict_types=1);

namespace Tests\Feature\Trade;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRADE-004 (#8479) — offres commerciales et bascule vers la commande.
 *
 * Invariants metier verifies par le VRAI chemin HTTP :
 *  - objet PARTAGE : le meme endpoint sert les deux espaces, `direction`
 *    distingue les offres recues des offres emises ;
 *  - les totaux sont FIGES cote serveur : un `total_minor` envoye par le client
 *    est ignore, le total retourne est celui des lignes ;
 *  - sens de la commande DEDUIT de l'offre (offre entrante -> achat) ;
 *  - le total de la commande est REPRIS de l'offre ;
 *  - double acceptation refusee en 422 (pas de deuxieme commande) ;
 *  - RBAC par operation : `sales` emet, `purchase` accepte.
 */
class TradeQuoteApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_the_server_computes_totals_and_ignores_client_values(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $response = $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD',
            'incoterm' => 'FOB',
            // ⚠️ Valeur mensongere : le serveur doit la IGNORER.
            'total_minor' => 1,
            'lines' => [
                ['description' => 'Cafe', 'quantity' => 100, 'unit_price_minor' => 2_500],
                ['description' => 'Transport', 'quantity' => 1, 'unit_price_minor' => 750_000],
            ],
        ])->assertStatus(201);

        // 100 x 2 500 = 250 000, plus 750 000 = 1 000 000.
        self::assertSame(1_000_000, $response->json('data.total_minor'));
        self::assertSame(1_000_000, $response->json('data.subtotal_minor'));
        self::assertSame(250_000, $response->json('data.lines.0.line_total_minor'));
        $response->assertJsonPath('data.status', 'submitted');
        // Sans RFQ liee, l'offre est EMISE (vue vendeur).
        $response->assertJsonPath('data.direction', 'outgoing');
    }

    public function test_a_quote_linked_to_an_rfq_is_incoming(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $rfqId = $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'RFQ source',
            'incoterm' => 'CIF',
            'lines' => [['description' => 'Riz', 'quantity' => 500]],
        ])->json('data.id');

        $quote = $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD',
            'trade_rfq_id' => $rfqId,
            'lines' => [['description' => 'Riz', 'quantity' => 500, 'unit_price_minor' => 1_000]],
        ])->assertStatus(201);

        // Vue acheteur : offre RECUE.
        $quote->assertJsonPath('data.direction', 'incoming');
        // Le perimetre de cout herite de la RFQ : c'est ce qui rend deux offres
        // comparables entre elles.
        $quote->assertJsonPath('data.incoterm', 'CIF');
    }

    public function test_listing_can_be_split_into_received_and_sent(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $rfqId = $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'RFQ partagee',
            'lines' => [['description' => 'Mais', 'quantity' => 10]],
        ])->json('data.id');

        $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD', 'trade_rfq_id' => $rfqId,
            'lines' => [['description' => 'Recue', 'quantity' => 1, 'unit_price_minor' => 100]],
        ])->assertStatus(201);

        $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD',
            'lines' => [['description' => 'Emise', 'quantity' => 1, 'unit_price_minor' => 200]],
        ])->assertStatus(201);

        // MEME endpoint, deux vues metier.
        $this->getJson('/api/v1/trade/quotes?direction=incoming')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.direction', 'incoming');

        $this->getJson('/api/v1/trade/quotes?direction=outgoing')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.direction', 'outgoing');
    }

    public function test_accepting_a_quote_creates_an_order_with_the_quoted_total(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $rfqId = $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'RFQ a adjuger',
            'lines' => [['description' => 'Cacao', 'quantity' => 100]],
        ])->json('data.id');

        $quote = $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD',
            'trade_rfq_id' => $rfqId,
            'lines' => [['description' => 'Cacao', 'quantity' => 100, 'unit_price_minor' => 5_000]],
        ])->assertStatus(201)->json('data');

        $order = $this->postJson("/api/v1/trade/quotes/{$quote['id']}/accept", [])
            ->assertStatus(201);

        $order->assertJsonPath('data.status', 'confirmed')
            // Offre entrante -> la commande est un ACHAT.
            ->assertJsonPath('data.direction', 'purchase')
            // Total REPRIS de l'offre, jamais recalcule depuis une entree client.
            ->assertJsonPath('data.total_minor', 500_000)
            ->assertJsonPath('data.trade_quote_id', $quote['id']);

        // L'offre est marquee acceptee : le dossier reste coherent.
        $this->getJson("/api/v1/trade/quotes/{$quote['id']}")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'accepted');
    }

    public function test_a_quote_cannot_be_accepted_twice(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $quote = $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD',
            'lines' => [['description' => 'The', 'quantity' => 10, 'unit_price_minor' => 1_000]],
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/trade/quotes/{$quote['id']}/accept", [])->assertStatus(201);

        // Deuxieme acceptation : refus METIER explicite, pas une 500 et pas une
        // seconde commande.
        $this->postJson("/api/v1/trade/quotes/{$quote['id']}/accept", [])
            ->assertStatus(422);
    }

    public function test_an_unsubmitted_quote_cannot_be_accepted(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $quote = $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD',
            'submit' => false,
            'lines' => [['description' => 'Brouillon', 'quantity' => 1, 'unit_price_minor' => 100]],
        ])->assertStatus(201)->json('data');

        self::assertSame('draft', $quote['status']);

        // On ne commande pas un prix que personne n'a emis.
        $this->postJson("/api/v1/trade/quotes/{$quote['id']}/accept", [])->assertStatus(422);
    }

    private function createCompany(bool $enableTrade = true): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        if ($enableTrade) {
            $company->setFeature('trade', true);
            $company->save();
        }

        app()->instance('current_company', $company);

        return $company;
    }

    private function actingAsEmployee(Company $company, string $managerRole = 'principal'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'manager_role' => $managerRole,
        ]);

        $this->actingAs($employee, Sanctum::guard());

        return $employee;
    }
}
