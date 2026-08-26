<?php

declare(strict_types=1);

namespace App\Http\Controllers\AI;

use App\AI\DTOs\AIRequest;
use App\AI\Orchestrator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoiceController extends Controller
{
    /** Durée de validité des URLs audio signées (en secondes). */
    private const TTS_URL_TTL_SECONDS = 60;

    public function transcribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audio' => 'required|file|mimes:wav,mp3,webm,ogg,m4a|max:10240',
            'language' => 'nullable|in:fr,ar,tr,en',
        ]);

        $audio = $request->file('audio');
        $language = $validated['language'] ?? 'fr';

        $provider = config('ai.voice.stt_provider', 'whisper');
        $text = $this->speechToText($audio, $language, $provider);

        return response()->json([
            'data' => [
                'text' => $text,
                'language' => $language,
                'provider' => $provider,
            ],
        ]);
    }

    public function synthesize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string|max:2000',
            'language' => 'nullable|in:fr,ar,tr,en',
            'voice' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        $provider = $this->resolveEffectiveTtsProvider(config('ai.voice.tts_provider', 'edge_tts'));
        $audioUrl = $this->textToSpeech(
            $validated['text'],
            $validated['language'] ?? 'fr',
            $validated['voice'] ?? null,
            $provider,
        );

        return response()->json([
            'data' => [
                'audio_url' => $audioUrl,
                'provider' => $provider,
            ],
        ]);
    }

    public function command(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audio' => 'required|file|mimes:wav,mp3,webm,ogg,m4a|max:10240',
            'language' => 'nullable|in:fr,ar,tr,en',
            'conversation_id' => 'nullable|integer|min:1|max:2147483647',
        ]);

        $audio = $request->file('audio');
        $language = $validated['language'] ?? 'fr';

        $sttProvider = config('ai.voice.stt_provider', 'whisper');
        $transcribedText = $this->speechToText($audio, $language, $sttProvider);

        $orchestrator = app(Orchestrator::class);
        $user = $request->user();
        $conversationId = $validated['conversation_id'] ?? null;
        $aiResponse = $orchestrator->handle(new AIRequest(
            message: $transcribedText,
            userId: (int) $user->id,
            companyId: (string) $user->company_id,
            conversationId: is_numeric($conversationId) ? (int) $conversationId : null,
        ));

        $ttsProvider = $this->resolveEffectiveTtsProvider(config('ai.voice.tts_provider', 'edge_tts'));
        $audioUrl = $this->textToSpeech(
            $aiResponse['response'] ?? $transcribedText,
            $language,
            null,
            $ttsProvider,
        );

        return response()->json([
            'data' => [
                'transcribed_text' => $transcribedText,
                'ai_response' => $aiResponse['response'] ?? '',
                'conversation_id' => $aiResponse['conversation_id'] ?? null,
                'audio_url' => $audioUrl,
                'language' => $language,
            ],
        ]);
    }

    /**
     * Sert un fichier TTS via URL signée temporaire (#5616).
     *
     * Route nommée `tts.serve` — accessible sans authentification Sanctum mais
     * protégée par une signature Laravel expirante (TTL = TTS_URL_TTL_SECONDS).
     */
    public function serveTts(Request $request, string $filename): StreamedResponse
    {
        // hasValidSignature() vérifie la signature absolue (schéma + domaine),
        // cohérent avec URL::temporarySignedRoute() qui génère des URLs absolues.
        abort_unless($request->hasValidSignature(), 403);

        // Sanity check : accepter uniquement les noms générés par ce contrôleur.
        if (! preg_match('/^tts_[a-f0-9]+\.mp3$/', $filename)) {
            abort(404);
        }

        $path = 'tts/'.$filename;

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->streamDownload(function () use ($path): void {
            echo Storage::disk('local')->get($path);
        }, $filename, [
            'Content-Type' => 'audio/mpeg',
            'Content-Length' => Storage::disk('local')->size($path),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Résout le provider TTS effectif.
     *
     * Règle issue #5616 : si `edge_tts` est configuré mais qu'une clé
     * ElevenLabs est disponible, on préfère le cloud (aucun exec()) pour éviter
     * la dépendance au binaire Python en production.
     */
    private function resolveEffectiveTtsProvider(string $configured): string
    {
        if ($configured === 'edge_tts' && (bool) config('ai.voice.elevenlabs_key')) {
            Log::info('TTS: edge_tts configuré mais ElevenLabs disponible — bascule automatique (#5616)');

            return 'elevenlabs';
        }

        return $configured;
    }

    /**
     * @param  UploadedFile  $audio
     */
    private function speechToText($audio, string $language, string $provider): string
    {
        if ($provider === 'whisper') {
            return $this->whisperTranscribe($audio, $language);
        }

        if ($provider === 'deepgram') {
            return $this->deepgramTranscribe($audio, $language);
        }

        return '';
    }

    /**
     * @param  UploadedFile  $audio
     */
    private function whisperTranscribe($audio, string $language): string
    {
        $apiKey = config('ai.providers.openai.key');
        if (! $apiKey) {
            Log::warning('OpenAI API key not configured for Whisper STT');

            return '';
        }

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->attach('file', file_get_contents($audio->getRealPath()), $audio->getClientOriginalName())
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'language' => $language,
                'response_format' => 'text',
            ]);

        if ($response->successful()) {
            return trim($response->body());
        }

        Log::error('Whisper transcription failed', ['status' => $response->status()]);

        return '';
    }

    /**
     * @param  UploadedFile  $audio
     */
    private function deepgramTranscribe($audio, string $language): string
    {
        $apiKey = config('ai.voice.deepgram_key');
        if (! $apiKey) {
            Log::warning('Deepgram API key not configured');

            return '';
        }

        $response = Http::withToken($apiKey)
            ->withHeaders(['Content-Type' => $audio->getMimeType()])
            ->timeout(30)
            ->withBody(file_get_contents($audio->getRealPath()), $audio->getMimeType())
            ->post('https://api.deepgram.com/v1/listen?language='.$language.'&model=nova-2');

        if ($response->successful()) {
            return $response->json('results.channels.0.alternatives.0.transcript') ?? '';
        }

        Log::error('Deepgram transcription failed', ['status' => $response->status()]);

        return '';
    }

    private function textToSpeech(string $text, string $language, ?string $voice, string $provider): ?string
    {
        if ($provider === 'edge_tts') {
            return $this->edgeTtsSynthesize($text, $language, $voice);
        }

        if ($provider === 'elevenlabs') {
            return $this->elevenLabsSynthesize($text, $voice);
        }

        return null;
    }

    /**
     * Synthèse edge-tts (fallback sans clé cloud).
     *
     * Issue #5616 — correctifs sécurité :
     *  - Les fichiers sont stockés dans le disk `local` (non public).
     *  - L'URL renvoyée est une URL signée temporaire (TTL = TTS_URL_TTL_SECONDS)
     *    via la route nommée `tts.serve` — plus aucun accès public permanent.
     */
    private function edgeTtsSynthesize(string $text, string $language, ?string $voice): ?string
    {
        $voiceMap = [
            'fr' => 'fr-FR-DeniseNeural',
            'ar' => 'ar-SA-ZariyahNeural',
            'tr' => 'tr-TR-EmelNeural',
            'en' => 'en-US-JennyNeural',
        ];

        $selectedVoice = $voice ?? ($voiceMap[$language] ?? 'fr-FR-DeniseNeural');
        $filename = 'tts_'.bin2hex(random_bytes(8)).'.mp3';
        $storagePath = storage_path('app/tts/'.$filename);

        if (! is_dir(dirname($storagePath))) {
            mkdir(dirname($storagePath), 0755, true);
        }

        $escapedText = escapeshellarg($text);
        $escaped = escapeshellarg($selectedVoice);
        $escapedPath = escapeshellarg($storagePath);

        exec("edge-tts --voice={$escaped} --text={$escapedText} --write-media={$escapedPath} 2>&1", $output, $code);

        if ($code !== 0) {
            Log::warning('Edge TTS failed, returning null', ['code' => $code, 'output' => implode("\n", $output)]);

            return null;
        }

        // Issue #5616 : URL signée expirant dans TTS_URL_TTL_SECONDS secondes
        // (plus d'URL publique permanente).
        return URL::temporarySignedRoute(
            'tts.serve',
            now()->addSeconds(self::TTS_URL_TTL_SECONDS),
            ['filename' => $filename],
        );
    }

    /**
     * Synthèse ElevenLabs (provider cloud préféré).
     *
     * Issue #5616 : URL signée temporaire, stockage privé (disk local).
     */
    private function elevenLabsSynthesize(string $text, ?string $voiceId): ?string
    {
        $apiKey = config('ai.voice.elevenlabs_key');
        if (! $apiKey) {
            Log::warning('ElevenLabs API key not configured');

            return null;
        }

        $selectedVoice = $voiceId ?? config('ai.voice.elevenlabs_default_voice', '21m00Tcm4TlvDq8ikWAM');

        $response = Http::withHeaders(['xi-api-key' => $apiKey])
            ->timeout(30)
            ->post("https://api.elevenlabs.io/v1/text-to-speech/{$selectedVoice}", [
                'text' => $text,
                'model_id' => 'eleven_multilingual_v2',
            ]);

        if ($response->successful()) {
            $filename = 'tts_'.bin2hex(random_bytes(8)).'.mp3';
            Storage::disk('local')->put('tts/'.$filename, $response->body());

            // Issue #5616 : URL signée expirant dans TTS_URL_TTL_SECONDS secondes.
            return URL::temporarySignedRoute(
                'tts.serve',
                now()->addSeconds(self::TTS_URL_TTL_SECONDS),
                ['filename' => $filename],
            );
        }

        Log::error('ElevenLabs TTS failed', ['status' => $response->status()]);

        return null;
    }
}
