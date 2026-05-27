<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Employee;

use Illuminate\Foundation\Http\FormRequest;

class ImportEmployeeImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ];
    }
}
