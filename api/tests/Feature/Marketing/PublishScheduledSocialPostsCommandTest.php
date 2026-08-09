<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Domain\Models\SocialPost;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class PublishScheduledSocialPostsCommandTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeAccount(string $companyId): SocialAccount
    {
        return SocialAccount::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'provider' => 'ayrshare',
            'provider_profile_ref' => 'profile-key-abc',
            'status' => 'active',
        ]);
    }

    public function test_publishes_due_scheduled_posts(): void
    {
        Http::fake([
            'api.ayrshare.com/api/post' => Http::response([
                'status' => 'success',
                'id' => 'post_ayr_due',
            ], 200),
        ]);

        $company = Company::factory()->create();
        $account = $this->makeAccount($company->id);

        $duePost = SocialPost::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'social_account_id' => $account->id,
            'content' => 'Post du',
            'target_platforms' => ['linkedin'],
            'status' => SocialPost::STATUS_SCHEDULED,
            'scheduled_at' => Carbon::now()->subMinutes(5),
        ]);

        $futurePost = SocialPost::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'social_account_id' => $account->id,
            'content' => 'Post futur',
            'target_platforms' => ['linkedin'],
            'status' => SocialPost::STATUS_SCHEDULED,
            'scheduled_at' => Carbon::now()->addHour(),
        ]);

        $this->artisan('marketing:publish-scheduled-posts')->assertSuccessful();

        $this->assertSame(SocialPost::STATUS_PUBLISHED, $duePost->fresh()->status);
        $this->assertSame('post_ayr_due', $duePost->fresh()->provider_post_ref);
        $this->assertSame(SocialPost::STATUS_SCHEDULED, $futurePost->fresh()->status);
    }

    public function test_marks_post_as_failed_when_ayrshare_errors(): void
    {
        Http::fake([
            'api.ayrshare.com/api/post' => Http::response([
                'status' => 'error',
                'errors' => [['message' => 'rate limited']],
            ], 429),
        ]);

        $company = Company::factory()->create();
        $account = $this->makeAccount($company->id);

        $duePost = SocialPost::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'social_account_id' => $account->id,
            'content' => 'Post du en echec',
            'target_platforms' => ['linkedin'],
            'status' => SocialPost::STATUS_SCHEDULED,
            'scheduled_at' => Carbon::now()->subMinute(),
        ]);

        $this->artisan('marketing:publish-scheduled-posts')->assertSuccessful();

        $fresh = $duePost->fresh();
        $this->assertSame(SocialPost::STATUS_FAILED, $fresh->status);
        $this->assertSame(1, $fresh->attempts);
        $this->assertNotNull($fresh->error_message);
    }

    public function test_does_nothing_when_no_posts_are_due(): void
    {
        Http::fake();

        $this->artisan('marketing:publish-scheduled-posts')->assertSuccessful();

        Http::assertNothingSent();
    }
}
