<?php

declare(strict_types=1);

namespace App\Modules\Platform\Providers;

use App\Core\Tenant\Domain\Models\Company;
use App\Events\CompanyCreated;
use App\Events\SubscriptionPaid;
use App\Modules\Platform\Infrastructure\Services\Consumers\PlatformCompanyCreatedAuditConsumer;
use App\Modules\Platform\Infrastructure\Services\Consumers\PlatformSubscriptionPaidAuditConsumer;
use App\Modules\Platform\Infrastructure\Services\PlatformAiSettingsApplier;
use App\Modules\Platform\Infrastructure\Services\PlatformAiSettingsRepository;
use App\Modules\Platform\Infrastructure\Services\PlatformOutboxConsumerRegistry;
use App\Modules\Platform\Infrastructure\Services\PlatformOutboxPublisher;
use App\Modules\Platform\Infrastructure\Services\ScheduledTaskRunRecorder;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PlatformOutboxPublisher::class);
        $this->app->singleton(PlatformOutboxConsumerRegistry::class);

        // #7384 — réglages IA du cockpit : repository (secrets chiffrés) et
        // applier (surcharge de config). Singletons : le repository mémoïse sa
        // lecture le temps de la requête.
        $this->app->singleton(PlatformAiSettingsRepository::class);
        $this->app->singleton(PlatformAiSettingsApplier::class);
    }

    public function boot(): void
    {
        // #7384 — les réglages enregistrés depuis le super-admin priment sur
        // l'environnement. Appliqué au boot pour que TOUT le code IA existant
        // (AIFeatureCheck, Orchestrator, AiCloudPolicy, clients fournisseurs)
        // voie la valeur éditée sans être modifié. Fail-safe : table absente ou
        // cache indisponible ⇒ repli silencieux sur l'environnement.
        $this->app->make(PlatformAiSettingsApplier::class)->apply();

        // PA2-QA-006 — record last start/finish outcome of every scheduled
        // Artisan command so the platform admin "System" screen can surface
        // it (queue depth / failed jobs already exist; this adds "last run").
        // Only fires while `schedule:run` executes, never from web requests.
        $this->app->singleton(ScheduledTaskRunRecorder::class);

        Event::listen(ScheduledTaskStarting::class, [ScheduledTaskRunRecorder::class, 'onStarting']);
        Event::listen(ScheduledTaskFinished::class, [ScheduledTaskRunRecorder::class, 'onFinished']);
        Event::listen(ScheduledTaskFailed::class, [ScheduledTaskRunRecorder::class, 'onFailed']);

        // MAT-008 (#5866) — outbox plateforme : les consommateurs de
        // production sont enregistrés ici ; la publication se fait au moment
        // des événements métier (CompanyCreated / SubscriptionPaid), sans
        // toucher aux modules émetteurs (Billing, …) — boundary BC-01.
        $registry = $this->app->make(PlatformOutboxConsumerRegistry::class);
        $registry->register(new PlatformCompanyCreatedAuditConsumer);
        $registry->register(new PlatformSubscriptionPaidAuditConsumer);

        Event::listen(CompanyCreated::class, function (CompanyCreated $event): void {
            $company = $event->company;

            try {
                $this->app->make(PlatformOutboxPublisher::class)->publish(
                    companyId: $company->id,
                    eventType: PlatformCompanyCreatedAuditConsumer::EVENT_TYPE,
                    payload: [
                        'event_id' => 'company.created.'.$company->id,
                        'company_id' => $company->id,
                        'company_name' => $company->name,
                    ],
                    aggregateType: Company::class,
                    aggregateId: $company->id,
                );
            } catch (\Throwable $exception) {
                // #6958 : la publication outbox est un effet de bord auxiliaire
                // (audit/consommation asynchrone). Un échec ici — ex. table
                // `platform_outbox_events` absente sur un env partiel — ne doit
                // JAMAIS faire échouer la création du tenant (le provisioning
                // CompanyCreated tourne dans une transaction, cf.
                // ProvisionGuidedTrial). Log structuré, événement absorbé.
                Log::channel('structured')->warning('platform.outbox.publish_failed.company_created', [
                    'company_id' => $company->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        });

        Event::listen(SubscriptionPaid::class, function (SubscriptionPaid $event): void {
            $payment = $event->payment;

            if ($payment->company_id === null) {
                return;
            }

            $this->app->make(PlatformOutboxPublisher::class)->publish(
                companyId: (string) $payment->company_id,
                eventType: PlatformSubscriptionPaidAuditConsumer::EVENT_TYPE,
                payload: [
                    'event_id' => 'subscription.paid.'.$payment->id,
                    'company_id' => (string) $payment->company_id,
                    'payment_id' => (string) $payment->id,
                    'amount' => (string) $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                ],
                aggregateType: 'App\\Modules\\Payroll\\Domain\\Models\\Payment',
                aggregateId: (string) $payment->id,
            );
        });
    }
}
