<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Providers;

use App\Events\CompanyCreated;
use App\Modules\Accounting\Application\Listeners\ProvisionAccountingSettings;
use Illuminate\Support\Facades\Event;
use App\Modules\Accounting\Domain\Contracts\PdfRendererInterface;
use App\Modules\Accounting\Infrastructure\Services\DocumentPdfRenderer;
use App\Modules\Accounting\Domain\Contracts\DocumentNumberingInterface;
use App\Modules\Accounting\Infrastructure\Services\DocumentNumberingService;
use Illuminate\Support\ServiceProvider;

/**
 * Module Comptabilité — 19ᵉ module DDD (COMPTABILITE_CONCEPTION.md §3).
 *
 * Les bindings de domaine sont enregistrés par les issues qui fournissent les
 * implémentations d'infrastructure : DocumentNumberingInterface (#5223),
 * PdfRendererInterface (#5224).
 */
class AccountingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PdfRendererInterface::class, DocumentPdfRenderer::class);
        // #5223 — numérotation paramétrable des documents comptables.
        $this->app->bind(
            DocumentNumberingInterface::class,
            DocumentNumberingService::class,
        );

        // #5224 — rendu PDF (fr + ar RTL) fourni par l'issue #5224.
    }

    public function boot(): void
    {
        // Issue #5232 — défauts pays appliqués à la création d'entreprise.
        // Enregistrement local au module (Event::listen) pour ne pas toucher
        // EventServiceProvider central (isolation module, anti-collision).
        Event::listen(CompanyCreated::class, ProvisionAccountingSettings::class);
    }
}
