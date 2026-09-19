<?php

declare(strict_types=1);

namespace App\Modules\Retail\Application\Services;

use App\Modules\Retail\Domain\Contracts\RetailPaymentProviderContract;
use App\Modules\Retail\Domain\Enums\RetailOrderSource;
use App\Modules\Retail\Domain\Enums\RetailOrderStatus;
use App\Modules\Retail\Domain\Enums\RetailPaymentIntentStatus;
use App\Modules\Retail\Domain\Enums\RetailPaymentMethod;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailOrderPayment;
use App\Modules\Retail\Domain\Models\RetailPaymentIntent;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * BC-17 RETAIL (#7812) — Paiement en ligne marketplace Leopardo Marché
 * (mobile money / PSP, chantier BC-21) : intents de paiement, réconciliation
 * webhook, marquage de la commande payée.
 *
 * Invariants :
 *  - montants TOUJOURS relus serveur (solde restant de la commande — jamais
 *    de confiance client) ;
 *  - un seul intent `pending` par commande (index unique partiel) : le rejeu
 *    de la demande retourne l'intent existant (`created = false`) ;
 *  - la confirmation ne vient QUE du webhook signé (HMAC-SHA256 fail-closed,
 *    contrôleur) : transition `pending → paid` idempotente au rejeu, qui
 *    capture un `RetailOrderPayment` `method=online` (le COD du handoff
 *    BC-26 #7811 devient alors nul — solde non encaissé recalculé) ;
 *  - commande annulée ou déjà payée → 422 (aucun intent).
 *
 * Pas de facade Laravel (pureté de couche Application, garde #6568) : la
 * connexion et le port PSP (contrat BC-21, seam journalisé tant que
 * bc/bc21-paiements-encaissement n'est pas mergée) sont injectés.
 */
final class RetailOnlinePaymentService
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly RetailPaymentProviderContract $provider,
    ) {}

    /**
     * Crée (ou retourne) l'intent de paiement `pending` d'une commande en
     * ligne. Idempotent : un intent `pending` existant est réutilisé tel
     * quel — le PSP n'est PAS rappelé.
     *
     * @return array{intent: RetailPaymentIntent, created: bool}
     *
     * @throws ValidationException 422 (commande hors canal online, annulée ou déjà payée).
     */
    public function createIntent(RetailOrder $order): array
    {
        /** @var array{intent: RetailPaymentIntent, created: bool} $result */
        $result = $this->connection->transaction(function () use ($order): array {
            /** @var RetailOrder $locked */
            $locked = RetailOrder::query()
                ->withoutGlobalScope('company')
                ->where('company_id', (string) $order->company_id)
                ->lockForUpdate()
                ->findOrFail((int) $order->id);

            if ($locked->source !== RetailOrderSource::Online) {
                throw ValidationException::withMessages(['order' => 'ORDER_NOT_ONLINE']);
            }

            if ($locked->status === RetailOrderStatus::Cancelled) {
                throw ValidationException::withMessages(['order' => 'ORDER_CANCELLED']);
            }

            $outstanding = $this->outstandingAmountMinor($locked);

            if ($outstanding <= 0) {
                throw ValidationException::withMessages(['order' => 'ORDER_ALREADY_PAID']);
            }

            /** @var RetailPaymentIntent|null $existing */
            $existing = RetailPaymentIntent::query()
                ->withoutGlobalScope('company')
                ->where('company_id', (string) $locked->company_id)
                ->where('order_id', (int) $locked->id)
                ->where('status', RetailPaymentIntentStatus::Pending->value)
                ->first();

            if ($existing instanceof RetailPaymentIntent) {
                return ['intent' => $existing, 'created' => false];
            }

            $initiated = $this->provider->initiate(
                companyId: (string) $locked->company_id,
                orderReference: $locked->reference,
                amountMinor: $outstanding,
                currency: $locked->currency,
                customerPhone: $locked->customer_phone,
            );

            /** @var RetailPaymentIntent $intent */
            $intent = RetailPaymentIntent::query()->create([
                'company_id' => (string) $locked->company_id,
                'order_id' => (int) $locked->id,
                'provider' => $initiated['provider'],
                'status' => RetailPaymentIntentStatus::Pending->value,
                'amount_minor' => $outstanding,
                'currency' => $locked->currency,
                'provider_reference' => $initiated['provider_reference'],
                'checkout_url' => $initiated['checkout_url'],
            ]);

            return ['intent' => $intent, 'created' => true];
        });

        return $result;
    }

    /**
     * Réconciliation webhook (payload DÉJÀ vérifié par signature côté
     * contrôleur — fail-closed #2615). Transitions idempotentes :
     * `pending → paid` capture le paiement `online` ; `pending → failed`
     * consigne la raison ; un rejeu (`paid → paid`, `failed → failed`)
     * retourne l'intent tel quel sans double écriture.
     *
     * @return RetailPaymentIntent|null null si provider_reference inconnu (→ 404 contrôleur).
     */
    public function reconcile(string $providerReference, string $status, ?string $failureReason = null): ?RetailPaymentIntent
    {
        /** @var RetailPaymentIntent|null $result */
        $result = $this->connection->transaction(function () use ($providerReference, $status, $failureReason): ?RetailPaymentIntent {
            /** @var RetailPaymentIntent|null $intent */
            $intent = RetailPaymentIntent::query()
                ->withoutGlobalScope('company')
                ->where('provider_reference', $providerReference)
                ->lockForUpdate()
                ->first();

            if (! $intent instanceof RetailPaymentIntent) {
                return null;
            }

            if ($intent->status !== RetailPaymentIntentStatus::Pending) {
                // Rejeu de webhook : état final déjà atteint, aucune écriture.
                return $intent;
            }

            if ($status === RetailPaymentIntentStatus::Paid->value) {
                RetailOrderPayment::query()->create([
                    'company_id' => $intent->company_id,
                    'order_id' => (int) $intent->order_id,
                    'pos_session_id' => null,
                    'method' => RetailPaymentMethod::Online->value,
                    'amount_minor' => (int) $intent->amount_minor,
                    'currency' => $intent->currency,
                    'status' => 'captured',
                    'paid_at' => Carbon::now(),
                    'reference' => $intent->provider_reference,
                    'idempotency_key' => 'rpi-'.$intent->provider_reference,
                ]);

                $intent->forceFill([
                    'status' => RetailPaymentIntentStatus::Paid->value,
                    'paid_at' => Carbon::now(),
                ])->save();

                return $intent->refresh();
            }

            $intent->forceFill([
                'status' => RetailPaymentIntentStatus::Failed->value,
                'failure_reason' => $failureReason,
            ])->save();

            return $intent->refresh();
        });

        return $result;
    }

    /**
     * Solde restant à encaisser (total moins paiements capturés) — même
     * définition que le COD du handoff BC-26 (#7811).
     */
    public function outstandingAmountMinor(RetailOrder $order): int
    {
        $captured = (int) RetailOrderPayment::query()
            ->withoutGlobalScope('company')
            ->where('company_id', (string) $order->company_id)
            ->where('order_id', (int) $order->id)
            ->where('status', 'captured')
            ->sum('amount_minor');

        return max(0, (int) $order->total_minor - $captured);
    }
}
