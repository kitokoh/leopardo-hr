<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ouverture d'une session de caisse POS Retail (BC-17 RETAIL, #7674).
 *
 * L'emplacement doit exister DANS le tenant (Rule::exists scoped company_id
 * — pas de fuite cross-tenant). Fonds d'ouverture en minor units (entier).
 * L'autorisation est tranchee par RetailPosSessionPolicy::create().
 */
class StoreRetailPosSessionRequest extends FormRequest
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
        /** @var Employee $actor */
        $actor = $this->user();

        return [
            'location_id' => [
                'required',
                'integer',
                Rule::exists('retail_locations', 'id')
                    ->where('company_id', (string) $actor->company_id),
            ],
            'opening_cash_minor' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
        ];
    }
}
