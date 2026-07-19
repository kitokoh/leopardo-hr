<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Application\Actions;

use App\Modules\Marketing\Domain\Exceptions\SocialAccountNotFoundException;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use Illuminate\Support\Facades\DB;

/**
 * Revoque le compte social connecte d'un tenant. Idempotent : si le
 * compte est deja `revoked`, ne fait rien. On ne supprime jamais la
 * ligne (historique des posts lies conserve via social_account_id).
 */
class DisconnectSocialAccount
{
    /** @throws SocialAccountNotFoundException */
    public function execute(string $companyId, string $provider = 'ayrshare'): SocialAccount
    {
        return DB::transaction(function () use ($companyId, $provider): SocialAccount {
            $account = SocialAccount::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('provider', $provider)
                ->first();

            if (! $account) {
                throw SocialAccountNotFoundException::forCompany($companyId);
            }

            if ($account->status === 'revoked') {
                return $account;
            }

            $account->status = 'revoked';
            $account->last_error = null;
            $account->save();

            return $account;
        });
    }
}
