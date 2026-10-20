<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Infrastructure\Services;

use App\Shared\Contracts\Discovery\PublicDiscoverableProvider;
use Illuminate\Database\Query\Builder;
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

    /**
     * #8761 — une vitrine PUBLIÉE accepte une demande de contact. Elle ne
     * propose ni réservation ni commande : la verticale n'expose aucun service
     * métier de ce type, et un bouton inopérant serait pire qu'aucun bouton.
     *
     * @return list<string>
     */
    public function supportedActions(): array
    {
        return ['contact'];
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

        $term = $this->textFilter($filters['q'] ?? null);

        if ($term !== null) {
            $query->where('c.name', $like, '%'.addcslashes($term, '%_\\').'%');
        }

        $city = $this->textFilter($filters['city'] ?? null);

        if ($city !== null) {
            $query->where('c.city', $like, addcslashes($city, '%_\\'));
        }

        $limit = isset($filters['limit']) && is_numeric($filters['limit'])
            ? max(1, min(500, (int) $filters['limit']))
            : 200;

        $items = [];

        foreach ($query->orderBy('c.name')->limit($limit)->get() as $row) {
            /** @var array<string, mixed> $rowArray */
            $rowArray = (array) $row;
            $items[] = $this->toPublicItem($rowArray);
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function toPublicItem(array $row): array
    {
        $slug = self::asString($row['slug'] ?? null) ?? '';

        return [
            'type' => $this->type(),
            'slug' => $slug,
            'name' => self::asString($row['name'] ?? null) ?? '',
            'category' => self::asString($row['category'] ?? null),
            'city' => self::asString($row['city'] ?? null),
            'country' => self::asString($row['country'] ?? null),
            'description' => null,
            'image_url' => null,
            // Vitrine sans géolocalisation : non retenue par une recherche `near`.
            'latitude' => null,
            'longitude' => null,
            'url' => '/vitrine/'.$slug,
        ];
    }

    /**
     * Valeur texte non vide, sinon null (les filtres HTTP ne sont pas fiables).
     */
    private function textFilter(mixed $value): ?string
    {
        $text = self::asString($value);

        return $text === null || $text === '' ? null : $text;
    }

    private static function asString(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    private function baseQuery(): Builder
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
