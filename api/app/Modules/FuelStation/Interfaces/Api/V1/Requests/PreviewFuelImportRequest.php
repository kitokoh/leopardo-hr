<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Modules\FuelStation\Domain\Models\FuelImport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Preview d'un import CSV FuelStation (FUEL-018, issue #5812).
 *
 * Fichier ≤ 2 Mo ; entité dans l'allowlist (products, pumps, tanks,
 * shifts, readings) ; aucune écriture pendant le preview.
 */
class PreviewFuelImportRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'entity_type' => ['required', Rule::in(FuelImport::ENTITIES)],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }
}
