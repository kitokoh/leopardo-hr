<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Marketing\Application\Actions\ConnectSocialAccount;
use App\Modules\Marketing\Application\DTOs\ConnectSocialAccountDTO;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class ConnectSocialAccountActionTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_creates_a_new_active_social_account_with_encrypted_profile_ref(): void
    {
        Http::fake([
            'api.ayrshare.com/api/profiles/profile' => Http::response([
                'profileKey' => 'newly-created-profile-key',
                'refId' => 'ref-123',
                'title' => 'Leopardo Marketing',
            ], 200),
        ]);

        $company = Company::factory()->create();

        $action = app(ConnectSocialAccount::class);
        $account = $action->execute(ConnectSocialAccountDTO::fromArray([
            'company_id' => $company->id,
            'created_by' => 7,
            'display_name' => 'Leopardo Marketing',
        ]));

        $this->assertTrue($account->isActive());
        $this->assertSame($company->id, $account->company_id);

        $raw = DB::table('social_accounts')->where('id', $account->id)->value('provider_profile_ref');
        $this->assertNotSame('newly-created-profile-key', $raw);
        $this->assertSame('newly-created-profile-key', $account->provider_profile_ref);
    }

    public function test_reconnecting_an_already_active_account_is_idempotent(): void
    {
        Http::fake();

        $company = Company::factory()->create();

        SocialAccount::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'provider' => 'ayrshare',
            'provider_profile_ref' => 'already-active-key',
            'status' => 'active',
        ]);

        $action = app(ConnectSocialAccount::class);
        $account = $action->execute(ConnectSocialAccountDTO::fromArray([
            'company_id' => $company->id,
            'display_name' => 'Leopardo Marketing',
        ]));

        $this->assertSame('already-active-key', $account->provider_profile_ref);
        Http::assertNothingSent();
        $this->assertSame(1, SocialAccount::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }
}
