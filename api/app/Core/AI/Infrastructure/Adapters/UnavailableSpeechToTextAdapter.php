<?php

declare(strict_types=1);

namespace App\Core\AI\Infrastructure\Adapters;

use App\Core\AI\Domain\Contracts\SpeechToTextPort;
use App\Core\AI\Domain\ValueObjects\SpeechToTextRequest;
use App\Core\AI\Domain\ValueObjects\SpeechToTextResult;

/**
 * Adaptateur STT fail-closed par défaut (issue #6849).
 *
 * Aucun fournisseur STT configuré → `STT_UNAVAILABLE`. Une transcription
 * n'est donc JAMAIS rendue tant qu'un adaptateur réel n'est pas branché
 * (config `ai.models.stt.adapter` ou clé `GROQ_API_KEY` présente).
 */
final class UnavailableSpeechToTextAdapter implements SpeechToTextPort
{
    public function transcribe(SpeechToTextRequest $request): SpeechToTextResult
    {
        return new SpeechToTextResult(
            text: '',
            language: $request->language,
            provider: 'unavailable',
            error: 'STT_UNAVAILABLE',
            message: 'Aucun fournisseur de transcription vocale configuré.',
        );
    }
}
