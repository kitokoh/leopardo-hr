<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TravelOutboxEvent>
 */
class TravelOutboxEventFactory extends Factory
{
    protected $model = TravelOutboxEvent::class;

    public function definition(): array
    {
        return [
            'event_type' => 'travel.booking.confirmed.v1',
            'payload_redacted' => ['booking_reference' => 'GV-'.strtoupper(Str::random(10))],
            'status' => TravelOutboxEvent::STATUS_PENDING,
            'attempts' => 0,
            'available_at' => now(),
            'last_error' => null,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
