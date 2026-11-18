<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Trade\Domain\Models\TradeCompanyProfile;
use App\Modules\Trade\Interfaces\Api\V1\Requests\PublishTradeCompanyProfileRequest;
use App\Modules\Trade\Interfaces\Api\V1\Requests\UpdateTradeCompanyProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Profil commercial Trade — vitrine `/entreprises` (#9617).
 *
 * ## Le trou que ce contrôleur ferme
 *
 * `trade_company_profiles` portait `is_public`, `public_slug`, `public_name`
 * (migration #8802), et `TradeDiscoverableProvider` + `TradePublicProfileController`
 * les lisaient. Mais `routes/modules/trade.php` n'exposait **aucune** route de
 * profil : uniquement RFQ, quotes, cost-estimates, meta.
 *
 * Conséquence : `is_public` restait `false` par défaut et **aucun moyen ne
 * permettait de le passer à `true`**. `/entreprises` et `/entreprises/{slug}`
 * ne pouvaient jamais rien afficher, et le CTA « Devenir tenant » de la vitrine
 * B2B menait à un espace où l'on ne pouvait pas se publier.
 *
 * ## Deux routes, deux actes
 *
 * - `PUT /trade/profile` — décrire son activité (directions, marchés, devise).
 * - `PUT /trade/profile/publication` — choisir d'apparaître publiquement.
 *
 * Les séparer évite qu'un enregistrement de champ commercial publie
 * l'entreprise par inadvertance.
 *
 * ## Slug unique GLOBAL
 *
 * Contrairement à la pharmacie (unicité par tenant), le slug Trade est unique
 * **cross-tenant** : `TradeProfileResolver` résout un slug vers un tenant sans
 * connaître le tenant appelant. Deux entreprises ne peuvent donc pas partager
 * un slug, et la vérification porte sur toute la table.
 */
class TradeCompanyProfileController extends Controller
{
    /**
     * GET /trade/profile — profil courant (`null` si jamais créé).
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', TradeCompanyProfile::class);

        /** @var TradeCompanyProfile|null $profile */
        $profile = TradeCompanyProfile::query()
            ->where('company_id', $actor->company_id)
            ->first();

        return response()->json([
            'data' => $profile instanceof TradeCompanyProfile ? $this->payload($profile) : null,
        ]);
    }

    /**
     * PUT /trade/profile — create-or-update du profil commercial.
     */
    public function update(UpdateTradeCompanyProfileRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        /** @var TradeCompanyProfile|null $profile */
        $profile = TradeCompanyProfile::query()
            ->where('company_id', $actor->company_id)
            ->first();

        $importEnabled = $request->boolean('import_enabled');
        $exportEnabled = $request->boolean('export_enabled');
        $domestic = $request->boolean('domestic_scope');

        // Un profil sans aucune direction n'est pas une entreprise commerciale.
        // On refuse plutôt que d'enregistrer un profil qui n'apparaîtrait dans
        // aucun filtre de la vitrine.
        if (! $importEnabled && ! $exportEnabled && ! $domestic) {
            throw ValidationException::withMessages([
                'import_enabled' => 'Déclarez au moins une direction : import, export ou marché domestique.',
            ]);
        }

        $attributes = [
            'import_enabled' => $importEnabled,
            'export_enabled' => $exportEnabled,
            'domestic_scope' => $domestic,
            'hq_country' => $this->normalizeUpper($request->input('hq_country')),
            'markets_served' => $this->normalizeList($request->input('markets_served')),
            'product_categories' => $this->normalizeList($request->input('product_categories')),
            'default_currency' => $this->normalizeUpper($request->input('default_currency')),
            'default_incoterm' => $this->normalizeUpper($request->input('default_incoterm')),
            'notes' => $this->normalizeText($request->input('notes')),
        ];

        if ($profile instanceof TradeCompanyProfile) {
            $this->authorize('update', $profile);
            $profile->update($attributes);
        } else {
            $this->authorize('create', TradeCompanyProfile::class);

            $profile = TradeCompanyProfile::query()->create([
                'company_id' => $actor->company_id,
                'hq_country' => $attributes['hq_country'] ?? 'DZ',
                'default_currency' => $attributes['default_currency'] ?? 'DZD',
                ...$attributes,
            ]);
        }

        return response()->json(['data' => $this->payload($profile->refresh())]);
    }

    /**
     * PUT /trade/profile/publication — opt-in explicite dans l'annuaire public.
     */
    public function publish(PublishTradeCompanyProfileRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        /** @var TradeCompanyProfile|null $profile */
        $profile = TradeCompanyProfile::query()
            ->where('company_id', $actor->company_id)
            ->first();

        if (! $profile instanceof TradeCompanyProfile) {
            throw ValidationException::withMessages([
                'is_public' => 'Complétez d’abord votre profil commercial avant de le publier.',
            ]);
        }

        $this->authorize('update', $profile);

        $publish = $request->boolean('is_public');
        $slug = $this->normalizeSlug($request->input('public_slug'));
        $name = $this->normalizeText($request->input('public_name'));

        if ($publish && ($slug === null || $name === null)) {
            throw ValidationException::withMessages([
                'public_slug' => 'Un slug et un nom commercial publics sont requis pour publier l’entreprise.',
            ]);
        }

        if ($slug !== null) {
            $this->assertSlugAvailable($slug, $profile);
        }

        $wasPublic = $profile->is_public;

        $profile->update([
            'is_public' => $publish,
            'public_slug' => $slug,
            'public_name' => $name,
            'public_description' => $this->normalizeText($request->input('public_description')),
            'public_city' => $this->normalizeText($request->input('public_city')),
            // Date de PREMIÈRE publication : on ne l'écrase pas à chaque
            // republication, sinon la vitrine perdrait l'ancienneté du profil.
            'public_published_at' => $publish && ! $wasPublic
                ? now()
                : $profile->public_published_at,
        ]);

        return response()->json(['data' => $this->payload($profile->refresh())]);
    }

    /**
     * Le slug Trade est unique GLOBAL (cross-tenant) : `TradeProfileResolver`
     * résout un slug vers un tenant sans connaître l'appelant.
     */
    private function assertSlugAvailable(string $slug, TradeCompanyProfile $current): void
    {
        $taken = TradeCompanyProfile::query()
            ->where('public_slug', $slug)
            ->where('id', '!=', $current->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'public_slug' => 'Ce slug public est déjà utilisé par une autre entreprise.',
            ]);
        }
    }

    private function normalizeSlug(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = mb_strtolower(trim($value));

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeUpper(mixed $value): ?string
    {
        $text = $this->normalizeText($value);

        return $text === null ? null : mb_strtoupper($text);
    }

    /**
     * @return list<string>|null
     */
    private function normalizeList(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $items = [];

        foreach ($value as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            $trimmed = trim($entry);

            if ($trimmed !== '') {
                $items[] = $trimmed;
            }
        }

        return $items === [] ? null : array_values(array_unique($items));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TradeCompanyProfile $profile): array
    {
        return [
            'import_enabled' => $profile->import_enabled,
            'export_enabled' => $profile->export_enabled,
            'domestic_scope' => $profile->domestic_scope,
            'hq_country' => $profile->hq_country,
            'markets_served' => $profile->markets_served ?? [],
            'product_categories' => $profile->product_categories ?? [],
            'default_currency' => $profile->default_currency,
            'default_incoterm' => $profile->default_incoterm,
            'notes' => $profile->notes,
            'onboarding_completed_at' => $profile->onboarding_completed_at?->toIso8601String(),
            'is_public' => $profile->is_public,
            'public_slug' => $profile->public_slug,
            'public_name' => $profile->public_name,
            'public_description' => $profile->public_description,
            'public_city' => $profile->public_city,
            'public_published_at' => $profile->public_published_at?->toIso8601String(),
            // L'URL publique est calculée ici : l'espace professionnel affiche
            // « votre entreprise est visible à cette adresse » sans reconstruire
            // le chemin côté client.
            'public_url' => $profile->isPubliclyVisible()
                ? '/entreprises/'.$profile->public_slug
                : null,
        ];
    }
}
