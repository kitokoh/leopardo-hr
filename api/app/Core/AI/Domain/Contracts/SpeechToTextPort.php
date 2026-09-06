<?php

declare(strict_types=1);

namespace App\Core\AI\Domain\Contracts;

use App\Core\AI\Domain\ValueObjects\SpeechToTextRequest;
use App\Core\AI\Domain\ValueObjects\SpeechToTextResult;

/**
 * Contrat de transcription vocale (STT — issue #6849, BC-23 AI).
 *
 * Même pattern que FaceVerificationPort / ModelInferencePort : le domaine ne
 * connaît ni le fournisseur ni son format ; l'adaptateur est remplaçable par
 * configuration (`config/ai.php` → `ai.models.stt.adapter`) :
 *
 *   - défaut (fail-closed) : `UnavailableSpeechToTextAdapter` — aucun
 *     fournisseur configuré → erreur `STT_UNAVAILABLE` (503 côté API),
 *     JAMAIS de faux texte retourné ;
 *   - production : `GroqWhisperAdapter` (whisper-large-v3 via GROQ_API_KEY) ;
 *   - tests : `FakeSpeechToTextAdapter` (texte scriptable).
 */
interface SpeechToTextPort
{
    public function transcribe(SpeechToTextRequest $request): SpeechToTextResult;
}
