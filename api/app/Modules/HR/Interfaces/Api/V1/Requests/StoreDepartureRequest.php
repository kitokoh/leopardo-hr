<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Requests;

use App\Modules\HR\Domain\Models\EmployeeDeparture;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'departure_type' => ['required', Rule::in(EmployeeDeparture::TYPES)],
            'reason' => ['nullable', 'string', 'max:500'],
            'last_work_day' => ['required', 'date', 'after_or_equal:today'],
            'notice_served' => ['sometimes', 'boolean'],
            'notice_days_served' => ['nullable', 'integer', 'min:0', 'max:365'],
            'departed_at' => ['nullable', 'date'],
        ];
    }
}
