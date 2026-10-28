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
        // #8396 — `global(X)` veut dire « pénalité X % pour tout le tenant ».
        // La fabrique ne renseignait que la colonne héritée
        // `hours_before_departure`, avec une sémantique de seuil, alors que le
        // service canonique lit `cancel_before_hours` (null = toujours
        // applicable). Les deux colonnes sont donc alignées : une politique
        // globale sans fenêtre s'applique réellement.
        return $this->state([
            'trip_id' => null,
            'class_id' => null,
            'penalty_percent' => $penaltyPercent,
            'cancel_before_hours' => $cancelBeforeHours,
            'hours_before_departure' => $cancelBeforeHours ?? 0,
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
