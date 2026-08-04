<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Domain\Models\SocialPost;
use App\Modules\Marketing\Infrastructure\Jobs\PublishScheduledPostJob;
use App\Modules\Marketing\Infrastructure\Services\SocialPublishingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class PublishScheduledPostJobTest extends TestCase
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

    public function test_command_dispatches_one_job_per_due_post(): void
    {
        Bus::fake();

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

        Bus::assertDispatched(
            PublishScheduledPostJob::class,
            fn (PublishScheduledPostJob $job): bool => $job->socialPostId === $duePost->id
        );
        Bus::assertNotDispatched(
            PublishScheduledPostJob::class,
            fn (PublishScheduledPostJob $job): bool => $job->socialPostId === $futurePost->id
        );
    }

    public function test_job_publishes_the_post_and_resolves_its_own_tenant(): void
    {
        Http::fake([
            'api.ayrshare.com/api/post' => Http::response([
                'status' => 'success',
                'id' => 'post_ayr_job',
            ], 200),
        ]);

        $company = Company::factory()->create();
        $account = $this->makeAccount($company->id);

        $duePost = SocialPost::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'social_account_id' => $account->id,
            'content' => 'Post du via job',
            'target_platforms' => ['linkedin'],
            'status' => SocialPost::STATUS_SCHEDULED,
            'scheduled_at' => Carbon::now()->subMinute(),
        ]);

        // Aucun tenant courant etabli au moment du handle() : le job doit
        // resoudre et etablir son propre contexte via EnsureTenantContext,
        // exactement comme s'il etait execute par un worker de queue qui
        // vient de traiter le job d'un autre tenant.
        (new PublishScheduledPostJob($duePost->id))->handle(
            app(SocialPublishingService::class)
        );

        $fresh = $duePost->fresh();
        $this->assertSame(SocialPost::STATUS_PUBLISHED, $fresh->status);
        $this->assertSame('post_ayr_job', $fresh->provider_post_ref);
    }

    public function test_job_is_a_noop_when_post_is_no_longer_due(): void
    {
        Http::fake();

        $company = Company::factory()->create();
        $account = $this->makeAccount($company->id);

        $alreadyPublished = SocialPost::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'social_account_id' => $account->id,
            'content' => 'Deja publie manuellement',
            'target_platforms' => ['linkedin'],
            'status' => SocialPost::STATUS_PUBLISHED,
            'scheduled_at' => Carbon::now()->subMinute(),
            'published_at' => Carbon::now()->subSeconds(30),
        ]);

        (new PublishScheduledPostJob($alreadyPublished->id))->handle(
            app(SocialPublishingService::class)
        );

        Http::assertNothingSent();
        $this->assertSame(SocialPost::STATUS_PUBLISHED, $alreadyPublished->fresh()->status);
    }

    public function test_job_is_a_noop_when_post_no_longer_exists(): void
    {
        Http::fake();

        (new PublishScheduledPostJob(999_999))->handle(
            app(SocialPublishingService::class)
        );

        Http::assertNothingSent();
    }
}
