<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Models\TradeProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeProduct>
 */
class TradeProductFactory extends Factory
{
    protected $model = TradeProduct::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'category' => 'agroalimentaire',
            // hs_code reste null par defaut : le produit n'est jamais
            // presente comme classe douanierement sans saisie humaine.
            'hs_code' => null,
            'origin_country' => 'CM',
            'unit' => 'kg',
            'moq_quantity' => '1000.000',
            'indicative_unit_price_minor' => 250_000,
            'currency' => 'XAF',
            'specs' => null,
            'status' => 'active',
        ];
    }

    public function withHsCode(string $hsCode = '090111'): static
    {
        return $this->state(fn (): array => ['hs_code' => $hsCode]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => 'archived']);
    }
}
