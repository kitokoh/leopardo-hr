<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\HR\Domain\Models\OnboardingStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OnboardingStep
 */
class OnboardingStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $metadata = $this->metadata ?? [];

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'step_key' => $this->step_key,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'order' => $this->order,
            'required' => $this->required,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'completed_by' => $this->completed_by,
            // #R10 backend — estimated_minutes exposé en top-level depuis metadata.
            'estimated_minutes' => isset($metadata['estimated_minutes'])
                ? (int) $metadata['estimated_minutes']
                : null,
            'metadata' => $metadata,
        ];
    }
}
