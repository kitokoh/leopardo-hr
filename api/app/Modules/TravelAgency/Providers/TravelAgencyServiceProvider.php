<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Providers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Solutions\Contracts\DemoDataKit;
use App\Core\Solutions\DemoDataRegistry;
use App\Core\Solutions\SolutionCatalogue;
use App\Events\SolutionActivated;
use App\Modules\TravelAgency\Application\Actions\ActivateTravelAgencyAction;
use App\Modules\TravelAgency\Console\Commands\RecalculateTravelReadModelsCommand;
use App\Modules\TravelAgency\Console\Commands\TravelExpireAdvertsCommand;
use App\Modules\TravelAgency\Console\Commands\TravelExpirePendingBookingsCommand;
use App\Modules\TravelAgency\Console\Commands\TravelLegacyImportCommand;
use App\Modules\TravelAgency\Console\Commands\TravelOutboxDispatchCommand;
use App\Modules\TravelAgency\Console\Commands\TravelSalesSettleCommand;
use App\Modules\TravelAgency\Console\Commands\TravelSettleSalesCommand;
use App\Modules\TravelAgency\Console\Commands\TravelWebhookDispatchCommand;
use App\Modules\TravelAgency\Domain\Manifests\TravelAgencyManifest;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerAccount;
use App\Modules\TravelAgency\Infrastructure\Services\Payment\CashPaymentGateway;
use App\Modules\TravelAgency\Infrastructure\Services\Payment\PaymentGatewayRegistry;
use App\Modules\TravelAgency\Infrastructure\Services\Payment\PvitPaymentGateway;
use App\Modules\TravelAgency\Infrastructure\Services\TravelDemoSeederService;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxConsumerRegistry;
use App\Modules\TravelAgency\Policies\TravelReportPolicy;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Facades\Auth;
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

        // #7865 — kit de données de démonstration de la verticale
        // (TRAVEL-107) : même pattern de registre partagé que le catalogue
        // ci-dessus (garde `bound()` + `resolving()`, factory paresseuse —
        // le seeder n'est instancié qu'à l'import effectif).
        if (! $this->app->bound(DemoDataRegistry::class)) {
            $this->app->singleton(DemoDataRegistry::class, static fn (): DemoDataRegistry => new DemoDataRegistry);
        }

        $this->app->resolving(DemoDataRegistry::class, function (DemoDataRegistry $registry): void {
            $registry->register(TravelAgencyManifest::CODE, fn (): DemoDataKit => $this->app->make(TravelDemoSeederService::class));
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
            // #7420 : `travel:expire-adverts` n'était PAS enregistrée ici ; la
            // commande racine homonyme (App\Console\Commands\...) la masquait et
            // ne connaissait ni `--company` ni le scoping tenant — le test
            // d'expiration échouait en InvalidOptionException. La version du
            // module (tenant-aware, --company/--limit, expire + archive) est
            // désormais l'unique implémentation, le doublon racine est supprimé.
            TravelExpireAdvertsCommand::class,
            // #8004 — MÊME piège (#7420) sur les commandes restantes du module :
            // Laravel n'auto-découvre QUE `app/Console/Commands`, jamais
            // `app/Modules/*/Console/Commands` — sans cette liste, `artisan`
            // répond « Command not found » et ~30 tests Feature rougissent.
            // Le doublon racine `travel:expire-pending-bookings` et le doublon
            // racine `leopardo:travel:import-legacy` (mêmes noms, options
            // différentes → masquage dépendant de l'ordre) sont supprimés :
            // les implémentations du module sont désormais les seules.
            TravelExpirePendingBookingsCommand::class,
            TravelLegacyImportCommand::class,
            RecalculateTravelReadModelsCommand::class,
            TravelSalesSettleCommand::class,
            TravelSettleSalesCommand::class,
        ]);
    }

    public function boot(): void
    {
        // #7739 — provider d'auth des clients GRAND PUBLIC marketplace
        // (guard Sanctum dédié `travel_customer`). Enregistré comme DRIVER
        // custom plutôt que par une clé `model` statique dans
        // `config/auth.php` : Larastan ajoute chaque `auth.providers.*.model`
        // à l'union de type de `request->user()`/`Auth::user()` sur TOUTE
        // l'app — la clé statique invalidait ~70 entrées de la baseline
        // strict (dérive de message) hors de la verticale. Runtime identique
        // (EloquentUserProvider standard sur TravelCustomerAccount).
        Auth::provider(
            'travel_customer_accounts',
            static fn ($app): EloquentUserProvider => new EloquentUserProvider($app['hash'], TravelCustomerAccount::class),
        );

        // #7739/#7781 — la clé `model` du provider est INDISPENSABLE au
        // runtime : `Sanctum\Guard::hasValidProvider()` lit
        // `auth.providers.travel_customers.model` et un provider sans cette
        // clé fatale en `instanceof null` (« Class name must be a valid
        // object or a string », Guard.php:153) à CHAQUE requête authentifiée
        // du guard — me/logout/bookings 500 (reproduit par
        // TravelCustomerAccountApiTest en local). Elle est posée ici au
        // RUNTIME, jamais statiquement dans config/auth.php : sous analyse
        // (LEOPARDO_STATIC_ANALYSIS) le guard `travel_customer` est masqué,
        // et Larastan ne parcourt que les providers RÉFÉRENCÉS par un guard
        // — un provider sans guard n'entre pas dans l'union de
        // `request->user()`/`Auth::user()` ni ne fait dériver la baseline.
        config()->set(
            'auth.providers.travel_customers.model',
            TravelCustomerAccount::class,
        );

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
