<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\TravelStaffAssignmentStatus;
use App\Modules\TravelAgency\Domain\Enums\TravelStaffRole;
use App\Modules\TravelAgency\Domain\Models\TravelOffice;
use App\Modules\TravelAgency\Domain\Models\TravelStaffAssignment;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * #7638 (TRAVEL-STAFF) — par défaut : guichetier actif affecté à un bureau.
 *
 * @extends Factory<TravelStaffAssignment>
 */
class TravelStaffAssignmentFactory extends Factory
{
    protected $model = TravelStaffAssignment::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'role' => TravelStaffRole::AGENT->value,
            'office_id' => TravelOffice::factory(),
            'trip_id' => null,
            'status' => TravelStaffAssignmentStatus::ACTIVE->value,
        ];
    }

    public function forTrip(): static
    {
        return $this->state(fn (): array => [
            'role' => TravelStaffRole::DRIVER->value,
            'office_id' => null,
            'trip_id' => TravelTrip::factory(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'status' => TravelStaffAssignmentStatus::REVOKED->value,
            'revoked_at' => now(),
        ]);
    }
}
