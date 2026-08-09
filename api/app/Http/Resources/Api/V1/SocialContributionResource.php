<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Payroll\Domain\Models\SocialContribution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SocialContribution
 */
class SocialContributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'country_code' => $this->country_code,
            // Champs réels du modèle (PA2-ARCH-004) : `type` (employee|employer)
            // + `rate` unique. Les anciens champs fantômes employee_rate/
            // employer_rate/is_active n'existent pas sur le modèle et
            // renvoyaient toujours null (bug documenté #1409) — remplacés.
            'type' => $this->type,
            'rate' => $this->rate,
            'cap' => $this->cap,
            'effective_from' => $this->effective_from->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

