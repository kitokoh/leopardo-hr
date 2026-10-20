<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Shared\Contracts\Discovery\PublicProfileResolver;
use Illuminate\Support\Facades\DB;

/**
 * SUPERAPP-P1 (#8760) — Résolution d'une branche de restaurant PUBLIQUE vers son tenant.
 *
 * Mêmes gardes que {@see RestaurantDiscoverableProvider} : `is_public`,
 * `status = active`, `public_slug` non nul, société non `suspended`/`expired`,
 * feature `restaurantmanager` active. Toute condition manquante → `null`
 * (404 uniforme côté public, disparition de la recherche).
 */
final class RestaurantProfileResolver implements PublicProfileResolver
{
    public function type(): string
    {
        return 'restaurant';
    }

    public function resolve(string $slug): ?array
    {
        $table = $this->isPostgres() ? 'shared_tenants.restaurant_branches' : 'restaurant_branches';

        $row = DB::table($table.' as b')
            ->join('companies as c', 'c.id', '=', 'b.company_id')
            ->where('b.public_slug', $slug)
            ->where('b.is_public', true)
            ->where('b.status', 'active')
            ->when($this->isPostgres(), function ($query): void {
                $query->whereRaw("coalesce((c.features ->> 'restaurantmanager')::boolean, false) is true");
            })
            ->select(['b.company_id as company_id', 'b.name as name'])
            ->first();

        if ($row === null) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = (array) $row;

        $companyId = $data['company_id'] ?? null;

        if (! is_string($companyId) || $companyId === '') {
            return null;
        }

        $name = $data['name'] ?? null;

        return [
            'company_id' => $companyId,
            'display_name' => is_string($name) ? $name : '',
        ];
    }

    private function isPostgres(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }
}
