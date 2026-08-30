<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelLoyaltyAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelLoyaltyAccount>
 */
class TravelLoyaltyAccountFactory extends Factory
{
    protected $model = TravelLoyaltyAccount::class;

    public function definition(): array
    {
        return [
            'contact_id' => $this->faker->unique()->numberBetween(1, 100000),
            'points_balance' => 0,
            'opt_in_at' => now(),
            'opt_out_at' => null,
        ];
    }
}
