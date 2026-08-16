<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionRequest extends FormRequest
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
            'department_id' => ['nullable', 'integer', 'min:1', Rule::exists('departments', 'id')->where(fn ($query) => $query->where('company_id', $this->companyId()))],
        ];
    }
    /** Compagnie du user courant (pattern #3065/#3428 — scope compagnie sur les FK). */
    private function companyId(): ?string
    {
        return $this->user()?->company_id
            ?? (app()->bound('current_company') ? currentCompany()->id : null);
    }
}
