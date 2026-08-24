<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Core\Auth\Domain\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class JobRecommendationTest extends TestCase
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

    public function test_recommendations_require_active_job_search_status(): void
    {
        $user = User::factory()->create(['personal_statuses' => ['student']]);
        Sanctum::actingAs($user, [], 'user_api');

        $this->getJson('/api/v1/user/job-recommendations')
            ->assertStatus(403)
            ->assertJsonPath('error', 'JOB_SEARCH_STATUS_REQUIRED');
    }

    public function test_user_can_update_job_search_profile(): void
    {
        $user = User::factory()->create(['personal_statuses' => ['job_seeker']]);
        Sanctum::actingAs($user, [], 'user_api');

        $this->putJson('/api/v1/user/job-search-profile', [
            'target_titles' => ['Développeur backend'],
            'skills' => ['PHP', 'Laravel'],
            'locations' => ['Alger'],
            'contract_types' => ['cdi'],
            'remote_only' => true,
            'min_salary' => 180000,
        ])
            ->assertOk()
            ->assertJsonPath('data.preferences.remote_only', true)
            ->assertJsonPath('data.preferences.skills.0', 'PHP');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $preferences = $user->fresh()?->job_search_preferences;
        $this->assertIsArray($preferences);
        $this->assertSame('Alger', $preferences['locations'][0] ?? null);
    }

    public function test_job_search_profile_rejects_invalid_contract_type(): void
    {
        $user = User::factory()->create(['personal_statuses' => ['job_seeker']]);
        Sanctum::actingAs($user, [], 'user_api');

        $this->putJson('/api/v1/user/job-search-profile', [
            'contract_types' => ['permanent_unknown'],
        ])->assertStatus(422);
    }
}
