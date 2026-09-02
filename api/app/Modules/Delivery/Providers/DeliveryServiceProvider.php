<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Providers;

<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
use App\Modules\Delivery\Domain\Contracts\SolutionManifest;
use App\Modules\Delivery\Domain\Manifests\DeliveryManifest;
=======
=======
>>>>>>> origin/feat/delivery-206-notifications
=======
>>>>>>> origin/feat/delivery-d07-async-export
use App\Modules\Delivery\Domain\Contracts\DeliveryAccountingContract;
use App\Modules\Delivery\Domain\Contracts\DeliveryRepositoryInterface;
use App\Modules\Delivery\Domain\Contracts\SolutionManifest;
use App\Modules\Delivery\Domain\Manifests\DeliveryManifest;
use App\Modules\Delivery\Domain\Contracts\RecipientMessageContract;
use App\Modules\Delivery\Domain\Models\DeliveryEvent;
use App\Modules\Delivery\Infrastructure\Repositories\DeliveryRepository;
use App\Modules\Delivery\Infrastructure\Services\LoggingDeliveryAccountingAdapter;
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
>>>>>>> origin/feat/delivery-205-cod
=======
use App\Modules\Delivery\Infrastructure\Services\LoggingRecipientMessageAdapter;
use App\Modules\Delivery\Application\Services\DeliveryNotificationService;
use Illuminate\Support\Facades\Event;
>>>>>>> origin/feat/delivery-206-notifications
=======
use App\Modules\Delivery\Infrastructure\Services\LoggingRecipientMessageAdapter;
use App\Modules\Delivery\Application\Services\DeliveryNotificationService;
use Illuminate\Support\Facades\Event;
>>>>>>> origin/feat/delivery-208-source-contracts
=======
use App\Modules\Delivery\Infrastructure\Services\LoggingRecipientMessageAdapter;
use App\Modules\Delivery\Application\Services\DeliveryNotificationService;
use Illuminate\Support\Facades\Event;
>>>>>>> origin/feat/delivery-d07-async-export
use Illuminate\Support\ServiceProvider;

/**
 * Provider du module Delivery (BC-26 DELIVERY).
 *
 * Fondations du module de livraison dernier-kilomètre générique
 * (DELIVERY-101, issue #6282) : module DDD conforme aux conventions
 * (api/stubs/module-template), porte d'entrée de l'outillage opérationnel de
 * la livraison (colis/livraisons, tournées, livreurs, POD, tracking, COD,
 * rapports) pour tout tenant qui livre (agence, restaurant, retail,
 * e-commerce, CRM, pharmacie).
 *
 * `register()` enregistre les ports & adapters du module (contrats →
 * implémentations) et le manifest de solution ; les Policies métier seront
 * enregistrées dans `boot()` au fil des lots API (épics 2xx).
 *
 * L'activation par tenant passe par le feature flag `delivery`
 * (companies.features) — voir EnsureDeliveryModuleMiddleware (DELIVERY-101)
 * et la spec SOLUTION_DELIVERY.md.
 */
class DeliveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SolutionManifest::class, DeliveryManifest::class);

<<<<<<< HEAD
        // Ports & adapters de persistance (DELIVERY-2xx) : les implémentations
        // Eloquent seront résolues en singleton derrière leur contrat,
        // conformément au pattern CrmLeadRepository / RestaurantOrderRepository.
=======
        // Ports & adapters de persistance (DELIVERY-201/#6285) : les
        // implémentations Eloquent sont résolues en singleton derrière leur
        // contrat, conformément au pattern CrmLeadRepository /
        // RestaurantOrderRepository.
        $this->app->singleton(DeliveryRepositoryInterface::class, DeliveryRepository::class);

        // Contrat BC-08 (DELIVERY-205/#6289) : posting comptable des
        // encaissements COD — seam journalisé tant que les écritures
        // source-référencées ne sont pas branchées.
        $this->app->singleton(DeliveryAccountingContract::class, LoggingDeliveryAccountingAdapter::class);
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
>>>>>>> origin/feat/delivery-205-cod
=======
=======
>>>>>>> origin/feat/delivery-208-source-contracts
=======
>>>>>>> origin/feat/delivery-d07-async-export

        // Contrat BC-13 COMMS (DELIVERY-206/#6290) : envoi destinataire externe
        // — seam journalisé (PII hachée) tant que les providers ne sont pas
        // branchés sur les destinataires externes.
        $this->app->singleton(RecipientMessageContract::class, LoggingRecipientMessageAdapter::class);
<<<<<<< HEAD
<<<<<<< HEAD
>>>>>>> origin/feat/delivery-206-notifications
=======
>>>>>>> origin/feat/delivery-208-source-contracts
=======
>>>>>>> origin/feat/delivery-d07-async-export
    }

    public function boot(): void
    {
        // Notifications destinataire (DELIVERY-206/#6290) : chaque événement de
        // tracking inséré planifie la notification (outbox tenant-scoped) — le
        // listener ne tire QUE sur les inserts (l'idempotence des événements
        // garantit l'absence de doublons de notification).
        Event::listen('eloquent.created: '.DeliveryEvent::class, function (DeliveryEvent $event): void {
            app(DeliveryNotificationService::class)->scheduleForEvent($event);
        });
    }
}
