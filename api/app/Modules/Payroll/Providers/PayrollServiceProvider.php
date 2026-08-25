<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Providers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Payroll\Infrastructure\Listeners\PayrollAccountingEntryObserver;
use Illuminate\Support\ServiceProvider;

class PayrollServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Issue #5239 — écritures salariales automatiques : à la validation RH
        // d'un run (`AuditLog` action payroll_run_validated), générer les
        // écritures comptables via PayrollAccountingEntryService.
        AuditLog::observe(PayrollAccountingEntryObserver::class);
    }
}
