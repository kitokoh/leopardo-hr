<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Modules\FuelStation\Domain\Models\FuelImport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Import CSV FuelStation (FUEL-018, #5812) — multipart.
 *
 * `dry_run` : preview sans écriture. Limites : fichier ≤ 2 Mo, CSV ≤ 5 000
 * lignes de données (vérifiées au niveau service).
 */
class StoreFuelImportRequest extends FormRequest
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
            'import_type' => ['required', Rule::in(FuelImport::TYPES)],
            'file' => ['required', 'file', 'max:2048', 'mimes:csv,txt'],
            'dry_run' => ['nullable', 'boolean'],
        ];
    }
}
