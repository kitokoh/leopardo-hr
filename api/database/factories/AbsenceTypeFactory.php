<?php

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Models\AbsenceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AbsenceType> */
class AbsenceTypeFactory extends Factory
{
    protected $model = AbsenceType::class;

    public function definition(): array
    {
        return [
            // #8004 — `absence_types.company_id` est NOT NULL (index unique
            // composite #5967) : hors contexte tenant, le trait BelongsToCompany
            // ne peut pas l'injecter (même constat que #7452 sur les factories
            // Restaurant/Travel). Un test qui cible un tenant doit passer
            // explicitement `['company_id' => $company->id]`.
            'company_id' => Company::factory(),
            'name' => $this->faker->words(2, true),
            'code' => strtoupper($this->faker->unique()->lexify('TYPE_????')),
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
            'max_days_once' => null,
        ];
    }

    public function nonDeductible(): static
    {
        return $this->state(fn () => ['deducts_leave' => false]);
    }
}
