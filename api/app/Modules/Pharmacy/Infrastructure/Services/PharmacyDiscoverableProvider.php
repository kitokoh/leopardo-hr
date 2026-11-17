<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Pharmacy\Domain\Models\PharmacyPublicProfile;
use App\Shared\Contracts\Discovery\PublicDiscoverableProvider;

/**
 * Provider de découverte publique — officines de pharmacie (#9592).
 *
 * ## Pourquoi il manquait
 *
 * Restaurant, Hospitality, Retail, Showcase, Trade et VTC avaient déjà leur
 * provider tagué `public.discoverable`. Pharmacy n'en avait aucun : la
 * verticale existait côté tenant (BC PharmaManager, PHARMA-001) mais n'avait
 * **aucune sémantique de publication**. `GET /api/v1/public/discover?type=pharmacy`
 * ne pouvait donc rien renvoyer, et la vitrine `/pharmacies` restait
 * éternellement sur « bientôt disponible ».
 *
 * ## Sémantique de publication retenue
 *
 * Même doctrine que Trade (#8802) et Retail (#8773) :
 *
 *  - **opt-in explicite** (`pharmacy_public_profiles.enabled`, défaut `false`) ;
 *  - **fail-closed** : un profil incomplet (slug ou nom vide) n'est jamais
 *    publié, même si `enabled` est vrai ;
 *  - **périmètre public minimal** : nom, ville, description, services, garde.
 *    Jamais `company_id`, ni stock, ni lots, ni ordonnances, ni prix.
 *
 * ## Actions exposées
 *
 * `contact` uniquement. Il n'y a **pas** de `order` : la vente en ligne de
 * médicaments est réglementée et n'est pas exposée par cette verticale.
 * Déclarer `order` produirait exactement le bouton inopérant interdit par
 * #8761 — la garde `PublicDiscoverableProviderContractTest` le refuserait.
 */
final class PharmacyDiscoverableProvider implements PublicDiscoverableProvider
{
    public function type(): string
    {
        return 'pharmacy';
    }

    /**
     * @return list<string>
     */
    public function supportedActions(): array
    {
        return ['contact'];
    }

    /**
     * `contact` est servi par la passerelle elle-même : aucune destination
     * propre, donc `null` (et non une URL devinée).
     *
     * @return array<string, string|null>
     */
    public function supportedActionUrls(): array
    {
        return ['contact' => null];
    }

    public function search(array $filters): array
    {
        $term = $this->textFilter($filters['q'] ?? null);
        $city = $this->textFilter($filters['city'] ?? null);

        // Fail-closed à la requête : `enabled = true` est dans le WHERE, donc
        // une officine non opt-in ne peut pas sortir par erreur, même si un
        // futur refactor oubliait `isPubliclyVisible()`.
        $query = PharmacyPublicProfile::query()
            ->where('enabled', true)
            ->whereNotNull('public_slug')
            ->whereNotNull('public_name')
            ->where('public_slug', '!=', '')
            ->where('public_name', '!=', '');

        if ($city !== null) {
            $query->whereRaw('LOWER(public_city) = ?', [mb_strtolower($city)]);
        }

        if ($term !== null) {
            $like = '%'.mb_strtolower($term).'%';

            $query->where(function ($inner) use ($like): void {
                $inner->whereRaw('LOWER(public_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(public_description) LIKE ?', [$like]);
            });
        }

        $limit = isset($filters['limit']) && is_numeric($filters['limit'])
            ? max(1, min(500, (int) $filters['limit']))
            : 200;

        /** @var list<PharmacyPublicProfile> $profiles */
        $profiles = $query->orderBy('public_name')->limit($limit)->get()->all();

        $companies = $this->companiesById(
            array_values(array_unique(array_map(
                static fn (PharmacyPublicProfile $p): string => (string) $p->company_id,
                $profiles
            )))
        );

        $items = [];

        foreach ($profiles as $profile) {
            // Double barrière : le WHERE ci-dessus ET la règle du modèle.
            if (! $profile->isPubliclyVisible()) {
                continue;
            }

            $company = $companies[(string) $profile->company_id] ?? null;

            $items[] = $this->toPublicItem($profile, $company);
        }

        return $items;
    }

    /**
     * @param  list<string>  $companyIds
     * @return array<string, Company>
     */
    private function companiesById(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        /** @var array<string, Company> $companies */
        $companies = Company::query()
            ->whereIn('id', $companyIds)
            ->get()
            ->keyBy(static fn (Company $c): string => (string) $c->id)
            ->all();

        return $companies;
    }

    /**
     * DTO public STRICT.
     *
     * @return array<string, mixed>
     */
    private function toPublicItem(PharmacyPublicProfile $profile, ?Company $company): array
    {
        $slug = (string) $profile->public_slug;

        return [
            'type' => $this->type(),
            'slug' => $slug,
            'name' => (string) $profile->public_name,
            // Catégorie : la garde de nuit est l'information de service la plus
            // utile au public. `null` quand elle n'est pas déclarée — on
            // n'invente pas « fermée ».
            'category' => $profile->on_duty ? 'on_duty' : null,
            'city' => $profile->public_city,
            // Pays d'ENREGISTREMENT de la société, pas une saisie libre du
            // profil — même choix que Retail et Trade.
            'country' => $company?->country,
            'description' => $profile->public_description,
            'image_url' => null,
            // Aucune géolocalisation stockée : en inventer rendrait la
            // recherche `near` faussement pertinente.
            'latitude' => null,
            'longitude' => null,
            // Fiche publique servie par `front/web` sous `/pharmacies/{slug}`.
            'url' => '/pharmacies/'.$slug,
        ];
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
