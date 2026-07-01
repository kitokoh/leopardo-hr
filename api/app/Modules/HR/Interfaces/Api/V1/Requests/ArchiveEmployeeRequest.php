<?php

namespace App\Modules\HR\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArchiveEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
