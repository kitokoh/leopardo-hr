<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\DTOs\AIRequest;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #5616 (P0-SEC) — TTS edge-tts : fail-closed sans binaire,
 * provider par défaut préférant le cloud, et validation `voice` stricte.
 */
class TtsExecSecurityTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        config(['ai.enabled' => true]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_tts_provider_defaults_to_elevenlabs_when_api_key_is_set(): void
    {
        putenv('AI_TTS_PROVIDER');
        putenv('ELEVENLABS_API_KEY=test-key');

        $voice = require base_path('config/ai.php');

        $this->assertSame('elevenlabs', $voice['voice']['tts_provider']);

        putenv('ELEVENLABS_API_KEY');
    }

    public function test_tts_provider_defaults_to_edge_tts_without_api_key(): void
    {
        putenv('AI_TTS_PROVIDER');
        putenv('ELEVENLABS_API_KEY');

        $voice = require base_path('config/ai.php');

        $this->assertSame('edge_tts', $voice['voice']['tts_provider']);
    }

    public function test_edge_tts_fails_closed_when_binary_is_missing(): void
    {
        config(['ai.voice.edge_tts_binary' => '/nonexistent/edge-tts']);

        $controller = new \App\Http\Controllers\AI\VoiceController();
        $method = new \ReflectionMethod($controller, 'textToSpeech');

        $result = $method->invoke($controller, 'Bonjour', 'fr', null, 'edge_tts');

        $this->assertNull($result, 'edge-tts absent → fail-closed (null), pas d\'exec() silencieux');
    }

    public function test_synthesize_rejects_malicious_voice_value(): void
    {
        [$company, $employee] = $this->aiFixture();
        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/ai/voice/synthesize', [
            'text' => 'Bonjour',
            'voice' => '--danger --exec="rm -rf"',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('voice');
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function aiFixture(): array
    {
        $company = Company::factory()->create();

        $employee = Employee::query()->forceCreate([
            'company_id' => $company->id,
            'first_name' => 'AI',
            'last_name' => 'Voice',
            'email' => 'ai-voice@example.test',
            'matricule' => 'AI-VOICE-01',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        return [$company, $employee];
    }
}
