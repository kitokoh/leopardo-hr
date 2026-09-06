<?php

declare(strict_types=1);

namespace App\Core\AI\Domain\Exceptions;

use RuntimeException;

/**
 * STT indisponible — issue #6849 (BC-23).
 *
 * Levée par les adaptateurs SpeechToTextPort quand la transcription ne peut
 * pas aboutir (clé API absente, échec HTTP, réponse vide). Le contrôleur la
 * traduit en 503 STT_UNAVAILABLE (fail-closed, jamais de faux texte).
 */
class SpeechToTextUnavailableException extends RuntimeException
{
}
