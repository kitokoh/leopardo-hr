<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

use App\Modules\Planning\Domain\Models\LeaveBalanceLog;

class LeaveBalanceLogFactory extends Factory
{
    protected $model = LeaveBalanceLog::class;
    public function definition(): array
    {
        $balance = $this->faker->randomFloat(2, 5, 30);

        return [
            'delta' => $balance,
            'reason' => 'initial_credit',
            'reference_id' => 0,
            'balance_after' => $balance,
        ];
    }
}
