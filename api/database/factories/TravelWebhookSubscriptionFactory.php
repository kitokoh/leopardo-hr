<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Auth\Infrastructure\Services\SensitiveDataEncryptor;
use App\Modules\TravelAgency\Domain\Enums\TravelWebhookEvent;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelWebhookSubscription>
 */
class TravelWebhookSubscriptionFactory extends Factory
{
    protected $model = TravelWebhookSubscription::class;

    public function definition(): array
    {
        $secret = \Illuminate\Support\Str::random(40);

        return [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'company_id' => \App\Core\Tenant\Domain\Models\Company::factory(),
            'carrier_id' => null,
            'name' => $this->faker->company,
            'url' => 'https://carrier.example.test/hooks/travel',
            'secret_encrypted' => app(SensitiveDataEncryptor::class)->encrypt($secret),
            'events' => [TravelWebhookEvent::BOOKING_CONFIRMED->value, TravelWebhookEvent::TICKET_ISSUED->value],
            'active' => true,
        ];
    }
}
