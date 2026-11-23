<?php

declare(strict_types=1);

namespace App\Modules\Planning\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paramètre commun aux endpoints de planning (`week_start`).
 *
 * Extrait de `PlanningController::index()` et `weeklyOptimization()` — la
 * validation en ligne y violait la décision « FormRequest partout »
 * (#9699/#9706).
 */
class PlanningWeekRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission tranchée par la route
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'week_start' => ['sometimes', 'date'],
        ];
    }
}
