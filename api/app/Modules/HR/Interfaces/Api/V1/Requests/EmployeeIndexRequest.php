<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres de `GET /v1/employees` (liste paginée).
 *
 * Extrait de `EmployeeController::index()` — la validation en ligne y violait
 * la décision « FormRequest partout » (#9699/#9706).
 */
class EmployeeIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy `viewAny` tranchée par le contrôleur
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:active,archived,suspended'],
            'role' => ['nullable', 'in:employee,manager,admin,super_admin'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'in:id,first_name,last_name,email,role,status'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ];
    }
}
