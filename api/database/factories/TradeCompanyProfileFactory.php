<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Models\TradeCompanyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeCompanyProfile>
 */
class TradeCompanyProfileFactory extends Factory
{
    protected $model = TradeCompanyProfile::class;

    public function definition(): array
    {
        return [
            // company_id est NOT NULL : hors contexte tenant, le trait
            // BelongsToCompany ne peut pas l'injecter (meme lecon #7452).
            'company_id' => Company::factory(),
            'import_enabled' => true,
            'export_enabled' => false,
            'domestic_scope' => false,
            'hq_country' => 'CM',
            'markets_served' => ['FR', 'BE'],
            'product_categories' => ['agroalimentaire'],
            'default_currency' => 'XAF',
            'default_incoterm' => 'FOB',
            'capabilities' => null,
            'notes' => null,
            'onboarding_completed_at' => now(),
        ];
    }

    /**
     * Entreprise qui importe ET exporte — cas explicitement supporte.
     */
    public function bothDirections(): static
    {
        return $this->state(fn (): array => [
            'import_enabled' => true,
            'export_enabled' => true,
        ]);
    }

    public function domesticWholesaler(): static
    {
        return $this->state(fn (): array => [
            'import_enabled' => false,
            'export_enabled' => false,
            'domestic_scope' => true,
        ]);
    }
}
