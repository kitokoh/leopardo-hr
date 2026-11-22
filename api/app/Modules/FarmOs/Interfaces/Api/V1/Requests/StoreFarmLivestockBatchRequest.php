<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Requests;

use App\Modules\FarmOs\Domain\Enums\FarmAnimalSex;
use App\Modules\FarmOs\Domain\Enums\FarmSpecies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creation d'un lot d'elevage (#9732).
 */
final class StoreFarmLivestockBatchRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'reference' => ['nullable', 'string', 'max:60'],
            'species' => ['required', 'string', Rule::in(FarmSpecies::values())],
            'breed' => ['nullable', 'string', 'max:80'],
            'sex' => ['nullable', 'string', Rule::in(FarmAnimalSex::values())],
            'farm_site_id' => ['nullable', 'integer'],
            'location' => ['nullable', 'string', 'max:160'],
            'started_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_batch_id' => ['nullable', 'string', 'max:80'],
        ];
    }
}
