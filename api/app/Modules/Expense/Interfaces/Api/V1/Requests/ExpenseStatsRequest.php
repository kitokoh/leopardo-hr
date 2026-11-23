<?php

declare(strict_types=1);

namespace App\Modules\Expense\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres de `GET /v1/expense/stats` (KPIs Finance).
 *
 * Extrait de `ExpenseStatsController::index()` — la validation en ligne y
 * violait la décision « FormRequest partout » (#9699/#9706).
 */
class ExpenseStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permissions `expense.reports`/`expense.manage` tranchées par le contrôleur
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }
}
