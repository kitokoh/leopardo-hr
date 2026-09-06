<?php

declare(strict_types=1);

namespace Tests\Unit\Core\AI;

use App\Core\AI\Infrastructure\Adapters\FakeSpeechToTextAdapter;
use Tests\TestCase;

/**
 * Issue #6849 (BC-23) — FakeSpeechToTextAdapter : STT déterministe pour tests.
 */
class FakeSpeechToTextAdapterTest extends TestCase
{
    public function test_returns_configured_text(): void
    {
        $adapter = new FakeSpeechToTextAdapter('Bonjour factice');

        self::assertSame(
            'Bonjour factice',
            $adapter->transcribe('audio-binary', 'voix.wav', 'audio/wav', 'fr'),
        );
    }

    public function test_returns_default_text_when_not_configured(): void
    {
        $adapter = new FakeSpeechToTextAdapter;

        self::assertSame(
            'Transcription factice de test.',
            $adapter->transcribe('audio-binary', 'voix.wav', 'audio/wav', 'fr'),
        );
    }
}
