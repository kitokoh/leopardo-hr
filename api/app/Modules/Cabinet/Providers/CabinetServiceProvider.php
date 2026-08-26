<?php

declare(strict_types=1);

namespace App\Modules\Cabinet\Providers;

use App\Modules\Cabinet\Infrastructure\Services\ContractPdfGenerator;
use App\Modules\HR\Domain\Contracts\ContractDocumentGeneratorInterface;
use Illuminate\Support\ServiceProvider;

class CabinetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // PA2-ARCH-003 — chaque module enregistre SA propre implémentation des
        // contrats qu'il fournit (composition root décentralisée).
        $this->app->bind(ContractDocumentGeneratorInterface::class, ContractPdfGenerator::class);
    }

    public function boot(): void {}
}
