<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\TicketStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPassenger;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelTicket>
 */
class TravelTicketFactory extends Factory
{
    protected $model = TravelTicket::class;

    public function definition(): array
    {
        return [
            'booking_id' => TravelBooking::factory(),
            'passenger_id' => TravelPassenger::factory(),
            'validation_code' => hash('sha256', $this->faker->unique()->bothify('QR-##########')),
            'pdf_asset_id' => null,
            'issued_at' => now(),
            'valid_from' => now(),
            'valid_until' => now()->addDays(1),
            'status' => TicketStatus::ISSUED->value,
            'checked_in_at' => null,
            'checked_in_by_user_id' => null,
        ];
    }
}
