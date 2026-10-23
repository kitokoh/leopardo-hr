<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Shared\Contracts\Discovery\PublicDiscoverableProvider;
use Illuminate\Database\Query\Builder;
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

    /**
     * #8761 — le restaurant prend en charge la commande publique
     * (`RestaurantPublicOrderService` / `POST /api/v1/restaurant/public/orders`)
     * ET le contact via la passerelle commune.
     *
     * AUDIT PHASE 1 — `booking` RETIRÉ (2026-10-21). Aucun contrat public de
     * réservation n'existe : `RestaurantReservationController` n'est monté que
     * dans le groupe `auth:sanctum` du dashboard (`routes/modules/
     * restaurantmanager.php`), et aucune route `public/.../reservation` n'est
     * déclarée. Déclarer `booking` produisait un bouton inopérant côté
     * `front/web`. La capacité est donc retirée tant qu'un contrat public
     * (P3-01) n'est pas livré : `supportedActions()` doit rester la vérité.
     *
     * @return list<string>
     */
    public function supportedActions(): array
    {
        return ['contact', 'order'];
    }

    /**
     * #8761 — la commande est servie DANS le parcours public de la verticale :
     * `front/web/src/app/restaurants/[slug]/restaurant-order-panel.tsx` (menu →
     * panier → `POST /api/v1/restaurant/public/orders` → suivi). `contact` n'a
     * pas de destination propre : il ouvre le formulaire de la passerelle.
     *
     * @return array<string, string|null>
     */
    public function supportedActionUrls(): array
    {
        return [
            'contact' => null,
            'order' => '/restaurants/{slug}#commander',
        ];
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

        $term = $this->textFilter($filters['q'] ?? null);

        if ($term !== null) {
            $pattern = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function (Builder $q) use ($like, $pattern): void {
                $q->where('b.name', $like, $pattern)
                    ->orWhere('b.public_description', $like, $pattern);
            });
        }

        $city = $this->textFilter($filters['city'] ?? null);

        if ($city !== null) {
            $query->where('b.city', $like, addcslashes($city, '%_\\'));
        }

        $limit = isset($filters['limit']) && is_numeric($filters['limit'])
            ? max(1, min(500, (int) $filters['limit']))
            : 200;

        $items = [];

        foreach ($query->orderBy('b.name')->limit($limit)->get() as $row) {
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
            'country' => null,
            'description' => self::asString($row['description'] ?? null),
            'image_url' => self::asString($row['image_url'] ?? null),
            'latitude' => self::asFloat($row['latitude'] ?? null),
            'longitude' => self::asFloat($row['longitude'] ?? null),
            // AUDIT PHASE 1 (2026-10-21) — la fiche publique est servie par
            // `front/web` sous `/restaurants/{slug}` (`src/app/restaurants/
            // [slug]/page.tsx`, RESTO-903/#7748). L'ancienne valeur
            // `/public/restaurants/{slug}` désignait le préfixe de l'API
            // (`/api/v1/public/restaurants`), pas une route du navigateur :
            // chaque résultat Restaurant de `/decouvrir` pointait donc vers
            // une page inexistante (même classe de bug que P1-02, mais côté
            // Restaurant, et non détectée alors).
            'url' => '/restaurants/'.$slug,
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
