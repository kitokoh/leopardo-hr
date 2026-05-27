<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformCompanyRequestIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['pending', 'approved', 'rejected'];
    }
}
