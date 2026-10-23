<?php

declare(strict_types=1);

namespace App\Modules\Trade\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Models\TradeCompanyProfile;
use App\Shared\Contracts\Discovery\PublicDiscoverableProvider;

/**
 * P2-01 (#8802) — provider de découverte publique : entreprises de commerce
 * international / B2B (BC-36 TRADE).
 *
 * **Ce provider est le premier d'une verticale qui n'avait AUCUNE sémantique
 * de publication.** Restaurant, Hospitality, Retail, Showcase et VTC avaient
 * déjà un opt-in (une colonne `is_public`, une table de réglages, ou une
 * marketplace). Trade n'en avait aucun : `trade_company_profiles` décrit les
 * directions commerciales (`import_enabled` / `export_enabled`), les marchés
 * et les catégories produits, mais rien qui dise « cette entreprise accepte
 * d'être trouvée ». La sémantique retenue, appliquée ici et documentée dans la
 * migration, est :
 *
 *  - **opt-in explicite** (`is_public = false` par défaut) ;
 *  - **fail-closed** (un profil incomplet n'est jamais publié) ;
 *  - **périmètre public minimal** (nom commercial, ville, pays, description,
 *    catégories choisies) — jamais `notes`, `capabilities`, ni `company_id`.
 *
 * ⚠️ **Périmètre de lecture.** Ce provider s'exécute dans le contexte du
 * tenant courant (`BelongsToCompany` sur `TradeCompanyProfile`) : il alimente
 * l'annuaire pour le tenant actif. La recherche cross-tenant par slug public
 * passe par `TradePublicProfileController` (`GET /api/v1/public/trade/
 * companies/{slug}`), qui résout le tenant AVANT de lire. Ne pas confondre les
 * deux : ce provider ne fait aucune requête cross-tenant lui-même.
 */
final class TradeDiscoverableProvider implements PublicDiscoverableProvider
{
    public function type(): string
    {
        return 'trade_company';
    }

    /**
     * Trade n'expose **que** le contact.
     *
     * Il n'y a ni commande publique ni réservation dans BC-36 : `TradeOrder`
     * est un ordre commercial négocié entre professionnels (devis, incoterms,
     * douane), pas un panier grand public. Déclarer `order` ici produirait un
     * bouton inopérant — exactement le défaut corrigé par #8794 côté
     * Restaurant. La garde `PublicDiscoverableProviderContractTest` refuserait
     * d'ailleurs cette déclaration.
     *
     * @return list<string>
     */
    public function supportedActions(): array
    {
        return ['contact'];
    }

    /**
     * #8761 — Trade n'expose que le contact, servé par la passerelle : aucune
     * destination propre.
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

        // Fail-closed à la requête : `is_public = true` est dans le WHERE, donc
        // un profil non opt-in ne peut pas sortir par erreur, même si un futur
        // refactor oubliait `isPubliclyVisible()`.
        $query = TradeCompanyProfile::query()
            ->where('is_public', true)
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

        /** @var list<TradeCompanyProfile> $profiles */
        $profiles = $query->orderBy('public_name')->limit($limit)->get()->all();

        $companies = $this->companiesById(
            array_values(array_unique(array_map(
                static fn (TradeCompanyProfile $p): string => (string) $p->company_id,
                $profiles
            )))
        );

        $items = [];

        foreach ($profiles as $profile) {
            // Double barrière : le WHERE ci-dessus ET la règle du modèle. Les
            // deux doivent rester — la première protège la requête, la seconde
            // protège tout appelant qui construirait le DTO autrement.
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
    private function toPublicItem(TradeCompanyProfile $profile, ?Company $company): array
    {
        $slug = (string) $profile->public_slug;

        return [
            'type' => $this->type(),
            'slug' => $slug,
            'name' => (string) $profile->public_name,
            // Catégorie : la première catégorie produit explicitement publiée.
            // On n'invente pas de libellé (« Autre ») quand la liste est vide —
            // le champ reste nul, ce que le DTO autorise.
            'category' => $this->firstCategory($profile),
            'city' => $profile->public_city,
            // Pays = pays d'ENREGISTREMENT de la soci\u00e9t\u00e9, pas `hq_country`.
            // `hq_country` est une saisie libre du profil (si\u00e8ge op\u00e9rationnel,
            // souvent "FR" par d\u00e9faut) : la publier comme pays de la fiche
            // afficherait une nationalit\u00e9 que le pro n'a pas d\u00e9clar\u00e9e. C'est
            // le choix d\u00e9j\u00e0 retenu par `RetailDiscoverableProvider`
            // (`'country' => $company->country`), et la fiche publique doit dire
            // la m\u00eame chose que la carte de d\u00e9couverte.
            'country' => $company?->country,
            'description' => $profile->public_description,
            'image_url' => null,
            // Aucune géolocalisation : le profil ne stocke pas de coordonnées,
            // et en inventer rendrait la recherche `near` faussement pertinente.
            'latitude' => null,
            'longitude' => null,
            // ⚠️ Segment `/entreprises`, PAS `/commerce`. `/commerce` est un préfixe
            // **dashboard** (espace vendeur BC-17) déclaré dans `src/proxy.ts` : une
            // fiche sous `/commerce/{slug}` serait redirigée vers `/auth/login`
            // (307) pour tout visiteur public — lien mort. Le premier jet utilisait
            // `/commerce` et la garde de contrat ne l'a pas vu, car elle vérifiait
            // l'EXISTENCE du fichier, pas sa JOIGNABILITÉ publique.
            // Fiche publique servie par `front/web` sous `/entreprises/{slug}`.
            // Le segment est déclaré dans le mapping de la garde de contrat
            // (#8794) : ajouter une verticale sans y toucher fait échouer le test.
            'url' => '/entreprises/'.$slug,
        ];
    }

    private function firstCategory(TradeCompanyProfile $profile): ?string
    {
        $categories = $profile->product_categories;

        if (! is_array($categories) || $categories === []) {
            return null;
        }

        // `product_categories` est castée en `array` mais PHPStan sait que la
        // valeur est une `non-empty-list<string>` (PHPDoc) : `[0]` existe donc
        // toujours et `is_string()` est toujours vrai. On garde uniquement le
        // test de vacuité, qui est la vraie règle métier.
        $first = $categories[0];

        return $first !== '' ? $first : null;
    }

    private function textFilter(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
