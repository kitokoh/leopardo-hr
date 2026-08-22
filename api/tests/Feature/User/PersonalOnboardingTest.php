<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Core\Auth\Domain\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PersonalOnboardingTest extends TestCase
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

    public function test_personal_onboarding_requires_authentication(): void
    {
        $this->getJson('/api/v1/user/personal-onboarding')->assertStatus(401);
    }

    public function test_user_can_select_multiple_personal_statuses(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, [], 'user_api');

        $this->putJson('/api/v1/user/personal-onboarding', [
            'statuses' => ['student', 'employee', 'job_seeker'],
        ])
            ->assertOk()
            ->assertJsonPath('data.statuses.0', 'student')
            ->assertJsonPath('data.statuses.1', 'employee')
            ->assertJsonPath('data.completed', true)
            ->assertJsonPath('data.employee_access.linked', false)
            ->assertJsonPath('data.employee_access.pointage_enabled', false);

        $this->assertSame(
            ['student', 'employee', 'job_seeker'],
            $user->fresh()?->personal_statuses,
        );
    }

    public function test_personal_onboarding_rejects_unknown_statuses(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, [], 'user_api');

        $this->putJson('/api/v1/user/personal-onboarding', [
            'statuses' => ['student', 'admin'],
        ])->assertStatus(422);
    }
}
