<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Application\Actions;

use App\Modules\Marketing\Application\DTOs\CreateSocialPostDTO;
use App\Modules\Marketing\Domain\Contracts\SocialAccountRepositoryInterface;
use App\Modules\Marketing\Domain\Exceptions\SocialAccountNotFoundException;
use App\Modules\Marketing\Domain\Models\SocialPost;
use Illuminate\Support\Facades\DB;

/**
 * Cree une publication a l'etat `draft`. Ne contacte jamais l'agregateur :
 * la publication immediate ou planifiee est du ressort de SchedulePost,
 * qui accepte a la fois un post existant (workflow calendrier/editeur web)
 * et le cas terrain mobile (photo evenement -> publication immediate).
 */
class CreateSocialPost
{
    public function __construct(
        private readonly SocialAccountRepositoryInterface $socialAccounts,
    ) {}

    /** @throws SocialAccountNotFoundException */
    public function execute(CreateSocialPostDTO $dto): SocialPost
    {
        $account = $this->socialAccounts->findForCompany($dto->companyId);

        if (! $account) {
            throw SocialAccountNotFoundException::forCompany($dto->companyId);
        }

        return DB::transaction(function () use ($dto, $account): SocialPost {
            return SocialPost::query()->withoutGlobalScopes()->create([
                'company_id' => $dto->companyId,
                'social_account_id' => $account->id,
                'created_by' => $dto->createdBy,
                'content' => $dto->content,
                'media_paths' => $dto->mediaPaths,
                'target_platforms' => $dto->targetPlatforms,
                'status' => SocialPost::STATUS_DRAFT,
                'scheduled_at' => null,
            ]);
        });
    }
}
