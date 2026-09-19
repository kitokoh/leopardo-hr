<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
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
            // #7452 — company_id est NOT NULL : hors contexte tenant (tests
            // outbox), le trait BelongsToCompany ne peut pas l'injecter.
            'company_id' => Company::factory(),
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
