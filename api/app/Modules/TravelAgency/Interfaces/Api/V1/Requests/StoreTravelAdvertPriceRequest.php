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
 * TRAVEL-906 (#6109) — Création d'une grille tarifaire.
 * Références scoped tenant ; devise cohérente avec celle du tenant ;
 * montants > 0 (unités mineures).
 */
class StoreTravelAdvertPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // rôles gestion tranchés au controller
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
            'price_per_image_minor' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'price_per_character_minor' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $user = $this->user();

            if (! $user instanceof Employee) {
                return;
            }

            $tenantCurrency = $user->company?->currency;

            if (is_string($tenantCurrency) && $tenantCurrency !== ''
                && strtoupper((string) $this->input('currency')) !== strtoupper($tenantCurrency)) {
                $validator->errors()->add('currency', 'La devise doit être cohérente avec celle du tenant ('.$tenantCurrency.').');
            }
        });
    }
}
