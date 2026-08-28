<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Models\CrmPipelineStage;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Issue #5711 — Création d'une opportunité CRM client.
 *
 * `pipeline_id`/`stage_id` validés dans le tenant courant ET le stage doit
 * appartenir au pipeline fourni (cohérence d'intégrité, rejet 422).
 */
class StoreCrmOpportunityRequest extends BaseCrmRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pipeline_id' => ['required', 'integer', Rule::exists('crm_pipelines', 'id')->where('company_id', currentCompany()->id)],
            'stage_id' => ['required', 'integer', Rule::exists('crm_pipeline_stages', 'id')->where('company_id', currentCompany()->id)],
            'name' => ['required', 'string', 'max:150'],
            'account_id' => ['nullable', 'integer'],
            'converted_from_lead_id' => ['nullable', 'integer'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'expected_close_date' => ['nullable', 'date'],
            'owner_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('company_id', currentCompany()->id)],
            'source' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:10000'],
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
