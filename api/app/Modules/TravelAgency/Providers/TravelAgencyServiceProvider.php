<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Providers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Solutions\SolutionCatalogue;
use App\Events\SolutionActivated;
use App\Modules\TravelAgency\Application\Actions\ActivateTravelAgencyAction;
use App\Modules\TravelAgency\Console\Commands\TravelOutboxDispatchCommand;
use App\Modules\TravelAgency\Console\Commands\TravelWebhookDispatchCommand;
use App\Modules\TravelAgency\Domain\Manifests\TravelAgencyManifest;
use App\Modules\TravelAgency\Infrastructure\Services\Payment\CashPaymentGateway;
use App\Modules\TravelAgency\Infrastructure\Services\Payment\PaymentGatewayRegistry;
use App\Modules\TravelAgency\Infrastructure\Services\Payment\PvitPaymentGateway;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxConsumerRegistry;
use App\Modules\TravelAgency\Policies\TravelReportPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Provider du module TravelAgency (BC-24 TRAVEL).
 *
 * Fondations de la verticale « Agence de Voyage » (TRAVEL-101, issue #5977) :
 * portage de l'ancien projet gv-back (vente de billets en ligne) dans
 * l'architecture DDD multi-tenant Leopardo HR.
 *
 * `register()` enregistre les ports & adapters du module (contrats →
 * implémentations) ; les Policies métier sont enregistrées dans `boot()` au
 * fil des lots API (épic 3xx).
 *
 * `register()` enregistre aussi le manifest de la verticale dans le
 * `SolutionCatalogue` (clé d'allowlist `travelagency`) — condition sine qua
 * non pour que la verticale soit demandable au signup self-service et
 * activable par le provisioning (#7220-bis).
 *
 * L'activation par tenant passe par le feature flag `travelagency`
 * (companies.features) — voir EnsureTravelAgencyModuleMiddleware (TRAVEL-102)
 * et ActivateTravelAgencyAction (TRAVEL-105).
 */
class TravelAgencyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // #7220-bis (audit 2026-09-14) — enregistrement du manifest au
        // catalogue de solutions, pattern partagé Restaurant/FuelStation
        // (singleton avec garde `bound()` + `resolving()` : le catalogue est
        // un service partagé entre modules, ne jamais le ré-écraser).
        //
        // Sans cet enregistrement, l'allowlist du catalogue ne connaît pas
        // `travelagency` : le signup self-service renvoyait 422
        // `INVALID_SOLUTION` et `leopardo:solution:activate travelagency`
        // levait `SolutionNotFoundException` — la verticale restait
        // inactivable pour un client agence de voyage.
        if (! $this->app->bound(SolutionCatalogue::class)) {
            $this->app->singleton(SolutionCatalogue::class, static fn (): SolutionCatalogue => new SolutionCatalogue);
        }

        $this->app->resolving(SolutionCatalogue::class, function (SolutionCatalogue $catalogue): void {
            $catalogue->register(TravelAgencyManifest::CODE, static fn (): TravelAgencyManifest => new TravelAgencyManifest);
        });

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

    public function boot(): void
    {
        // Audit 2026-09-14 — amorçage de la verticale à l'ACTIVATION.
        //
        // `SolutionActivator` ne posait que le feature flag : un tenant agence
        // de voyage onboardé par le signup self-service (`solutions:
        // ["travelagency"]`) recevait le flag, l'UI et les routes… mais un
        // référentiel géographique VIDE (0 pays / 0 ville). Or `travel_routes`
        // exige une ville d'origine et une ville d'arrivée : la verticale
        // était donc inexploitable sans intervention manuelle d'un opérateur
        // (`leopardo:travel:activate`).
        //
        // Le module écoute donc `SolutionActivated` et installe ses données
        // d'amorçage — même pattern d'isolation que Accounting sur
        // `CompanyCreated`. `ActivateTravelAgencyAction` est idempotent
        // (flag + `insertOrIgnore` sur le référentiel), donc rejouable sans
        // risque ; il est aussi le point d'entrée des commandes OPS, ce qui
        // garantit un seul chemin d'amorçage.
        Event::listen(SolutionActivated::class, static function (SolutionActivated $event): void {
            if ($event->solution !== TravelAgencyManifest::CODE) {
                return;
            }

            app(ActivateTravelAgencyAction::class)->execute($event->company);
        });

        // Permission `travel.reports` (rapports d'exploitation internes) — le
        // câblage Gate::define manquait (perdu dans les merges de la
        // consolidation) : `cannot('travel.reports')` retournait 403 pour
        // TOUS les rôles. Ouverte aux rôles opérationnels de l'agence
        // (TravelReportPolicy::authorize — principal/rh/manager/agent/checkin).
        Gate::define('travel.reports', static fn (Employee $actor): bool => TravelReportPolicy::authorize($actor));
    }
}
