<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Events\SolutionActivated;
use App\Modules\EduManager\Application\Actions\SeedMinimalEduManagerAction;
use App\Modules\EduManager\Console\Commands\EduOutboxDispatchCommand;
use App\Modules\EduManager\Domain\Solution\EduManagerManifest;
use App\Modules\EduManager\Infrastructure\Services\EduOutboxConsumerRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Module EduManager — enregistre le manifest de solution dans le
 * catalogue (allowlist). Aucune route métier avant EDU-006/EDU-010.
 * catalogue (allowlist) et les commandes du module (outbox #5832).
 */
class EduManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! $this->app->bound(SolutionCatalogue::class)) {
            $this->app->singleton(SolutionCatalogue::class, static fn (): SolutionCatalogue => new SolutionCatalogue);
        }

        $this->app->resolving(SolutionCatalogue::class, function (SolutionCatalogue $catalogue): void {
            $catalogue->register('edumanager', static fn (): EduManagerManifest => new EduManagerManifest);
        });

        // Registre des consommateurs d'outbox EduManager (EDU-016 #5832) :
        // les adaptateurs (Accounting, CRM client, Notification) s'y
        // déclarent au fil des issues de consommation.
        $this->app->singleton(EduOutboxConsumerRegistry::class);
    }

    public function boot(): void
    {
        // BOS-016 (#8205) — amorçage minimal de l'établissement (campus + année scolaire par défaut)
        // déclenché à l'activation de la solution edumanager.
        Event::listen(SolutionActivated::class, static function (SolutionActivated $event): void {
            if ($event->solution !== 'edumanager') {
                return;
            }

            app(SeedMinimalEduManagerAction::class)->execute($event->company);
        });

        // Rien à booter tant que l'API EduManager n'existe pas (EDU-006/EDU-010).
        $this->commands([
            EduOutboxDispatchCommand::class,
        ]);
    }
}
