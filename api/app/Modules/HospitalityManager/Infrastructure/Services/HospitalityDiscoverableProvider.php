<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Infrastructure\Services;

use App\Shared\Contracts\Discovery\PublicDiscoverableProvider;
use Illuminate\Database\Query\Builder;
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

    /**
     * #8761 — l'hébergement expose la réservation sur sa fiche publique et
     * accepte le contact. Aucune commande publique n'est servie.
     *
     * @return list<string>
     */
    public function supportedActions(): array
    {
        return ['contact', 'booking'];
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

        $term = $this->textFilter($filters['q'] ?? null);

        if ($term !== null) {
            $query->where('p.name', $like, '%'.addcslashes($term, '%_\\').'%');
        }

        $city = $this->textFilter($filters['city'] ?? null);

        if ($city !== null) {
            $query->where('p.city', $like, addcslashes($city, '%_\\'));
        }

        $limit = isset($filters['limit']) && is_numeric($filters['limit'])
            ? max(1, min(500, (int) $filters['limit']))
            : 200;

        $items = [];

        foreach ($query->orderBy('p.name')->limit($limit)->get() as $row) {
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
            'latitude' => self::asFloat($row['latitude'] ?? null),
            'longitude' => self::asFloat($row['longitude'] ?? null),
            'url' => '/stay/'.$slug,
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

    private static function asFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function baseQuery(): Builder
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
