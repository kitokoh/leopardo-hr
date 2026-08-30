<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\PosSessionStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantPosSession>
 */
class RestaurantPosSessionFactory extends Factory
{
    protected $model = RestaurantPosSession::class;

    public function definition(): array
    {
        return [
            'branch_id' => RestaurantBranch::factory(),
            'opened_at' => now()->subHours(2),
            'closed_at' => null,
            'opened_by_user_id' => $this->faker->numberBetween(1, 999999),
            'closed_by_user_id' => null,
            'opening_cash_minor' => $this->faker->numberBetween(1000, 20000),
            'expected_cash_minor' => null,
            'counted_cash_minor' => null,
            'variance_minor' => null,
            'variance_reason' => null,
            'status' => PosSessionStatus::OPEN->value,
            'version' => 1,
        ];
    }
}
