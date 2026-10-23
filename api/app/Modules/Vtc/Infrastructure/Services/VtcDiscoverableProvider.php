<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Shared\Contracts\Discovery\PublicDiscoverableProvider;
use Illuminate\Database\Eloquent\Collection;

/**
 * C-1 (#8773) — Provider de découverte publique : opérateurs VTC/taxi opt-in.
 *
 * Réutilise EXACTEMENT l'annuaire public déjà opérationnel de la marketplace
 * VTC (#8423, M-11) : `VtcMarketplaceService::eligibleOperators()` borne aux
 * sociétés ayant un opt-in `vtc_marketplace_operators.status = active`, dont
 * la feature `vtc` est active et qui ne sont ni `suspended` ni `expired`
 * (fail-closed). Aucune nouvelle table, aucune requête SQL ré-implémentée :
 * la source de vérité « cet opérateur est-il publiable ? » reste le service
 * métier de la verticale.
 *
 * DTO public STRICT : identité d'annuaire uniquement (slug public, nom
 * commercial, ville/pays d'ancrage). Jamais de `company_id`, de grille
 * tarifaire (`fare_profile_id`, coefficient routier), de positions ou de
 * données de flotte — le devis public passe par son propre endpoint
 * (`POST /api/v1/public/vtc/marketplace/estimate`).
 */
final class VtcDiscoverableProvider implements PublicDiscoverableProvider
{
    public function __construct(private readonly VtcMarketplaceService $marketplace) {}

    public function type(): string
    {
        return 'vtc_operator';
    }

    /**
     * #8773 — un opérateur publié accepte une demande de contact via la
     * passerelle commune. NI `booking` NI `order` : la course se demande par
     * l'endpoint public dédié (`POST /public/vtc/marketplace/rides`), pas par
     * un bouton générique de réservation qui n'aurait aucun service derrière.
     *
     * @return list<string>
     */
    public function supportedActions(): array
    {
        return ['contact'];
    }

    /**
     * #8761 — VTC n'expose que le contact public. La course (`POST /public/vtc/
     * marketplace/rides`) exige une origine ET une destination : elle ne se
     * déclenche pas depuis un CTA de fiche, donc aucune destination.
     *
     * @return array<string, string|null>
     */
    public function supportedActionUrls(): array
    {
        return ['contact' => null];
    }

    public function search(array $filters): array
    {
        /** @var Collection<int, Company> $operators */
        $operators = $this->marketplace->eligibleOperators();

        $term = $this->textFilter($filters['q'] ?? null);
        $city = $this->textFilter($filters['city'] ?? null);

        $items = [];
        $names = [];

        foreach ($operators as $company) {
            if ($term !== null && ! $this->matches($company->name, $term)) {
                continue;
            }

            if ($city !== null && ! $this->equalsIgnoreCase($company->city, $city)) {
                continue;
            }

            $items[] = $this->toPublicItem($company);
            $names[] = $company->name;
        }

        // Tri alphabétique stable sur le nom public, calculé sur les objets
        // typés (jamais sur le tableau DTO, dont les valeurs sont `mixed`).
        array_multisort($names, SORT_STRING | SORT_FLAG_CASE, $items);

        $limit = isset($filters['limit']) && is_numeric($filters['limit'])
            ? max(1, min(500, (int) $filters['limit']))
            : 200;

        return array_slice($items, 0, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    private function toPublicItem(Company $company): array
    {
        $slug = $company->slug;

        return [
            'type' => $this->type(),
            'slug' => $slug,
            'name' => $company->name,
            // Pas de catégorie métier propre à l'opérateur : le type canonique
            // porte déjà le métier (`vtc_operator`).
            'category' => null,
            'city' => $company->city,
            'country' => $company->country,
            'description' => null,
            'image_url' => null,
            // Pas de géolocalisation d'opérateur : la proximité VTC est celle
            // des chauffeurs disponibles (dispatch interne), jamais exposée
            // dans un annuaire public.
            'latitude' => null,
            'longitude' => null,
            // P1-02 — la fiche publique de l'opérateur est servie par
            // `front/web` (`/operateurs/{slug}`), qui consomme
            // `GET /public/vtc/marketplace/operators/{slug}` (même DTO et même
            // périmètre fail-closed que l'annuaire).
            'url' => '/operateurs/'.$slug,
        ];
    }

    private function matches(string $haystack, string $needle): bool
    {
        if ($haystack === '') {
            return false;
        }

        return mb_stripos($haystack, $needle) !== false;
    }

    private function equalsIgnoreCase(string $haystack, string $needle): bool
    {
        if ($haystack === '') {
            return false;
        }

        return mb_strtolower($haystack) === mb_strtolower($needle);
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
