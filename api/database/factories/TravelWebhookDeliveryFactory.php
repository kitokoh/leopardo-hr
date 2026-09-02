<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\TravelWebhookDeliveryStatus;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookDelivery;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelWebhookDelivery>
 */
class TravelWebhookDeliveryFactory extends Factory
{
    protected $model = TravelWebhookDelivery::class;

    public function definition(): array
    {
        return [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'company_id' => \App\Core\Tenant\Domain\Models\Company::factory(),
            'subscription_id' => TravelWebhookSubscription::factory(),
            'outbox_event_id' => null,
            'event_type' => 'travel.booking.confirmed.v1',
            'payload_redacted' => ['booking_reference' => 'GV-2026-0001', 'trip_code' => 'DLA-YDE-01'],
            'status' => TravelWebhookDeliveryStatus::PENDING,
            'attempts' => 0,
            'next_attempt_at' => null,
            'last_status_code' => null,
            'last_error' => null,
            'delivered_at' => null,
        ];
    }
}
