<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\TableSessionStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTableSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantTableSession>
 */
class RestaurantTableSessionFactory extends Factory
{
    protected $model = RestaurantTableSession::class;

    public function definition(): array
    {
        return [
            'branch_id' => RestaurantBranch::factory(),
            'table_id' => RestaurantTable::factory(),
            'order_id' => null,
            'opened_at' => now()->subHour(),
            'closed_at' => null,
            'covers' => $this->faker->optional()->numberBetween(1, 8),
            'status' => TableSessionStatus::OPEN->value,
        ];
    }
}
