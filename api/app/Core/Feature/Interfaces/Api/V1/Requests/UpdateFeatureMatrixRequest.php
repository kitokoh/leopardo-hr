<?php

declare(strict_types=1);

namespace App\Core\Feature\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFeatureMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'feature_key' => 'required|string|max:50',
            'plan' => 'required|in:trial,starter,business,enterprise',
            'enabled' => 'required|boolean',
            'limit_value' => 'nullable|integer|min:0',
        ];
    }
}
