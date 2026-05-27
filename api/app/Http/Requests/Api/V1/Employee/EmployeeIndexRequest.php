<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Employee;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
