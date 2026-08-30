<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelCustomerContact>
 */
class TravelCustomerContactFactory extends Factory
{
    protected $model = TravelCustomerContact::class;

    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'phone' => '+2376'.$this->faker->numerify('########'),
            // Défaut : AUCUN consentement — pas d'envoi sans opt-in explicite.
            'email_consent_given' => false,
            'email_consent_at' => null,
            'sms_consent_given' => false,
            'sms_consent_at' => null,
            'whatsapp_consent_given' => false,
            'whatsapp_consent_at' => null,
            'metadata_json' => null,
        ];
    }

    public function withEmailConsent(): static
    {
        return $this->state(fn (): array => [
            'email_consent_given' => true,
            'email_consent_at' => now(),
        ]);
    }
}
