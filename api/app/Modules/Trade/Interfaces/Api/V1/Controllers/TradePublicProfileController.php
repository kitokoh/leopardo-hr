<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Shared\Discovery\PublicProfileLocator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * P2-01 (#8802) — fiche publique d'une entreprise de commerce (BC-36 TRADE).
 *
 * `GET /api/v1/public/trade/companies/{slug}`
 *
 * **Pourquoi elle passe par `PublicProfileLocator` puis relit le tenant.**
 * `TradeProfileResolver::resolve()` répond à « ce slug est-il joignable, et par
 * quel tenant ? » — il ne renvoie que des données de routage, jamais de contenu.
 * Il serait tentant d'y ajouter le nom et la ville pour éviter cette seconde
 * lecture ; ce serait une erreur d'architecture : le contrat `PublicProfileResolver`
 * est partagé par les six verticales et sa doc interdit explicitement de
 * sérialiser son résultat vers le public. On résout donc le tenant, on bascule
 * dans son contexte, puis on lit le profil — et on **revalide** `is_public`,
 * parce qu'une course entre la résolution et la lecture est possible (le pro
 * peut dépublier entre les deux requêtes).
 *
 * `404` uniforme : slug inconnu, profil dépublié et société suspendue sont
 * indistinguables. `403` n'est jamais utilisé ici — il révélerait l'existence
 * du slug.
 */
class TradePublicProfileController extends Controller
{
    public function __construct(
        private readonly PublicProfileLocator $locator,
    ) {}

    public function show(string $slug): JsonResponse
    {
        $tenant = $this->locator->locate('trade_company', $slug);

        if ($tenant === null) {
            abort(404);
        }

        $companyId = $tenant['company_id'];

        // Lecture dans le schéma du tenant propriétaire. On ne fait pas confiance
        // au seul slug : le profil est aussi filtré sur `company_id`, ce qui
        // interdit toute lecture d'un profil d'un autre tenant même si un slug
        // venait à être dupliqué (l'index unique l'empêche, ceci est la seconde
        // barrière).
        $profile = $this->readProfile($companyId, $slug);

        if ($profile === null) {
            abort(404);
        }

        return response()->json([
            'data' => $profile,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readProfile(string $companyId, string $slug): ?array
    {
        $table = DB::getDriverName() === 'pgsql'
            ? 'shared_tenants.trade_company_profiles'
            : 'trade_company_profiles';

        $row = DB::table($table.' as p')
            ->join('companies as c', 'c.id', '=', 'p.company_id')
            ->where('p.public_slug', $slug)
            ->where('p.company_id', $companyId)
            ->where('p.is_public', true)
            ->whereNotNull('p.public_name')
            ->where('p.public_name', '!=', '')
            ->select([
                'p.public_slug as slug',
                'p.public_name as name',
                'p.public_description as description',
                'p.public_city as city',
                // M\u00eame r\u00e8gle que la d\u00e9couverte : le pays de la fiche est
                // celui de la soci\u00e9t\u00e9, pas `hq_country` (saisie libre).
                'c.country as country',
                'p.product_categories as product_categories',
                'p.default_currency as currency',
                'p.import_enabled as import_enabled',
                'p.export_enabled as export_enabled',
                'p.domestic_scope as domestic_scope',
            ])
            ->first();

        if ($row === null) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = (array) $row;

        $categories = $data['product_categories'] ?? null;

        if (is_string($categories)) {
            /** @var mixed $decoded */
            $decoded = json_decode($categories, true);
            $categories = is_array($decoded) ? $decoded : [];
        }

        return [
            'slug' => (string) $data['slug'],
            'name' => (string) $data['name'],
            'description' => $data['description'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
            // Catégories produits : publiées volontairement par le pro (elles
            // font partie du périmètre opt-in). Filtrées aux chaînes non vides.
            'product_categories' => is_array($categories)
                ? array_values(array_filter($categories, static fn ($c): bool => is_string($c) && $c !== ''))
                : [],
            'currency' => $data['currency'] ?? null,
            // Directions commerciales : information d'activité, pas de donnée
            // interne (aucun volume, aucun client, aucune marge).
            'import_enabled' => (bool) ($data['import_enabled'] ?? false),
            'export_enabled' => (bool) ($data['export_enabled'] ?? false),
            'domestic_scope' => (bool) ($data['domestic_scope'] ?? false),
        ];
    }
}
