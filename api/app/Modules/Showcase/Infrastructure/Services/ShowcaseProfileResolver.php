<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Infrastructure\Services;

use App\Shared\Contracts\Discovery\PublicProfileResolver;
use Illuminate\Support\Facades\DB;

/**
 * SUPERAPP-P1 (#8760) — Résolution d'une vitrine PUBLIÉE vers son tenant.
 *
 * Réutilise à l'identique les gardes de {@see ShowcaseDiscoverableProvider}
 * (seule source de vérité « ce profil est-il joignable ? ») :
 * `status = published`, société ni `suspended` ni `expired`, feature
 * `showcase` active. Si l'une manque → `null`, donc 404 uniforme côté public
 * et disparition de la recherche (exigence « profil suspendu invisible »).
 */
final class ShowcaseProfileResolver implements PublicProfileResolver
{
    public function type(): string
    {
        return 'showcase';
    }

    public function resolve(string $slug): ?array
    {
        $table = $this->isPostgres() ? 'shared_tenants.company_showcases' : 'company_showcases';

        $row = DB::table($table.' as s')
            ->join('companies as c', 'c.id', '=', 's.company_id')
            ->where('s.slug', $slug)
            ->where('s.status', 'published')
            ->whereNotIn('c.status', ['suspended', 'expired'])
            ->when($this->isPostgres(), function ($query): void {
                $query->whereRaw("coalesce((c.features ->> 'showcase')::boolean, false) is true");
            })
            ->select(['s.company_id as company_id', 'c.name as name'])
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
