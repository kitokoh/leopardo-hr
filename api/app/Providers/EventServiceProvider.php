<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\AbsenceApproved;
use App\Events\AbsenceRejected;
use App\Events\AbsenceRequested;
use App\Events\AttendanceCheckedIn;
use App\Events\AttendanceCheckedOut;
use App\Events\EmployeeArchived;
use App\Events\EmployeeCreated;
use App\Events\EmployeeRoleAssigned;
use App\Events\CompanyCreated;
use App\Events\PayrollValidated;
use App\Events\SubscriptionPaid;
use App\Events\TaxRateApproved;
use App\Events\TaxRateRejected;
use App\Events\TaxRateSubmittedForValidation;
use App\Listeners\AuditLogger;
use App\Listeners\LinkPartnerToNewCompany;
use App\Listeners\ProcessCommissionOnPayment;
use App\Listeners\TaxRateValidationLogger;
use App\Listeners\WebhookListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

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
        // Issue #1813 — workflow de validation des taux légaux.
        TaxRateSubmittedForValidation::class => [TaxRateValidationLogger::class],
        TaxRateApproved::class => [TaxRateValidationLogger::class],
        TaxRateRejected::class => [TaxRateValidationLogger::class],
    ];
}
