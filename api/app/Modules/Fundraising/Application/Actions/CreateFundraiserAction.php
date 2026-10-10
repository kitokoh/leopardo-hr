<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Application\Actions;

use App\Modules\Fundraising\Domain\Enums\FundraiserStatus;
use App\Modules\Fundraising\Domain\Models\Fundraiser;
use App\Modules\Fundraising\Domain\Support\SlugGenerator;

/**
 * Création d'une cagnotte en `draft` (verticale FUNDRAISING — spec §5.2).
 *
 * Le slug public est généré ici (`SlugGenerator` : slugifié + suffixe
 * aléatoire, anti-énumération) et ne change plus ensuite — le lien reste
 * stable même si le titre est édité. Contexte tenant requis (middleware
 * `tenant`) : `company_id` est auto-rempli par BelongsToCompany.
 */
final class CreateFundraiserAction
{
    /**
     * @param  array<string, mixed>  $data  payload validé (StoreFundraiserRequest)
     */
    public function execute(array $data, ?int $createdBy = null): Fundraiser
    {
        /** @var Fundraiser $fundraiser */
        $fundraiser = Fundraiser::query()->create([
            'slug' => SlugGenerator::generate((string) $data['title']),
            'title' => (string) $data['title'],
            'description' => $data['description'] ?? null,
            'beneficiary_name' => (string) $data['beneficiary_name'],
            'beneficiary_contact' => $data['beneficiary_contact'] ?? null,
            'category' => $data['category'] ?? 'other',
            'goal_amount' => $data['goal_amount'] ?? null,
            'currency' => strtoupper((string) ($data['currency'] ?? 'XOF')),
            'suggested_amounts' => $data['suggested_amounts'] ?? null,
            'min_amount' => $data['min_amount'] ?? null,
            'max_amount' => $data['max_amount'] ?? null,
            'status' => FundraiserStatus::DRAFT,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'created_by' => $createdBy,
        ]);

        return $fundraiser;
    }
}
