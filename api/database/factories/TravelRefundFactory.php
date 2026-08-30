<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelRefund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelRefund>
 */
class TravelRefundFactory extends Factory
{
    protected $model = TravelRefund::class;

    public function definition(): array
    {
        return [
            'booking_id' => TravelBooking::factory(),
            'passenger_id' => null,
            'amount_minor' => 15000,
            'penalty_minor' => 0,
            'currency' => 'XOF',
            'reason' => 'Remboursement test',
            'refund_key' => $this->faker->unique()->uuid(),
            'refunded_by_user_id' => null,
        ];
    }
}
