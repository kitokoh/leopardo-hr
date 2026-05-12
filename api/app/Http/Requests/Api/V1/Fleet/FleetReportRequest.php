<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Fleet;

use Illuminate\Foundation\Http\FormRequest;

class FleetReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    public function rules(): array
    {
        return [
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ];
    }
}
