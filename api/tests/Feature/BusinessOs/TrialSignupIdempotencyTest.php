<?php

declare(strict_types=1);

namespace Tests\Feature\BusinessOs;

use App\Core\Tenant\Domain\Models\CompanyRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-O-05 / #8626 — idempotence de l'inscription PUBLIQUE.
 *
 * `POST /trial/signup` est une écriture sans session : le middleware
 * `IdempotencyMiddleware` (#5277) ne la couvre pas (sa clé de cache est scopée
 * par le token `Authorization`). Ce que ce test prouve, et rien de plus :
 *
 *  1. deux POST avec la MÊME clé et le MÊME corps ne créent qu'UNE demande et
 *     qu'UNE ligne de suivi — la seconde réponse est le rejeu exact ;
 *  2. la même clé avec un corps DIFFÉRENT est refusée (422) : une clé ne peut
 *     pas servir deux intentions ;
 *  3. sans clé, le comportement historique est strictement préservé ;
 *  4. une clé invalide est refusée (422) AVANT toute écriture.
 */
class TrialSignupIdempotencyTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Accept-Language', 'fr');
    }

    /** @return array<string, mixed> */
    private function payload(string $email): array
    {
        return [
            'email' => $email,
            'company' => 'Idempotence SARL',
            'country' => 'DZ',
        ];
    }

    public function test_same_key_and_body_creates_a_single_request_and_replays_the_response(): void
    {
        Mail::fake();

        $email = 'idem-'.uniqid().'@exemple.test';
        $key = 'idem-key-'.uniqid();

        $first = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/trial/signup', $this->payload($email));

        $first->assertStatus(200)->assertJsonPath('success', true);
        $firstToken = $first->json('data.provisioning_token');

        $second = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/trial/signup', $this->payload($email));

        $second->assertStatus(200)
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('success', true);

        // Rejeu EXACT : même token, donc aucune seconde intention.
        $this->assertSame($firstToken, $second->json('data.provisioning_token'));

        $this->assertSame(
            1,
            CompanyRequest::query()->where('email', $email)->count(),
            'Deux POST avec la même clé ne doivent créer qu\'une demande.'
        );

        $this->assertSame(
            1,
            DB::table('trial_provisionings')->where('email', $email)->count(),
            'Deux POST avec la même clé ne doivent créer qu\'une ligne de suivi.'
        );
    }

    public function test_same_key_with_a_different_body_is_rejected(): void
    {
        Mail::fake();

        $email = 'idem-conflict-'.uniqid().'@exemple.test';
        $key = 'idem-conflict-'.uniqid();

        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/trial/signup', $this->payload($email))
            ->assertStatus(200);

        // Même clé, corps différent (autre pays) → intention différente.
        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/trial/signup', [
                'email' => $email,
                'company' => 'Idempotence SARL',
                'country' => 'TR',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'IDEMPOTENCY_KEY_REUSED');
    }

    public function test_without_key_the_historical_behaviour_is_preserved(): void
    {
        Mail::fake();

        $email = 'idem-nokey-'.uniqid().'@exemple.test';

        $this->postJson('/api/v1/trial/signup', $this->payload($email))->assertStatus(200);
        $this->postJson('/api/v1/trial/signup', $this->payload($email))->assertStatus(200);

        // Sans clé, chaque POST crée sa propre demande : le comportement
        // historique (anti-énumération, OTP) reste strictement inchangé.
        $this->assertSame(2, CompanyRequest::query()->where('email', $email)->count());
    }

    public function test_invalid_key_is_rejected_before_any_write(): void
    {
        Mail::fake();

        $email = 'idem-invalid-'.uniqid().'@exemple.test';

        $this->withHeader('Idempotency-Key', 'short')
            ->postJson('/api/v1/trial/signup', $this->payload($email))
            ->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_IDEMPOTENCY_KEY');

        $this->assertSame(0, CompanyRequest::query()->where('email', $email)->count());
    }
}
