<?php

declare(strict_types=1);

namespace App\Http\Controllers\AI;

use App\AI\DTOs\AIRequest;
use App\AI\Orchestrator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class VoiceController extends Controller
{
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

        $provider = config('ai.voice.tts_provider', 'edge_tts');
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

        $ttsProvider = config('ai.voice.tts_provider', 'edge_tts');
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
        // #5616 — Si ElevenLabs est configuré, il est prioritaire sur edge-tts
        // qui n'est pas installé en production et utilise exec() non maîtrisé.
        $elevenLabsKey = config('ai.voice.elevenlabs_key');
        if ($elevenLabsKey && $provider === 'edge_tts') {
            Log::info('TTS: edge-tts demandé mais ElevenLabs disponible — basculement automatique (issue #5616)');
            $provider = 'elevenlabs';
        }

        if ($provider === 'edge_tts') {
            return $this->edgeTtsSynthesize($text, $language, $voice);
        }

        if ($provider === 'elevenlabs') {
            return $this->elevenLabsSynthesize($text, $voice);
        }

        return null;
    }

    /**
     * Sert un fichier TTS privé via une URL signée temporaire (60 s).
     * Route : GET /ai/voice/download/{filename}?signature=...&expires=...
     *
     * #5616 — Les fichiers audio ne sont plus exposés publiquement de façon permanente.
     */
    public function download(string $filename): Response
    {
        // Sécurité : seul un nom de fichier simple (sans chemin) est accepté.
        if (! preg_match('/^tts_[a-f0-9]+\.mp3$/', $filename)) {
            abort(404);
        }

        $path = 'tts/'.$filename;

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $content = Storage::disk('local')->get($path);

        if ($content === null) {
            abort(404);
        }

        return response($content, 200, [
            'Content-Type'        => 'audio/mpeg',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control'       => 'no-store, max-age=0',
        ]);
    }

    /**
     * Génère une URL signée temporaire (60 s) vers le fichier TTS stocké
     * en disque local privé.  N'est jamais une URL publique permanente.
     * #5616 — Remplace url('storage/tts/...')
     */
    private function signedTtsUrl(string $filename): string
    {
        return URL::temporarySignedRoute(
            'ai.voice.download',
            now()->addSeconds(60),
            ['filename' => $filename],
        );
    }

    private function edgeTtsSynthesize(string $text, string $language, ?string $voice): ?string
    {
        // #5616 — Vérifier que edge-tts est bien installé avant tout appel exec().
        exec('which edge-tts 2>/dev/null', $which, $whichCode);
        if ($whichCode !== 0) {
            Log::warning('edge-tts non trouvé sur le système — TTS désactivé (issue #5616). Installez-le via pip install edge-tts ou configurez ELEVENLABS_API_KEY.');

            return null;
        }

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
            Log::warning('Edge TTS failed', ['code' => $code, 'output' => implode("\n", $output)]);

            return null;
        }

        // #5616 — URL signée 60 s, non publique permanente.
        return $this->signedTtsUrl($filename);
    }

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
            // #5616 — Stocké dans le disque local privé (hors public/).
            Storage::disk('local')->put('tts/'.$filename, $response->body());

            // #5616 — URL signée 60 s, non publique permanente.
            return $this->signedTtsUrl($filename);
        }

        Log::error('ElevenLabs TTS failed', ['status' => $response->status()]);

        return null;
    }
}
