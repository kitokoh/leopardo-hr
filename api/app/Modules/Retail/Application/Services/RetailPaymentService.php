<?php

declare(strict_types=1);

namespace App\Modules\Retail\Application\Services;

use App\Modules\Retail\Domain\Contracts\RetailPaymentProviderInterface;
use App\Modules\Retail\Domain\Enums\RetailPaymentIntentStatus;
use App\Modules\Retail\Domain\Enums\RetailPaymentMethod;
use App\Modules\Retail\Domain\Exceptions\RetailPaymentProviderException;
use App\Modules\Retail\Domain\Models\RetailOnlinePaymentIntent;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailOrderPayment;
use App\Modules\Retail\Domain\Payments\RetailPaymentWebhookEvent;
use App\Modules\Retail\Infrastructure\Payments\RetailPaymentProviderRegistry;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * BC-17 RETAIL (#7812) — Orchestration du paiement en ligne marketplace :
 * intents de paiement, transitions webhook/reconciliation, remboursement.
 *
 * VOIE UNIQUE des transitions de statut d'intent et du marquage « payee »
 * d'une commande : le webhook signe (verifie fail-closed en amont par le
 * controleur), la reconciliation `retail:payments:reconcile` et le
 * remboursement vendeur passent TOUS par `applyStatus()` — idempotent
 * (rejeu → aucun double effet), transactionnel, verrou de ligne.
 *
 * Au succes : intent `succeeded`, commande `payment_status = paid` +
 * `paid_at`, et enregistrement RetailOrderPayment (`method = online`,
 * `status = captured`, idempotency_key derivee de l'intent — coherence
 * avec l'encaissement POS #7674). En echec/expiration : seul l'intent
 * change, la commande reste non payee (`pending`).
 *
 * Abstraction provider locale au module (config env, registre) — la
 * resolution des credentials PAR TENANT arrivera avec BC-21 (PR #7732,
 * non merge). Montants en minor units. Pas de facade Laravel ici (purete
 * de couche Application, garde #6568).
 */
final class RetailPaymentService
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly RetailPaymentProviderRegistry $providers,
    ) {}

    /**
     * Cree (ou retourne, idempotent) l'intent de paiement d'une commande
     * en ligne `payment_method = online`. La session PSP est creee chez le
     * provider actif ; en cas d'echec provider l'intent passe `failed` et
     * une 422 `PAYMENT_PROVIDER_UNAVAILABLE` est levee (le client peut
     * rejouer le checkout avec la meme cle d'idempotence).
     *
     * @throws ValidationException
     */
    public function createIntentForOrder(RetailOrder $order): RetailOnlinePaymentIntent
    {
        $companyId = (string) $order->company_id;

        /** @var RetailOnlinePaymentIntent|null $existing */
        $existing = RetailOnlinePaymentIntent::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('order_id', (int) $order->id)
            ->whereNotIn('status', [
                RetailPaymentIntentStatus::Failed->value,
                RetailPaymentIntentStatus::Expired->value,
            ])
            ->orderByDesc('id')
            ->first();

        if ($existing instanceof RetailOnlinePaymentIntent) {
            return $existing;
        }

        $provider = $this->defaultProvider();

        $attempt = RetailOnlinePaymentIntent::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('order_id', (int) $order->id)
            ->count();

        /** @var RetailOnlinePaymentIntent $intent */
        $intent = $this->connection->transaction(
            fn (): RetailOnlinePaymentIntent => RetailOnlinePaymentIntent::query()->create([
                'company_id' => $companyId,
                'order_id' => (int) $order->id,
                'intent_reference' => bin2hex(random_bytes(32)),
                'provider' => $provider->providerCode(),
                'amount_minor' => (int) $order->total_minor,
                'currency' => $order->currency,
                'status' => RetailPaymentIntentStatus::Pending->value,
                'checkout_url' => null,
                'provider_payload' => null,
                'idempotency_key' => 'order-'.$order->id.'-'.($attempt + 1),
            ])
        );

        try {
            $result = $provider->createIntent($intent);
        } catch (RetailPaymentProviderException) {
            $intent->forceFill(['status' => RetailPaymentIntentStatus::Failed->value])->save();

            throw ValidationException::withMessages([
                'payment_method' => 'PAYMENT_PROVIDER_UNAVAILABLE',
            ]);
        }

        $intent->forceFill([
            'checkout_url' => $result->checkoutUrl,
            'provider_payload' => $result->payload + ['provider_reference' => $result->providerReference],
        ])->save();

        return $intent->refresh();
    }

    /**
     * Applique un evenement webhook DEJA VERIFIE (signature fail-closed
     * cote controleur). Resolution cross-tenant par `intent_reference`
     * (aleatoire 256 bits) + provider. Idempotent : evenement deja
     * applique, inconnu ou ignore → false sans effet (le webhook repond
     * 200 dans tous ces cas, seul un payload illisible est un 4xx).
     */
    public function handleWebhookEvent(string $providerCode, RetailPaymentWebhookEvent $event): bool
    {
        if ($event->intentReference === null || $event->status === null) {
            return false;
        }

        /** @var RetailOnlinePaymentIntent|null $intent */
        $intent = RetailOnlinePaymentIntent::query()
            ->withoutGlobalScope('company')
            ->where('provider', $providerCode)
            ->where('intent_reference', $event->intentReference)
            ->first();

        if (! $intent instanceof RetailOnlinePaymentIntent) {
            return false;
        }

        // Controle anti-fraude : un montant notifie incoherent est ignore
        // (l'intent reste ouvert, la reconciliation tranchera).
        if ($event->amountMinor !== null && $event->amountMinor !== $intent->amount_minor) {
            return false;
        }

        return $this->applyStatus($intent, $event->status, ['webhook_event' => $event->raw]);
    }

    /**
     * Reconciliation (commande `retail:payments:reconcile`, #7812 D) :
     * re-verifie aupres du provider les intents pending/processing plus
     * vieux que $olderThanMinutes et applique les MEMES transitions que le
     * webhook (voie unique `applyStatus`).
     *
     * @return array{checked: int, updated: int}
     */
    public function reconcilePendingIntents(int $olderThanMinutes): array
    {
        $threshold = Carbon::now()->subMinutes(max(0, $olderThanMinutes));

        /** @var \Illuminate\Database\Eloquent\Collection<int, RetailOnlinePaymentIntent> $intents */
        $intents = RetailOnlinePaymentIntent::query()
            ->withoutGlobalScope('company')
            ->whereIn('status', RetailPaymentIntentStatus::openValues())
            ->where('created_at', '<', $threshold)
            ->orderBy('id')
            ->get();

        $checked = 0;
        $updated = 0;

        foreach ($intents as $intent) {
            if (! $this->providers->has($intent->provider)) {
                continue;
            }

            $checked++;

            $status = $this->providers->resolve($intent->provider)->verifyIntent($intent);

            if ($status instanceof RetailPaymentIntentStatus
                && $this->applyStatus($intent, $status, ['reconciled_at' => Carbon::now()->toIso8601String()])) {
                $updated++;
            }
        }

        return ['checked' => $checked, 'updated' => $updated];
    }

    /**
     * Remboursement vendeur (#7812 E) : uniquement sur un intent
     * `succeeded`. Appelle `provider->refund` puis applique la transition
     * `refunded` (intent + commande + trace RetailOrderPayment).
     *
     * @throws ValidationException 422 PAYMENT_NOT_REFUNDABLE / PAYMENT_PROVIDER_UNAVAILABLE.
     */
    public function refund(RetailOrder $order): RetailOnlinePaymentIntent
    {
        /** @var RetailOnlinePaymentIntent|null $intent */
        $intent = RetailOnlinePaymentIntent::query()
            ->withoutGlobalScope('company')
            ->where('company_id', (string) $order->company_id)
            ->where('order_id', (int) $order->id)
            ->where('status', RetailPaymentIntentStatus::Succeeded->value)
            ->orderByDesc('id')
            ->first();

        if (! $intent instanceof RetailOnlinePaymentIntent) {
            throw ValidationException::withMessages([
                'payment' => 'PAYMENT_NOT_REFUNDABLE',
            ]);
        }

        if (! $this->providers->has($intent->provider)) {
            throw ValidationException::withMessages([
                'payment' => 'PAYMENT_PROVIDER_UNAVAILABLE',
            ]);
        }

        try {
            $result = $this->providers->resolve($intent->provider)->refund($intent);
        } catch (RetailPaymentProviderException) {
            throw ValidationException::withMessages([
                'payment' => 'PAYMENT_PROVIDER_UNAVAILABLE',
            ]);
        }

        if (! $result->succeeded) {
            throw ValidationException::withMessages([
                'payment' => 'PAYMENT_REFUND_REJECTED',
            ]);
        }

        $this->applyStatus($intent, RetailPaymentIntentStatus::Refunded, $result->payload);

        return $intent->refresh();
    }

    /**
     * VOIE UNIQUE des transitions d'intent — transactionnelle, verrou de
     * ligne, idempotente : une transition invalide pour la machine d'etats
     * (dont le rejeu d'un evenement deja applique) retourne false sans
     * effet.
     *
     * @param  array<string, mixed>  $payloadPatch  fusionne dans provider_payload (trace auditable)
     */
    private function applyStatus(
        RetailOnlinePaymentIntent $intent,
        RetailPaymentIntentStatus $target,
        array $payloadPatch = [],
    ): bool {
        /** @var bool $applied */
        $applied = $this->connection->transaction(function () use ($intent, $target, $payloadPatch): bool {
            /** @var RetailOnlinePaymentIntent $locked */
            $locked = RetailOnlinePaymentIntent::query()
                ->withoutGlobalScope('company')
                ->lockForUpdate()
                ->findOrFail((int) $intent->id);

            if (! $locked->status->canTransitionTo($target)) {
                return false;
            }

            $payload = is_array($locked->provider_payload) ? $locked->provider_payload : [];

            $locked->forceFill([
                'status' => $target->value,
                'provider_payload' => $payloadPatch + $payload,
            ])->save();

            if ($target === RetailPaymentIntentStatus::Succeeded) {
                $this->markOrderPaid($locked);
            }

            if ($target === RetailPaymentIntentStatus::Refunded) {
                $this->markOrderRefunded($locked);
            }

            return true;
        });

        $intent->refresh();

        return $applied;
    }

    /**
     * Succes de paiement : commande `payment_status = paid` + `paid_at`,
     * et trace d'encaissement RetailOrderPayment (`online`/`captured`,
     * idempotency_key derivee de l'intent → rejeu absorbe par l'unique
     * tenant + la garde d'existence).
     */
    private function markOrderPaid(RetailOnlinePaymentIntent $intent): void
    {
        /** @var RetailOrder|null $order */
        $order = RetailOrder::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $intent->company_id)
            ->find($intent->order_id);

        if (! $order instanceof RetailOrder) {
            return;
        }

        $order->forceFill([
            'payment_status' => 'paid',
            'paid_at' => Carbon::now(),
        ])->save();

        $paymentKey = 'pay-intent-'.$intent->id;

        $exists = RetailOrderPayment::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $intent->company_id)
            ->where('idempotency_key', $paymentKey)
            ->exists();

        if (! $exists) {
            RetailOrderPayment::query()->create([
                'company_id' => $intent->company_id,
                'order_id' => $intent->order_id,
                'pos_session_id' => null,
                'method' => RetailPaymentMethod::Online->value,
                'amount_minor' => $intent->amount_minor,
                'currency' => $intent->currency,
                'status' => 'captured',
                'paid_at' => Carbon::now(),
                'reference' => $intent->intent_reference,
                'idempotency_key' => $paymentKey,
            ]);
        }
    }

    /**
     * Remboursement : commande `payment_status = refunded` et trace
     * d'encaissement basculee `refunded` (le montant reste visible dans
     * l'historique — trace auditable, pas de suppression).
     */
    private function markOrderRefunded(RetailOnlinePaymentIntent $intent): void
    {
        /** @var RetailOrder|null $order */
        $order = RetailOrder::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $intent->company_id)
            ->find($intent->order_id);

        if ($order instanceof RetailOrder) {
            $order->forceFill(['payment_status' => 'refunded'])->save();
        }

        RetailOrderPayment::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $intent->company_id)
            ->where('idempotency_key', 'pay-intent-'.$intent->id)
            ->update(['status' => 'refunded']);
    }

    /**
     * @throws ValidationException 422 si le provider configure est inconnu.
     */
    private function defaultProvider(): RetailPaymentProviderInterface
    {
        try {
            return $this->providers->default();
        } catch (RetailPaymentProviderException) {
            throw ValidationException::withMessages([
                'payment_method' => 'PAYMENT_PROVIDER_UNAVAILABLE',
            ]);
        }
    }
}
