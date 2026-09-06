<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #6849 (BC-23) — endpoint voix : fail-closed sans clé (503
 * STT_UNAVAILABLE) et succès avec clé via l'adaptateur Groq-Whisper.
 */
class VoiceSttTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        config(['ai.enabled' => true]);
        config(['ai.voice.stt_provider' => 'groq_whisper']);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function actingAsManager(): Employee
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    public function test_transcribe_returns_503_when_groq_key_missing(): void
    {
        config()->set('ai.providers.groq.key', '');
        $this->actingAsManager();

        $audio = UploadedFile::fake()->create('voix.wav', 2048, 'audio/wav');

        $response = $this->post('/api/v1/ai/voice/transcribe', ['audio' => $audio]);

        $response->assertStatus(503);
        $response->assertJson(['error' => ['code' => 'STT_UNAVAILABLE']]);
    }

    public function test_transcribe_returns_text_when_groq_key_set(): void
    {
        config()->set('ai.providers.groq.key', 'test-groq-key');
        Http::fake([
            'api.groq.com/*' => Http::response(['text' => 'Bonjour le monde'], 200),
        ]);
        $this->actingAsManager();

        $audio = UploadedFile::fake()->create('voix.wav', 2048, 'audio/wav');

        $response = $this->post('/api/v1/ai/voice/transcribe', ['audio' => $audio]);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => ['text' => 'Bonjour le monde', 'provider' => 'groq_whisper'],
        ]);
    }
}
