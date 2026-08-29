<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Queries;

use App\Modules\CRM\Domain\Models\CrmActivity;
use App\Modules\CRM\Domain\Models\CrmAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Issue #5720 — Timeline d'un account CRM (append-only, cursor pagination).
 *
 * Cursor keyset simple : ordre `id DESC`, pagination par `before_id`
 * (les ids auto-incrémentés d'activités append-only sont strictement
 * croissants). N+1 évité (owner eager-loadé).
 */
class AccountTimelineQuery
{
    /**
     * @return array{items: Collection<int, CrmActivity>, next_cursor: int|null}
     */
    public function execute(CrmAccount $account, int $limit = 25, ?int $beforeId = null): array
    {
        $limit = max(1, min($limit, 50));

        $query = CrmActivity::query()
            ->where('account_id', $account->id)
            ->with('actor:id,first_name,last_name')
            ->orderByDesc('id')
            ->limit($limit + 1); // +1 pour détecter une page suivante

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        /** @var Collection<int, CrmActivity> $rows */
        $rows = $query->get();

        $hasMore = $rows->count() > $limit;
        $items = $hasMore ? $rows->take($limit) : $rows;

        $nextCursor = $hasMore ? $items->last()?->id : null;

        return [
            'items' => $items,
            'next_cursor' => $nextCursor,
        ];
    }
}
