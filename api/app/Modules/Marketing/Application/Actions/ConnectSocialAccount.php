<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Application\Actions;

use App\Modules\Marketing\Application\DTOs\ConnectSocialAccountDTO;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Infrastructure\Services\AyrshareClient;
use Illuminate\Support\Facades\DB;

/**
 * Cree (ou reactive) le profil Ayrshare d'un tenant et persiste la
 * reference chiffree correspondante dans social_accounts. Idempotent au
 * niveau tenant+provider grace a la contrainte unique (company_id, provider).
 */
class ConnectSocialAccount
{
    public function __construct(
        private readonly AyrshareClient $ayrshareClient,
    ) {}

    public function execute(ConnectSocialAccountDTO $dto): SocialAccount
    {
        return DB::transaction(function () use ($dto): SocialAccount {
            $existing = SocialAccount::query()
                ->withoutGlobalScopes()
                ->where('company_id', $dto->companyId)
                ->where('provider', $dto->provider)
                ->first();

            if ($existing && $existing->isActive()) {
                return $existing;
            }

            $profile = $this->ayrshareClient->createProfile($dto->displayName);

            /** @var SocialAccount $account */
            $account = SocialAccount::query()->withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $dto->companyId, 'provider' => $dto->provider],
                [
                    'provider_profile_ref' => $profile['profileKey'],
                    'display_name' => $dto->displayName,
                    'status' => 'active',
                    'last_error' => null,
                    'connected_at' => now(),
                    'created_by' => $dto->createdBy,
                ]
            );

            return $account;
        });
    }
}
