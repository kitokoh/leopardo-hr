<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
