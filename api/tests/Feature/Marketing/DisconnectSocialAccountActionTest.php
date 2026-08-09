<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Marketing\Application\Actions\DisconnectSocialAccount;
use App\Modules\Marketing\Domain\Exceptions\SocialAccountNotFoundException;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class DisconnectSocialAccountActionTest extends TestCase
{
    use Tests\RefreshTenantDatabase;

    public function test_disconnect_marks_active_account_as_revoked(): void
    {
        $company = Company::factory()->create();

        SocialAccount::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'provider' => 'ayrshare',
            'provider_profile_ref' => 'profile-key-abc',
            'status' => 'active',
            'last_error' => 'previous transient error',
        ]);

        $action = app(DisconnectSocialAccount::class);
        $result = $action->execute($company->id);

        $this->assertSame('revoked', $result->status);
        $this->assertNull($result->last_error);
        $this->assertDatabaseHas('social_accounts', [
            'company_id' => $company->id,
            'status' => 'revoked',
        ]);
    }

    public function test_disconnect_is_idempotent_on_already_revoked_account(): void
    {
        $company = Company::factory()->create();

        $account = SocialAccount::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'provider' => 'ayrshare',
            'provider_profile_ref' => 'profile-key-abc',
            'status' => 'revoked',
        ]);

        $action = app(DisconnectSocialAccount::class);
        $result = $action->execute($company->id);

        $this->assertSame($account->id, $result->id);
        $this->assertSame('revoked', $result->status);
    }

    public function test_disconnect_throws_when_no_account_connected(): void
    {
        $company = Company::factory()->create();

        $action = app(DisconnectSocialAccount::class);

        $this->expectException(SocialAccountNotFoundException::class);
        $action->execute($company->id);
    }
}
