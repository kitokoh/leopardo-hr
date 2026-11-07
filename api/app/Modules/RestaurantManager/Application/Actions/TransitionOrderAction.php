<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Application\Services\OrderStateMachine;
use App\Modules\RestaurantManager\Application\Services\StockDecrementer;
use App\Modules\RestaurantManager\Domain\Enums\OrderItemStatus;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use App\Shared\Contracts\Notification\PublicClientNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * RESTO-404 (#6191) / RESTO-411 (#6198) — Transitions d'état d'une commande.
 *
 * Toute transition est validée par la machine à états (spec §4.5) : hors
 * workflow → 409. `version` protège la commande contre les écritures
 * concurrentes. À la CONFIRMATION (open → in_preparation), le stock des
 * ingrédients des lignes actives est décrémenté dans la MÊME transaction
 * (verrous SELECT FOR UPDATE, jamais négatif — RESTO-411). Événement
 * `restaurant.order.created.v1` publié à la soumission (payload redigé).
 */
final class TransitionOrderAction
{
    public const EVENT_ORDER_CREATED = 'restaurant.order.created.v1';

    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly RestaurantOutboxPublisher $outbox,
        private readonly StockDecrementer $stockDecrementer,
        private readonly PublicClientNotifier $publicClientNotifier,
    ) {}

    public function execute(Employee $actor, RestaurantOrder $order, OrderStatus $target): RestaurantOrder
    {
        if ($order->company_id !== $actor->company_id) {
            throw new RuntimeException('Order does not belong to tenant.');
        }

        $source = $order->status;

        if (! $this->stateMachine->canTransition($source, $target)) {
            abort(409, sprintf('Transition not allowed from "%s" to "%s".', $source->value, $target->value));
        }

        // Une commande soumise doit contenir au moins une ligne active.
        if ($target === OrderStatus::OPEN) {
            $hasActiveItem = $order->items()
                ->where('status', OrderItemStatus::ACTIVE->value)
                ->exists();

            if (! $hasActiveItem) {
                abort(422, 'Cannot submit an order without at least one active item.');
            }
        }

        DB::transaction(function () use ($order, $target): void {
            // RESTO-411 : le décrément de stock à la confirmation se fait dans
            // la transaction (les lignes de stock sont verrouillées AVANT la
            // mise à jour d'état — deux confirmations simultanées sur le
            // dernier stock : une seule passe).
            if ($target === OrderStatus::IN_PREPARATION) {
                $this->stockDecrementer->decrementForOrder($order);
            }

            $affected = DB::table('restaurant_orders')
                ->where('id', $order->id)
                ->where('company_id', $order->company_id)
                ->where('version', $order->version)
                ->update([
                    'status' => $target->value,
                    'version' => $order->version + 1,
                ]);

            if ($affected !== 1) {
                abort(409, 'Order was modified concurrently; reload and retry.');
            }
        });

        $order->refresh();

        if ($target === OrderStatus::OPEN) {
            $this->outbox->publish(
                $order->company_id,
                self::EVENT_ORDER_CREATED,
                $this->redactedPayload($order),
            );
        }

        // COULOIR 16 — M3-bis (#9142) · Suivi de commande côté client.
        //
        // Le client public suit sa commande depuis l'onglet Activités ; sans
        // cette notification, il doit rouvrir l'app et deviner. C'est la
        // seconde moitié de la « première habitude quotidienne ».
        //
        // Best-effort : une notification manquée ne doit JAMAIS annuler une
        // transition d'état déjà commitée. On journalise et on continue.
        $this->notifyPublicClientOfStatus($order, $target);

        return $order;
    }

    /**
     * Prévient le client public du nouvel état de sa commande.
     *
     * Ne fait rien si la commande n'est pas rattachée à un client public
     * (`public_client_id` NULL) : une commande passée au comptoir n'a pas de
     * destinataire, et c'est le cas majoritaire.
     *
     * Le nom commercial vient de `Company.name` — une donnée publique. Aucun
     * nom d'employé, aucun numéro, aucun prix ne traverse l'appel.
     */
    private function notifyPublicClientOfStatus(RestaurantOrder $order, OrderStatus $target): void
    {
        $clientId = $order->public_client_id;

        if ($clientId === null) {
            return;
        }

        try {
            $merchantName = (string) (DB::table('companies')
                ->where('id', $order->company_id)
                ->value('name') ?? '');

            $this->publicClientNotifier->notifyActivityStatus(
                clientId: (int) $clientId,
                merchantName: $merchantName !== '' ? $merchantName : 'Le commerçant',
                vertical: 'restaurant',
                activityType: 'order',
                externalRef: (string) $order->reference,
                status: $target->value,
            );
        } catch (Throwable $e) {
            Log::warning('public_activity_status_notify_failed', [
                'order_id' => $order->id,
                'status' => $target->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function redactedPayload(RestaurantOrder $order): array
    {
        return [
            'order_id' => $order->id,
            'reference' => $order->reference,
            'branch_id' => $order->branch_id,
            'order_type' => $order->order_type->value,
            'status' => $order->status->value,
            'subtotal_minor' => $order->subtotal_minor,
            'tax_minor' => $order->tax_minor,
            'discount_minor' => $order->discount_minor,
            'total_minor' => $order->total_minor,
            'currency' => $order->currency,
        ];
    }
}