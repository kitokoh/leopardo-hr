<?php

declare(strict_types=1);

namespace App\Core\AI\Infrastructure\Adapters;

use App\Core\AI\Domain\Contracts\SpeechToTextPort;
use App\Core\AI\Domain\Exceptions\SpeechToTextUnavailableException;
use Illuminate\Support\Facades\Http;

/**
 * STT via l'API Groq (whisper-large-v3) — issue #6849 (BC-23).
 *
 * Même fournisseur que le LLM (Groq, free tier) : une seule clé
 * (GROQ_API_KEY). Endpoint OpenAI-compatible /audio/transcriptions.
 * Fail-closed : clé absente, échec HTTP ou transcription vide → exception.
 */
class GroqWhisperAdapter implements SpeechToTextPort
{
    public function transcribe(string $audioContents, string $fileName, string $mimeType, string $language): string
    {
        $apiKey = (string) (config('ai.providers.groq.key') ?? '');

        if ($apiKey === '') {
            throw new SpeechToTextUnavailableException(
                'Groq API key missing — set GROQ_API_KEY (config ai.providers.groq.key).'
            );
        }

        $baseUrl = (string) (config('ai.providers.groq.base_url') ?? 'https://api.groq.com/openai/v1');

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->attach('file', $audioContents, $fileName)
                ->post($baseUrl.'/audio/transcriptions', [
                    'model' => 'whisper-large-v3',
                    'language' => $language,
                ]);
        } catch (\Throwable $e) {
            throw new SpeechToTextUnavailableException('STT request failed: '.$e->getMessage());
        }

        if ($response->failed()) {
            throw new SpeechToTextUnavailableException('STT API error: '.$response->status());
        }

        $text = trim((string) ($response->json('text') ?? ''));

        if ($text === '') {
            throw new SpeechToTextUnavailableException('STT returned an empty transcription.');
        }

        return $text;
    }
}
