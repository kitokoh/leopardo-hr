<?php

declare(strict_types=1);

namespace Tests\Feature\Fundraising;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanyRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Recette E2E de la verticale FUNDRAISING (issue #8421) — le parcours
 * complet promis au propriétaire, par le VRAI chemin HTTP (jamais de
 * raccourci factory après le signup) :
 *
 *   signup vitrine (solution `fundraising`) → verify OTP → set-password
 *   → login Sanctum → /auth/me (manager principal, verticale active) →
 *   paramétrage du module (GET/PUT settings) → création d'une cagnotte →
 *   publication → page publique lisible SANS auth → don mobile money
 *   (sandbox, polling) → don carte (Stripe mocké, webhook signé) →
 *   mur des soutiens → demande de reversement → reversement payé →
 *   solde disponible à zéro.
 *
 * Chaque maillon de la checklist #8421 est adressé par son endpoint
 * réel : une régression d'activation (SolutionCatalogue), de publication
 * (annuaire public), de passerelle de paiement ou de règle de solde
 * casse CE test.
 */
class FundraisingTenantJourneyTest extends TestCase
{
    use RefreshTenantDatabase;

    private const PASSWORD = 'Teranga-2026-Cagnotte!';

    private const EMAIL = 'presidente@asso-teranga.sn';

    public function test_fundraising_journey_from_signup_to_paid_payout(): void
    {
        Mail::fake();
        $this->withHeader('Accept-Language', 'fr');

        // Config Stripe posée AVANT toute requête : la factory des
        // passerelles capture `config/fundraising.php` à sa première
        // résolution — une clé définie après le premier appel (don mobile
        // money) ne serait jamais vue (503 fail-closed).
        config(['fundraising.stripe.secret_key' => 'sk_test_journey']);
        config(['fundraising.stripe.webhook_secret' => 'whsec_journey']);

        // ── 1. Signup depuis la vitrine — secteur solidaire ─────────────
        // La solution `fundraising` est demandée seule : son manifest
        // (FUND-080) est enregistré au SolutionCatalogue, l'activation est
        // donc résolue au provisioning (fail-closed sinon).
        $signup = $this->postJson('/api/v1/trial/signup', [
            'email' => self::EMAIL,
            'company' => 'Association Teranga Solidarité',
            'first_name' => 'Aminata',
            'last_name' => 'Sow',
            'role' => 'founder',
            'employees' => '1-10',
            'country' => 'SN',
            'solutions' => ['fundraising'],
        ])->assertStatus(200);

        $provisioningToken = $signup->json('data.provisioning_token');
        self::assertIsString($provisioningToken);

        // ── 2. Vérification OTP → tenant provisionné, verticale active ──
        /** @var CompanyRequest $companyRequest */
        $companyRequest = CompanyRequest::query()->where('email', self::EMAIL)->firstOrFail();

        $verify = $this->postJson('/api/v1/trial/verify', [
            'email' => self::EMAIL,
            'code' => $companyRequest->verification_token,
        ])->assertStatus(201)->assertJson(['success' => true]);

        /** @var Company $company */
        $company = Company::query()->findOrFail($verify->json('data.company.id'));
        self::assertTrue(
            $company->hasFeature('fundraising'),
            'la verticale fundraising devrait être active dès le provisioning'
        );

        // ── 3. La responsable définit son mot de passe puis se connecte ──
        $this->postJson('/api/v1/trial/set-password', [
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], ['X-Token' => $provisioningToken])
            ->assertOk()
            ->assertJson(['success' => true]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
            'device_name' => 'journey-fundraising',
        ])->assertOk();

        $token = $login->json('token');
        self::assertIsString($token);
        $auth = ['Authorization' => 'Bearer '.$token];

        // ── 4. /auth/me : compte rattaché à SON organisation, principal ──
        $this->getJson('/api/v1/auth/me', $auth)
            ->assertOk()
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonPath('data.manager_role', 'principal');

        // ── 5. Paramétrage du module (console « Cagnottes solidaires ») ──
        // Les défauts serveur sont lisibles…
        $this->getJson('/api/v1/fundraising/settings', $auth)
            ->assertOk()
            ->assertJsonPath('data.enabled_methods', function (mixed $methods): bool {
                return is_array($methods) && $methods !== [];
            });

        // …puis la responsable paramètre sa verticale : moyens de paiement
        // offerts, garde-fous, montants suggérés, options publiques.
        $this->putJson('/api/v1/fundraising/settings', [
            'enabled_methods' => ['card', 'mobile_money', 'cash'],
            'default_min_amount' => 500,
            'default_max_amount' => 500000,
            'default_suggested_amounts' => [5000, 10000, 20000],
            'allow_anonymous' => true,
            'allow_messages' => true,
            'show_supporters' => true,
        ], $auth)->assertOk();

        // ── 6. Création de la cagnotte (brouillon) ──────────────────────
        $created = $this->postJson('/api/v1/fundraising/fundraisers', [
            'title' => 'Aide pour Fatou — frais médicaux',
            'description' => 'Fatou doit être opérée rapidement. Chaque don compte.',
            'beneficiary_name' => 'Fatou Ndiaye',
            'beneficiary_contact' => '+221770000000',
            'category' => 'medical',
            'goal_amount' => 100000,
            'currency' => 'XOF',
            'suggested_amounts' => [5000, 10000, 20000],
            'min_amount' => 500,
        ], $auth)->assertCreated();

        $fundraiserId = $created->json('data.id');
        self::assertTrue(is_int($fundraiserId) || is_string($fundraiserId));
        $slug = $created->json('data.slug');
        self::assertIsString($slug);

        // ── 7. Publication → le lien public devient consultable ──────────
        $this->postJson('/api/v1/fundraising/fundraisers/'.$fundraiserId.'/publish', [], $auth)
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        // Hors session (grand public) : la fiche est lisible, sans donnée
        // interne (contact bénéficiaire, company_id) — DTO public strict.
        $this->getJson('/api/v1/public/fundraisers/'.$slug)
            ->assertOk()
            ->assertJsonPath('data.slug', $slug)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.accepts_contributions', true)
            ->assertJsonMissingPath('data.beneficiary_contact')
            ->assertJsonMissingPath('data.company_id');

        // ── 8. Don mobile money (sandbox) → polling → crédité ────────────
        $mobile = $this->postJson('/api/v1/public/fundraisers/'.$slug.'/contribute', [
            'amount' => 5000,
            'payment_method' => 'mobile_money',
            'contributor_name' => 'Moussa Diop',
            'contributor_phone' => '+221770000001',
            'message' => 'Courage Fatou !',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $mobileReference = $mobile->json('data.reference');
        self::assertIsString($mobileReference);

        // Polling : la re-conciliation sandbox solde la contribution.
        $this->getJson('/api/v1/public/contributions/'.$mobileReference)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        // ── 9. Don carte (Stripe mocké) → webhook signé → crédité ────────
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_journey_1',
                'url' => 'https://checkout.stripe.com/pay/cs_journey_1',
            ], 200),
        ]);

        $card = $this->postJson('/api/v1/public/fundraisers/'.$slug.'/contribute', [
            'amount' => 10000,
            'payment_method' => 'card',
            'contributor_name' => 'Payeur Carte',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment.redirect_url', 'https://checkout.stripe.com/pay/cs_journey_1');

        $cardReference = $card->json('data.reference');
        self::assertIsString($cardReference);

        $payload = (string) json_encode([
            'id' => 'evt_journey_1',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_journey_1', 'created' => time()]],
        ]);
        $timestamp = (string) time();
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_journey');

        $this->call('POST', '/api/v1/webhooks/fundraising/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk()->assertJsonPath('status', 'applied');

        $this->getJson('/api/v1/public/contributions/'.$cardReference)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        // ── 10. Compteurs + mur des soutiens ─────────────────────────────
        $this->getJson('/api/v1/public/fundraisers/'.$slug)
            ->assertOk()
            ->assertJsonPath('data.collected_amount', 15000)
            ->assertJsonPath('data.contributions_count', 2);

        $supporters = $this->getJson('/api/v1/public/fundraisers/'.$slug.'/supporters')
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $supportersData = $supporters->json('data');
        self::assertTrue(is_array($supportersData));
        $names = array_column($supportersData, 'name');
        self::assertContains('Moussa Diop', $names);
        self::assertContains('Payeur Carte', $names);

        // ── 11. Reversement au bénéficiaire : règle de solde + workflow ──
        // Dépassement du solde → refus explicite (argent = strict).
        $this->postJson('/api/v1/fundraising/fundraisers/'.$fundraiserId.'/payouts', [
            'amount' => 999999,
            'method' => 'mobile_money',
            'recipient_name' => 'Fatou Ndiaye',
            'recipient_account' => '+221770000000',
        ], $auth)->assertStatus(422)
            ->assertJsonPath('error', 'PAYOUT_AMOUNT_EXCEEDS_BALANCE');

        // Demande valide → requested → processing → paid.
        $payoutId = $this->postJson('/api/v1/fundraising/fundraisers/'.$fundraiserId.'/payouts', [
            'amount' => 15000,
            'method' => 'mobile_money',
            'recipient_name' => 'Fatou Ndiaye',
            'recipient_account' => '+221770000000',
        ], $auth)->assertStatus(201)
            ->assertJsonPath('data.status', 'requested')
            ->json('data.id');
        self::assertTrue(is_int($payoutId) || is_string($payoutId));

        $this->postJson('/api/v1/fundraising/payouts/'.$payoutId.'/process', [], $auth)
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');

        $this->postJson('/api/v1/fundraising/payouts/'.$payoutId.'/mark-paid', [
            'provider_reference' => 'MM-PAYOUT-JOURNEY-1',
        ], $auth)->assertOk()
            ->assertJsonPath('data.status', 'paid');

        // Solde disponible retombé à zéro (comparaison numérique souple :
        // JSON ne distingue pas 0 de 0.0).
        $balance = $this->getJson('/api/v1/fundraising/fundraisers/'.$fundraiserId, $auth)
            ->assertOk()
            ->json('data.available_balance');
        self::assertTrue(is_numeric($balance));
        self::assertEquals(0.0, (float) $balance);
    }
}
