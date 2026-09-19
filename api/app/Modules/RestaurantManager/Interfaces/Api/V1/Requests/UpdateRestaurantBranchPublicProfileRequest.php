<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantEstablishmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-901 (#7746) — Validation stricte du profil public d'une succursale.
 *
 * `public_slug` est unique GLOBAL (cross-tenant, jamais scopé company_id :
 * c'est l'identifiant de l'annuaire public) et déjà slugifié côté client ;
 * omis ou null avec `is_public=true`, il est généré côté serveur
 * (RestaurantBranchPublicProfileController). L'autorisation est tranchée par
 * `RestaurantBranchPolicy::update()`.
 */
class UpdateRestaurantBranchPublicProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantBranchPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $branchId = $this->route('restaurantBranch');

        return [
            'is_public' => ['sometimes', 'boolean'],
            'public_slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:160',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                // Unicité GLOBALE : pas de filtre company_id (annuaire public).
                Rule::unique('restaurant_branches', 'public_slug')->ignore($branchId),
            ],
            'establishment_type' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(RestaurantEstablishmentType::values()),
            ],
            'cuisine_types' => ['sometimes', 'nullable', 'array', 'max:10'],
            'cuisine_types.*' => ['string', 'max:40'],
            'public_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'cover_image_url' => ['sometimes', 'nullable', 'string', 'url', 'max:500'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
