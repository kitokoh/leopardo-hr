<?php

declare(strict_types=1);

namespace Tests\Unit\Core\AI;

use App\Core\AI\Domain\ValueObjects\SpeechToTextRequest;
use App\Core\AI\Infrastructure\Adapters\FakeSpeechToTextAdapter;
use App\Core\AI\Infrastructure\Adapters\GroqWhisperAdapter;
use App\Core\AI\Infrastructure\Adapters\UnavailableSpeechToTextAdapter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Issue #6849 — port SpeechToText : adaptateurs fail-closed / fake / Groq
 * (0 appel réseau dans les tests).
 */
class SpeechToTextAdapterTest extends TestCase
{
    private function request(): SpeechToTextRequest
    {
        return new SpeechToTextRequest(
            audio: 'fake-audio-bytes',
            mime: 'audio/webm',
            language: 'fr',
            filename: 'voix.webm',
        );
    }

    public function test_unavailable_adapter_fails_closed(): void
    {
        $result = (new UnavailableSpeechToTextAdapter)->transcribe($this->request());

        $this->assertFalse($result->ok());
        $this->assertSame('STT_UNAVAILABLE', $result->error);
        $this->assertSame('', $result->text);
    }

    public function test_fake_adapter_returns_scripted_text(): void
    {
        $result = (new FakeSpeechToTextAdapter)->transcribe($this->request());

        $this->assertTrue($result->ok());
        $this->assertSame('Transcription vocale factice (fake).', $result->text);
        $this->assertSame('fake', $result->provider);
    }

    public function test_groq_missing_key_fails_closed_without_network(): void
    {
        config()->set('ai.providers.groq.key', '');

        $result = (new GroqWhisperAdapter)->transcribe($this->request());

        $this->assertFalse($result->ok());
        $this->assertSame('STT_UNAVAILABLE', $result->error);
        Http::assertNothingSent();
    }

    public function test_groq_success_returns_transcription(): void
    {
        config()->set('ai.providers.groq.key', 'test-key');

        Http::fake([
            'api.groq.com/*' => Http::response(['text' => 'Demande de congé approuvée'], 200),
        ]);

        $result = (new GroqWhisperAdapter)->transcribe($this->request());

        $this->assertTrue($result->ok());
        $this->assertSame('Demande de congé approuvée', $result->text);
        $this->assertSame('groq-whisper', $result->provider);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'audio/transcriptions'));
    }

    public function test_groq_http_failure_fails_closed(): void
    {
        config()->set('ai.providers.groq.key', 'test-key');

        Http::fake(['api.groq.com/*' => Http::response([], 500)]);

        $result = (new GroqWhisperAdapter)->transcribe($this->request());

        $this->assertFalse($result->ok());
        $this->assertSame('STT_UNAVAILABLE', $result->error);
        $this->assertSame('', $result->text);
    }

    public function test_groq_empty_transcription_fails_closed(): void
    {
        config()->set('ai.providers.groq.key', 'test-key');

        Http::fake(['api.groq.com/*' => Http::response(['text' => '   '], 200)]);

        $result = (new GroqWhisperAdapter)->transcribe($this->request());

        $this->assertFalse($result->ok());
        $this->assertSame('STT_UNAVAILABLE', $result->error);
    }
}
