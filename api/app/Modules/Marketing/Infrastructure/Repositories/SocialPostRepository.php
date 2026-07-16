<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Repositories;

use App\Modules\Marketing\Domain\Contracts\SocialPostRepositoryInterface;
use App\Modules\Marketing\Domain\Models\SocialPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SocialPostRepository implements SocialPostRepositoryInterface
{
    public function findById(int $id): ?SocialPost
    {
        return SocialPost::query()->find($id);
    }

    /** @return LengthAwarePaginator<int, SocialPost> */
    public function paginateByCompany(string $companyId, int $perPage = 15): LengthAwarePaginator
    {
        return SocialPost::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Parcourt tous les tenants (job planifie) : ignore volontairement le
     * scope tenant par defaut, comme AutoCloseAttendanceCommand.
     *
     * @return Collection<int, SocialPost>
     */
    public function findDuePosts(int $limit = 50): Collection
    {
        /** @var Collection<int, SocialPost> */
        return SocialPost::query()
            ->withoutGlobalScopes()
            ->where('status', SocialPost::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();
    }

    public function save(SocialPost $post): SocialPost
    {
        $post->save();

        return $post;
    }

    public function delete(SocialPost $post): void
    {
        $post->delete();
    }
}
