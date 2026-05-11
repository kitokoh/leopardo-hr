<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobPostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasManagerRole('principal', 'rh') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'department_id' => 'nullable|integer|exists:departments,id',
            'position_id' => 'nullable|integer|exists:positions,id',
            'location' => 'nullable|string|max:200',
            'remote_policy' => 'nullable|in:onsite,hybrid,remote',
            'contract_type' => 'nullable|in:cdi,cdd,stage,freelance',
            'salary_range_min' => 'nullable|numeric|min:0',
            'salary_range_max' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'skills_required' => 'nullable|array',
            'closes_at' => 'nullable|date',
        ];
    }
}
