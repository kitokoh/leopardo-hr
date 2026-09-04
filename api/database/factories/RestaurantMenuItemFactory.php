<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Models\RestaurantMenu;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenuItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantMenuItem>
 */
class RestaurantMenuItemFactory extends Factory
{
    protected $model = RestaurantMenuItem::class;

    public function definition(): array
    {
        return [
            'menu_id' => RestaurantMenu::factory(),
            'product_id' => RestaurantProduct::factory(),
            'position' => $this->faker->numberBetween(0, 30),
            'is_optional' => $this->faker->boolean(10),
        ];
    }
}
