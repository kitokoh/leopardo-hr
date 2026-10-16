<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Infrastructure\Services;

use App\Shared\Contracts\Discovery\PublicDiscoverableProvider;
use Illuminate\Support\Facades\DB;

/**
 * SUPERAPP-P0 — Provider de découverte publique : vitrines d'entreprise (BC-27).
 *
 * Réutilise l'invariant public de `/vitrine/{slug}` (#6865) : showcases
 * `status=published`, société non `suspended`/`expired` ET feature `showcase`
 * active (fail-closed), dans le schéma partagé `shared_tenants`.
 *
 * La vitrine n'a ni nom ni géolocalisation propres : le NOM exposé est celui de
 * la société et la ville/pays proviennent de la société (données publiques
 * d'annuaire) — aucun `id`, `company_id`, `settings` ni `custom_domain`.
 */
final class ShowcaseDiscoverableProvider implements PublicDiscoverableProvider
{
    public function type(): string
    {
        return 'showcase';
    }

    public function search(array $filters): array
    {
        $query = $this->baseQuery()->select([
            's.slug as slug',
            'c.name as name',
            'c.sector as category',
            'c.city as city',
            'c.country as country',
        ]);

        $like = $this->isPostgres() ? 'ilike' : 'like';

        if (isset($filters['q']) && is_string($filters['q']) && $filters['q'] !== '') {
            $query->where('c.name', $like, '%'.addcslashes($filters['q'], '%_\\').'%');
        }

        if (isset($filters['city']) && is_string($filters['city']) && $filters['city'] !== '') {
            $query->where('c.city', $like, addcslashes($filters['city'], '%_\\'));
        }

        $limit = isset($filters['limit']) && is_numeric($filters['limit'])
            ? max(1, min(500, (int) $filters['limit']))
            : 200;

        return $query->orderBy('c.name')->limit($limit)->get()->map(
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
            // Vitrine sans géolocalisation : non retenue par une recherche `near`.
            'latitude' => null,
            'longitude' => null,
            'url' => '/vitrine/'.$row->slug,
        ];
    }

    private function baseQuery()
    {
        $table = $this->isPostgres() ? 'shared_tenants.company_showcases' : 'company_showcases';

        $query = DB::table($table.' as s')
            ->join('companies as c', 'c.id', '=', 's.company_id')
            ->where('s.status', 'published')
            ->whereNotIn('c.status', ['suspended', 'expired']);

        if ($this->isPostgres()) {
            $query->whereRaw("coalesce((c.features ->> 'showcase')::boolean, false) is true");
        }

        return $query;
    }

    private function isPostgres(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }
}
