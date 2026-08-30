<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelCancellationPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelCancellationPolicy>
 */
class TravelCancellationPolicyFactory extends Factory
{
    protected $model = TravelCancellationPolicy::class;

    public function definition(): array
    {
        return [
            'trip_id' => null,
            'class_id' => null,
            'hours_before_departure' => 12,
            'penalty_percent' => 25,
            'refundable' => true,
            'created_by_user_id' => null,
        ];
    }
}
