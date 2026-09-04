<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelCancellationPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Modules\TravelAgency\Domain\Models\TravelClass
use App\Modules\TravelAgency\Domain\Models\TravelTrip;

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


    public function forTrip(int $tripId): static
    {
        return $this->state(['trip_id' => $tripId]);
    }


    public function forClass(int $classId): static
    {
        return $this->state(['class_id' => $classId]);
    }

    public function global(int $penaltyPercent, ?int $cancelBeforeHours = null): static
    {
        return $this->state([
            'trip_id' => null,
            'class_id' => null,
            'penalty_percent' => $penaltyPercent,
            'cancel_before_hours' => $cancelBeforeHours,
            'refundable' => $penaltyPercent < 100,
        ]);
    }


    public function nonRefundable(): static
    {
        return $this->state(['refundable' => false, 'penalty_percent' => 100]);
    }

    public function attached(int $companyId, ?int $tripId = null, ?int $classId = null): static
    {
        $tripId ??= TravelTrip::factory()->create(['company_id' => $companyId])->id;
        $classId ??= TravelClass::factory()->create(['company_id' => $companyId])->id;

        return $this->state(['trip_id' => $tripId, 'class_id' => $classId]);
    }


}