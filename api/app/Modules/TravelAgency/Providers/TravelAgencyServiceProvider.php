<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Providers;

use App\Modules\TravelAgency\Console\Commands\TravelOutboxDispatchCommand;
use App\Modules\TravelAgency\Console\Commands\TravelWebhookDispatchCommand;
use App\Modules\TravelAgency\Domain\Contracts\SolutionManifest;
use App\Modules\TravelAgency\Domain\Manifests\TravelAgencyManifest;
use App\Modules\TravelAgency\Infrastructure\Services\Payment\CashPaymentGateway;
use App\Modules\TravelAgency\Infrastructure\Services\Payment\PaymentGatewayRegistry;
use App\Modules\TravelAgency\Infrastructure\Services\Payment\PvitPaymentGateway;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxConsumerRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Provider du module TravelAgency (BC-24 TRAVEL).
 *
 * Fondations de la verticale « Agence de Voyage » (TRAVEL-101, issue #5977) :
 * portage de l'ancien projet gv-back (vente de billets en ligne) dans
 * l'architecture DDD multi-tenant Leopardo HR.
 *
 * `register()` enregistre les ports & adapters du module (contrats →
 * implémentations) ; les Policies métier seront enregistrées dans `boot()`
 * au fil des lots API (épic 3xx).
 *
 * L'activation par tenant passe par le feature flag `travelagency`
 * (companies.features) — voir EnsureTravelAgencyModuleMiddleware (TRAVEL-102)
 * et ActivateTravelAgencyAction (TRAVEL-105).
 */
class TravelAgencyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SolutionManifest::class, TravelAgencyManifest::class);

        // Passerelles de paiement (TRAVEL-405..407) — registre par code.
        $this->app->singleton(PaymentGatewayRegistry::class, function (): PaymentGatewayRegistry {
            return new PaymentGatewayRegistry([
                'cash' => new CashPaymentGateway,
                'pvit' => new PvitPaymentGateway(config('travel.payments.pvit', [])),
            ]);
        });

        // Outbox (TRAVEL-414) : registre des consommateurs d'événements.
        $this->app->singleton(TravelOutboxConsumerRegistry::class, function (): TravelOutboxConsumerRegistry {
            return new TravelOutboxConsumerRegistry;
        });

        // Commandes artisan du module (hors app/Console/Commands → enregistrement
        // explicite, pattern CRM #5729). travel:outbox-dispatch et
        // travel:webhook-dispatch sont consommées par le scheduler (bootstrap/app.php)
        // et par les tests d'intégration ; l'implémentation canonique vit dans le
        // module (l'ancien doublon racine App\Console\Commands\TravelOutboxDispatchCommand
        // a été supprimé lors de la consolidation CI 2026-09-04).
        $this->commands([
            TravelOutboxDispatchCommand::class,
            TravelWebhookDispatchCommand::class,
        ]);
    }

    public function boot(): void {}
}
