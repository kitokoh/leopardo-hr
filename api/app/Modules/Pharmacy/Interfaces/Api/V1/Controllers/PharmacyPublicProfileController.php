<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Pharmacy\Domain\Models\PharmacyPublicProfile;
use App\Modules\Pharmacy\Interfaces\Api\V1\Requests\UpdatePharmacyPublicProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Profil public d'officine — vitrine `/pharmacies` (#9616).
 *
 * ## Le trou que ce contrôleur ferme
 *
 * `pharmacy_public_profiles` existait (#9592), `PharmacyDiscoverableProvider`
 * la lisait, `/pharmacies` l'affichait — mais **aucune route n'écrivait cette
 * table**. `enabled` restait donc à `false` pour tout le monde, pour toujours :
 * la vitrine affichait « bientôt disponible » en permanence et le parcours
 * « le pharmacien publie → le public trouve » était cassé au milieu.
 *
 * ## Sémantique de publication
 *
 * Même doctrine que Retail (#7807) et Trade (#8802) :
 *
 *  - **opt-in explicite** (`enabled`, défaut `false`) ;
 *  - **fail-closed** : activer sans slug ou sans nom est refusé en 422, plutôt
 *    que d'enregistrer un profil que `isPubliclyVisible()` refuserait ensuite
 *    de publier (l'utilisateur croirait avoir publié) ;
 *  - **une ligne par tenant** (`company_id` UNIQUE), create-or-update ;
 *  - `version` incrémentée à chaque écriture (verrou optimiste, patron Retail).
 *
 * ## Ce que ce contrôleur n'expose pas
 *
 * Ni stock, ni lots, ni ordonnances, ni prix. La vitrine présente l'officine ;
 * elle ne vend pas de médicament — la vente en ligne est réglementée et n'est
 * pas exposée par cette verticale.
 */
class PharmacyPublicProfileController extends Controller
{
    /**
     * GET /pharmacy/public-profile — profil courant (`null` si jamais configuré).
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', PharmacyPublicProfile::class);

        /** @var PharmacyPublicProfile|null $profile */
        $profile = PharmacyPublicProfile::query()
            ->where('company_id', $actor->company_id)
            ->first();

        return response()->json([
            'data' => $profile instanceof PharmacyPublicProfile ? $this->payload($profile) : null,
        ]);
    }

    /**
     * PUT /pharmacy/public-profile — create-or-update (manager uniquement).
     */
    public function update(UpdatePharmacyPublicProfileRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        /** @var PharmacyPublicProfile|null $profile */
        $profile = PharmacyPublicProfile::query()
            ->where('company_id', $actor->company_id)
            ->first();

        $enabled = $request->boolean('enabled');
        $slug = $this->normalizeSlug($request->input('public_slug'));
        $name = $this->normalizeText($request->input('public_name'));

        // Fail-closed explicite : on refuse AVANT d'écrire. Enregistrer un
        // profil activé mais incomplet donnerait au pharmacien l'illusion
        // d'avoir publié, alors que le provider le filtrerait.
        if ($enabled && ($slug === null || $name === null)) {
            throw ValidationException::withMessages([
                'public_slug' => 'Un slug et un nom publics sont requis pour publier l’officine.',
            ]);
        }

        if ($slug !== null) {
            $this->assertSlugAvailable($slug, $profile);
        }

        $attributes = [
            'enabled' => $enabled,
            'public_slug' => $slug,
            'public_name' => $name,
            'public_city' => $this->normalizeText($request->input('public_city')),
            'public_description' => $this->normalizeText($request->input('public_description')),
            'public_phone' => $this->normalizeText($request->input('public_phone')),
            'on_duty' => $request->boolean('on_duty'),
            'services' => $this->normalizeServices($request->input('services')),
        ];

        if ($profile instanceof PharmacyPublicProfile) {
            $this->authorize('update', $profile);

            $profile->update([...$attributes, 'version' => $profile->version + 1]);
        } else {
            $this->authorize('create', PharmacyPublicProfile::class);

            $profile = PharmacyPublicProfile::query()->create([
                'company_id' => $actor->company_id,
                ...$attributes,
                'version' => 1,
            ]);
        }

        return response()->json(['data' => $this->payload($profile->refresh())]);
    }

    /**
     * Un slug public est unique par tenant (index `company_id` + slug).
     *
     * On vérifie ici pour renvoyer un 422 lisible plutôt qu'une violation de
     * contrainte SQL. La contrainte reste la seconde barrière.
     */
    private function assertSlugAvailable(string $slug, ?PharmacyPublicProfile $current): void
    {
        $query = PharmacyPublicProfile::query()->where('public_slug', $slug);

        if ($current instanceof PharmacyPublicProfile) {
            $query->where('id', '!=', $current->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'public_slug' => 'Ce slug public est déjà utilisé par une autre officine.',
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

    /**
     * @return list<string>|null
     */
    private function normalizeServices(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $services = [];

        foreach ($value as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            $trimmed = trim($entry);

            if ($trimmed !== '') {
                $services[] = $trimmed;
            }
        }

        return $services === [] ? null : array_values(array_unique($services));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PharmacyPublicProfile $profile): array
    {
        return [
            'enabled' => $profile->enabled,
            'public_slug' => $profile->public_slug,
            'public_name' => $profile->public_name,
            'public_city' => $profile->public_city,
            'public_description' => $profile->public_description,
            'public_phone' => $profile->public_phone,
            'on_duty' => $profile->on_duty,
            'services' => $profile->services ?? [],
            'version' => $profile->version,
            // L'URL publique est calculée ici pour que l'espace professionnel
            // puisse afficher « votre officine est visible à cette adresse »
            // sans reconstruire le chemin côté client.
            'public_url' => $profile->isPubliclyVisible()
                ? '/pharmacies/'.$profile->public_slug
                : null,
            'updated_at' => $profile->updated_at?->toIso8601String(),
        ];
    }
}
