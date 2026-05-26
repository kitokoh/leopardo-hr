<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Evaluation
 */
class EvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'evaluator_id' => $this->evaluator_id,
            'period' => $this->period,
            'score' => $this->score,
            'criteria' => $this->criteria,
            'strengths' => $this->strengths,
            'improvements' => $this->improvements,
            'overall_comment' => $this->overall_comment,
            'status' => $this->status,
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'employee' => $this->whenLoaded('employee'),
            'evaluator' => $this->whenLoaded('evaluator'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
