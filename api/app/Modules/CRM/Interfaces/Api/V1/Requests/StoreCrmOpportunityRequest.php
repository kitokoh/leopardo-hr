<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une opportunité CRM — Issue #5711 (CRM-V0-07).
 *
 * Invariants : `stage_id` doit appartenir au `pipeline_id` fourni (règle
 * croisée), toutes les références (pipeline, stage, account, owner) sont
 * scopées au tenant courant, `amount` borné [0, 999999999999.99]. Champs
 * inconnus refusés.
 */
class StoreCrmOpportunityRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'pipeline_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('crm_pipelines', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'stage_id' => [
                'required',
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
