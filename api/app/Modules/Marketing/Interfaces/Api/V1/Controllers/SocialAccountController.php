<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Marketing\Application\Actions\ConnectSocialAccount;
use App\Modules\Marketing\Application\Actions\DisconnectSocialAccount;
use App\Modules\Marketing\Application\DTOs\ConnectSocialAccountDTO;
use App\Modules\Marketing\Domain\Contracts\SocialAccountRepositoryInterface;
use App\Modules\Marketing\Domain\Exceptions\SocialAccountNotFoundException;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\ConnectSocialAccountRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Module Marketing — Phase 3.
 *
 * Connexion/deconnexion du compte social (agregateur Ayrshare) d'un
 * tenant. Un seul compte actif par tenant+provider (contrainte unique
 * social_accounts.company_id+provider, cf. migration Phase 1).
 */
class SocialAccountController extends Controller
{
    public function __construct(
        private readonly SocialAccountRepositoryInterface $socialAccounts,
        private readonly ConnectSocialAccount $connectSocialAccount,
        private readonly DisconnectSocialAccount $disconnectSocialAccount,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $account = $this->socialAccounts->findForCompany($actor->company_id);

        if (! $account) {
            return new JsonResponse([
                'error' => 'SOCIAL_ACCOUNT_NOT_FOUND',
                'message' => "Aucun compte social connecte pour l'entreprise.",
            ], 404);
        }

        $this->authorize('view', $account);

        return new JsonResponse(['data' => $account]);
    }

    public function connect(ConnectSocialAccountRequest $request): JsonResponse
    {
        $this->authorize('connect', SocialAccount::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $dto = ConnectSocialAccountDTO::fromArray([
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
            'display_name' => $request->validated('display_name'),
            'provider' => $request->validated('provider', 'ayrshare'),
        ]);

        $account = $this->connectSocialAccount->execute($dto);

        return new JsonResponse(['data' => $account], 201);
    }

    public function disconnect(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $account = $this->socialAccounts->findForCompany($actor->company_id);

        if (! $account) {
            throw SocialAccountNotFoundException::forCompany($actor->company_id);
        }

        $this->authorize('disconnect', $account);

        $account = $this->disconnectSocialAccount->execute($actor->company_id);

        return new JsonResponse(['data' => $account]);
    }
}
