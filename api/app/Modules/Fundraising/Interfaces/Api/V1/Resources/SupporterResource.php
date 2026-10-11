<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Interfaces\Api\V1\Resources;

use App\Modules\Fundraising\Domain\Models\FundraisingContribution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Entrée du MUR PUBLIC des soutiens (verticale FUNDRAISING — spec §5.1,
 * RGPD §6) : seules les contributions `completed` y figurent ; le nom est
 * masqué (« Anonyme ») ET le montant masqué quand `is_anonymous` ; jamais
 * d'email/téléphone ni d'identifiant technique.
 *
 * @mixin FundraisingContribution
 */
final class SupporterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FundraisingContribution $contribution */
        $contribution = $this->resource;

        return [
            'name' => $contribution->is_anonymous
                ? 'Anonyme'
                : ($contribution->contributor_name ?? 'Anonyme'),
            'amount' => $contribution->is_anonymous ? null : (float) $contribution->amount,
            'currency' => $contribution->is_anonymous ? null : $contribution->currency,
            'message' => $contribution->message,
            'paid_at' => $contribution->paid_at?->toIso8601String(),
        ];
    }
}
