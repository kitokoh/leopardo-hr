<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * EmployeeFactory — Génère des employés de test (schéma tenant actif)
 *
 * USAGE dans les tests :
 *   Employee::factory()->create()                    → Employé standard
 *   Employee::factory()->manager()->create()         → Manager principal
 *   Employee::factory()->managerRh()->create()       → Manager RH
 *   Employee::factory()->archived()->create()        → Archivé
 *   Employee::factory()->count(20)->create()         → 20 employés
 *
 * NOTE : La factory opère sur le schéma actif (SET search_path par TenantMiddleware)
 * Les tests doivent switcher le schéma AVANT d'utiliser la factory.
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    protected static string $defaultPassword = 'password123';

    public function definition(): array
    {
        return [
            // Pas de company_id en mode schema isolé (Enterprise)
            // En mode shared : company_id sera set via for() dans les tests
            'matricule' => 'EMP-'.str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'password_hash' => Hash::make(self::$defaultPassword),
            'role' => 'employee',
            'manager_role' => null,
            'contract_type' => $this->faker->randomElement(['CDI', 'CDD', 'Stage']),
            'contract_start' => $this->faker->dateTimeBetween('-3 years', '-1 month')->format('Y-m-d'),
            'salary_base' => $this->faker->numberBetween(50000, 200000), // DZD
            'salary_type' => 'fixed',
            'payment_method' => 'bank_transfer',
            'leave_balance' => $this->faker->randomFloat(1, 0, 30),
            'status' => 'active',
        ];
    }

    // ── États ──────────────────────────────────────────────────────────────

    /**
     * Manager principal (DG)
     */
    public function manager(): static
    {
        return $this->state(fn () => [
            'role' => 'manager',
            'manager_role' => 'principal',
            'salary_base' => $this->faker->numberBetween(150000, 300000),
        ]);
    }

    /**
     * Manager RH
     */
    public function managerRh(): static
    {
        return $this->state(fn () => [
            'role' => 'manager',
            'manager_role' => 'rh',
        ]);
    }

    /**
     * Manager département
     */
    public function managerDept(): static
    {
        return $this->state(fn () => [
            'role' => 'manager',
            'manager_role' => 'dept',
        ]);
    }

    /**
     * Employé archivé (soft delete logique)
     */
    public function archived(): static
    {
        return $this->state(fn () => ['status' => 'archived']);
    }

    /**
     * Employé suspendu
     */
    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }

    /**
     * Contrat CDD expirant bientôt
     */
    public function expiringContract(): static
    {
        return $this->state(fn () => [
            'contract_type' => 'CDD',
            'contract_end' => now()->addDays(rand(1, 30))->format('Y-m-d'),
        ]);
    }

    /**
     * Employé avec ZKTeco ID (lié à un lecteur biométrique)
     */
    public function withBiometric(): static
    {
        return $this->state(fn () => [
            'zkteco_id' => 'ZK'.$this->faker->unique()->numberBetween(1000, 9999),
        ]);
    }

    /**
     * Crée l'employé ET un token Sanctum — utile pour les tests d'API
     */
    public function createWithToken(string $tokenName = 'test-token'): array
    {
        $employee = $this->create();
        $token = $employee->createToken($tokenName)->plainTextToken;

        return ['employee' => $employee, 'token' => $token];
    }
}
