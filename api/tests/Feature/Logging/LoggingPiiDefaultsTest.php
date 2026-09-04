<?php

declare(strict_types=1);

namespace Tests\Feature\Logging;

use App\Logging\PiiRedactionProcessor;
use Tests\TestCase;

/**
 * #6551 (audit-secu M5) — la rédaction PII doit couvrir les canaux de logs
 * par défaut (stack→single) et les breadcrumbs Sentry ne doivent plus
 * embarquer les bindings SQL.
 */
class LoggingPiiDefaultsTest extends TestCase
{
    public function test_default_channels_apply_pii_redaction(): void
    {
        $this->assertContains(
            PiiRedactionProcessor::class,
            (array) config('logging.channels.single.processors'),
            'le canal single (défaut du stack) doit appliquer PiiRedactionProcessor'
        );

        $this->assertContains(
            PiiRedactionProcessor::class,
            (array) config('logging.channels.daily.processors'),
            'le canal daily doit appliquer PiiRedactionProcessor'
        );
    }

    public function test_sentry_sql_bindings_are_disabled(): void
    {
        $this->assertFalse(
            (bool) config('sentry.breadcrumbs.sql_bindings'),
            'sql_bindings Sentry doit être désactivé (bindings SQL = PII en clair)'
        );
    }
}
