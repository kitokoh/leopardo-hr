<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7496 — ingestion des événements d'étape du funnel d'acquisition.
 *
 * Couvre : secret partagé (fail-closed quand configuré, accepté avec alerte
 * quand absent — même arbitrage que #7301), liste FERMÉE d'événements,
 * filtrage du contexte (aucune clé hors liste blanche ne doit atteindre la
 * base — c'est le critère 4 de #7496 : pas de PII, pas de réponse
 * d'entretien brute).
 */
class AcquisitionFunnelEventControllerTest extends TestCase
{
    use RefreshTenantDatabase;

    private const SECRET = 'funnel-shared-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.marketing_lead_webhook.secret', self::SECRET);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'event' => 'signup_view',
            'correlation_id' => 'corr-0001',
            'occurred_at' => now()->toIso8601String(),
            'attribution' => [
                'source' => 'facebook_ads',
                'utm_source' => 'facebook',
                'utm_campaign' => 'rentree',
            ],
            'context' => ['page' => '/signup'],
        ], $overrides);
    }

    public function test_it_persists_a_funnel_step_with_the_shared_secret(): void
    {
        $response = $this->postJson('/api/v1/funnel/events', $this->payload(), [
            'Authorization' => 'Bearer '.self::SECRET,
        ]);

        $response->assertStatus(202)->assertJson(['received' => true]);

        $this->assertDatabaseHas('acquisition_funnel_events', [
            'event' => 'signup_view',
            'correlation_id' => 'corr-0001',
            'source' => 'facebook_ads',
            'utm_source' => 'facebook',
            'utm_campaign' => 'rentree',
        ]);
    }

    public function test_it_rejects_an_invalid_secret_without_writing(): void
    {
        $this->postJson('/api/v1/funnel/events', $this->payload(), [
            'Authorization' => 'Bearer wrong-secret',
        ])->assertStatus(400);

        $this->assertSame(0, DB::table('acquisition_funnel_events')->count());
    }

    /**
     * Même arbitrage que #7301 : secret non configuré → accepté (perdre la
     * mesure du funnel au lancement des campagnes est le pire des deux maux),
     * jamais silencieux (log d'avertissement côté contrôleur).
     */
    public function test_it_accepts_events_when_the_secret_is_not_configured(): void
    {
        config()->set('services.marketing_lead_webhook.secret', '');

        $this->postJson('/api/v1/funnel/events', $this->payload())
            ->assertStatus(202);

        $this->assertSame(1, DB::table('acquisition_funnel_events')->count());
    }

    public function test_it_rejects_an_event_outside_the_closed_list(): void
    {
        $this->postJson('/api/v1/funnel/events', $this->payload(['event' => 'password_typed']), [
            'Authorization' => 'Bearer '.self::SECRET,
        ])->assertStatus(422);

        $this->assertSame(0, DB::table('acquisition_funnel_events')->count());
    }

    /**
     * Critère 4 de #7496 : le contexte est filtré par liste blanche — une clé
     * inattendue (e-mail, réponse d'entretien…) n'atteint JAMAIS la base.
     */
    public function test_it_never_stores_context_keys_outside_the_allowlist(): void
    {
        $this->postJson('/api/v1/funnel/events', $this->payload([
            'event' => 'interview_question_answered',
            'context' => [
                'step_key' => 'first_employee',
                'question_index' => 3,
                'email' => 'prospect@example.test',
                'answer' => 'restaurateur avec 12 salariés',
            ],
        ]), [
            'Authorization' => 'Bearer '.self::SECRET,
        ])->assertStatus(202);

        $row = DB::table('acquisition_funnel_events')->first();
        $this->assertNotNull($row);
        $context = is_string($row->context) ? $row->context : json_encode($row->context);
        $this->assertIsString($context);
        $this->assertStringContainsString('first_employee', $context);
        $this->assertStringNotContainsString('prospect@example.test', $context);
        $this->assertStringNotContainsString('restaurateur', $context);
    }
}
