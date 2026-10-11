<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Interfaces\Api\V1\Resources;

use App\Modules\Fundraising\Domain\Models\Fundraiser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation PRIVÉE (gestion tenant) d'une cagnotte (verticale
 * FUNDRAISING). Inclut les champs de gestion (beneficiary_contact,
 * compteurs, disponible pour reversement) — jamais exposée publiquement.
 *
 * @mixin Fundraiser
 */
final class FundraiserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Fundraiser $fundraiser */
        $fundraiser = $this->resource;

        return [
            'id' => $fundraiser->id,
            'slug' => $fundraiser->slug,
            'title' => $fundraiser->title,
            'description' => $fundraiser->description,
            'beneficiary_name' => $fundraiser->beneficiary_name,
            'beneficiary_contact' => $fundraiser->beneficiary_contact,
            'category' => $fundraiser->category->value,
            'category_label' => $fundraiser->category->label(),
            'goal_amount' => $fundraiser->goal_amount !== null ? (float) $fundraiser->goal_amount : null,
            'collected_amount' => (float) $fundraiser->collected_amount,
            'contributions_count' => $fundraiser->contributions_count,
            'available_balance' => $fundraiser->availableBalance(),
            'currency' => $fundraiser->currency,
            'suggested_amounts' => $fundraiser->suggested_amounts,
            'min_amount' => $fundraiser->min_amount !== null ? (float) $fundraiser->min_amount : null,
            'max_amount' => $fundraiser->max_amount !== null ? (float) $fundraiser->max_amount : null,
            'cover_image_path' => $fundraiser->cover_image_path,
            'status' => $fundraiser->status->value,
            'status_label' => $fundraiser->status->label(),
            'starts_at' => $fundraiser->starts_at?->toIso8601String(),
            'ends_at' => $fundraiser->ends_at?->toIso8601String(),
            'published_at' => $fundraiser->published_at?->toIso8601String(),
            'public_url' => '/cagnottes/'.$fundraiser->slug,
            'created_at' => $fundraiser->created_at?->toIso8601String(),
            'updated_at' => $fundraiser->updated_at?->toIso8601String(),
        ];
    }
}
