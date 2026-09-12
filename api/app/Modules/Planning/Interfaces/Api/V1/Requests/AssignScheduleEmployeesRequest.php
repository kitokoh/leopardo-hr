<?php

declare(strict_types=1);

namespace App\Modules\Planning\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

class AssignScheduleEmployeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Employee && $user->isManager();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_ids' => ['required', 'array', 'min:1', 'max:500'],
            'employee_ids.*' => ['integer', 'distinct', 'min:1'],
        ];
    }
}
