<?php

declare(strict_types=1);

namespace App\Core\AI\Domain\ValueObjects;

/**
 * Requête de transcription vocale (issue #6849).
 */
final readonly class SpeechToTextRequest
{
    public function __construct(
        public string $audio,
        public string $mime = 'audio/webm',
        public string $language = 'fr',
        public string $filename = 'audio.webm',
    ) {}
}
