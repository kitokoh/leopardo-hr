<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\AbsenceApproved;
use App\Events\AbsenceRejected;
use App\Events\AbsenceRequested;
use App\Events\AttendanceCheckedIn;
use App\Events\AttendanceCheckedOut;
use App\Events\CompanyCreated;
use App\Events\EmployeeArchived;
use App\Events\EmployeeCreated;
use App\Events\EmployeeRoleAssigned;
use App\Events\PayrollValidated;
use App\Events\SubscriptionPaid;
use App\Events\TaxRateApproved;
use App\Events\TaxRateRejected;
use App\Events\TaxRateSubmitted;
use App\Listeners\AuditLogger;
use App\Listeners\LinkPartnerToNewCompany;
use App\Listeners\NotifyTaxRateValidation;
use App\Listeners\ProcessCommissionOnPayment;
use App\Listeners\WebhookListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /** @var array<class-string, array<int, class-string>> */
    protected $listen = [
        EmployeeCreated::class => [AuditLogger::class, WebhookListener::class],
        EmployeeArchived::class => [AuditLogger::class, WebhookListener::class],
        AttendanceCheckedIn::class => [AuditLogger::class, WebhookListener::class],
        AttendanceCheckedOut::class => [AuditLogger::class, WebhookListener::class],
        AbsenceRequested::class => [AuditLogger::class, WebhookListener::class],
        AbsenceApproved::class => [AuditLogger::class, WebhookListener::class],
        AbsenceRejected::class => [AuditLogger::class, WebhookListener::class],
        PayrollValidated::class => [AuditLogger::class, WebhookListener::class],
        EmployeeRoleAssigned::class => [AuditLogger::class],
        CompanyCreated::class => [LinkPartnerToNewCompany::class],
        SubscriptionPaid::class => [ProcessCommissionOnPayment::class],
    ];

    public function boot(): void
    {
        // Issue #1813/#1923 — workflow de validation des taux légaux : le
        // listener n'était enregistré nulle part (mort) alors que le
        // CHANGELOG #1813 promet des notifications aux platform_admins.
        // L'event discovery (handle{Event}) est désactivé dans ce repo :
        // enregistrement explicite `Class@méthode` pour dispatcher chaque
        // événement vers son handler dédié. Enregistré via Event::listen
        // (boot) pour garder `$listen` dans le shape
        // array<class-string, array<int, class-string>> (PHPStan Strict).
        Event::listen(TaxRateSubmitted::class, NotifyTaxRateValidation::class.'@handleTaxRateSubmitted');
        Event::listen(TaxRateApproved::class, NotifyTaxRateValidation::class.'@handleTaxRateApproved');
        Event::listen(TaxRateRejected::class, NotifyTaxRateValidation::class.'@handleTaxRateRejected');

        parent::boot();
    }
}
