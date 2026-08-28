<?php

declare(strict_types=1);

namespace App\Modules\CRM\Providers;

use App\Modules\CRM\Infrastructure\Services\CrmOutboxConsumerRegistry;
use App\Modules\CRM\Infrastructure\Services\CrmOutboxPublisher;
use Illuminate\Support\ServiceProvider;

/**
 * #5741 — Provider du module CRM (outbox).
 *
 * Enregistre le registre de consommateurs et le publisher d'outbox. Les
 * autres contrats du module (repositories, policies, actions) arrivent avec
 * #5714/#5717/#5718 et le squelette complet #5707.
 */
class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CrmOutboxPublisher::class);
        $this->app->singleton(CrmOutboxConsumerRegistry::class);
    }
}
