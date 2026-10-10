<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Application\Actions;

use App\Modules\Fundraising\Domain\Enums\FundraiserStatus;
use App\Modules\Fundraising\Domain\Exceptions\FundraisingException;
use App\Modules\Fundraising\Domain\Models\Fundraiser;

/**
 * Édition d'une cagnotte (verticale FUNDRAISING — spec §5.2).
 *
 * Interdite sur `closed`/`cancelled` (INVALID_STATUS_TRANSITION). Le slug
 * n'est JAMAIS réédité (stabilité du lien public) ; les compteurs ne sont
 * pas modifiables via l'API (champs exclus ici — jamais dans le FormRequest).
 */
final class UpdateFundraiserAction
{
    /**
     * @param  array<string, mixed>  $data  payload validé (UpdateFundraiserRequest)
     */
    public function handle(Fundraiser $fundraiser, array $data): Fundraiser
    {
        if (in_array($fundraiser->status, [FundraiserStatus::CLOSED, FundraiserStatus::CANCELLED], true)) {
            throw FundraisingException::invalidStatusTransition($fundraiser->status->value, 'updated');
        }

        $fundraiser->fill(array_intersect_key($data, array_flip([
            'title',
            'description',
            'beneficiary_name',
            'beneficiary_contact',
            'category',
            'goal_amount',
            'suggested_amounts',
            'min_amount',
            'max_amount',
            'cover_image_path',
            'starts_at',
            'ends_at',
        ])));

        $fundraiser->save();

        return $fundraiser;
    }
}
