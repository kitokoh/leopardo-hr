<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Domain\Models\SocialPost;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Module Marketing — Phase 1.
 *
 * Verifies the social_accounts / social_posts migrations run cleanly and
 * that the Eloquent models behave as expected (tenant scoping, casts,
 * relation). No HTTP endpoints exist yet — those land in Phase 3.
 */
class SocialAccountModelTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_social_account_can_be_created_and_is_scoped_to_company(): void
    {
        $company = Company::factory()->create();

        $account = SocialAccount::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'provider' => 'ayrshare',
            'provider_profile_ref' => 'profile-key-123',
            'connected_platforms' => ['linkedin', 'facebook_page'],
            'display_name' => 'Leopardo Marketing',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('social_accounts', [
            'id' => $account->id,
            'company_id' => $company->id,
            'provider' => 'ayrshare',
            'display_name' => 'Leopardo Marketing',
            'status' => 'active',
        ]);

        $this->assertTrue($account->isActive());
        $this->assertSame(['linkedin', 'facebook_page'], $account->connected_platforms);

        // provider_profile_ref must never be exposed via array/JSON serialization.
        $this->assertArrayNotHasKey('provider_profile_ref', $account->toArray());

        // Stored value must be encrypted at rest, never the raw plaintext.
        $raw = DB::table('social_accounts')
            ->where('id', $account->id)
            ->value('provider_profile_ref');
        $this->assertNotSame('profile-key-123', $raw);
    }

    public function test_social_post_belongs_to_social_account_and_reports_due_state(): void
    {
        $company = Company::factory()->create();

        $account = SocialAccount::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'provider' => 'ayrshare',
            'provider_profile_ref' => 'profile-key-456',
            'status' => 'active',
        ]);

        $duePost = SocialPost::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'social_account_id' => $account->id,
            'content' => 'Journee portes ouvertes ce vendredi !',
            'target_platforms' => ['linkedin'],
            'status' => SocialPost::STATUS_SCHEDULED,
            'scheduled_at' => now()->subMinute(),
        ]);

        $futurePost = SocialPost::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'social_account_id' => $account->id,
            'content' => 'Publication planifiee pour demain.',
            'target_platforms' => ['facebook_page'],
            'status' => SocialPost::STATUS_SCHEDULED,
            'scheduled_at' => now()->addDay(),
        ]);

        $this->assertTrue($duePost->isDue());
        $this->assertFalse($futurePost->isDue());
        $this->assertTrue($duePost->socialAccount->is($account));
    }
}
