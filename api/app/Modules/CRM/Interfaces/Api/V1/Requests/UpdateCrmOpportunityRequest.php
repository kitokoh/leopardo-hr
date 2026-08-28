<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une opportunité CRM — Issue #5711 (CRM-V0-07).
 *
 * `won_at` / `lost_at` / `created_by` sont dérivés côté serveur (transition
 * d'étape gagnée/perdue) ; jamais acceptés depuis le client. Champs inconnus
 * refusés.
 */
class UpdateCrmOpportunityRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:150'],
            'pipeline_id' => [
                'sometimes',
                'integer',
                'min:1',
                Rule::exists('crm_pipelines', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'stage_id' => [
                'sometimes',
                'integer',
                'min:1',
                Rule::exists('crm_pipeline_stages', 'id')
                    ->where('company_id', $this->user()?->company_id)
                    ->where('pipeline_id', (int) ($this->input('pipeline_id') ?? 0)),
            ],
            'account_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('crm_accounts', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'expected_close_date' => ['nullable', 'date'],
            'owner_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('employees', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'source' => ['nullable', 'in:manual,import,web,referral,other'],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnknownFields($validator);
    }
}
