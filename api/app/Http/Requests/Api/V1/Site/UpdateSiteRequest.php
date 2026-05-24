<?php

namespace App\Http\Requests\Api\V1\Site;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isManager();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'gps_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'gps_radius_m' => ['nullable', 'integer', 'min:10', 'max:5000'],
        ];
    }
}
