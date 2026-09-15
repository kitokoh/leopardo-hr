<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPosition;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPrice;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertType;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            // #7420 : la devise est OPTIONNELLE — omise, elle prend celle du
            // tenant (défaut appliqué au controller), ce qui est le cas nominal
            // d'une agence monolocale. Fournie, elle doit être cohérente avec
            // celle du tenant (422 sinon).
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $user = $this->user();

            if (! $user instanceof Employee) {
                return;
            }

            $requested = $this->input('currency');

            if ($requested !== null && $requested !== '') {
                $tenantCurrency = $user->company?->currency;

                if (is_string($tenantCurrency) && $tenantCurrency !== ''
                    && strtoupper((string) $requested) !== strtoupper($tenantCurrency)) {
                    $validator->errors()->add('currency', 'La devise doit être cohérente avec celle du tenant ('.$tenantCurrency.').');
                }
            }

            // #7420 — unicité (tenant, type, position) : c'est la contrainte du
            // schéma (`travel_advert_prices_company_type_pos_unique`) ; sans
            // cette garde applicative, un doublon remontait en 500 QueryException
            // au lieu du 422 attendu par le contrat.
            if ($validator->errors()->isEmpty()) {
                $this->assertSingleGridPerTypeAndPosition($validator, $user);
            }
        });
    }

    private function assertSingleGridPerTypeAndPosition(Validator $validator, Employee $actor): void
    {
        $currentId = $this->route('travelAdvertPrice');

        $exists = TravelAdvertPrice::query()
            ->where('company_id', $actor->company_id)
            ->where('advert_type_id', (int) $this->input('advert_type_id'))
            ->where('advert_position_id', (int) $this->input('advert_position_id'))
            ->when($currentId !== null, fn ($query) => $query->whereKeyNot($currentId))
            ->exists();

        if ($exists) {
            $validator->errors()->add(
                'advert_type_id',
                'Une grille tarifaire existe déjà pour ce type et cette position.'
            );
        }
    }
}
