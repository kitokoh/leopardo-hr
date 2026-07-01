<?php

namespace App\Modules\Absence\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejected_reason' => ['required', 'string', 'min:1', 'max:1000'],
        ];
    }
}
