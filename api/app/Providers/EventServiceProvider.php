<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\AbsenceApproved;
use App\Events\AbsenceRejected;
use App\Events\AbsenceRequested;
use App\Events\AccountLocked;
use App\Events\AttendanceCheckedIn;
use App\Events\AttendanceCheckedOut;
use App\Events\CatalogInquiryErased;
use App\Events\CatalogInquiryReceived;
use App\Events\CompanyCreated;
use App\Events\EmployeeArchived;
use App\Events\EmployeeCreated;
use App\Events\EmployeeRoleAssigned;
use App\Events\FuelStationAlert;
use App\Events\InvoicePaid;
use App\Events\MarketingLeadQualified;
use App\Events\PayrollValidated;
use App\Events\SubscriptionPaid;
use App\Events\TaxRateApproved;
use App\Events\TaxRateRejected;
use App\Events\TaxRateSubmitted;
use App\Listeners\AuditLogger;
use App\Listeners\ConvertMarketingLeadToContact;
use App\Listeners\CreateCrmLeadFromCatalogInquiry;
use App\Listeners\EraseCrmLeadsOnCatalogInquiryErased;
use App\Listeners\FuelStationAlertListener;
use App\Listeners\LinkPartnerToNewCompany;
use App\Listeners\NotifyAccountLocked;
use App\Listeners\NotifyTaxRateValidation;
use App\Listeners\ProcessCommissionOnPayment;
use App\Listeners\SendInvoicePaymentReceipt;
use App\Listeners\WebhookListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /** @var array<class-string, array<int, string>> */
    protected $listen = [
        EmployeeCreated::class => [AuditLogger::class, WebhookListener::class],
        EmployeeArchived::class => [AuditLogger::class, WebhookListener::class],
        AttendanceCheckedIn::class => [AuditLogger::class, WebhookListener::class],
        AttendanceCheckedOut::class => [AuditLogger::class, WebhookListener::class],
        AbsenceRequested::class => [AuditLogger::class, WebhookListener::class],
        AbsenceApproved::class => [AuditLogger::class, WebhookListener::class],
        AbsenceRejected::class => [AuditLogger::class, WebhookListener::class],
        PayrollValidated::class => [AuditLogger::class, WebhookListener::class],

        // #8163 — verrouillage de compte tenant (anti-brute-force) :
        // notification au titulaire via le store canonique `notifications`.
        // Méthode `notify` (hors pattern `handle*` de la découverte
        // automatique) : tir unique garanti — voir le docblock du listener.
        AccountLocked::class => [NotifyAccountLocked::class.'@notify'],
        FuelStationAlert::class => [FuelStationAlertListener::class],
        EmployeeRoleAssigned::class => [AuditLogger::class],
        CompanyCreated::class => [LinkPartnerToNewCompany::class],
        SubscriptionPaid::class => [ProcessCommissionOnPayment::class],

        // #7763 (BC-21 BILLING) — reçu de paiement au principal du tenant sur
        // la transition unique Invoice::transitionTo(Paid), Stripe et Chargily
        // confondus (PDF joint, i18n via EmailTemplateResolver).
        InvoicePaid::class => [SendInvoicePaymentReceipt::class],

        // Issue #1813/#1923 — workflow de validation des taux légaux : le
        // listener n'était enregistré nulle part (mort) alors que le
        // CHANGELOG #1813 promet des notifications aux platform_admins.
        // L'event discovery (handle{Event}) est désactivé dans ce repo :
        // enregistrement explicite `Class@méthode` pour dispatcher chaque
        // événement vers son handler dédié.
        TaxRateSubmitted::class => [NotifyTaxRateValidation::class.'@handleTaxRateSubmitted'],
        TaxRateApproved::class => [NotifyTaxRateValidation::class.'@handleTaxRateApproved'],
        TaxRateRejected::class => [NotifyTaxRateValidation::class.'@handleTaxRateRejected'],
        MarketingLeadQualified::class => [ConvertMarketingLeadToContact::class.'@handleMarketingLeadQualified'],

        // BC-28 CATALOG (C-LEAD #6884) — demande de devis B2B publique →
        // lead CRM BC-11 + notification tenant BC-13 (contrat cross-BC).
        CatalogInquiryReceived::class => [CreateCrmLeadFromCatalogInquiry::class],

        // BC-28 CATALOG (C-RGPD #6889) — effacement RGPD d'une demande →
        // propagation aux leads CRM BC-11 du même acheteur.
        CatalogInquiryErased::class => [EraseCrmLeadsOnCatalogInquiryErased::class],
    ];
}
