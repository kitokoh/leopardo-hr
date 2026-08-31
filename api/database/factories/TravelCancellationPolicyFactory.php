<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelCancellationPolicy;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelCancellationPolicy>
 */
class TravelCancellationPolicyFactory extends Factory
{
    protected $model = TravelCancellationPolicy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => null,
            'class_id' => null,
            'cancel_before_hours' => null,
            'penalty_percent' => 0,
            'refundable' => true,
            'is_active' => true,
            'description' => null,
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

    /**
     * Règle globale du tenant : pénalité fixe, non remboursable sous N heures.
     */
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

    /**
     * Fixture complète rattachée à un trajet/une classe réels (tests).
     */
    public function attached(int $companyId, ?int $tripId = null, ?int $classId = null): static
    {
        $tripId ??= TravelTrip::factory()->create(['company_id' => $companyId])->id;
        $classId ??= TravelClass::factory()->create(['company_id' => $companyId])->id;

        return $this->state(['trip_id' => $tripId, 'class_id' => $classId]);
    }
}
