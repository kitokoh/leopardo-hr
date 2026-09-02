<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Providers;

use App\Events\CompanyCreated;
use App\Modules\Accounting\Application\Listeners\ProvisionAccountingSettings;
use App\Modules\Accounting\Application\Listeners\ProvisionChartOfAccounts;
use App\Modules\Accounting\Console\Commands\SendPaymentRemindersCommand;
use App\Modules\Accounting\Domain\Contracts\DocumentNumberingInterface;
use App\Modules\Accounting\Domain\Contracts\PdfRendererInterface;
use App\Modules\Accounting\Console\Commands\SendPaymentRemindersCommand;
use App\Modules\Accounting\Infrastructure\Services\DocumentNumberingService;
use App\Modules\Accounting\Infrastructure\Services\DocumentPdfRenderer;
use App\Modules\Accounting\Interfaces\Console\RecomputeReportingSnapshotCommand;
use App\Modules\Accounting\Console\Commands\SendPaymentRemindersCommand;
use App\Modules\Accounting\Interfaces\Console\SeedAccountingDemoCommand;
use Illuminate\Support\Facades\Event;
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
        // Issue #5274 — démo exploitable en 1 clic (données vitrine, jamais réelles).
        // #6574 — SendPaymentRemindersCommand était silencieusement inactive :
        // Laravel ne découvre que `app/Console/Commands` ; une commande de module
        // doit être enregistrée par son provider pour apparaître dans `artisan list`
        // et pouvoir être planifiée.
        // Issue #6574 — SendPaymentRemindersCommand était silencieusement inactive :
        // Laravel ne découvre que app/Console/Commands, la commande du module devait
        // être enregistrée explicitement ici (même pattern que SeedAccountingDemoCommand).
        // Issue #6243 — recompute des snapshots de read models (BC-22-D10).
        $this->commands([
            SeedAccountingDemoCommand::class,
            SendPaymentRemindersCommand::class,
            // Issue #6243 — recompute des snapshots de read models (BC-22-D10).
            RecomputeReportingSnapshotCommand::class,
            // #6574 — relances de paiement (J+7/J+15/J+30) : la commande n'était
            // enregistrée nulle part → elle ne partait jamais.
            SendPaymentRemindersCommand::class,
        ]);
    }

    public function boot(): void
    {
        // Issue #5232 — défauts pays appliqués à la création d'entreprise.
        // Enregistrement local au module (Event::listen) pour ne pas toucher
        // EventServiceProvider central (isolation module, anti-collision).
        Event::listen(CompanyCreated::class, ProvisionAccountingSettings::class);
        // #5422 — plan comptable par défaut provisionné à la création d'entreprise.
        Event::listen(CompanyCreated::class, ProvisionChartOfAccounts::class);
    }
}
