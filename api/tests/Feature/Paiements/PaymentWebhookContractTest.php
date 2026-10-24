<?php

declare(strict_types=1);

namespace Tests\Feature\Paiements;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Paiements\Domain\Enums\PaymentTransactionStatus;
use App\Modules\Paiements\Domain\Enums\PaymentTransactionType;
use App\Modules\Paiements\Domain\Models\PaymentProviderInstance;
use App\Modules\Paiements\Domain\Models\PaymentTransaction;
use App\Modules\Paiements\Domain\Models\PaymentWebhookEndpoint;
use App\Modules\Paiements\Domain\Models\PaymentWebhookEvent;
use App\Modules\Paiements\Infrastructure\Services\Rails\SimulatorRail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-38 PAIEMENTS (#8742) — contrat de webhook entrant.
 *
 * L'ecart de l'audit readiness : le webhook verifiait la signature et resolvait
 * l'instance, mais **n'ecrivait rien en base** — il appelait le rail et
 * renvoyait son statut. Aucun contrat n'existait pour un evenement hors
 * sequence, en retard ou duplique.
 *
 * Ce test verrouille les trois regles du contrat, et surtout celle qui protege
 * l'integrite : **un evenement hors sequence ne ressuscite jamais un etat
 * terminal**. Un test qui ne verifierait que le chemin nominal passerait meme
 * si le webhook ecrasait un `cancelled` par un `succeeded` — c'est exactement
 * le defaut que l'audit a releve.
 */
class PaymentWebhookContractTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private PaymentProviderInstance $instance;

    private PaymentWebhookEndpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        $this->instance = PaymentProviderInstance::query()->create([
            'company_id' => null,
            'type_code' => SimulatorRail::CODE,
            'display_name' => 'Simulateur (contrat webhook)',
            'countries' => [],
            'currencies' => [],
            'status' => 'active',
            'config' => ['webhook_success_rate' => 100],
            'priority' => 10,
        ]);

        $this->endpoint = PaymentWebhookEndpoint::query()->create([
            'company_id' => $company->id,
            'provider_instance_id' => $this->instance->id,
            'token' => PaymentWebhookEndpoint::generateToken(),
            'path' => 'paiements/webhooks/test',
            'secret_signature' => 'whsec_contract_test',
            'status' => 'active',
        ]);
    }

    private function transaction(PaymentTransactionStatus $status, string $reference): PaymentTransaction
    {
        return PaymentTransaction::query()->create([
            'company_id' => $this->company->id,
            'type' => PaymentTransactionType::CashIn,
            'status' => $status,
            'provider_instance_id' => $this->instance->id,
            'amount' => 10_000,
            'fee_amount' => 0,
            'currency' => 'DZD',
            'idempotency_key' => 'contract-'.$reference,
            'external_reference' => $reference,
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function postWebhook(array $body): \Illuminate\Testing\TestResponse
    {
        $json = (string) json_encode($body, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $json, 'whsec_contract_test');

        return $this->call('POST', '/api/v1/paiements/webhooks/'.$this->endpoint->token, [], [], [], [
            'HTTP_X_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $json);
    }

    public function test_webhook_advances_the_transaction_and_journals_the_event(): void
    {
        $transaction = $this->transaction(PaymentTransactionStatus::Pending, 'ref-advance');

        $this->postWebhook([
            'event_id' => 'evt_advance_1',
            'event' => 'payment.succeeded',
            'external_reference' => 'ref-advance',
        ])->assertOk()->assertJsonPath('data.outcome', PaymentWebhookEvent::OUTCOME_PROCESSED);

        // La transaction a REELLEMENT progresse : c'est le defaut corrige.
        $this->assertSame(PaymentTransactionStatus::Succeeded, $transaction->fresh()->status);

        $this->assertDatabaseHas('payment_webhook_events', [
            'event_id' => 'evt_advance_1',
            'outcome' => PaymentWebhookEvent::OUTCOME_PROCESSED,
            'requested_status' => 'succeeded',
            'applied_status' => 'succeeded',
            'transaction_id' => $transaction->id,
        ]);
    }

    public function test_replayed_event_is_a_no_op_returning_the_existing_result(): void
    {
        $transaction = $this->transaction(PaymentTransactionStatus::Pending, 'ref-replay');

        $body = [
            'event_id' => 'evt_replay_1',
            'event' => 'payment.succeeded',
            'external_reference' => 'ref-replay',
        ];

        $this->postWebhook($body)->assertOk()->assertJsonPath('data.replayed', false);
        $this->postWebhook($body)->assertOk()->assertJsonPath('data.replayed', true);

        // Un seul evenement journalise, une seule application.
        $this->assertSame(1, PaymentWebhookEvent::query()->where('event_id', 'evt_replay_1')->count());
        $this->assertSame(PaymentTransactionStatus::Succeeded, $transaction->fresh()->status);
    }

    public function test_out_of_sequence_event_never_resurrects_a_terminal_state(): void
    {
        // LA garantie centrale : la transaction est deja `cancelled` (terminal).
        // Un `succeeded` recu apres coup ne doit PAS la faire repasser a
        // `succeeded` — sinon un webhook en retard annulerait une annulation.
        $transaction = $this->transaction(PaymentTransactionStatus::Cancelled, 'ref-oos');

        $this->postWebhook([
            'event_id' => 'evt_oos_1',
            'event' => 'payment.succeeded',
            'external_reference' => 'ref-oos',
        ])->assertOk()->assertJsonPath('data.outcome', PaymentWebhookEvent::OUTCOME_OUT_OF_SEQUENCE);

        $this->assertSame(
            PaymentTransactionStatus::Cancelled,
            $transaction->fresh()->status,
            'Un evenement hors sequence ne doit JAMAIS ressusciter un etat terminal.',
        );

        // Le refus est journalise, pas silencieusement jete : un webhook perdu
        // reste diagnosticable en lisant la table.
        $this->assertDatabaseHas('payment_webhook_events', [
            'event_id' => 'evt_oos_1',
            'outcome' => PaymentWebhookEvent::OUTCOME_OUT_OF_SEQUENCE,
            'requested_status' => 'succeeded',
            'applied_status' => 'cancelled',
        ]);
    }

    public function test_event_for_unknown_reference_is_journaled_without_side_effect(): void
    {
        $this->postWebhook([
            'event_id' => 'evt_unknown_1',
            'event' => 'payment.succeeded',
            'external_reference' => 'ref-does-not-exist',
        ])->assertOk()->assertJsonPath('data.outcome', PaymentWebhookEvent::OUTCOME_NO_TRANSACTION);

        $this->assertDatabaseHas('payment_webhook_events', [
            'event_id' => 'evt_unknown_1',
            'outcome' => PaymentWebhookEvent::OUTCOME_NO_TRANSACTION,
            'transaction_id' => null,
        ]);
    }

    public function test_event_without_provider_id_falls_back_to_payload_fingerprint(): void
    {
        // Un fournisseur qui n'envoie pas d'identifiant doit quand meme
        // beneficier de l'idempotence : l'empreinte du corps sert d'`event_id`.
        $transaction = $this->transaction(PaymentTransactionStatus::Pending, 'ref-fingerprint');

        $body = [
            'event' => 'payment.succeeded',
            'external_reference' => 'ref-fingerprint',
        ];

        $this->postWebhook($body)->assertOk()->assertJsonPath('data.replayed', false);
        $this->postWebhook($body)->assertOk()->assertJsonPath('data.replayed', true);

        $this->assertSame(1, PaymentWebhookEvent::query()->count());
        $this->assertSame(PaymentTransactionStatus::Succeeded, $transaction->fresh()->status);
    }

    public function test_invalid_signature_is_still_rejected_before_any_journaling(): void
    {
        $this->transaction(PaymentTransactionStatus::Pending, 'ref-sig');

        $this->call('POST', '/api/v1/paiements/webhooks/'.$this->endpoint->token, [], [], [], [
            'HTTP_X_SIGNATURE' => 'deadbeef',
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['event_id' => 'evt_sig', 'external_reference' => 'ref-sig']))->assertStatus(400);

        // Aucun evenement journalise : la signature est verifiee AVANT le
        // contrat d'application.
        $this->assertSame(0, PaymentWebhookEvent::query()->count());
    }
}
