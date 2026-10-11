<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Interfaces\Api\V1\Resources;

use App\Modules\Fundraising\Domain\Models\Fundraiser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * DTO PUBLIC d'une cagnotte (verticale FUNDRAISING — spec §5.1/§6 RGPD).
 *
 * SEUL contrat exposé sur la surface publique : ZÉRO donnée interne —
 * pas d'id séquentiel, pas de company_id, pas de beneficiary_contact,
 * pas de coordonnées contributeur. Le `public_path` est le chemin à
 * préfixer côté front (phase v1.1).
 *
 * @mixin Fundraiser
 */
final class FundraiserPublicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Fundraiser $fundraiser */
        $fundraiser = $this->resource;

        $goal = $fundraiser->goal_amount !== null ? (float) $fundraiser->goal_amount : null;
        $collected = (float) $fundraiser->collected_amount;

        return [
            'slug' => $fundraiser->slug,
            'title' => $fundraiser->title,
            'description' => $fundraiser->description,
            'beneficiary_name' => $fundraiser->beneficiary_name,
            'category' => $fundraiser->category->value,
            'category_label' => $fundraiser->category->label(),
            'goal_amount' => $goal,
            'collected_amount' => $collected,
            'progress_percent' => $goal !== null && $goal > 0
                ? round(min(100, ($collected / $goal) * 100), 1)
                : null,
            'contributions_count' => $fundraiser->contributions_count,
            'currency' => $fundraiser->currency,
            'suggested_amounts' => $fundraiser->suggested_amounts,
            'min_amount' => $fundraiser->min_amount !== null ? (float) $fundraiser->min_amount : null,
            'max_amount' => $fundraiser->max_amount !== null ? (float) $fundraiser->max_amount : null,
            'cover_image_path' => $fundraiser->cover_image_path,
            'status' => $fundraiser->status->value,
            'accepts_contributions' => $fundraiser->status->acceptsContributions()
                && $fundraiser->isWithinCollectionWindow(),
            'ends_at' => $fundraiser->ends_at?->toIso8601String(),
            'published_at' => $fundraiser->published_at?->toIso8601String(),
            'public_path' => '/cagnottes/'.$fundraiser->slug,
        ];
    }
}
