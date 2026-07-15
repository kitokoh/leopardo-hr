<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Contracts;

use App\Modules\Marketing\Domain\Models\SocialAccount;

interface SocialAccountRepositoryInterface
{
    public function findForCompany(string $companyId, string $provider = 'ayrshare'): ?SocialAccount;
}
