<?php

declare(strict_types=1);

namespace App\Core\AI\Infrastructure\Adapters;

use App\Core\AI\Domain\Contracts\SpeechToTextPort;
use App\Core\AI\Domain\ValueObjects\SpeechToTextRequest;
use App\Core\AI\Domain\ValueObjects\SpeechToTextResult;
use Illuminate\Support\Facades\Http;

/**
 * Adaptateur STT Groq Whisper (issue #6849 — whisper-large-v3, free tier).
 *
 * `POST https://api.groq.com/openai/v1/audio/transcriptions` (multipart).
 * Fail-closed : clé absente, HTTP ≥ 400, timeout ou JSON invalide →
 * `STT_UNAVAILABLE` — jamais de texte faux ou partiel.
 */
final class GroqWhisperAdapter implements SpeechToTextPort
{
    private string $apiKey;

    private string $model;

    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) (config('ai.providers.groq.key') ?? '');
        $this->model = (string) (config('ai.models.stt.groq_model') ?? 'whisper-large-v3');
        $this->baseUrl = (string) (config('ai.providers.groq.base_url') ?? 'https://api.groq.com/openai/v1');
    }

    public function transcribe(SpeechToTextRequest $request): SpeechToTextResult
    {
        if ($this->apiKey === '') {
            return $this->unavailable('GROQ_API_KEY non configurée pour la transcription vocale.');
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(60)
                ->attach('file', $request->audio, $request->filename)
                ->post("{$this->baseUrl}/audio/transcriptions", [
                    'model' => $this->model,
                    'language' => $request->language,
                    'response_format' => 'json',
                ]);

            if ($response->failed()) {
                return $this->unavailable('Groq Whisper a répondu HTTP '.$response->status().'.');
            }

            $data = $response->json();
            $text = trim((string) ($data['text'] ?? ''));
            if ($text === '') {
                return $this->unavailable('Groq Whisper a retourné une transcription vide.');
            }

            return new SpeechToTextResult(
                text: $text,
                language: $request->language,
                provider: 'groq-whisper',
                confidence: 1.0,
            );
        } catch (\Throwable $e) {
            return $this->unavailable('Groq Whisper injoignable : '.$e->getMessage());
        }
    }

    private function unavailable(string $message): SpeechToTextResult
    {
        return new SpeechToTextResult(
            text: '',
            language: 'fr',
            provider: 'groq-whisper',
            error: 'STT_UNAVAILABLE',
            message: $message,
        );
    }
}
