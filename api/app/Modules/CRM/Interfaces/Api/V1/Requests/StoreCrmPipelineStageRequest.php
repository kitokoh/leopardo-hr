<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ajout d'une étape à un pipeline CRM — Issue #5711 (CRM-V0-07).
 *
 * Une étape ne peut pas être gagnée ET perdue (même CHECK qu'en base) ;
 * `position` unique dans le pipeline (contrainte base) — le contrôle
 * applicatif évite un 500.
 */
class StoreCrmPipelineStageRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'position' => ['nullable', 'integer', 'min:0'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_won' => ['nullable', 'boolean'],
            'is_lost' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);

        $validator->after(function ($validator): void {
            if ($this->boolean('is_won') && $this->boolean('is_lost')) {
                $validator->errors()->add('is_lost', 'Une étape ne peut pas être gagnée et perdue à la fois.');
            }
        });
    }
}
