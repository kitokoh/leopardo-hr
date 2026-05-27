<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Fleet;

use Illuminate\Foundation\Http\FormRequest;

class AssignVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|integer',
            'start_date' => 'required|date',
            'reason' => 'nullable|string|max:500',
        ];
    }
}
