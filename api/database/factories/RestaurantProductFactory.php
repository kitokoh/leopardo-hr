<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantCategory;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantProduct>
 */
class RestaurantProductFactory extends Factory
{
    protected $model = RestaurantProduct::class;

    public function definition(): array
    {
        return [
            'branch_id' => null,
            'category_id' => RestaurantCategory::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('PRD-###')),
            'name' => $this->faker->unique()->words(2, true),
            'description_redacted' => $this->faker->optional()->sentence(),
            'price_minor' => $this->faker->numberBetween(500, 20000),
            'currency' => 'DZD',
            'cost_minor' => $this->faker->optional()->numberBetween(100, 10000),
            'tax_rate_id' => RestaurantTaxRate::factory(),
            'is_available' => $this->faker->boolean(90),
            'image_asset_id' => null,
            'status' => RestaurantRecordStatus::ACTIVE->value,
        ];
    }
}
