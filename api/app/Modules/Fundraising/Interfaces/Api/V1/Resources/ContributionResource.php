<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Interfaces\Api\V1\Resources;

use App\Modules\Fundraising\Domain\Models\FundraisingContribution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation PRIVÉE d'une contribution (gestion tenant) : coordonnées
 * complètes, statut, références provider. Jamais exposée publiquement.
 *
 * @mixin FundraisingContribution
 */
final class ContributionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FundraisingContribution $contribution */
        $contribution = $this->resource;

        return [
            'id' => $contribution->id,
            'reference' => $contribution->reference,
            'fundraiser_id' => $contribution->fundraiser_id,
            'amount' => (float) $contribution->amount,
            'currency' => $contribution->currency,
            'payment_method' => $contribution->payment_method->value,
            'payment_method_label' => $contribution->payment_method->label(),
            'provider' => $contribution->provider,
            'provider_reference' => $contribution->provider_reference,
            'status' => $contribution->status->value,
            'status_label' => $contribution->status->label(),
            'contributor_name' => $contribution->contributor_name,
            'contributor_email' => $contribution->contributor_email,
            'contributor_phone' => $contribution->contributor_phone,
            'is_anonymous' => $contribution->is_anonymous,
            'message' => $contribution->message,
            'paid_at' => $contribution->paid_at?->toIso8601String(),
            'created_at' => $contribution->created_at?->toIso8601String(),
        ];
    }
}
