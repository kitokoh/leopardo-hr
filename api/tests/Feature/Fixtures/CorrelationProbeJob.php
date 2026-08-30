<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Job sonde pour MAT-009 (#5867) : capture le correlation ID visible depuis
 * le worker de file (exécution sync en test, événements JobProcessing réels).
 */
final class CorrelationProbeJob implements ShouldQueue
{
    public static ?string $capturedCorrelation = null;

    public function handle(): void
    {
        self::$capturedCorrelation = correlation_id();
    }
}
