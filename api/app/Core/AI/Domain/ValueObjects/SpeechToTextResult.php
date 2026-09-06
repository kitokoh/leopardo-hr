<?php

declare(strict_types=1);

namespace App\Core\AI\Domain\ValueObjects;

/**
 * Résultat de transcription vocale (issue #6849).
 *
 * `error !== null` → échec (jamais de texte partiel ou faux) :
 * le code stable `STT_UNAVAILABLE` signale un fournisseur absent/échoué.
 */
final readonly class SpeechToTextResult
{
    public function __construct(
        public string $text,
        public string $language,
        public string $provider,
        public float $confidence = 0.0,
        public ?string $error = null,
        public ?string $message = null,
    ) {}

    public function ok(): bool
    {
        return $this->error === null;
    }
}
