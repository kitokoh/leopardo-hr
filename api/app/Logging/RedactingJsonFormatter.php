<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

/**
 * MAT-009 (#5867) — formateur JSON appliquant la redaction PII.
 *
 * Variante du `JsonFormatter` Monolog : le processeur `PiiRedactionProcessor`
 * est appliqué au moment de la sérialisation, SANS résolution anticipée du
 * canal. Contrairement à un `pushProcessor` posé au boot (qui fige
 * l'instance du canal dans le LogManager), cette approche reste compatible
 * avec les reconfigurations tardives du canal `structured` (tests et outils
 * qui re-pointent `logging.channels.structured.path` après coup).
 */
final class RedactingJsonFormatter extends JsonFormatter
{
    private PiiRedactionProcessor $redactor;

    public function __construct()
    {
        parent::__construct();

        $this->redactor = new PiiRedactionProcessor();
    }

    public function format(LogRecord $record): string
    {
        return parent::format(($this->redactor)($record));
    }
}
