<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Marketing\Application\Actions\CreateSocialPost;
use App\Modules\Marketing\Application\DTOs\CreateSocialPostDTO;
use App\Modules\Marketing\Domain\Exceptions\SocialAccountNotFoundException;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Domain\Models\SocialPost;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class CreateSocialPostActionTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_creates_a_draft_post_for_a_connected_company(): void
    {
        $company = Company::factory()->create();

        SocialAccount::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'provider' => 'ayrshare',
            'provider_profile_ref' => 'profile-key-abc',
            'status' => 'active',
        ]);

        $action = app(CreateSocialPost::class);

        $post = $action->execute(CreateSocialPostDTO::fromArray([
            'company_id' => $company->id,
            'created_by' => 42,
            'content' => 'Notre nouvelle offre est disponible !',
            'target_platforms' => ['linkedin', 'facebook_page'],
        ]));

        $this->assertSame(SocialPost::STATUS_DRAFT, $post->status);
        $this->assertSame($company->id, $post->company_id);
        $this->assertSame(['linkedin', 'facebook_page'], $post->target_platforms);
        $this->assertNull($post->scheduled_at);
        $this->assertDatabaseHas('social_posts', [
            'id' => $post->id,
            'status' => SocialPost::STATUS_DRAFT,
        ]);
    }

    public function test_throws_when_company_has_no_connected_social_account(): void
    {
        $company = Company::factory()->create();

        $action = app(CreateSocialPost::class);

        $this->expectException(SocialAccountNotFoundException::class);

        $action->execute(CreateSocialPostDTO::fromArray([
            'company_id' => $company->id,
            'content' => 'Contenu sans compte connecte',
            'target_platforms' => ['linkedin'],
        ]));
    }
}
