<?php

declare(strict_types=1);

namespace App\Modules\Notification\Providers;

use App\Contracts\Communication\CommunicationServiceInterface;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Contrat partagé (App\Contracts) → implémentation concrète BC-13 :
        // permet aux autres modules d'envoyer des notifications sans import
        // croisé (isolation des modules, issue #5584).
        $this->app->bind(CommunicationServiceInterface::class, CommunicationService::class);
    }

    public function boot(): void {}
}
