<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Shared\Contracts\Discovery\PublicDiscoverableProvider;
use Illuminate\Support\Facades\DB;

/**
 * SUPERAPP-P0 — Provider de découverte publique : restaurants publiés.
 *
 * Réutilise EXACTEMENT la requête publique de l'annuaire `RestaurantPublicDirectoryController`
 * (RESTO-901/#7746) : branches `is_public=true`, actives, `public_slug` non nul,
 * société non `suspended`/`expired` ET feature `restaurantmanager` active
 * (fail-closed, tout en PostgreSQL), dans le schéma partagé `shared_tenants`.
 *
 * Aucune nouvelle table, aucun nouveau rôle : lecture seule d'une surface
 * publique déjà opérationnelle, exposée en DTO public strict (aucun `id`,
 * `company_id`, `status`).
 */
final class RestaurantDiscoverableProvider implements PublicDiscoverableProvider
{
    public function type(): string
    {
        return 'restaurant';
    }

    public function search(array $filters): array
    {
        $query = $this->baseQuery()->select([
            'b.public_slug as slug',
            'b.name',
            'b.establishment_type as category',
            'b.city',
            'b.public_description as description',
            'b.cover_image_url as image_url',
            'b.latitude',
            'b.longitude',
        ]);

        $like = $this->isPostgres() ? 'ilike' : 'like';

        if (isset($filters['q']) && is_string($filters['q']) && $filters['q'] !== '') {
            $term = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(function ($q) use ($like, $term): void {
                $q->where('b.name', $like, $term)
                    ->orWhere('b.public_description', $like, $term);
            });
        }

        if (isset($filters['city']) && is_string($filters['city']) && $filters['city'] !== '') {
            $query->where('b.city', $like, addcslashes($filters['city'], '%_\\'));
        }

        $limit = isset($filters['limit']) && is_numeric($filters['limit'])
            ? max(1, min(500, (int) $filters['limit']))
            : 200;

        return $query->orderBy('b.name')->limit($limit)->get()->map(
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
            'country' => null,
            'description' => $row->description !== null ? (string) $row->description : null,
            'image_url' => $row->image_url !== null ? (string) $row->image_url : null,
            'latitude' => $row->latitude !== null ? (float) $row->latitude : null,
            'longitude' => $row->longitude !== null ? (float) $row->longitude : null,
            // Le client résout la fiche publique via SON annuaire vertical (slug).
            'url' => '/public/restaurants/'.$row->slug,
        ];
    }

    private function baseQuery()
    {
        $table = $this->isPostgres() ? 'shared_tenants.restaurant_branches' : 'restaurant_branches';

        $query = DB::table($table.' as b')
            ->join('companies as c', 'c.id', '=', 'b.company_id')
            ->where('b.is_public', true)
            ->where('b.status', 'active')
            ->whereNotNull('b.public_slug')
            ->whereNotIn('c.status', ['suspended', 'expired']);

        if ($this->isPostgres()) {
            $query->whereRaw("coalesce((c.features ->> 'restaurantmanager')::boolean, false) is true");
        }

        return $query;
    }

    private function isPostgres(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }
}
