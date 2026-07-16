<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Marketing\Domain\Exceptions\SocialAccountInactiveException;
use App\Modules\Marketing\Domain\Exceptions\SocialAccountNotFoundException;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Domain\Models\SocialPost;
use App\Modules\Marketing\Infrastructure\Services\SocialPublishingService;
use Illuminate\Support\Facades\Http;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class SocialPublishingServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makePost(string $companyId, ?SocialAccount $account = null): SocialPost
    {
        $account ??= SocialAccount::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'provider' => 'ayrshare',
            'provider_profile_ref' => 'profile-key-abc',
            'status' => 'active',
        ]);

        return SocialPost::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'social_account_id' => $account->id,
            'content' => 'Journee portes ouvertes ce vendredi !',
            'target_platforms' => ['linkedin'],
            'status' => SocialPost::STATUS_DRAFT,
        ]);
    }

    public function test_publish_now_marks_post_as_published_on_success(): void
    {
        Http::fake([
            'api.ayrshare.com/api/post' => Http::response([
                'status' => 'success',
                'id' => 'post_ayr_123',
            ], 200),
        ]);

        $company = Company::factory()->create();
        $post = $this->makePost($company->id);

        $service = app(SocialPublishingService::class);
        $result = $service->publishNow($post);

        $this->assertSame(SocialPost::STATUS_PUBLISHED, $result->status);
        $this->assertSame('post_ayr_123', $result->provider_post_ref);
        $this->assertNotNull($result->published_at);
        $this->assertNull($result->error_message);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.ayrshare.com/api/post'
                && $request->hasHeader('Profile-Key', 'profile-key-abc')
                && $request['post'] === 'Journee portes ouvertes ce vendredi !';
        });
    }

    public function test_publish_now_marks_post_as_failed_on_ayrshare_error(): void
    {
        Http::fake([
            'api.ayrshare.com/api/post' => Http::response([
                'status' => 'error',
                'errors' => [['message' => 'Invalid platform']],
            ], 400),
        ]);

        $company = Company::factory()->create();
        $post = $this->makePost($company->id);

        $service = app(SocialPublishingService::class);
        $result = $service->publishNow($post);

        $this->assertSame(SocialPost::STATUS_FAILED, $result->status);
        $this->assertSame(1, $result->attempts);
        $this->assertStringContainsString('Invalid platform', $result->error_message);
    }

    public function test_publish_now_throws_when_no_social_account_connected(): void
    {
        $company = Company::factory()->create();

        $account = SocialAccount::withoutGlobalScopes()->create([
            'company_id' => Company::factory()->create()->id,
            'provider' => 'ayrshare',
            'provider_profile_ref' => 'other-company-key',
            'status' => 'active',
        ]);

        $post = SocialPost::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'social_account_id' => $account->id,
            'content' => 'Sans compte connecte pour ce tenant',
            'target_platforms' => ['linkedin'],
            'status' => SocialPost::STATUS_DRAFT,
        ]);

        $service = app(SocialPublishingService::class);

        $this->expectException(SocialAccountNotFoundException::class);
        $service->publishNow($post);
    }

    public function test_publish_now_throws_when_social_account_is_inactive(): void
    {
        $company = Company::factory()->create();

        $account = SocialAccount::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'provider' => 'ayrshare',
            'provider_profile_ref' => 'profile-key-abc',
            'status' => 'revoked',
        ]);

        $post = $this->makePost($company->id, $account);

        $service = app(SocialPublishingService::class);

        $this->expectException(SocialAccountInactiveException::class);
        $service->publishNow($post);
    }
}
