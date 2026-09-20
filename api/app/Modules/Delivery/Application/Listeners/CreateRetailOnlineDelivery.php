<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Application\Listeners;

use App\Modules\Delivery\Application\Actions\CreateDeliveryAction;
use App\Modules\Delivery\Domain\Enums\DeliverySource;
use App\Shared\Events\RetailOnlineOrderConfirmed;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Handoff BC-17 → BC-26 (#7811) : à la confirmation d'une commande en ligne
 * retail (`RetailOnlineOrderConfirmed`, émis APRÈS commit), crée la livraison
 * `source = retail_online` / `source_reference = référence de commande` via
 * `CreateDeliveryAction` — déjà idempotente sur (company_id, source,
 * source_reference) : un rejeu (double dispatch, retry) retourne la
 * livraison existante, jamais de doublon.
 *
 * Intégration PAR ÉVÉNEMENT uniquement : le module Retail n'accède JAMAIS
 * aux tables Delivery (règle d'isolation #5584) — l'événement vit sous
 * `App\Shared\Events` et ne transporte que des scalaires.
 *
 * COD v1 (spec MARKETPLACE_RETAIL_PUBLIC.md) : la commande est payée à la
 * livraison — le montant à encaisser (total en minor units) et sa devise
 * sont reportés sur la livraison (`cod_amount_minor` + `cod_currency`).
 *
 * Gardes fail-closed :
 *  - le tenant vendeur doit être le tenant COURANT (l'événement est dispatché
 *    dans la requête de confirmation, sous middleware `tenant`) ET porter le
 *    feature flag `delivery` — sinon skip journalisé, pas de livraison pour
 *    un tenant sans module BC-26 ;
 *  - toute exception est journalisée SANS remonter : la confirmation de la
 *    commande (déjà commitée) ne doit jamais échouer à cause du handoff —
 *    l'idempotence de la source permet une re-création manuelle/en rattrapage.
 */
final class CreateRetailOnlineDelivery
{
    public function __construct(
        private readonly CreateDeliveryAction $createDelivery,
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(RetailOnlineOrderConfirmed $event): void
    {
        if (! app()->bound('current_company')) {
            $this->logger->warning('Retail online delivery handoff skipped: no tenant context.', [
                'reference' => $event->reference,
            ]);

            return;
        }

        $company = currentCompany();

        if ((string) $company->id !== $event->companyId) {
            $this->logger->warning('Retail online delivery handoff skipped: tenant mismatch.', [
                'reference' => $event->reference,
            ]);

            return;
        }

        if (! $company->hasFeature('delivery')) {
            // Vendeur sans module BC-26 : pas de livraison à créer (le suivi
            // public reste celui de la commande retail).
            return;
        }

        $dropoffAddress = trim($event->deliveryAddress);

        if (trim($event->deliveryCity) !== '') {
            $dropoffAddress = $dropoffAddress === ''
                ? trim($event->deliveryCity)
                : $dropoffAddress.', '.trim($event->deliveryCity);
        }

        try {
            $this->createDelivery->execute($event->companyId, [
                'source' => DeliverySource::RetailOnline->value,
                'source_reference' => $event->reference,
                'type' => 'parcel',
                'declared_value_minor' => $event->totalMinor,
                'cod_amount_minor' => $event->totalMinor,
                'cod_currency' => $event->currency,
                'dropoff_contact' => $event->customerName !== '' ? $event->customerName : 'Client',
                'dropoff_phone' => $event->customerPhone !== '' ? $event->customerPhone : null,
                'dropoff_address' => $dropoffAddress !== '' ? $dropoffAddress : 'Adresse communiquee a la commande',
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('Retail online delivery handoff failed.', [
                'reference' => $event->reference,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
