<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Domain\Models\SocialPost;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class SocialPostControllerTest extends TestCase
{
    use Tests\RefreshTenantDatabase;

    private function marketingManager(Company $company): Employee
    {
        return Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'marketing',
        ]);
    }

    private function connectedAccount(Company $company): SocialAccount
    {
        return SocialAccount::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'provider' => 'ayrshare',
            'provider_profile_ref' => 'profile-key-abc',
            'status' => 'active',
        ]);
    }

    public function test_store_creates_a_draft_post(): void
    {
        $company = Company::factory()->create();
        $manager = $this->marketingManager($company);
        $this->connectedAccount($company);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/marketing/social-posts', [
            'content' => 'Nouvelle offre disponible !',
            'target_platforms' => ['linkedin', 'facebook_page'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', SocialPost::STATUS_DRAFT)
            ->assertJsonPath('data.content', 'Nouvelle offre disponible !');

        $this->assertDatabaseHas('social_posts', [
            'company_id' => $company->id,
            'status' => SocialPost::STATUS_DRAFT,
        ]);
    }

    public function test_store_with_scheduled_at_marks_post_as_scheduled(): void
    {
        Http::fake();

        $company = Company::factory()->create();
        $manager = $this->marketingManager($company);
        $this->connectedAccount($company);

        Sanctum::actingAs($manager);

        $when = Carbon::now()->addDay();

        $response = $this->postJson('/api/v1/marketing/social-posts', [
            'content' => 'Post planifie',
            'target_platforms' => ['linkedin'],
            'scheduled_at' => $when->toIso8601String(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', SocialPost::STATUS_SCHEDULED);

        Http::assertNothingSent();
    }

    public function test_store_rejects_missing_target_platforms(): void
    {
        $company = Company::factory()->create();
        $manager = $this->marketingManager($company);
        $this->connectedAccount($company);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/marketing/social-posts', [
            'content' => 'Sans plateforme',
        ])->assertStatus(422);
    }

    public function test_index_lists_only_the_tenant_posts(): void
    {
        $company = Company::factory()->create();
        $manager = $this->marketingManager($company);
        $account = $this->connectedAccount($company);

        SocialPost::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'social_account_id' => $account->id,
            'content' => 'Post A',
            'target_platforms' => ['linkedin'],
            'status' => SocialPost::STATUS_DRAFT,
        ]);

        $otherCompany = Company::factory()->create();
        $otherAccount = $this->connectedAccount($otherCompany);
        SocialPost::withoutGlobalScopes()->create([
            'company_id' => $otherCompany->id,
            'social_account_id' => $otherAccount->id,
            'content' => 'Post B (autre tenant)',
            'target_platforms' => ['linkedin'],
            'status' => SocialPost::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/marketing/social-posts');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Post A', $response->json('data.0.content'));
    }

    public function test_update_is_rejected_for_published_post(): void
    {
        $company = Company::factory()->create();
        $manager = $this->marketingManager($company);
        $account = $this->connectedAccount($company);

        $post = SocialPost::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'social_account_id' => $account->id,
            'content' => 'Post publie',
            'target_platforms' => ['linkedin'],
            'status' => SocialPost::STATUS_PUBLISHED,
        ]);

        Sanctum::actingAs($manager);

        $this->patchJson("/api/v1/marketing/social-posts/{$post->id}", [
            'content' => 'Tentative de modification',
        ])->assertStatus(403);
    }

    public function test_destroy_removes_a_draft_post(): void
    {
        $company = Company::factory()->create();
        $manager = $this->marketingManager($company);
        $account = $this->connectedAccount($company);

        $post = SocialPost::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'social_account_id' => $account->id,
            'content' => 'A supprimer',
            'target_platforms' => ['linkedin'],
            'status' => SocialPost::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($manager);

        $this->deleteJson("/api/v1/marketing/social-posts/{$post->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('social_posts', ['id' => $post->id]);
    }

    public function test_publish_now_publishes_immediately(): void
    {
        Http::fake([
            'api.ayrshare.com/api/post' => Http::response([
                'status' => 'success',
                'id' => 'post_ayr_http',
            ], 200),
        ]);

        $company = Company::factory()->create();
        $manager = $this->marketingManager($company);
        $account = $this->connectedAccount($company);

        $post = SocialPost::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'social_account_id' => $account->id,
            'content' => 'A publier',
            'target_platforms' => ['linkedin'],
            'status' => SocialPost::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/marketing/social-posts/{$post->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', SocialPost::STATUS_PUBLISHED)
            ->assertJsonPath('data.provider_post_ref', 'post_ayr_http');
    }

    public function test_publish_with_future_date_schedules_the_post(): void
    {
        Http::fake();

        $company = Company::factory()->create();
        $manager = $this->marketingManager($company);
        $account = $this->connectedAccount($company);

        $post = SocialPost::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'social_account_id' => $account->id,
            'content' => 'A planifier',
            'target_platforms' => ['linkedin'],
            'status' => SocialPost::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($manager);

        $when = Carbon::now()->addDays(2);

        $this->postJson("/api/v1/marketing/social-posts/{$post->id}/publish", [
            'scheduled_at' => $when->toIso8601String(),
        ])->assertOk()
            ->assertJsonPath('data.status', SocialPost::STATUS_SCHEDULED);

        Http::assertNothingSent();
    }

    public function test_non_marketing_manager_cannot_access_posts(): void
    {
        $company = Company::factory()->create();
        $rhManager = Employee::factory()->managerRh()->create(['company_id' => $company->id]);

        Sanctum::actingAs($rhManager);

        $this->getJson('/api/v1/marketing/social-posts')->assertStatus(403);
    }
}
