<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class VoiceControllerTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_unauthenticated_user_cannot_transcribe(): void
    {
        $this->postJson('/api/v1/ai/voice/transcribe')->assertStatus(401);
    }

    public function test_transcribe_requires_audio_file(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/ai/voice/transcribe', []);
        $response->assertStatus(422);
    }

    public function test_synthesize_requires_text(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/ai/voice/synthesize', []);
        $response->assertStatus(422);
    }

    public function test_unauthenticated_user_cannot_synthesize(): void
    {
        $this->postJson('/api/v1/ai/voice/synthesize')->assertStatus(401);
    }
}
