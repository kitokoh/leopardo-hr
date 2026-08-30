<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\QuizStatus;
use App\Modules\TravelAgency\Domain\Models\TravelQuiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelQuiz>
 */
class TravelQuizFactory extends Factory
{
    protected $model = TravelQuiz::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'description_redacted' => $this->faker->paragraph(2),
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
            'max_participations_per_contact' => 1,
            'status' => QuizStatus::ACTIVE->value,
        ];
    }
}
