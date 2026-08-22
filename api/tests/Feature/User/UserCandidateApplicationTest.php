<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Core\Auth\Domain\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class UserCandidateApplicationTest extends TestCase
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

    public function test_application_history_requires_authentication(): void
    {
        $this->getJson('/api/v1/user/job-applications')->assertStatus(401);
    }

    public function test_candidate_cannot_apply_without_job_search_status(): void
    {
        $user = User::factory()->create(['personal_statuses' => ['student']]);
        Sanctum::actingAs($user, [], 'user_api');

        $this->postJson('/api/v1/user/job-applications/acme/1', [
            'cover_letter' => 'Je souhaite rejoindre votre équipe.',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'JOB_SEARCH_STATUS_REQUIRED');
    }
}
