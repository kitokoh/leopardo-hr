<?php

declare(strict_types=1);

namespace App\Core\AI\Infrastructure\Adapters;

use App\Core\AI\Domain\Contracts\SpeechToTextPort;
use App\Core\AI\Domain\ValueObjects\SpeechToTextRequest;
use App\Core\AI\Domain\ValueObjects\SpeechToTextResult;

/**
 * Adaptateur STT FAKE (tests / hors prod — issue #6849).
 *
 * Texte scriptable par configuration (`ai.stt.fake_text`), aucun appel
 * réseau. Ne JAMAIS utiliser en production.
 */
final class FakeSpeechToTextAdapter implements SpeechToTextPort
{
    public function __construct(
        private readonly string $text = 'Transcription vocale factice (fake).',
    ) {}

    public function transcribe(SpeechToTextRequest $request): SpeechToTextResult
    {
        $configured = config('ai.stt.fake_text');

        return new SpeechToTextResult(
            text: is_string($configured) && $configured !== '' ? $configured : $this->text,
            language: $request->language,
            provider: 'fake',
            confidence: 1.0,
        );
    }
}
