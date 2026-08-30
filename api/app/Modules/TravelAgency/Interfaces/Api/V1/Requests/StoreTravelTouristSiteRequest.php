<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-909 (#6112) — Création/mise à jour d'un site touristique.
 * `city_id` référencé scoped tenant ; bornes strictes.
 */
class StoreTravelTouristSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelTouristSitePolicy::create()/update() tranchent
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user() instanceof Employee ? $this->user()->company_id : null;

        return [
            'name' => ['required', 'string', 'min:1', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'city_id' => [
                'nullable', 'integer',
                Rule::exists((new TravelCity)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'image_asset_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'in:active,disabled'],
        ];
    }
}
