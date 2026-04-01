<?php

namespace Database\Factories\Public;

use App\Models\Public\Company;
use App\Models\Public\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * CompanyFactory — Génère des entreprises de test
 *
 * USAGE dans les tests :
 *   Company::factory()->create()                         → Starter, shared
 *   Company::factory()->withPlan('business')->create()   → Business, shared
 *   Company::factory()->enterprise()->create()           → Enterprise, schema isolé
 *   Company::factory()->trial()->create()                → Nouveau client Trial
 *   Company::factory()->suspended()->create()            → Compte suspendu
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $countries = ['DZ', 'MA', 'TN', 'FR', 'TR'];
        $timezones = [
            'DZ' => 'Africa/Algiers',
            'MA' => 'Africa/Casablanca',
            'TN' => 'Africa/Tunis',
            'FR' => 'Europe/Paris',
            'TR' => 'Europe/Istanbul',
        ];
        $currencies = ['DZ' => 'DZD', 'MA' => 'MAD', 'TN' => 'TND', 'FR' => 'EUR', 'TR' => 'TRY'];
        $country = $this->faker->randomElement($countries);

        $name = $this->faker->company();

        return [
            'id'                 => (string) Str::uuid(),
            'name'               => $name,
            'slug'               => Str::slug($name) . '-' . Str::lower(Str::random(4)),
            'sector'             => $this->faker->randomElement(['Technologie', 'Commerce', 'Industrie', 'Services', 'Santé']),
            'country'            => $country,
            'city'               => $this->faker->city(),
            'address'            => $this->faker->address(),
            'email'              => $this->faker->unique()->companyEmail(),
            'phone'              => $this->faker->phoneNumber(),
            'plan_id'            => Plan::where('name', 'Starter')->value('id') ?? 1,
            'schema_name'        => 'shared_tenants', // Par défaut shared
            'tenancy_type'       => 'shared',
            'status'             => 'active',
            'subscription_start' => now()->startOfMonth(),
            'subscription_end'   => now()->addYear(),
            'language'           => 'fr',
            'timezone'           => $timezones[$country],
            'currency'           => $currencies[$country],
        ];
    }

    // ── États ──────────────────────────────────────────────────────────────

    /**
     * Plan spécifique par nom
     */
    public function withPlan(string $planName): static
    {
        return $this->state(function () use ($planName) {
            $plan = Plan::where('name', ucfirst($planName))->firstOrFail();
            return ['plan_id' => $plan->id];
        });
    }

    /**
     * Entreprise Enterprise avec schéma PostgreSQL dédié
     */
    public function enterprise(): static
    {
        return $this->state(function () {
            $plan = Plan::where('name', 'Enterprise')->firstOrFail();
            $schemaName = 'company_' . Str::lower(Str::random(8));
            return [
                'plan_id'      => $plan->id,
                'schema_name'  => $schemaName,
                'tenancy_type' => 'schema',
                'subscription_end' => now()->addYear(),
            ];
        });
    }

    /**
     * Nouveau client en période Trial
     */
    public function trial(): static
    {
        return $this->state(fn () => [
            'status'             => 'trial',
            'subscription_start' => now(),
            'subscription_end'   => now()->addDays(14),
        ]);
    }

    /**
     * Compte suspendu (trial expiré ou abonnement non renouvelé)
     */
    public function suspended(): static
    {
        return $this->state(fn () => [
            'status'           => 'suspended',
            'subscription_end' => now()->subDays(10),
        ]);
    }

    /**
     * Abonnement dans la période de grâce (1-3 jours d'expiration)
     */
    public function inGracePeriod(): static
    {
        return $this->state(fn () => [
            'status'           => 'active',
            'subscription_end' => now()->subDays(2), // 2 jours après expiration = dans la grâce
        ]);
    }

    /**
     * Algérie spécifiquement (pour tests paie DZ)
     */
    public function algeria(): static
    {
        return $this->state(fn () => [
            'country'  => 'DZ',
            'timezone' => 'Africa/Algiers',
            'currency' => 'DZD',
            'language' => 'fr',
        ]);
    }
}
