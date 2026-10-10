<?php

declare(strict_types=1);

namespace Tests\Feature\Fundraising;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Fundraising\Domain\Enums\FundraiserStatus;
use App\Modules\Fundraising\Domain\Models\Fundraiser;
use App\Modules\Fundraising\Domain\Models\FundraiserPublicLink;
use App\Modules\Fundraising\Domain\Models\FundraisingContribution;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Verticale FUNDRAISING — flux public de bout en bout (spec §5.1) :
 * publication → fiche publique (DTO sans fuite) → contribution mobile
 * money sandbox → polling avec re-conciliation → compteurs crédités → mur
 * des soutiens (anonymat) → reversement (règle de solde + workflow).
 * Garde-fous : brouillon 404, honeypot 422, montant plancher 422, carte
 * non configurée 503 fail-closed, verticale coupée 404 (kill switch).
 */
class FundraisingPublicFlowTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'SN',
            'currency' => 'XOF',
            'slug' => 'asso-teranga',
        ]);
        $company->setFeature('fundraising', true);
        $company->save();

        return $company;
    }

    private function publishedFundraiser(Company $company, array $overrides = []): Fundraiser
    {
        $fundraiser = app(TenantManager::class)->withinTenant($company, function () use ($company, $overrides): Fundraiser {
            /** @var Fundraiser $fundraiser */
            $fundraiser = Fundraiser::query()->create(array_merge([
                'company_id' => $company->id,
                'slug' => 'aide-fatou-abc123',
                'title' => 'Aide pour Fatou',
                'description' => 'Frais médicaux.',
                'beneficiary_name' => 'Fatou Ndiaye',
                'beneficiary_contact' => '+221770000000', // interne — ne doit JAMAIS sortir
                'category' => 'medical',
                'goal_amount' => 20000,
                'currency' => 'XOF',
                'suggested_amounts' => [5000, 10000],
                'min_amount' => 1000,
                'status' => FundraiserStatus::ACTIVE,
                'published_at' => now(),
            ], $overrides));

            return $fundraiser;
        });

        FundraiserPublicLink::query()->create([
            'slug' => $fundraiser->slug,
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        return $fundraiser;
    }

    public function test_public_show_has_no_internal_data_and_draft_is_404(): void
    {
        $company = $this->company();
        $this->publishedFundraiser($company);

        $response = $this->getJson('/api/v1/public/fundraisers/aide-fatou-abc123');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'aide-fatou-abc123')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.accepts_contributions', true)
            ->assertJsonMissingPath('data.beneficiary_contact')
            ->assertJsonMissingPath('data.company_id')
            ->assertJsonMissingPath('data.id');

        // Slug inconnu → 404 anti-énumération.
        $this->getJson('/api/v1/public/fundraisers/inconnu-xyz')->assertStatus(404);

        // Brouillon : pas d'annuaire → 404.
        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            Fundraiser::query()->create([
                'company_id' => $company->id,
                'slug' => 'brouillon-xyz',
                'title' => 'Brouillon',
                'beneficiary_name' => 'X',
                'currency' => 'XOF',
                'status' => FundraiserStatus::DRAFT,
            ]);
        });
        $this->getJson('/api/v1/public/fundraisers/brouillon-xyz')->assertStatus(404);
    }

    public function test_full_mobile_money_sandbox_cycle_and_supporters_wall(): void
    {
        $company = $this->company();
        $fundraiser = $this->publishedFundraiser($company);

        // 1. Contribution sandbox mobile money (push USSD simulé).
        $contribute = $this->postJson('/api/v1/public/fundraisers/aide-fatou-abc123/contribute', [
            'amount' => 5000,
            'payment_method' => 'mobile_money',
            'contributor_name' => 'Moussa Diop',
            'contributor_phone' => '+221770000001',
            'message' => 'Courage Fatou !',
        ]);

        $contribute->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment.status', 'pending');

        $reference = $contribute->json('data.reference');
        $this->assertMatchesRegularExpression('/^FC-[A-Z0-9]{10}$/', (string) $reference);
        $this->assertNotNull($contribute->json('data.payment.ussd_code'));

        // 2. Polling → re-conciliation active sandbox → completed.
        $this->getJson('/api/v1/public/contributions/'.$reference)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.fundraiser_slug', 'aide-fatou-abc123');

        // 3. Compteurs crédités exactement une fois (second polling = zéro
        //    double crédit — événement déterministe idempotent).
        $this->getJson('/api/v1/public/contributions/'.$reference)->assertOk();

        $fundraiser = app(TenantManager::class)->withinTenant(
            $company,
            fn (): Fundraiser => Fundraiser::query()->where('slug', 'aide-fatou-abc123')->firstOrFail()
        );
        $this->assertSame(5000.0, (float) $fundraiser->collected_amount);
        $this->assertSame(1, $fundraiser->contributions_count);

        // 4. Mur des soutiens : la contribution apparaît, nom visible.
        $this->getJson('/api/v1/public/fundraisers/aide-fatou-abc123/supporters')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Moussa Diop')
            ->assertJsonPath('data.0.message', 'Courage Fatou !');
    }

    public function test_anonymous_contribution_is_masked_on_wall(): void
    {
        $company = $this->company();
        $this->publishedFundraiser($company);

        $contribute = $this->postJson('/api/v1/public/fundraisers/aide-fatou-abc123/contribute', [
            'amount' => 10000,
            'payment_method' => 'mobile_money',
            'contributor_name' => 'Donateur Secret',
            'contributor_phone' => '+221770000002',
            'is_anonymous' => true,
        ])->assertStatus(201);

        $this->getJson('/api/v1/public/contributions/'.$contribute->json('data.reference'))->assertOk();

        $this->getJson('/api/v1/public/fundraisers/aide-fatou-abc123/supporters')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Anonyme')
            ->assertJsonPath('data.0.amount', null);
    }

    public function test_guards_honeypot_min_amount_and_unconfigured_card(): void
    {
        $company = $this->company();
        $this->publishedFundraiser($company);

        // Honeypot rempli → 422.
        $this->postJson('/api/v1/public/fundraisers/aide-fatou-abc123/contribute', [
            'amount' => 5000,
            'payment_method' => 'mobile_money',
            'contributor_phone' => '+221770000003',
            'website' => 'http://spam.bot',
        ])->assertStatus(422);

        // Sous le min_amount de la cagnotte (1000) → 422 INVALID_CONTRIBUTION_AMOUNT.
        $this->postJson('/api/v1/public/fundraisers/aide-fatou-abc123/contribute', [
            'amount' => 100,
            'payment_method' => 'mobile_money',
            'contributor_phone' => '+221770000003',
        ])->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_CONTRIBUTION_AMOUNT');

        // Mobile money sans téléphone → 422 (validation).
        $this->postJson('/api/v1/public/fundraisers/aide-fatou-abc123/contribute', [
            'amount' => 5000,
            'payment_method' => 'mobile_money',
        ])->assertStatus(422);

        // Carte bancaire sans clé Stripe configurée → 503 fail-closed.
        config(['fundraising.stripe.secret_key' => '']);
        $this->postJson('/api/v1/public/fundraisers/aide-fatou-abc123/contribute', [
            'amount' => 5000,
            'payment_method' => 'card',
        ])->assertStatus(503)
            ->assertJsonPath('error', 'PAYMENT_GATEWAY_NOT_CONFIGURED');
    }

    public function test_kill_switch_feature_disabled_gives_uniform_404(): void
    {
        $company = $this->company();
        $this->publishedFundraiser($company);

        // Une contribution en cours AVANT le kill switch.
        $contribute = $this->postJson('/api/v1/public/fundraisers/aide-fatou-abc123/contribute', [
            'amount' => 5000,
            'payment_method' => 'mobile_money',
            'contributor_phone' => '+221770000009',
        ])->assertStatus(201);
        $reference = $contribute->json('data.reference');

        // Kill switch : la verticale est coupée → la surface d'encaissement
        // tombe en 404 uniforme (argent = strict, spec §6), y compris le
        // polling (qui déclenche la re-conciliation = encaissement).
        $company->setFeature('fundraising', false);
        $company->save();

        $this->getJson('/api/v1/public/fundraisers/aide-fatou-abc123')->assertStatus(404);
        $this->postJson('/api/v1/public/fundraisers/aide-fatou-abc123/contribute', [
            'amount' => 5000,
            'payment_method' => 'mobile_money',
            'contributor_phone' => '+221770000004',
        ])->assertStatus(404);
        $this->getJson('/api/v1/public/contributions/'.$reference)->assertStatus(404);
    }

    /**
     * Régression (revue statique, blocker) : le polling d'une session
     * Stripe encore OUVERTE ne doit JAMAIS solder la contribution en échec
     * — sinon le webhook de succès ultérieur serait refusé (argent encaissé
     * jamais crédité). La vérification active ne tranche que sur état
     * terminal (complete+paid / expired).
     */
    public function test_polling_open_stripe_session_keeps_contribution_pending_then_webhook_credits(): void
    {
        config(['fundraising.stripe.secret_key' => 'sk_test_fake']);
        config(['fundraising.stripe.webhook_secret' => 'whsec_open_test']);

        $company = $this->company();
        $this->publishedFundraiser($company);

        // Initiation carte : Checkout Session créée (mockée).
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_open_1',
                'url' => 'https://checkout.stripe.com/pay/cs_open_1',
            ], 200),
        ]);

        $contribute = $this->postJson('/api/v1/public/fundraisers/aide-fatou-abc123/contribute', [
            'amount' => 5000,
            'payment_method' => 'card',
            'contributor_name' => 'Payeur Carte',
        ]);

        $contribute->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment.redirect_url', 'https://checkout.stripe.com/pay/cs_open_1');

        $reference = $contribute->json('data.reference');

        // Le client est ENCORE sur la page Checkout : session `open`.
        Http::fake([
            'api.stripe.com/v1/checkout/sessions/cs_open_1' => Http::response([
                'id' => 'cs_open_1',
                'status' => 'open',
                'payment_status' => 'unpaid',
            ], 200),
        ]);

        // Polling : aucun verdict → toujours pending (JAMAIS failed).
        $this->getJson('/api/v1/public/contributions/'.$reference)
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        // Le client paie : le webhook `checkout.session.completed` crédite.
        $payload = (string) json_encode([
            'id' => 'evt_open_1',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_open_1', 'created' => time()]],
        ]);
        $timestamp = (string) time();
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_open_test');

        $this->call('POST', '/api/v1/webhooks/fundraising/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk()->assertJsonPath('status', 'applied');

        $this->getJson('/api/v1/public/contributions/'.$reference)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    /**
     * Objectif atteint (`completed`) : la collecte CONTINUE jusqu'à
     * `closed` (décision produit façon GoFundMe, spec §3.1).
     */
    public function test_contributions_still_accepted_after_goal_reached(): void
    {
        $company = $this->company();
        $this->publishedFundraiser($company); // goal 20000

        $first = $this->postJson('/api/v1/public/fundraisers/aide-fatou-abc123/contribute', [
            'amount' => 20000,
            'payment_method' => 'mobile_money',
            'contributor_phone' => '+221770000010',
        ])->assertStatus(201);
        $this->getJson('/api/v1/public/contributions/'.$first->json('data.reference'))->assertOk();

        // L'objectif est atteint : la cagnotte passe `completed`…
        $this->getJson('/api/v1/public/fundraisers/aide-fatou-abc123')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.accepts_contributions', true);

        // …et continue d'accepter les contributions.
        $this->postJson('/api/v1/public/fundraisers/aide-fatou-abc123/contribute', [
            'amount' => 5000,
            'payment_method' => 'mobile_money',
            'contributor_phone' => '+221770000011',
        ])->assertStatus(201);
    }

    public function test_payout_workflow_and_balance_rule(): void
    {
        $company = $this->company();
        $this->publishedFundraiser($company);

        // Collecte de 5000 via sandbox.
        $contribute = $this->postJson('/api/v1/public/fundraisers/aide-fatou-abc123/contribute', [
            'amount' => 5000,
            'payment_method' => 'mobile_money',
            'contributor_phone' => '+221770000005',
        ])->assertStatus(201);
        $this->getJson('/api/v1/public/contributions/'.$contribute->json('data.reference'))->assertOk();

        // Responsable du tenant.
        /** @var Employee $principal */
        $principal = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($principal);

        $fundraiserId = app(TenantManager::class)->withinTenant(
            $company,
            fn (): int => Fundraiser::query()->where('slug', 'aide-fatou-abc123')->value('id')
        );

        // Dépassement du solde → 422 PAYOUT_AMOUNT_EXCEEDS_BALANCE.
        $this->postJson('/api/v1/fundraising/fundraisers/'.$fundraiserId.'/payouts', [
            'amount' => 999999,
            'method' => 'mobile_money',
            'recipient_name' => 'Fatou Ndiaye',
            'recipient_account' => '+221770000000',
        ])->assertStatus(422)
            ->assertJsonPath('error', 'PAYOUT_AMOUNT_EXCEEDS_BALANCE');

        // Demande valide → requested → processing → paid.
        $payoutId = $this->postJson('/api/v1/fundraising/fundraisers/'.$fundraiserId.'/payouts', [
            'amount' => 5000,
            'method' => 'mobile_money',
            'recipient_name' => 'Fatou Ndiaye',
            'recipient_account' => '+221770000000',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'requested')
            ->json('data.id');

        $this->postJson('/api/v1/fundraising/payouts/'.$payoutId.'/process')
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');

        $this->postJson('/api/v1/fundraising/payouts/'.$payoutId.'/mark-paid', [
            'provider_reference' => 'MM-PAYOUT-1',
        ])->assertOk()
            ->assertJsonPath('data.status', 'paid');

        // Le solde disponible tombe à zéro.
        $this->getJson('/api/v1/fundraising/fundraisers/'.$fundraiserId)
            ->assertOk()
            ->assertJsonPath('data.available_balance', 0.0);
    }

    public function test_manual_contribution_confirmed_by_manager(): void
    {
        $company = $this->company();
        $this->publishedFundraiser($company);

        // Contribution espèces : pending jusqu'à confirmation du responsable.
        $contribute = $this->postJson('/api/v1/public/fundraisers/aide-fatou-abc123/contribute', [
            'amount' => 3000,
            'payment_method' => 'cash',
            'contributor_name' => 'Voisin Solidaire',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $reference = $contribute->json('data.reference');

        // Le polling manuel ne confirme PAS (pas de vérification active).
        $this->getJson('/api/v1/public/contributions/'.$reference)
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        /** @var Employee $principal */
        $principal = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($principal);

        $contributionId = app(TenantManager::class)->withinTenant(
            $company,
            fn (): int => FundraisingContribution::query()->where('reference', $reference)->value('id')
        );

        $this->postJson('/api/v1/fundraising/contributions/'.$contributionId.'/confirm')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        // Compteur crédité après confirmation.
        $collected = app(TenantManager::class)->withinTenant(
            $company,
            fn (): float => (float) Fundraiser::query()->where('slug', 'aide-fatou-abc123')->value('collected_amount')
        );
        $this->assertSame(3000.0, $collected);
    }
}
