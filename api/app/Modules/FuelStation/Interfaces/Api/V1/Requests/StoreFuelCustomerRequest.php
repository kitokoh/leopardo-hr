<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Upsert d'un client fidélité (FUEL-016, #5810) — idempotent par
 * `external_id` (synchronisation POS/ERP). phone/email sont chiffrés au
 * stockage ; `marketing_consent` est un opt-in RGPD explicite.
 */
class StoreFuelCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee|null $actor */
        $actor = $this->user();

        return [
            'station_id' => [
                'nullable',
                'integer',
                Rule::exists('fuel_stations', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'external_id' => ['required', 'string', 'max:120'],
            'full_name' => ['required', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:191'],
            'marketing_consent' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
