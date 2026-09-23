<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Shared\Support\TenantCache;
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
        // tenant-cache:via-helper — clé construite par TenantCache::keyFor (#8058)
        $cached = Cache::get($key, 0);
        $used = is_numeric($cached) ? (int) $cached : 0;

        if ($used >= $limitPerHour) {
            return false;
        }

        // tenant-cache:via-helper — clé construite par TenantCache::keyFor (#8058)
        Cache::add($key, 0, 3600);
        // tenant-cache:via-helper — clé construite par TenantCache::keyFor (#8058)
        $after = (int) Cache::increment($key);

        return $after <= $limitPerHour;
    }

    private function key(string $companyId, string $bucket): string
    {
        // #8058 — clé tenant via le helper central (préfixe company_id systématique)
        return TenantCache::keyFor($companyId, 'crm_email:'.$bucket.':'.now()->format('YmdH'));
    }
}
