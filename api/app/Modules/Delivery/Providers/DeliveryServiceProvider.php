<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Providers;

use App\Modules\Delivery\Domain\Contracts\DeliveryAccountingContract;
use App\Modules\Delivery\Domain\Contracts\DeliveryRepositoryInterface;
use App\Modules\Delivery\Domain\Contracts\SolutionManifest;
use App\Modules\Delivery\Domain\Manifests\DeliveryManifest;
use App\Modules\Delivery\Infrastructure\Repositories\DeliveryRepository;
use App\Modules\Delivery\Infrastructure\Services\LoggingDeliveryAccountingAdapter;
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

        // Ports & adapters de persistance (DELIVERY-201/#6285) : les
        // implémentations Eloquent sont résolues en singleton derrière leur
        // contrat, conformément au pattern CrmLeadRepository /
        // RestaurantOrderRepository.
        $this->app->singleton(DeliveryRepositoryInterface::class, DeliveryRepository::class);

        // Contrat BC-08 (DELIVERY-205/#6289) : posting comptable des
        // encaissements COD — seam journalisé tant que les écritures
        // source-référencées ne sont pas branchées.
        $this->app->singleton(DeliveryAccountingContract::class, LoggingDeliveryAccountingAdapter::class);
    }

    public function boot(): void
    {
        // Policies enregistrées ici dès que les modèles des épics 2xx/3xx
        // existent (Gate::policy(Delivery::class, DeliveryPolicy::class)).
    }
}
