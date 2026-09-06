<?php

declare(strict_types=1);

namespace Tests\Unit\Core\AI;

use App\Core\AI\Domain\Exceptions\SpeechToTextUnavailableException;
use App\Core\AI\Infrastructure\Adapters\GroqWhisperAdapter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Issue #6849 (BC-23) — GroqWhisperAdapter : STT whisper-large-v3 via Groq.
 *
 * - succès → texte transcrit ;
 * - clé absente → exception, aucune requête HTTP ;
 * - échec HTTP → exception ;
 * - réponse vide → exception (fail-closed).
 */
class GroqWhisperAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.providers.groq.key', 'test-groq-key');
        config()->set('ai.providers.groq.base_url', 'https://api.groq.com/openai/v1');
    }

    public function test_transcribe_returns_text(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response(['text' => 'Bonjour le monde'], 200),
        ]);

        $text = (new GroqWhisperAdapter)->transcribe('audio-binary', 'voix.wav', 'audio/wav', 'fr');

        self::assertSame('Bonjour le monde', $text);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/audio/transcriptions')
                && $request->hasHeader('Authorization', 'Bearer test-groq-key');
        });
    }

    public function test_transcribe_without_api_key_throws_and_does_not_call_http(): void
    {
        config()->set('ai.providers.groq.key', '');

        $this->expectException(SpeechToTextUnavailableException::class);
        $this->expectExceptionMessage('GROQ_API_KEY');

        (new GroqWhisperAdapter)->transcribe('audio-binary', 'voix.wav', 'audio/wav', 'fr');

        Http::assertNothingSent();
    }

    public function test_transcribe_throws_on_http_failure(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response(['error' => 'invalid api key'], 401),
        ]);

        $this->expectException(SpeechToTextUnavailableException::class);
        $this->expectExceptionMessage('401');

        (new GroqWhisperAdapter)->transcribe('audio-binary', 'voix.wav', 'audio/wav', 'fr');
    }

    public function test_transcribe_throws_on_empty_response(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response(['text' => '   '], 200),
        ]);

        $this->expectException(SpeechToTextUnavailableException::class);

        (new GroqWhisperAdapter)->transcribe('audio-binary', 'voix.wav', 'audio/wav', 'fr');
    }
}
