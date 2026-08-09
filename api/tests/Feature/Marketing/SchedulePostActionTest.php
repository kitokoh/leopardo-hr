<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Marketing\Application\Actions\SchedulePost;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Domain\Models\SocialPost;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class SchedulePostActionTest extends TestCase
{
    use Tests\RefreshTenantDatabase;

    private function makeDraftPost(string $companyId): SocialPost
    {
        $account = SocialAccount::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'provider' => 'ayrshare',
            'provider_profile_ref' => 'profile-key-abc',
            'status' => 'active',
        ]);

        return SocialPost::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'social_account_id' => $account->id,
            'content' => 'Photo evenement equipe',
            'target_platforms' => ['linkedin'],
            'status' => SocialPost::STATUS_DRAFT,
        ]);
    }

    public function test_schedule_with_future_date_marks_post_as_scheduled(): void
    {
        Http::fake();

        $company = Company::factory()->create();
        $post = $this->makeDraftPost($company->id);
        $when = Carbon::now()->addDay();

        $action = app(SchedulePost::class);
        $result = $action->execute($post, $when);

        $this->assertSame(SocialPost::STATUS_SCHEDULED, $result->status);
        $this->assertSame($when->toDateTimeString(), $result->scheduled_at->toDateTimeString());
        Http::assertNothingSent();
    }

    public function test_schedule_without_date_publishes_immediately(): void
    {
        Http::fake([
            'api.ayrshare.com/api/post' => Http::response([
                'status' => 'success',
                'id' => 'post_ayr_999',
            ], 200),
        ]);

        $company = Company::factory()->create();
        $post = $this->makeDraftPost($company->id);

        $action = app(SchedulePost::class);
        $result = $action->execute($post, null);

        $this->assertSame(SocialPost::STATUS_PUBLISHED, $result->status);
        $this->assertSame('post_ayr_999', $result->provider_post_ref);
    }
}
