<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Requests;

use App\Modules\FarmOs\Domain\Enums\FarmAnimalSex;
use App\Modules\FarmOs\Domain\Enums\FarmSpecies;
use App\Modules\FarmOs\Domain\Models\FarmAnimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Fiche individuelle d'un animal (#9732).
 */
final class StoreFarmAnimalRequest extends FormRequest
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
            'tag_number' => ['required', 'string', 'max:80'],
            'name' => ['nullable', 'string', 'max:120'],
            'species' => ['required', 'string', Rule::in(FarmSpecies::values())],
            'breed' => ['nullable', 'string', 'max:80'],
            'sex' => ['nullable', 'string', Rule::in(FarmAnimalSex::values())],
            'birth_date' => ['nullable', 'date'],
            'origin' => ['nullable', 'string', Rule::in(FarmAnimal::ORIGINS)],
            'farm_livestock_batch_id' => ['nullable', 'integer'],
            'farm_site_id' => ['nullable', 'integer'],
            'location' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_animal_id' => ['nullable', 'string', 'max:80'],
        ];
    }
}
