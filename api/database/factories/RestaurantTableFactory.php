<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantTable>
 */
class RestaurantTableFactory extends Factory
{
    protected $model = RestaurantTable::class;

    public function definition(): array
    {
        return [
            'branch_id' => RestaurantBranch::factory(),
            'zone_id' => null,
            'label' => 'T'.$this->faker->unique()->numberBetween(1, 999),
            'capacity' => $this->faker->numberBetween(2, 12),
            'min_covers' => $this->faker->optional()->numberBetween(1, 4),
            'is_mergeable' => $this->faker->boolean(15),
            'status' => RestaurantRecordStatus::ACTIVE->value,
        ];
    }
}
