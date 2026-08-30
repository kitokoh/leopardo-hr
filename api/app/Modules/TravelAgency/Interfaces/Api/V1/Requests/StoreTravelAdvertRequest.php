<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPosition;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertType;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-907 (#6110) — Soumission d'une annonce.
 * Le prix est calculé serveur (jamais accepté du client).
 */
class StoreTravelAdvertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelAdvertPolicy::create() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user() instanceof Employee ? $this->user()->company_id : null;

        return [
            'advert_type_id' => [
                'required', 'integer',
                Rule::exists((new TravelAdvertType)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'advert_position_id' => [
                'required', 'integer',
                Rule::exists((new TravelAdvertPosition)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'title' => ['required', 'string', 'min:1', 'max:160'],
            'content' => ['required', 'string', 'min:1', 'max:2000'],
            'image_asset_id' => ['nullable', 'integer'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}
