<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Marketing\Domain\Models\PostPublication;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Domain\Models\SocialPost;
use App\Modules\Marketing\Infrastructure\Services\SocialPublishingService;
use Illuminate\Support\Facades\Http;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1432 — verifie que `SocialPublishingService::publishNow()`
 * persiste bien un `PostPublication` par plateforme ciblee, avec le bon
 * statut/`external_post_id`/`error_message` selon la reponse Ayrshare
 * (`postIds[]`/`errors[]`), y compris dans le cas de succes partiel
 * (une plateforme reussit, une autre echoue dans le meme appel).
 */
class PostPublicationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeAccountAndPost(string $companyId, array $platforms): SocialPost
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
            'content' => 'Post multi-plateformes',
            'target_platforms' => $platforms,
            'status' => SocialPost::STATUS_DRAFT,
        ]);
    }

    public function test_publish_now_creates_one_publication_per_platform_on_full_success(): void
    {
        Http::fake([
            'api.ayrshare.com/api/post' => Http::response([
                'status' => 'success',
                'id' => 'post_ayr_123',
                'postIds' => [
                    ['status' => 'success', 'id' => 'li_456', 'platform' => 'linkedin'],
                    ['status' => 'success', 'id' => 'fb_789', 'platform' => 'facebook'],
                ],
            ], 200),
        ]);

        $company = Company::factory()->create();
        $post = $this->makeAccountAndPost($company->id, ['linkedin', 'facebook']);

        app(SocialPublishingService::class)->publishNow($post);

        $publications = PostPublication::withoutGlobalScopes()
            ->where('social_post_id', $post->id)
            ->orderBy('platform')
            ->get();

        $this->assertCount(2, $publications);

        $facebook = $publications->firstWhere('platform', 'facebook');
        $this->assertSame(PostPublication::STATUS_SUCCESS, $facebook->status);
        $this->assertSame('fb_789', $facebook->external_post_id);
        $this->assertNotNull($facebook->published_at);
        $this->assertNull($facebook->error_message);

        $linkedin = $publications->firstWhere('platform', 'linkedin');
        $this->assertSame(PostPublication::STATUS_SUCCESS, $linkedin->status);
        $this->assertSame('li_456', $linkedin->external_post_id);
    }

    public function test_publish_now_marks_only_the_failing_platform_as_failed_on_partial_success(): void
    {
        Http::fake([
            'api.ayrshare.com/api/post' => Http::response([
                'status' => 'success',
                'id' => 'post_ayr_124',
                'postIds' => [
                    ['status' => 'success', 'id' => 'li_456', 'platform' => 'linkedin'],
                ],
                'errors' => [
                    ['platform' => 'twitter', 'message' => 'Status is a duplicate.'],
                ],
            ], 200),
        ]);

        $company = Company::factory()->create();
        $post = $this->makeAccountAndPost($company->id, ['linkedin', 'twitter']);

        $result = app(SocialPublishingService::class)->publishNow($post);

        // Le post reste marque publie globalement (Ayrshare a renvoye
        // status=success au niveau du call), le detail par plateforme est
        // dans post_publications.
        $this->assertSame(SocialPost::STATUS_PUBLISHED, $result->status);

        $twitter = PostPublication::withoutGlobalScopes()
            ->where('social_post_id', $post->id)
            ->where('platform', 'twitter')
            ->first();

        $this->assertNotNull($twitter);
        $this->assertSame(PostPublication::STATUS_FAILED, $twitter->status);
        $this->assertNull($twitter->external_post_id);
        $this->assertSame('Status is a duplicate.', $twitter->error_message);
        $this->assertNull($twitter->published_at);

        $linkedin = PostPublication::withoutGlobalScopes()
            ->where('social_post_id', $post->id)
            ->where('platform', 'linkedin')
            ->first();

        $this->assertSame(PostPublication::STATUS_SUCCESS, $linkedin->status);
    }

    public function test_publish_now_marks_all_target_platforms_failed_on_total_failure(): void
    {
        Http::fake([
            'api.ayrshare.com/api/post' => Http::response([
                'status' => 'error',
                'errors' => [['message' => 'Invalid platform']],
            ], 400),
        ]);

        $company = Company::factory()->create();
        $post = $this->makeAccountAndPost($company->id, ['linkedin', 'facebook']);

        app(SocialPublishingService::class)->publishNow($post);

        $publications = PostPublication::withoutGlobalScopes()
            ->where('social_post_id', $post->id)
            ->get();

        $this->assertCount(2, $publications);
        $this->assertTrue($publications->every(fn (PostPublication $p) => $p->status === PostPublication::STATUS_FAILED));
        $this->assertTrue($publications->every(fn (PostPublication $p) => $p->external_post_id === null));
    }

    public function test_publish_now_is_idempotent_across_retries_for_the_same_platform(): void
    {
        Http::fake([
            'api.ayrshare.com/api/post' => Http::sequence()
                ->push([
                    'status' => 'success',
                    'id' => 'post_ayr_125',
                    'postIds' => [
                        ['status' => 'success', 'id' => 'li_1', 'platform' => 'linkedin'],
                    ],
                    'errors' => [
                        ['platform' => 'twitter', 'message' => 'Temporary error'],
                    ],
                ], 200)
                ->push([
                    'status' => 'success',
                    'id' => 'post_ayr_125',
                    'postIds' => [
                        ['status' => 'success', 'id' => 'li_1', 'platform' => 'linkedin'],
                        ['status' => 'success', 'id' => 'tw_2', 'platform' => 'twitter'],
                    ],
                ], 200),
        ]);

        $company = Company::factory()->create();
        $post = $this->makeAccountAndPost($company->id, ['linkedin', 'twitter']);

        $service = app(SocialPublishingService::class);
        $service->publishNow($post);
        $service->publishNow($post);

        $publications = PostPublication::withoutGlobalScopes()
            ->where('social_post_id', $post->id)
            ->get();

        // Toujours une seule ligne par plateforme malgre les 2 appels
        // (contrainte unique social_post_id+platform, upsert).
        $this->assertCount(2, $publications);

        $twitter = $publications->firstWhere('platform', 'twitter');
        $this->assertSame(PostPublication::STATUS_SUCCESS, $twitter->status);
        $this->assertSame('tw_2', $twitter->external_post_id);
    }
}
