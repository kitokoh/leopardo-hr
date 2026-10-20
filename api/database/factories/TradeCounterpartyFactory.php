<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Enums\TradeCounterpartyRole;
use App\Modules\Trade\Domain\Enums\TradeVerificationStatus;
use App\Modules\Trade\Domain\Models\TradeCounterparty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeCounterparty>
 */
class TradeCounterpartyFactory extends Factory
{
    protected $model = TradeCounterparty::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'crm_account_id' => null,
            'name' => $this->faker->company(),
            'country' => 'VN',
            'city' => $this->faker->city(),
            'email' => $this->faker->unique()->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'website' => null,
            'roles' => [TradeCounterpartyRole::Supplier->value],
            'verification_status' => TradeVerificationStatus::None->value,
            'verified_by_employee_id' => null,
            'verified_at' => null,
            'verification_note' => null,
            'capabilities' => null,
            'notes' => null,
        ];
    }

    /**
     * @param  list<TradeCounterpartyRole>  $roles
     */
    public function withRoles(array $roles): static
    {
        return $this->state(fn (): array => [
            'roles' => array_map(static fn (TradeCounterpartyRole $role): string => $role->value, $roles),
        ]);
    }

    public function carrier(): static
    {
        return $this->withRoles([TradeCounterpartyRole::Carrier, TradeCounterpartyRole::Forwarder]);
    }

    /**
     * Verification MANUELLE tracee — jamais presentee comme automatique.
     */
    public function manuallyVerified(int $employeeId = 1): static
    {
        return $this->state(fn (): array => [
            'verification_status' => TradeVerificationStatus::Verified->value,
            'verified_by_employee_id' => $employeeId,
            'verified_at' => now(),
            'verification_note' => 'Verifie manuellement par l\'equipe.',
        ]);
    }
}
