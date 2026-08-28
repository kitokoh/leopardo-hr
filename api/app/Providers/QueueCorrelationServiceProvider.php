<?php

declare(strict_types=1);

namespace App\Providers;

use App\Logging\PiiRedactionProcessor;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Monolog\Logger;

/**
 * MAT-009 (#5867) — observabilite et correlation commune.
 *
 * 1. Propagation du correlation ID des requetes HTTP vers les jobs de file :
 *    `Queue::createPayloadUsing()` capture `correlation_id()` au moment du
 *    dispatch (donc le correlation de la requete/commande d'origine) dans le
 *    payload du job ; `Queue::before` le rehydrate dans le conteneur au
 *    demarrage du job et `Queue::after`/`Queue::failing` nettoient le
 *    contexte en fin de traitement. Un incident est ainsi traçable de l'API
 *    au worker (logs structures, failed_jobs, audit) — critere d'acceptation
 *    MAT-009.
 *
 * 2. Redaction PII : le processeur `PiiRedactionProcessor` est branche sur le
 *    canal `structured` (JSON) — aucune PII/secret n'apparait dans les logs
 *    structures.
 */
final class QueueCorrelationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $structuredLogger = Log::channel('structured');

        if ($structuredLogger instanceof Logger) {
            $structuredLogger->pushProcessor(new PiiRedactionProcessor());
        }

        Queue::createPayloadUsing(static function (mixed $job, mixed $data, mixed $queue): array {
            return ['correlation_id' => correlation_id()];
        });

        Queue::before(static function (JobProcessing $event): void {
            $correlationId = self::correlationFromPayload($event->job);

            if ($correlationId !== null) {
                app()->instance('correlation_id', $correlationId);
            }
        });

        Queue::after(static function (JobProcessed $event): void {
            // Les workers sont des processus longs : le job suivant ne doit
            // pas heriter du contexte du job precedent.
            app()->forgetInstance('correlation_id');
        });

        Queue::failing(static function (JobFailed $event): void {
            // Le rapport d'echec (failed_jobs + exception report) doit rester
            // correlable : le payload conserve le correlation_id d'origine.
            app()->forgetInstance('correlation_id');
        });
    }

    private static function correlationFromPayload(Job $job): ?string
    {
        $payload = $job->payload();

        if (! is_array($payload) || ! array_key_exists('correlation_id', $payload)) {
            return null;
        }

        $correlationId = $payload['correlation_id'];

        if (! is_string($correlationId) || $correlationId === '') {
            return null;
        }

        return $correlationId;
    }
}
