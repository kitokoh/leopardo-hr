<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\PromotionDiscountType;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPromotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantPromotion>
 */
class RestaurantPromotionFactory extends Factory
{
    protected $model = RestaurantPromotion::class;

    public function definition(): array
    {
        return [
            'branch_id' => null,
            'code' => strtoupper($this->faker->unique()->bothify('PROMO-###')),
            'title' => $this->faker->unique()->words(3, true),
            'discount_type' => PromotionDiscountType::PERCENT->value,
            'value_minor' => $this->faker->numberBetween(100, 5000),
            'min_order_minor' => $this->faker->optional()->numberBetween(500, 20000),
            'starts_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'ends_at' => $this->faker->optional()->dateTimeBetween('now', '+2 months'),
            'max_uses' => $this->faker->optional()->numberBetween(10, 1000),
            'used_count' => 0,
            'is_active' => $this->faker->boolean(80),
        ];
    }
}
