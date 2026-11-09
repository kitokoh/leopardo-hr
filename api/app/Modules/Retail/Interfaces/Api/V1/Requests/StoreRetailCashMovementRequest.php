<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use App\Modules\Retail\Domain\Enums\RetailCashMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mouvement de tiroir-caisse d'une session POS Retail (BC-17 RETAIL, #9212).
 *
 * `type` ∈ {`in`,`out`,`drop`} ; `amount_minor` strictement positif ;
 * `reason` OBLIGATOIRE (un mouvement d'especes sans motif est un trou
 * d'audit).
 */
class StoreRetailCashMovementRequest extends FormRequest
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
        return [
            'type' => ['required', 'string', Rule::in(array_map(
                static fn (RetailCashMovementType $type): string => $type->value,
                RetailCashMovementType::cases(),
            ))],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
