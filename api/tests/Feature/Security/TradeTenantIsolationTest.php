<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Models\TradeCostEstimate;
use App\Modules\Trade\Domain\Models\TradeQuote;
use App\Modules\Trade\Domain\Models\TradeRfq;
use Tests\RefreshTenantDatabase;
use Tests\Support\SwitchesTenantContext;
use Tests\TestCase;

/**
 * TRADE-011 (#8486) — matrice d'isolation inter-tenant de la verticale Trade.
 *
 * Invariant central : une ressource appartenant à une AUTRE entreprise est
 * **introuvable (404), jamais 403**. Un 403 révélerait l'existence de la
 * ressource à un tenant qui n'a aucun droit de la connaître — c'est une fuite
 * d'information par énumération d'identifiants.
 *
 * Chaque ressource est couverte en LECTURE et en ÉCRITURE, et les listes sont
 * vérifiées pour ne jamais laisser fuiter une ligne d'un autre tenant.
 */
class TradeTenantIsolationTest extends TestCase
{
    use RefreshTenantDatabase;
    use SwitchesTenantContext;

    public function test_another_tenant_rfq_is_not_found_on_read(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $foreignRfqId = $this->createForeignResource(fn () => TradeRfq::factory()->create());

        $this->getJson("/api/v1/trade/rfqs/{$foreignRfqId}")->assertStatus(404);
    }

    public function test_another_tenant_quote_is_not_found_on_read(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $foreignQuoteId = $this->createForeignResource(fn () => TradeQuote::factory()->create());

        $this->getJson("/api/v1/trade/quotes/{$foreignQuoteId}")->assertStatus(404);
    }

    public function test_another_tenant_cost_estimate_is_not_found_on_read(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $foreignEstimateId = $this->createForeignResource(fn () => TradeCostEstimate::factory()->create());

        $this->getJson("/api/v1/trade/cost-estimates/{$foreignEstimateId}")->assertStatus(404);
    }

    /**
     * L'écriture est le cas le plus dangereux : accepter l'offre d'une autre
     * entreprise créerait une commande dans le mauvais tenant.
     */
    public function test_another_tenant_quote_cannot_be_accepted(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $foreignQuoteId = $this->createForeignResource(
            fn () => TradeQuote::factory()->submitted()->create(),
        );

        $this->postJson("/api/v1/trade/quotes/{$foreignQuoteId}/accept", [])
            ->assertStatus(404);
    }

    /**
     * Diffuser la RFQ d'autrui enverrait des liens de réponse à des
     * contreparties au nom d'une autre entreprise.
     */
    public function test_another_tenant_rfq_cannot_be_sent(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $foreignRfqId = $this->createForeignResource(fn () => TradeRfq::factory()->create());

        $this->postJson("/api/v1/trade/rfqs/{$foreignRfqId}/send", [
            'counterparty_ids' => [1],
        ])->assertStatus(404);
    }

    /**
     * Les listes ne doivent JAMAIS laisser fuiter une ligne d'un autre tenant,
     * même quand celui-ci possède des ressources du même type.
     */
    public function test_listings_never_leak_another_tenant_rows(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        // Le tenant courant possède UNE RFQ.
        $ownRfqId = $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'RFQ du tenant courant',
            'lines' => [['description' => 'Cacao', 'quantity' => 100]],
        ])->assertStatus(201)->json('data.id');

        // Un autre tenant en possède DEUX.
        $this->createForeignResource(function (): array {
            TradeRfq::factory()->create();
            TradeRfq::factory()->create();

            return [];
        });

        $response = $this->getJson('/api/v1/trade/rfqs')->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');

        self::assertSame([$ownRfqId], $ids, 'La liste ne doit contenir que les RFQ du tenant courant.');
    }

    /**
     * Crée une ressource sous le contexte d'un AUTRE tenant et retourne son id.
     *
     * Depuis le durcissement de `BelongsToCompany` (#7646), `company_id` est
     * forcé depuis le tenant actif : la fixture doit donc être créée sous le
     * contexte de SON tenant, sinon elle appartiendrait au tenant courant et le
     * test ne prouverait rien.
     */
    private function createForeignResource(\Closure $factory): mixed
    {
        return $this->withTenantContext($this->foreignCompany(), function () use ($factory): mixed {
            $created = $factory();

            return is_object($created) && isset($created->id) ? $created->id : $created;
        });
    }

    private function foreignCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('trade', true);
        $company->save();

        return $company;
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

        $this->actingAs($employee, 'sanctum');

        return $employee;
    }
}
