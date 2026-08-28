<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Models\CrmPipelineStage;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Issue #5711 — Mise à jour d'une opportunité CRM client.
 */
class UpdateCrmOpportunityRequest extends BaseCrmRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pipeline_id' => ['sometimes', 'required', 'integer', Rule::exists('crm_pipelines', 'id')->where('company_id', currentCompany()->id)],
            'stage_id' => ['sometimes', 'required', 'integer', Rule::exists('crm_pipeline_stages', 'id')->where('company_id', currentCompany()->id)],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'account_id' => ['sometimes', 'nullable', 'integer'],
            'converted_from_lead_id' => ['sometimes', 'nullable', 'integer'],
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'expected_close_date' => ['sometimes', 'nullable', 'date'],
            'owner_id' => ['sometimes', 'nullable', 'integer', Rule::exists('employees', 'id')->where('company_id', currentCompany()->id)],
            'source' => ['sometimes', 'nullable', 'string', 'max:40'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $stageId = $this->input('stage_id');
            $pipelineId = $this->input('pipeline_id');

            if ($stageId === null || $pipelineId === null) {
                return;
            }

            /** @var CrmPipelineStage|null $stage */
            $stage = CrmPipelineStage::query()->find($stageId);

            if ($stage !== null && (int) $stage->pipeline_id !== (int) $pipelineId) {
                $validator->errors()->add('stage_id', 'Le stage n’appartient pas au pipeline fourni.');
            }
        });
    }
}
