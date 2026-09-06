<?php

declare(strict_types=1);

namespace Tests\Unit\AI\Providers;

use App\AI\Providers\FakeLLMClient;
use Tests\TestCase;

/**
 * Issue #6848 — driver fake : réponse déterministe sans réseau, réservé
 * tests/hors prod.
 */
class FakeLLMClientTest extends TestCase
{
    public function test_fake_echoes_last_user_message(): void
    {
        $client = new FakeLLMClient;

        $response = $client->chat([
            ['role' => 'system', 'content' => 'sois bref'],
            ['role' => 'user', 'content' => 'combien d’absences ?'],
        ]);

        $this->assertFalse($response->failed());
        $this->assertStringContainsString('combien d’absences ?', $response->content);
        $this->assertFalse($response->hasToolCalls());
        $this->assertSame('fake', $client->provider());
    }

    public function test_fake_handles_empty_messages(): void
    {
        $response = (new FakeLLMClient)->chat([]);

        $this->assertFalse($response->failed());
        $this->assertNotEmpty($response->content);
    }
}
