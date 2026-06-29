<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
