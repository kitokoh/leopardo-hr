<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Infrastructure\Services;

use App\Shared\Contracts\Discovery\PublicDiscoverableProvider;
use Illuminate\Support\Facades\DB;

/**
 * SUPERAPP-P0 — Provider de découverte publique : hébergements (hôtels & résidences).
 *
 * Réutilise l'invariant public HOSP-006/#7944 : `hospitality_properties`
 * `is_public=true`, `status=active`, `public_slug` non nul, société non
 * `suspended`/`expired` ET feature `hospitality` active (fail-closed),
 * dans le schéma partagé `shared_tenants`.
 *
 * DTO public strict : type/code établissement en `category`, ville/pays, note
 * étoiles NON exposée (donnée commerciale), aucun `id` ni `company_id`.
 */
final class HospitalityDiscoverableProvider implements PublicDiscoverableProvider
{
    public function type(): string
    {
        return 'hospitality_property';
    }

    public function search(array $filters): array
    {
        $query = $this->baseQuery()->select([
            'p.public_slug as slug',
            'p.name',
            'p.type as category',
            'p.city',
            'p.country',
            'p.latitude',
            'p.longitude',
        ]);

        $like = $this->isPostgres() ? 'ilike' : 'like';

        if (isset($filters['q']) && is_string($filters['q']) && $filters['q'] !== '') {
            $query->where('p.name', $like, '%'.addcslashes($filters['q'], '%_\\').'%');
        }

        if (isset($filters['city']) && is_string($filters['city']) && $filters['city'] !== '') {
            $query->where('p.city', $like, addcslashes($filters['city'], '%_\\'));
        }

        $limit = isset($filters['limit']) && is_numeric($filters['limit'])
            ? max(1, min(500, (int) $filters['limit']))
            : 200;

        return $query->orderBy('p.name')->limit($limit)->get()->map(
            fn (object $row): array => $this->toPublicItem($row)
        )->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function toPublicItem(object $row): array
    {
        return [
            'type' => $this->type(),
            'slug' => (string) $row->slug,
            'name' => (string) $row->name,
            'category' => $row->category !== null ? (string) $row->category : null,
            'city' => $row->city !== null ? (string) $row->city : null,
            'country' => $row->country !== null ? (string) $row->country : null,
            'description' => null,
            'image_url' => null,
            'latitude' => $row->latitude !== null ? (float) $row->latitude : null,
            'longitude' => $row->longitude !== null ? (float) $row->longitude : null,
            'url' => '/stay/'.$row->slug,
        ];
    }

    private function baseQuery()
    {
        $table = $this->isPostgres() ? 'shared_tenants.hospitality_properties' : 'hospitality_properties';

        $query = DB::table($table.' as p')
            ->join('companies as c', 'c.id', '=', 'p.company_id')
            ->where('p.is_public', true)
            ->where('p.status', 'active')
            ->whereNotNull('p.public_slug')
            ->whereNotIn('c.status', ['suspended', 'expired']);

        if ($this->isPostgres()) {
            $query->whereRaw("coalesce((c.features ->> 'hospitality')::boolean, false) is true");
        }

        return $query;
    }

    private function isPostgres(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }
}
