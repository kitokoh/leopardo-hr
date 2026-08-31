<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Feature\Fixtures\CorrelationProbeJob;
use Tests\TestCase;

/**
 * MAT-009 (#5867) — un incident est traçable de l'API au job.
 *
 * La file sync (phpunit.xml) exécute le job dans la requête : le payload est
 * bien construit (createPayloadUsing), les événements JobProcessing /
 * JobProcessed sont levés — la chaîne testée est donc la vraie chaîne de
 * production (worker synchrone).
 */
class QueueCorrelationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CorrelationProbeJob::$capturedCorrelation = null;
    }

    public function test_job_receives_the_correlation_id_of_the_dispatching_context(): void
    {
        app()->instance('correlation_id', 'corr-api-12345');

        Queue::push(new CorrelationProbeJob());

        $this->assertSame('corr-api-12345', CorrelationProbeJob::$capturedCorrelation);
    }

    public function test_job_dispatched_without_correlation_generates_a_fresh_uuid(): void
    {
        app()->forgetInstance('correlation_id');

        Queue::push(new CorrelationProbeJob());

        $captured = CorrelationProbeJob::$capturedCorrelation;

        if (! is_string($captured)) {
            $this->fail('Expected a captured correlation string');
        }

        $this->assertTrue(Str::isUuid($captured), "Expected UUID, got [{$captured}]");
    }

    public function test_correlation_context_is_cleared_after_the_job_runs(): void
    {
        app()->instance('correlation_id', 'corr-clear-me');

        Queue::push(new CorrelationProbeJob());

        $this->assertFalse(app()->bound('correlation_id'), 'Le contexte doit être nettoyé après traitement');
    }
}
