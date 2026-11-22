<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Creation d'un cours de formation (#9732).
 */
final class StoreTrainingCourseRequest extends FormRequest
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
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'type' => 'required|in:internal,external,online,certification',
            'provider' => 'nullable|string|max:200',
            'duration_hours' => 'nullable|numeric|min:0',
            'max_participants' => 'nullable|integer|min:1',
            'cost_per_participant' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
        ];
    }
}
