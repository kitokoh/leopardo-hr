<?php

declare(strict_types=1);

namespace App\Modules\Retail\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Application\Services\RetailMarketplaceService;
use App\Modules\Retail\Domain\Models\RetailOnlineSettings;
use App\Shared\Contracts\Discovery\PublicDiscoverableProvider;

/**
 * C-1 (#8773) — Provider de découverte publique : boutiques Leopardo Marché.
 *
 * Réutilise EXACTEMENT l'opt-in public déjà opérationnel de la marketplace
 * Retail (#7807) via `RetailMarketplaceService` : `retail_online_settings.enabled
 * = true`, feature `retail` active, société ni `suspended` ni `expired`
 * (fail-closed). Aucune requête SQL parallèle, aucune nouvelle table : la
 * source de vérité « cette boutique est-elle publiable ? » reste le service
 * métier.
 *
 * DTO public STRICT : identité de boutique (slug public = `companies.slug`,
 * nom de la boutique, ville, description publique). Jamais de `company_id`,
 * de stock, de coûts/marges, ni de `contact_phone`/`contact_email` de la
 * table de réglages — ces coordonnées ne sont publiées que si le marchand les
 * expose lui-même via le contact de la passerelle.
 */
final class RetailDiscoverableProvider implements PublicDiscoverableProvider
{
    public function __construct(private readonly RetailMarketplaceService $marketplace) {}

    public function type(): string
    {
        return 'retail_shop';
    }

    /**
     * #8773 — une boutique opt-in accepte une demande de contact ET une
     * commande publique : `RetailMarketOrderPublicController`
     * (`POST /api/v1/public/market/orders`) sert réellement la commande en
     * ligne. Pas de `booking` : Retail ne prend pas de réservation.
     *
     * @return list<string>
     */
    public function supportedActions(): array
    {
        return ['contact', 'order'];
    }

    public function search(array $filters): array
    {
        /** @var list<string> $eligible */
        $eligible = $this->marketplace->eligibleCompanyIds();

        if ($eligible === []) {
            return [];
        }

        /** @var array<string, RetailOnlineSettings> $settings */
        $settings = $this->marketplace->settingsByCompanyId($eligible);
        /** @var array<string, Company> $companies */
        $companies = $this->marketplace->companiesById($eligible);

        $term = $this->textFilter($filters['q'] ?? null);
        $city = $this->textFilter($filters['city'] ?? null);

        $items = [];
        $names = [];

        foreach ($settings as $companyId => $shopSettings) {
            $company = $companies[$companyId] ?? null;

            if (! $company instanceof Company) {
                continue;
            }

            if ($term !== null
                && ! $this->matches($shopSettings->shop_name, $term)
                && ! $this->matchesNullable($shopSettings->shop_description, $term)) {
                continue;
            }

            if ($city !== null && ! $this->equalsIgnoreCaseNullable($shopSettings->city, $city)) {
                continue;
            }

            $items[] = $this->toPublicItem($company, $shopSettings);
            $names[] = $shopSettings->shop_name;
        }

        // Tri alphabétique stable sur le nom public de la boutique, calculé sur
        // les objets typés (jamais sur le tableau DTO, dont les valeurs sont `mixed`).
        array_multisort($names, SORT_STRING | SORT_FLAG_CASE, $items);

        $limit = isset($filters['limit']) && is_numeric($filters['limit'])
            ? max(1, min(500, (int) $filters['limit']))
            : 200;

        return array_slice($items, 0, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    private function toPublicItem(Company $company, RetailOnlineSettings $settings): array
    {
        $slug = $company->slug;

        return [
            'type' => $this->type(),
            'slug' => $slug,
            'name' => $settings->shop_name,
            'category' => null,
            'city' => $settings->city,
            'country' => $company->country,
            'description' => $settings->shop_description,
            'image_url' => null,
            // La boutique n'a pas d'adresse géolocalisée dans son opt-in :
            // seule la ville est publiée. Une recherche `near` l'exclut donc
            // légitimement (pas de coordonnées inventées).
            'latitude' => null,
            'longitude' => null,
            // La fiche publique est servie par l'application Leopardo Marché
            // (`/boutiques/{slug}`), dont l'origine est inconnue du backend :
            // exposer un chemin relatif ici produirait un lien mort depuis
            // `front/web`. On ne publie donc pas d'URL plutôt qu'une fausse.
            'url' => null,
        ];
    }

    private function matches(string $haystack, string $needle): bool
    {
        if ($haystack === '') {
            return false;
        }

        return mb_stripos($haystack, $needle) !== false;
    }

    private function matchesNullable(?string $haystack, string $needle): bool
    {
        return $haystack !== null && $this->matches($haystack, $needle);
    }

    private function equalsIgnoreCaseNullable(?string $haystack, string $needle): bool
    {
        return $haystack !== null && mb_strtolower($haystack) === mb_strtolower($needle);
    }

    /**
     * Valeur texte non vide, sinon null (les filtres HTTP ne sont pas fiables).
     */
    private function textFilter(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
