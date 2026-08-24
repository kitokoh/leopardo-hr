<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Providers;

use App\Events\CompanyCreated;
use App\Modules\Accounting\Application\Listeners\ProvisionAccountingSettings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Module Comptabilité — 19ᵉ module DDD (COMPTABILITE_CONCEPTION.md §3).
 *
 * Les bindings de domaine (DocumentNumberingInterface, PdfRendererInterface)
 * sont enregistrés par les issues #5223 / #5224 qui fournissent les
 * implémentations d'infrastructure.
 */
class AccountingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        // Issue #5232 — défauts pays appliqués à la création d'entreprise.
        // Enregistrement local au module (Event::listen) pour ne pas toucher
        // EventServiceProvider central (isolation module, anti-collision).
        Event::listen(CompanyCreated::class, ProvisionAccountingSettings::class);
    }
}
