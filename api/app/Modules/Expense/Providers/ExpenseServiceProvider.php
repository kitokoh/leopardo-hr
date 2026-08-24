<?php

declare(strict_types=1);

namespace App\Modules\Expense\Providers;

use App\Modules\Expense\Infrastructure\Listeners\ExpenseAccountingEntryObserver;
use App\Modules\Planning\Domain\Models\ExpenseClaim;
use Illuminate\Support\ServiceProvider;

class ExpenseServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Issue #5235 — écritures comptables des notes de frais : à
        // l'approbation d'un ExpenseClaim (status → approved), générer les
        // écritures via ExpenseAccountingEntryService.
        ExpenseClaim::observe(ExpenseAccountingEntryObserver::class);
    }
}
