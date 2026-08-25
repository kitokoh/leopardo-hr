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
        // Issue #5235 — notes de frais approuvées → écritures comptables
        // automatiques. Enregistrement local au module (isolation module,
        // anti-collision) : aucun fichier du workflow Expense existant n'est
        // modifié.
        ExpenseClaim::observe(ExpenseAccountingEntryObserver::class);
    }
}
