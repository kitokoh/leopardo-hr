<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Infrastructure\Services;

use App\Shared\Contracts\Discovery\PublicProfileResolver;
use Illuminate\Support\Facades\DB;

/**
 * SUPERAPP-P1 (#8760) — Résolution d'un établissement PUBLIQUE vers son tenant.
 *
 * Mêmes gardes que {@see HospitalityDiscoverableProvider} : `is_public`,
 * `status = active`, `public_slug` non nul, société non `suspended`/`expired`,
 * feature `hospitality` active. Toute condition manquante → `null`.
 */
final class HospitalityProfileResolver implements PublicProfileResolver
{
    public function type(): string
    {
        return 'hospitality_property';
    }

    public function resolve(string $slug): ?array
    {
        $table = $this->isPostgres() ? 'shared_tenants.hospitality_properties' : 'hospitality_properties';

        $row = DB::table($table.' as p')
            ->join('companies as c', 'c.id', '=', 'p.company_id')
            ->where('p.public_slug', $slug)
            ->where('p.is_public', true)
            ->where('p.status', 'active')
            ->when($this->isPostgres(), function ($query): void {
                $query->whereRaw("coalesce((c.features ->> 'hospitality')::boolean, false) is true");
            })
            ->select(['p.company_id as company_id', 'p.name as name'])
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
