<?php

namespace App\Http\Controllers\AI;

use App\AI\DTOs\AIRequest;
use App\AI\Orchestrator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VoiceController extends Controller
{
    public function transcribe(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => 'required|file|mimes:wav,mp3,webm,ogg,m4a|max:10240',
            'language' => 'nullable|in:fr,ar,tr,en',
        ]);

        $audio = $request->file('audio');
        $language = $request->input('language', 'fr');

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
            'voice' => 'nullable|string|max:50',
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
        $request->validate([
            'audio' => 'required|file|mimes:wav,mp3,webm,ogg,m4a|max:10240',
            'language' => 'nullable|in:fr,ar,tr,en',
            'conversation_id' => 'nullable|string',
        ]);

        $audio = $request->file('audio');
        $language = $request->input('language', 'fr');

        $sttProvider = config('ai.voice.stt_provider', 'whisper');
        $transcribedText = $this->speechToText($audio, $language, $sttProvider);

        $orchestrator = app(Orchestrator::class);
        $user = $request->user();
        $conversationId = $request->input('conversation_id');
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
        if ($provider === 'edge_tts') {
            return $this->edgeTtsSynthesize($text, $language, $voice);
        }

        if ($provider === 'elevenlabs') {
            return $this->elevenLabsSynthesize($text, $voice);
        }

        return null;
    }

    private function edgeTtsSynthesize(string $text, string $language, ?string $voice): ?string
    {
        $voiceMap = [
            'fr' => 'fr-FR-DeniseNeural',
            'ar' => 'ar-SA-ZariyahNeural',
            'tr' => 'tr-TR-EmelNeural',
            'en' => 'en-US-JennyNeural',
        ];

        $selectedVoice = $voice ?? ($voiceMap[$language] ?? 'fr-FR-DeniseNeural');
        $filename = 'tts_'.uniqid().'.mp3';
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

        return url('storage/tts/'.$filename);
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
            $filename = 'tts_'.uniqid().'.mp3';
            $storagePath = storage_path('app/tts/'.$filename);
            if (! is_dir(dirname($storagePath))) {
                mkdir(dirname($storagePath), 0755, true);
            }
            file_put_contents($storagePath, $response->body());

            return url('storage/tts/'.$filename);
        }

        Log::error('ElevenLabs TTS failed', ['status' => $response->status()]);

        return null;
    }
}
