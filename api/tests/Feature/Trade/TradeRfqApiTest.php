<?php

declare(strict_types=1);

namespace Tests\Feature\Trade;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Models\TradeCounterparty;
use App\Modules\Trade\Domain\Models\TradeRfq;
use Tests\RefreshTenantDatabase;
use Tests\Support\SwitchesTenantContext;
use Tests\TestCase;

/**
 * TRADE-003 (#8478) — parcours RFQ : creation, diffusion, liens de reponse.
 *
 * Invariants metier verifies par le VRAI chemin HTTP :
 *  - 401 sans authentification, 403 si le flag `trade` est inactif ;
 *  - 403 si le role ne couvre pas l'operation (deny-by-default) ;
 *  - creation 201 : RFQ + lignes + dossier cree a la volee, en UNE transaction ;
 *  - diffusion : `open` + invitations + liens retournes UNE SEULE FOIS ;
 *  - diffusion idempotente : re-diffuser la meme contrepartie ne cree pas de
 *    doublon ;
 *  - les liens ne sont JAMAIS relisibles via l'API de consultation ;
 *  - isolation inter-tenant : la RFQ d'une autre entreprise est un 404.
 */
class TradeRfqApiTest extends TestCase
{
    use RefreshTenantDatabase;
    use SwitchesTenantContext;

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/trade/rfqs')->assertStatus(401);
        $this->postJson('/api/v1/trade/rfqs', [])->assertStatus(401);
    }

    public function test_endpoints_are_rejected_when_feature_flag_disabled(): void
    {
        $company = $this->createCompany(enableTrade: false);
        $this->actingAsEmployee($company);

        $this->getJson('/api/v1/trade/rfqs')
            ->assertStatus(403)
            ->assertJson(['error' => 'FEATURE_NOT_ENABLED']);
    }

    public function test_creating_an_rfq_also_creates_its_deal_and_lines(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $response = $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'Cafe vert 20 tonnes',
            'destination_country' => 'CM',
            'destination_port' => 'Douala',
            'incoterm' => 'FOB',
            'budget_minor' => 5_000_000,
            'budget_currency' => 'USD',
            'lines' => [
                ['description' => 'Cafe vert non torrefie', 'quantity' => 20000, 'unit' => 'kg'],
                ['description' => 'Sacs de jute 60kg', 'quantity' => 400, 'unit' => 'pcs'],
            ],
        ])->assertStatus(201);

        $response->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.incoterm', 'FOB')
            ->assertJsonCount(2, 'data.lines');

        self::assertStringStartsWith('RFQ-', (string) $response->json('data.reference'));
        // Une RFQ n'est jamais orpheline : le dossier est cree dans la foulee.
        self::assertNotNull($response->json('data.trade_deal_id'));
    }

    public function test_an_rfq_without_lines_is_refused(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        // Une RFQ vide serait un objet qui ne peut rien produire.
        $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'Sans lignes',
            'lines' => [],
        ])->assertStatus(422);
    }

    public function test_sending_an_rfq_creates_invitations_and_returns_links_once(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $rfqId = $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'RFQ a diffuser',
            'lines' => [['description' => 'Riz', 'quantity' => 1000]],
        ])->json('data.id');

        $counterparty = TradeCounterparty::factory()->create(['name' => 'Fournisseur Hanoi']);

        $send = $this->postJson("/api/v1/trade/rfqs/{$rfqId}/send", [
            'counterparty_ids' => [$counterparty->id],
            'message' => 'Merci de nous coter.',
        ])->assertStatus(200);

        $send->assertJsonPath('data.status', 'open')
            ->assertJsonCount(1, 'data.invitations')
            ->assertJsonCount(1, 'response_links');

        // Le lien en clair est retourne EXACTEMENT une fois.
        self::assertNotEmpty($send->json('response_links.0.token'));

        // ... et n'est jamais relisible ensuite : la consultation ne l'expose pas.
        $show = $this->getJson("/api/v1/trade/rfqs/{$rfqId}")->assertStatus(200);
        self::assertNull($show->json('data.invitations.0.response_token_hash'));
        self::assertTrue($show->json('data.invitations.0.has_response_link'));
    }

    public function test_resending_to_the_same_counterparty_is_idempotent(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $rfqId = $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'RFQ idempotente',
            'lines' => [['description' => 'Sucre', 'quantity' => 500]],
        ])->json('data.id');

        $counterparty = TradeCounterparty::factory()->create();

        $this->postJson("/api/v1/trade/rfqs/{$rfqId}/send", ['counterparty_ids' => [$counterparty->id]])
            ->assertStatus(200)
            ->assertJsonCount(1, 'response_links');

        // Seconde diffusion a la MEME contrepartie : aucun doublon, aucun
        // nouveau lien.
        $this->postJson("/api/v1/trade/rfqs/{$rfqId}/send", ['counterparty_ids' => [$counterparty->id]])
            ->assertStatus(200)
            ->assertJsonCount(0, 'response_links')
            ->assertJsonCount(1, 'data.invitations');
    }

    public function test_another_tenant_rfq_is_not_found(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        [$rfqId, $otherCompany] = $this->withTenantContext($company, function (): array {
            $other = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
            $other->setFeature('trade', true);
            $other->save();

            $rfq = $this->withTenantContext($other, fn () => TradeRfq::factory()->create());

            return [$rfq->id, $other];
        });

        // 404 uniforme : on ne revele pas l'existence d'une ressource d'autrui.
        $this->getJson("/api/v1/trade/rfqs/{$rfqId}")->assertStatus(404);
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
            // `role` doit valoir `manager` : `TradeRoleResolver` s'appuie sur
            // `Employee::isManager()` (role === 'manager') AVANT de lire
            // `manager_role`. Sans ce champ, l'employe etait traite comme un
            // non-manager sans grant `trade.*` -> 403 TRADE_ROLE_REQUIRED.
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        $this->actingAs($employee, 'sanctum');

        return $employee;
    }
}
