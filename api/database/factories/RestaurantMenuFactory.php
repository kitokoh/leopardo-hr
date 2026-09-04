<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantMenu>
 */
class RestaurantMenuFactory extends Factory
{
    protected $model = RestaurantMenu::class;

    public function definition(): array
    {
        return [
            'branch_id' => null,
            'code' => strtoupper($this->faker->unique()->bothify('MEN-###')),
            'name' => $this->faker->unique()->words(2, true),
            'price_minor' => $this->faker->numberBetween(800, 15000),
            'currency' => 'DZD',
            'starts_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'ends_at' => $this->faker->optional()->dateTimeBetween('now', '+3 months'),
            'status' => RestaurantRecordStatus::ACTIVE->value,
        ];
    }
}
