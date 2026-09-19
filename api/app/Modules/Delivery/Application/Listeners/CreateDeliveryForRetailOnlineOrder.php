<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Application\Listeners;

use App\Core\Tenant\Domain\Models\Company;
use App\Events\RetailOnlineOrderConfirmed;
use App\Events\RetailOnlineOrderDeliveryCreated;
use App\Modules\Delivery\Application\Actions\CreateDeliveryAction;
use App\Modules\Delivery\Domain\Enums\DeliverySource;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * #7811 (BC-26 DELIVERY) — handoff Leopardo Marché : à la CONFIRMATION d'une
 * commande en ligne Retail (`RetailOnlineOrderConfirmed`), création
 * automatique de la livraison BC-26.
 *
 * Règles :
 *  - intégration par ÉVÉNEMENT uniquement (registre BC) : payload scalaire,
 *    aucun import du module Retail ;
 *  - gated par le feature flag tenant `delivery` (companies.features) — un
 *    tenant sans module livraison ne reçoit AUCUNE ligne ;
 *  - idempotence portée par CreateDeliveryAction : unique
 *    (company_id, source=retail_online, source_reference=WEB-…) — le rejeu
 *    de l'événement retourne la livraison existante, zéro doublon ;
 *  - COD : le solde non encaissé de la commande devient `cod_amount_minor`
 *    (settlement COD BC-26 existant, DELIVERY-205) ;
 *  - FAIL-SOFT : une erreur du handoff est journalisée mais ne casse JAMAIS
 *    la confirmation vendeur (déjà committée) ;
 *  - en retour, `RetailOnlineOrderDeliveryCreated` (scalaires) permet à
 *    Retail de stocker la référence DLV-… pour le suivi public — pas
 *    d'écriture croisée dans les tables Retail.
 */
final class CreateDeliveryForRetailOnlineOrder
{
    public function __construct(
        private readonly CreateDeliveryAction $createDelivery,
        private readonly Dispatcher $events,
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(RetailOnlineOrderConfirmed $event): void
    {
        try {
            $company = Company::query()->find($event->companyId);

            if (! $company instanceof Company || ! $company->hasFeature('delivery')) {
                return;
            }

            $dropoffAddress = trim($event->deliveryAddress ?? '');

            if ($dropoffAddress === '' || ($event->customerName ?? '') === '') {
                // Commande sans coordonnées de livraison exploitables (données
                // legacy) : pas de handoff, le vendeur gère manuellement.
                return;
            }

            if (($event->deliveryCity ?? '') !== '') {
                $dropoffAddress .= ', '.$event->deliveryCity;
            }

            $delivery = $this->createDelivery->execute($event->companyId, [
                'source' => DeliverySource::RetailOnline->value,
                'source_reference' => $event->reference,
                'type' => 'order',
                'declared_value_minor' => $event->totalMinor,
                'cod_amount_minor' => ($event->codAmountMinor ?? 0) > 0 ? $event->codAmountMinor : null,
                'dropoff_contact' => (string) $event->customerName,
                'dropoff_phone' => $event->customerPhone,
                'dropoff_address' => $dropoffAddress,
            ]);

            $this->events->dispatch(new RetailOnlineOrderDeliveryCreated(
                companyId: $event->companyId,
                orderId: $event->orderId,
                orderReference: $event->reference,
                deliveryReference: $delivery->reference,
                deliveryStatus: $delivery->status,
            ));
        } catch (Throwable $exception) {
            $this->logger->error('Retail online order delivery handoff failed.', [
                'company_id' => $event->companyId,
                'order_reference' => $event->reference,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
