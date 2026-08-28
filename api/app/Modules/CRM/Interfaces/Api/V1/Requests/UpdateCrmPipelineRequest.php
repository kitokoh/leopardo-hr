<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un pipeline CRM — Issue #5711 (CRM-V0-07).
 *
 * Champs inconnus refusés ; nom unique par entreprise (hors pipeline
 * courant).
 */
class UpdateCrmPipelineRequest extends FormRequest
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
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('crm_pipelines', 'name')
                    ->where('company_id', $this->user()?->company_id)
                    ->ignore((int) $this->route('pipeline')->id),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
