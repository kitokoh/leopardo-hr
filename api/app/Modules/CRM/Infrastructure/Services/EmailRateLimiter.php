<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Budget d'envoi email par tenant et par heure — Issue #5726.
 *
 * Compteur atomique (Cache) par (company, bucket, heure) : les quotas
 * marketing et transactionnel sont distincts (config `crm.email.*`).
 */
final class EmailRateLimiter
{
    public function consume(string $companyId, string $bucket, int $limitPerHour): bool
    {
        if ($limitPerHour <= 0) {
            return false;
        }

        $key = $this->key($companyId, $bucket);
        $used = (int) Cache::get($key, 0);

        if ($used >= $limitPerHour) {
            return false;
        }

        Cache::add($key, 0, 3600);
        $after = (int) Cache::increment($key);

        return $after <= $limitPerHour;
    }

    private function key(string $companyId, string $bucket): string
    {
        return 'crm_email:'.$companyId.':'.$bucket.':'.now()->format('YmdH');
    }
}
