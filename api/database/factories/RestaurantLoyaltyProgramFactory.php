<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantLoyaltyProgram>
 */
class RestaurantLoyaltyProgramFactory extends Factory
{
    protected $model = RestaurantLoyaltyProgram::class;

    public function definition(): array
    {
        return [
            'points_per_amount_minor' => 100,
            'redeem_rate_minor' => 100,
            'is_active' => true,
        ];
    }
}
