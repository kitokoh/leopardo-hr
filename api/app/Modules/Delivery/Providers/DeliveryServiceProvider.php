<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Providers;

use App\Modules\Delivery\Application\Services\DeliveryNotificationService;
use App\Modules\Delivery\Domain\Contracts\DeliveryAccountingContract;
use App\Modules\Delivery\Domain\Contracts\DeliveryRepositoryInterface;
use App\Modules\Delivery\Domain\Contracts\RecipientMessageContract;
use App\Modules\Delivery\Domain\Contracts\SolutionManifest;
use App\Modules\Delivery\Domain\Manifests\DeliveryManifest;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryEvent;
use App\Modules\Delivery\Domain\Models\DeliveryRoute;
use App\Modules\Delivery\Infrastructure\Repositories\DeliveryRepository;
use App\Modules\Delivery\Infrastructure\Services\LoggingDeliveryAccountingAdapter;
use App\Modules\Delivery\Infrastructure\Services\LoggingRecipientMessageAdapter;
use App\Modules\Delivery\Policies\DeliveryPolicy;
use App\Modules\Delivery\Policies\DeliveryRoutePolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
 * implémentations), le manifest de solution et les contrats inter-contextes
 * (BC-08 comptabilité COD, BC-13 COMMS notifications) ; `boot()` enregistre
 * les Policies RBAC (BC-26-D05) et le listener de planification des
 * notifications (BC-26-D06/DELIVERY-206).
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

        // Ports & adapters de persistance (DELIVERY-201/#6285) : les
        // implémentations Eloquent sont résolues en singleton derrière leur
        // contrat, conformément au pattern CrmLeadRepository /
        // RestaurantOrderRepository.
        $this->app->singleton(DeliveryRepositoryInterface::class, DeliveryRepository::class);

        // Contrat BC-08 (DELIVERY-205/#6289) : posting comptable des
        // encaissements COD — seam journalisé tant que les écritures
        // source-référencées ne sont pas branchées.
        $this->app->singleton(DeliveryAccountingContract::class, LoggingDeliveryAccountingAdapter::class);

        // Contrat BC-13 COMMS (DELIVERY-206/#6290) : envoi destinataire externe
        // — seam journalisé (PII hachée) tant que les providers ne sont pas
        // branchés sur les destinataires externes.
        $this->app->singleton(RecipientMessageContract::class, LoggingRecipientMessageAdapter::class);
    }

    public function boot(): void
    {
        // BC-26-D05 (#6294) : Policies RBAC du module — deny-by-default,
        // matrice docs/architecture/DELIVERY_RBAC.md. La garde de routes
        // (`delivery.role`) est complétée ici par les décisions par ressource
        // (ownership livreur, scope tenant) portées par les Policies.
        Gate::policy(Delivery::class, DeliveryPolicy::class);
        Gate::policy(DeliveryRoute::class, DeliveryRoutePolicy::class);

        // Notifications destinataire (DELIVERY-206/#6290) : chaque événement de
        // tracking inséré planifie la notification (outbox tenant-scoped) — le
        // listener ne tire QUE sur les inserts (l'idempotence des événements
        // garantit l'absence de doublons de notification).
        Event::listen('eloquent.created: '.DeliveryEvent::class, function (DeliveryEvent $event): void {
            app(DeliveryNotificationService::class)->scheduleForEvent($event);
        });
    }
}
