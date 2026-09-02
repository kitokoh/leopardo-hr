<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Modules\EduManager\Console\Commands\EduOutboxDispatchCommand;
use App\Modules\EduManager\Domain\Solution\EduManagerManifest;
use App\Modules\EduManager\Infrastructure\Services\EduOutboxConsumerRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Module EduManager — enregistre le manifest de solution dans le
 * catalogue (allowlist) et les commandes du module (outbox #5832).
 */
class EduManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SolutionCatalogue::class, function (): SolutionCatalogue {
            return new SolutionCatalogue;
        });

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
        $this->commands([
            EduOutboxDispatchCommand::class,
        ]);
    }
}
