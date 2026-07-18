<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Contracts;

use App\Modules\Marketing\Domain\Models\SocialPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SocialPostRepositoryInterface
{
    public function findById(int $id): ?SocialPost;

    /** @return LengthAwarePaginator<int, SocialPost> */
    public function paginateByCompany(string $companyId, int $perPage = 15): LengthAwarePaginator;

    /**
     * Publications planifiees dont l'echeance est passee, prêtes a etre
     * envoyees a l'agregateur par le job PublishScheduledSocialPost (Phase 4).
     *
     * @return Collection<int, SocialPost>
     */
    public function findDuePosts(int $limit = 50): Collection;

    public function save(SocialPost $post): SocialPost;

    public function delete(SocialPost $post): void;
}
