<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Exceptions\CrmQuotaExceededException;
use App\Modules\CRM\Domain\Models\CrmChannel;
use Illuminate\Support\Carbon;

/**
 * Quotas mensuels par canal et par tenant (issue #5727).
 *
 * `monthly_quota` null = illimité. Le compteur vit sur la ligne canal
 * (used_this_month + quota_period) et est réinitialisé au changement de
 * période. L'usage est observable (colonne + audit).
 */
final class CrmQuotaService
{
    public function assertQuotaAvailable(CrmChannel $channel): void
    {
        $quota = $channel->monthly_quota;
        if ($quota === null) {
            return;
        }

        $period = Carbon::now()->format('Y-m');
        if ($channel->quota_period !== $period) {
            $channel->forceFill([
                'quota_period' => $period,
                'used_this_month' => 0,
            ])->save();

            return;
        }

        if ($channel->used_this_month >= $quota) {
            throw new CrmQuotaExceededException();
        }
    }

    public function recordUsage(CrmChannel $channel): void
    {
        $period = Carbon::now()->format('Y-m');
        if ($channel->quota_period !== $period) {
            $channel->forceFill([
                'quota_period' => $period,
                'used_this_month' => 1,
            ])->save();

            return;
        }

        $channel->forceFill([
            'used_this_month' => $channel->used_this_month + 1,
        ])->save();
    }
}
