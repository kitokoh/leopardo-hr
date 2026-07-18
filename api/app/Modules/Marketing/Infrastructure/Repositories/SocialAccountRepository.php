<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Repositories;

use App\Modules\Marketing\Domain\Contracts\SocialAccountRepositoryInterface;
use App\Modules\Marketing\Domain\Models\SocialAccount;

class SocialAccountRepository implements SocialAccountRepositoryInterface
{
    public function findForCompany(string $companyId, string $provider = 'ayrshare'): ?SocialAccount
    {
        return SocialAccount::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('provider', $provider)
            ->first();
    }
}
