<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Providers;

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
    public function register(): void {}

    public function boot(): void {}
}
